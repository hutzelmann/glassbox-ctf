<?php
/*
 * native-run.php, shared helpers for the native binary family.
 *
 * A challenge's index.php includes this and calls into it: decode a payload, run
 * the vulnerable binary on it under a sandbox, report the exit status and the
 * fatal signal, and render the disassembly / symbols / checksec / hexdump / stack
 * table.
 * Nothing here echoes on include; every function returns data or an HTML string.
 */

// --- payload decoding --------------------------------------------------------

// The browser posts the payload as hex (the canonical, unambiguous wire form).
// Returns the raw bytes, or '' on malformed input.
function nrun_decode_hex(string $hex): string {
    $hex = preg_replace('/[\s:]+/', '', $hex);
    if ($hex === '' || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
        return '';
    }
    $bin = @hex2bin($hex);
    return $bin === false ? '' : $bin;
}

function nrun_hex(string $bytes): string {
    return bin2hex($bytes);
}

// --- running the binary ------------------------------------------------------

// Run $bin with $payload on stdin, sandboxed (wall-clock timeout + CPU/mem/file
// ulimits via a bash wrapper), and capture stdout/stderr/exit. The wrapper's exec
// replaces bash with the target, so the target still inherits our stdin pipe,
// which matters for ret2libc, where bytes past the vulnerable read feed the shell.
function nrun_run(string $bin, string $payload, int $timeout = 3): array {
    $script = 'ulimit -t ' . $timeout . ' -v 262144 -f 4096 2>/dev/null; exec "$@"';
    $cmd = ['timeout', '--signal=KILL', (string) $timeout, 'bash', '-c', $script, 'bash', $bin];
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $spec, $pipes);
    if (!is_resource($proc)) {
        return ['stdout' => '', 'stderr' => 'failed to start', 'exit' => -1, 'crashed' => false, 'timedout' => false];
    }
    fwrite($pipes[0], $payload);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    // The timeout/bash wrapper may surface a fatal signal as either 128+sig or the
    // raw sig; a wall-clock timeout comes back as 124 (GNU timeout) or SIGKILL.
    $sig = 0;
    if ($exit >= 128 && $exit < 192) {
        $sig = $exit - 128;
    } elseif ($exit > 0 && $exit < 64) {
        $sig = $exit;
    }
    $crashed  = in_array($sig, [4, 6, 8, 11], true);          // ILL, ABRT, FPE, SEGV
    $timedout = ($exit === 124) || $sig === 9 || $sig === 24;  // timeout / KILL / XCPU
    return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit, 'signal' => $sig, 'crashed' => $crashed, 'timedout' => $timedout];
}

// --- static introspection (objdump / nm / readelf) ---------------------------

// Disassembly of the whole .text (Intel syntax). Optionally keep only the blocks
// for the named functions, so debug shows vuln()/win() rather than a wall of libc.
function nrun_disasm(string $bin, array $funcs = []): string {
    $out = @shell_exec('objdump -d -M intel --no-show-raw-insn ' . escapeshellarg($bin) . ' 2>/dev/null');
    if (!$out) {
        return '';
    }
    if (empty($funcs)) {
        return $out;
    }
    $blocks = preg_split('/\n(?=[0-9a-f]+ <)/', $out);
    $keep = [];
    foreach ($blocks as $b) {
        foreach ($funcs as $f) {
            if (preg_match('/^[0-9a-f]+ <' . preg_quote($f, '/') . '>:/m', $b)) {
                $keep[] = trim($b, "\n");
                break;
            }
        }
    }
    return implode("\n\n", $keep);
}

// Symbol table (defined symbols). Returns [ ['addr'=>, 'type'=>, 'name'=>], ... ].
function nrun_symbols(string $bin): array {
    $out = @shell_exec('nm ' . escapeshellarg($bin) . ' 2>/dev/null');
    if (!$out) {
        return [];
    }
    $syms = [];
    foreach (explode("\n", trim($out)) as $line) {
        if (preg_match('/^([0-9a-fA-F]+)\s+(\S)\s+(\S+)$/', $line, $m)) {
            $syms[] = ['addr' => $m[1], 'type' => $m[2], 'name' => $m[3]];
        }
    }
    return $syms;
}

// Address of one symbol (e.g. 'win'), or null.
function nrun_symbol_addr(string $bin, string $name): ?string {
    foreach (nrun_symbols($bin) as $s) {
        if ($s['name'] === $name) {
            return '0x' . ltrim($s['addr'], '0') ?: '0x0';
        }
    }
    return null;
}

// Address of a PLT stub (e.g. 'system' -> the <system@plt> entry), or null.
function nrun_plt_addr(string $bin, string $sym): ?string {
    $out = (string) @shell_exec('objdump -d -M intel ' . escapeshellarg($bin) . ' 2>/dev/null');
    if (preg_match('/^0*([0-9a-f]+)\s+<' . preg_quote($sym, '/') . '@plt>:/m', $out, $m)) {
        return '0x' . $m[1];
    }
    return null;
}

// Virtual address of a byte string inside the binary (e.g. "/bin/sh"), mapping the
// file offset back through the section headers. Null if not present.
function nrun_string_addr(string $bin, string $needle): ?string {
    $data = @file_get_contents($bin);
    if ($data === false) {
        return null;
    }
    $off = strpos($data, $needle . "\0");
    if ($off === false) {
        $off = strpos($data, $needle);
    }
    if ($off === false) {
        return null;
    }
    $sect = (string) @shell_exec('readelf -SW ' . escapeshellarg($bin) . ' 2>/dev/null');
    foreach (explode("\n", $sect) as $line) {
        if (preg_match('/\]\s+\S+\s+\S+\s+([0-9a-f]+)\s+([0-9a-f]+)\s+([0-9a-f]+)/', $line, $m)) {
            $addr = hexdec($m[1]);
            $foff = hexdec($m[2]);
            $size = hexdec($m[3]);
            if ($addr !== 0 && $off >= $foff && $off < $foff + $size) {
                return '0x' . dechex($addr + ($off - $foff));
            }
        }
    }
    return null;
}

// A checksec-style protections report, computed from readelf/nm so the image needs
// no external checksec script and stays fully offline.
function nrun_checksec(string $bin): array {
    $header  = (string) @shell_exec('readelf -hW ' . escapeshellarg($bin) . ' 2>/dev/null');
    $prog    = (string) @shell_exec('readelf -lW ' . escapeshellarg($bin) . ' 2>/dev/null');
    $dyn     = (string) @shell_exec('readelf -dW ' . escapeshellarg($bin) . ' 2>/dev/null');
    $syms    = (string) @shell_exec('nm ' . escapeshellarg($bin) . ' 2>/dev/null');
    $dynsyms = (string) @shell_exec('readelf -sW --dyn-syms ' . escapeshellarg($bin) . ' 2>/dev/null');

    $isDyn = (bool) preg_match('/Type:\s+DYN/', $header);
    $hasInterp = (bool) preg_match('/\bINTERP\b/', $prog);
    $pie = $isDyn && $hasInterp;

    // NX: a GNU_STACK segment with the E (execute) flag means the stack is executable.
    $nx = true;
    if (preg_match('/GNU_STACK\s+(?:0x[0-9a-f]+\s+){3}0x[0-9a-f]+\s+0x[0-9a-f]+\s+(\S+)/', $prog, $m)) {
        $nx = (strpos($m[1], 'E') === false);
    }

    $canary = (strpos($syms, '__stack_chk_fail') !== false)
           || (strpos($dynsyms, '__stack_chk_fail') !== false);

    $relroPartial = (strpos($prog, 'GNU_RELRO') !== false);
    $bindNow = (bool) preg_match('/\bBIND_NOW\b/', $dyn) || (bool) preg_match('/FLAGS_1.*\bNOW\b/', $dyn);
    $relro = $bindNow && $relroPartial ? 'Full' : ($relroPartial ? 'Partial' : 'No');

    return [
        'Canary'      => $canary ? 'Yes' : 'No',
        'NX'          => $nx ? 'Yes' : 'No',
        'PIE'         => $pie ? 'Yes' : 'No',
        'RELRO'       => $relro,
    ];
}

// --- rendering (pico + <mark>, no custom CSS) --------------------------------

// Classic xxd-style hexdump of the received bytes.
function nrun_hexdump(string $bytes): string {
    $out = '';
    $len = strlen($bytes);
    for ($i = 0; $i < $len; $i += 16) {
        $chunk = substr($bytes, $i, 16);
        $hex = '';
        $ascii = '';
        for ($j = 0; $j < 16; $j++) {
            if ($j < strlen($chunk)) {
                $c = ord($chunk[$j]);
                $hex .= sprintf('%02x ', $c);
                $ascii .= ($c >= 0x20 && $c < 0x7f) ? $chunk[$j] : '.';
            } else {
                $hex .= '   ';
            }
            if ($j === 7) {
                $hex .= ' ';
            }
        }
        $out .= sprintf("%08x  %s |%s|\n", $i, $hex, $ascii);
    }
    return $out;
}

// A static picture of the vulnerable stack frame (no payload needed): the buffer,
// then the saved frame pointer, then the saved return address, with their offsets
// and sizes. Shows visually why the return address sits at bufSize + 8. Returns an
// HTML <figure> string. The dynamic stack table below fills this same layout with
// the learner's actual bytes once they send some.
function nrun_frame_diagram(int $bufSize): string {
    $slots = [
        ['off' => 0,            'size' => $bufSize, 'name' => "char buf[$bufSize]",   'note' => 'your input starts here',              'mark' => false],
        ['off' => $bufSize,     'size' => 8,        'name' => 'saved RBP',            'note' => 'saved frame pointer',                 'mark' => false],
        ['off' => $bufSize + 8, 'size' => 8,        'name' => 'saved return address', 'note' => 'the CPU jumps here on return, overwrite it', 'mark' => true],
    ];
    $rows = '';
    foreach ($slots as $s) {
        $name = $s['mark'] ? '<mark>' . htmlspecialchars($s['name']) . '</mark>' : htmlspecialchars($s['name']);
        $note = $s['mark'] ? '<strong>' . htmlspecialchars($s['note']) . '</strong>' : htmlspecialchars($s['note']);
        $rows .= '<tr>'
            . '<td><code>+' . $s['off'] . '</code></td>'
            . '<td>' . $s['size'] . ' bytes</td>'
            . '<td>' . $name . '</td>'
            . '<td>' . $note . '</td>'
            . '</tr>';
    }
    return '<figure><table>'
        . '<thead><tr><th>offset</th><th>size</th><th>stack slot</th><th></th></tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '</table></figure>';
}

// Describe what an address points at: a named function (with offset), other
// executable code, a non-canonical value, or nothing mapped. Used to explain the
// value the CPU is about to return to.
function nrun_resolve_addr(string $bin, string $addrHex): string {
    $h = ltrim(strtolower(preg_replace('/^0x/', '', $addrHex)), '0');
    if ($h === '') {
        $h = '0';
    }
    // On x86-64 a usable pointer has bits 48..63 equal to bit 47. A value longer
    // than 48 bits that is not in the high canonical half is non-canonical.
    if (strlen($h) > 12 && !preg_match('/^ffff[89a-f]/', str_pad($h, 16, '0', STR_PAD_LEFT))) {
        return 'a non-canonical address, so the CPU raises a #GP fault at the ret (you control it, but it is not a usable code pointer)';
    }
    $addr = hexdec($h);
    $syms = (string) @shell_exec('readelf -sW ' . escapeshellarg($bin) . ' 2>/dev/null');
    foreach (explode("\n", $syms) as $line) {
        if (preg_match('/^\s*\d+:\s+([0-9a-f]+)\s+(\d+)\s+FUNC\s+\S+\s+\S+\s+\S+\s+(\S+)/', $line, $m)) {
            $val = hexdec($m[1]);
            $size = (int) $m[2];
            $name = preg_replace('/@.*/', '', $m[3]);
            if ($size > 0 && $addr >= $val && $addr < $val + $size) {
                $delta = (int) ($addr - $val);
                return $delta === 0 ? "the start of $name()" : "inside $name() (+0x" . dechex($delta) . ")";
            }
        }
    }
    $sect = (string) @shell_exec('readelf -SW ' . escapeshellarg($bin) . ' 2>/dev/null');
    if (preg_match('/\.text\s+PROGBITS\s+([0-9a-f]+)\s+[0-9a-f]+\s+([0-9a-f]+)/', $sect, $m)) {
        $ta = hexdec($m[1]);
        $ts = hexdec($m[2]);
        if ($ts > 0 && $addr >= $ta && $addr < $ta + $ts) {
            return 'inside the program code (.text), but not a named function';
        }
    }
    if ($addr === 0.0 || $addr === 0) {
        return 'a null pointer';
    }
    return 'not mapped in the program, so the CPU faults when it returns here';
}

// Capture one run's process memory map. Runs the binary so its pid is the process
// (bash exec replaces itself in place), reads /proc/<pid>/maps while it blocks on
// read(0), then lets it go. Returns curated rows for the program, libc, stack, and
// heap. No ptrace needed (same-uid /proc read). Empty on failure.
function nrun_maps(string $bin): array {
    $script = 'ulimit -t 2 -u 64 2>/dev/null; exec "$0"';
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open(['bash', '-c', $script, $bin], $spec, $pipes);
    if (!is_resource($proc)) {
        return [];
    }
    $st = proc_get_status($proc);
    $pid = (int) ($st['pid'] ?? 0);
    usleep(80000);                       // let ld.so finish and the program reach read(0)
    $maps = $pid > 0 ? (string) @file_get_contents("/proc/$pid/maps") : '';
    @fclose($pipes[0]);
    @stream_get_contents($pipes[1]);
    @fclose($pipes[1]);
    @stream_get_contents($pipes[2]);
    @fclose($pipes[2]);
    @proc_terminate($proc, 9);
    @proc_close($proc);
    if ($maps === '') {
        return [];
    }

    $base = basename($bin);
    $rows = [];
    $seen = [];
    foreach (explode("\n", $maps) as $line) {
        if (!preg_match('/^([0-9a-f]+)-([0-9a-f]+)\s+(\S+)\s+\S+\s+\S+\s+\S+\s*(.*)$/', $line, $m)) {
            continue;
        }
        [$whole, $start, $end, $perms, $path] = $m;
        $path = trim($path);
        $label = null;
        if (basename($path) === $base && strpos($perms, 'x') !== false && empty($seen['bin'])) {
            $label = 'the program (executable code)';
            $seen['bin'] = true;
        } elseif (preg_match('#/libc[.\-]#', $path) && strpos($perms, 'x') !== false && empty($seen['libc'])) {
            $label = 'libc (executable code)';
            $seen['libc'] = true;
        } elseif ($path === '[stack]' && empty($seen['stack'])) {
            $label = 'stack';
            $seen['stack'] = true;
        } elseif ($path === '[heap]' && empty($seen['heap'])) {
            $label = 'heap';
            $seen['heap'] = true;
        }
        if ($label !== null) {
            $rows[] = ['range' => "0x$start - 0x$end", 'perms' => $perms, 'label' => $label];
        }
    }
    return $rows;
}

// The address vuln() would return to if the saved return address were left intact:
// the instruction in main() right after its `call vuln`. Shown in the stack table
// as the "original" return address when the input has not overwritten it. Null if
// it cannot be located.
function nrun_return_addr(string $bin): ?string {
    $out = (string) @shell_exec('objdump -d -M intel ' . escapeshellarg($bin) . ' 2>/dev/null');
    if ($out === '') {
        return null;
    }
    $inMain = false;
    $sawCall = false;
    foreach (explode("\n", $out) as $line) {
        if (preg_match('/^[0-9a-f]+ <([^>]+)>:/', $line, $m)) {
            $inMain = ($m[1] === 'main');
            $sawCall = false;
            continue;
        }
        if (!$inMain) {
            continue;
        }
        if ($sawCall && preg_match('/^\s*([0-9a-f]+):/', $line, $mm)) {
            return '0x' . ltrim($mm[1], '0');
        }
        if (preg_match('/call\s+[0-9a-f]+\s+<vuln>/', $line)) {
            $sawCall = true;
        }
    }
    return null;
}

// The dynamic, payload-driven stack-frame table. Always renders the whole frame
// (buffer, saved RBP, saved return address, plus the stack canary slot when the
// binary has one) so those slots are visible even when the input is too short to
// reach them, extending further when the payload runs past the frame. The
// saved-return-address row's value is exactly the address the CPU will return to
// (payload-derived), resolved to a symbol/region so the learner sees whether it
// lands in the program or faults; when untouched it shows the original
// return-into-main address. $opts: origRet, hasCanary, canaryVal.
function nrun_stack_table(string $bin, string $payload, int $bufSize, array $opts = []): string {
    $origRet   = $opts['origRet'] ?? null;
    $hasCanary = !empty($opts['hasCanary']);
    $canaryVal = $opts['canaryVal'] ?? null;

    $len = strlen($payload);
    $rbpOff = $bufSize + ($hasCanary ? 8 : 0);   // canary sits between buf and saved RBP
    $retOff = $rbpOff + 8;
    $total  = $retOff + 8;                        // through the saved return address
    if ($len > $total) {
        $total = (int) (ceil($len / 8) * 8);
    }

    $rows = '';
    for ($off = 0; $off < $total; $off += 8) {
        $slot = 'above';
        if ($off < $bufSize) {
            $slot = 'buf';
        } elseif ($hasCanary && $off < $bufSize + 8) {
            $slot = 'canary';
        } elseif ($off < $rbpOff + 8) {
            $slot = 'rbp';
        } elseif ($off < $retOff + 8) {
            $slot = 'ret';
        }

        $covered = $off < $len;
        $w = $covered ? substr($payload, $off, 8) : '';

        // The return slot is never empty: if the input did not reach it, it still
        // holds the original return address, so show those real 8 bytes.
        $origSlot = false;
        if ($slot === 'ret' && !$covered && $origRet !== null) {
            $bare = ltrim(strtolower(preg_replace('/^0x/', '', $origRet)), '0');
            $hex16 = str_pad($bare === '' ? '0' : $bare, 16, '0', STR_PAD_LEFT);
            $w = '';
            for ($k = 14; $k >= 0; $k -= 2) {
                $w .= chr(hexdec(substr($hex16, $k, 2)));
            }
            $origSlot = true;
        }
        $isCritical = in_array($slot, ['rbp', 'canary', 'ret'], true);
        $inputCount = max(0, min(8, $len - $off));   // bytes of this word from the input
        $full = $inputCount === 8;

        // Bytes column: your input fills the word first, then the rest is the known
        // original bytes (return slot) or muted "··" placeholders (pre-existing
        // content). Always a full 8 bytes wide.
        $inputHex = '';
        for ($j = 0; $j < $inputCount; $j++) {
            $inputHex .= sprintf('%02x ', ord($payload[$off + $j]));
        }
        $restHex = '';
        for ($j = $inputCount; $j < 8; $j++) {
            $restHex .= ($origSlot ? sprintf('%02x', ord($w[$j])) : '··') . ' ';
        }
        $inputHex = rtrim($inputHex);
        $restHex = rtrim($restHex);
        $parts = [];
        if ($inputHex !== '') {
            $parts[] = $isCritical ? '<mark>' . $inputHex . '</mark>' : $inputHex;
        }
        if ($restHex !== '') {
            $parts[] = '<small>' . $restHex . '</small>';
        }
        $bytesCell = implode(' ', $parts);

        // Value (LE): the 8 bytes read back as an address (high byte first). Known
        // bytes show hex, unknown bytes show "··", so it is always the same width and
        // never fakes zeros.
        $valStr = '0x';
        for ($j = 7; $j >= 0; $j--) {
            if ($j < $inputCount) {
                $valStr .= sprintf('%02x', ord($payload[$off + $j]));
            } elseif ($origSlot) {
                $valStr .= sprintf('%02x', ord($w[$j]));
            } else {
                $valStr .= '··';
            }
        }
        $known = strpos($valStr, '·') === false;
        $val = $known ? $valStr : '';   // resolvable only if every byte is known

        $ascii = '';
        for ($j = 0; $j < strlen($w); $j++) {
            $c = ord($w[$j]);
            $ascii .= ($c >= 0x20 && $c < 0x7f) ? $w[$j] : '.';
        }

        if ($slot === 'ret') {
            if ($covered && $full) {
                $note = 'saved return address<br/>the CPU returns to <strong>' . htmlspecialchars($val) . '</strong>: ' . nrun_resolve_addr($bin, $val);
            } elseif ($covered) {
                $note = 'saved return address, partly overwritten';
            } elseif ($origSlot) {
                $note = 'saved return address, not reached<br/>still holds the original, ' . nrun_resolve_addr($bin, $val);
            } else {
                $note = 'saved return address, not reached';
            }
        } elseif ($slot === 'canary') {
            if ($covered) {
                $note = $canaryVal !== null
                    ? 'stack canary, you overwrote it<br/>the real value is <code>' . htmlspecialchars($canaryVal) . '</code>, so the check fails and the program aborts'
                    : 'stack canary, you overwrote it<br/>so the check fails and the program aborts';
            } else {
                $note = 'stack canary (random, checked just before return)';
            }
        } elseif ($slot === 'rbp') {
            $note = $covered ? 'saved RBP (frame pointer), overwritten' : 'saved RBP (frame pointer), not reached';
        } elseif ($slot === 'buf') {
            $note = "buf[$bufSize], the vulnerable buffer";
        } else {
            $note = 'stack above the frame';
        }

        $valCell = htmlspecialchars($valStr);
        if ($slot === 'ret' && $full) {
            $valCell = '<mark>' . $valCell . '</mark>';
        } elseif (!$known || $origSlot) {
            $valCell = '<small>' . $valCell . '</small>';
        }

        $rows .= '<tr>'
            . '<td><code>+' . $off . '</code></td>'
            . '<td><code>' . $bytesCell . '</code></td>'
            . '<td><code>' . $valCell . '</code></td>'
            . '<td><code>' . htmlspecialchars($ascii) . '</code></td>'
            . '<td>' . $note . '</td>'
            . '</tr>';
    }

    return '<figure><table>'
        . '<thead><tr><th>offset</th><th>bytes</th><th>value (LE)</th><th>ascii</th><th>region</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '</table></figure>';
}
