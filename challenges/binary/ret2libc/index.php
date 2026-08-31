<?php
require 'native-run.php';

require 'debug.php';
$BIN = __DIR__ . '/ret2libc';
// Buffer size for the stack-view labels, read from the binary's DWARF so it tracks
// whatever the learner compiles; falls back to the shipped char buf[16].
$BUFSIZE = nrun_buf_size($BIN, 16);

$payloadHex = $_POST['payload_hex'] ?? '';
$ran = false;
$won = false;
$run = null;
$payload = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $payloadHex !== '') {
    $payload = nrun_decode_hex($payloadHex);
    // Run with the binary's working directory at the private flag dir so a spawned
    // shell's `cat flag.txt` resolves there; the flag lives outside the web root.
    $run = nrun_run($BIN, $payload, 3, '/var/challenge');
    $ran = true;
    // Success = a shell command the learner appended actually ran and printed the
    // flag. flag.txt is never read by the challenge itself and is not served by the
    // web layer, only code execution reveals it.
    $flagContent = trim((string) @file_get_contents('/var/challenge/flag.txt'));
    $won = ($flagContent !== '' && strpos($run['stdout'], $flagContent) !== false);
}
$sysAddr   = nrun_symbol_addr($BIN, 'system');
$binshAddr = nrun_string_addr($BIN, '/bin/sh');
$popAddr   = nrun_gadget_addr($BIN);
$bareRet   = $popAddr !== null ? '0x' . dechex(hexdec($popAddr) + 1) : null;

// Roles of the known ROP ingredients, keyed by address. The stack view and the Chain
// tab use these to label each link the learner actually sent (so an argument slot is
// named as an argument, not mislabelled as a return target). They only ever annotate
// a value the learner already submitted, so they reveal no new target address.
$chainRoles = [];
if ($bareRet   !== null) { $chainRoles[$bareRet]   = 'a bare <code>ret</code> (stack alignment)'; }
if ($popAddr   !== null) { $chainRoles[$popAddr]   = 'a <code>pop rdi; ret</code> gadget (loads the next word into RDI)'; }
if ($binshAddr !== null) { $chainRoles[$binshAddr] = 'the <code>"/bin/sh"</code> string address, popped into RDI as <code>system</code>&#39;s argument'; }
if ($sysAddr   !== null) { $chainRoles[$sysAddr]   = 'the <code>system()</code> call target'; }

// Stack geometry + alignment verdict for the submitted chain. glibc's system faults
// on a movaps unless the stack is 16-byte aligned at the call; with this frame that
// holds exactly when (system_slot_offset - retOff) % 16 == 8 (retOff is the saved
// return address; +8 for a canary when present).
$READ_LIMIT = 64;   // vuln reads sizeof(struct msg) = 64 bytes onto the stack
$hasCanary  = (nrun_checksec($BIN)['Canary'] ?? 'No') === 'Yes';
$retOff     = $BUFSIZE + ($hasCanary ? 8 : 0) + 8;
$sysOff     = null;
$aligned    = null;
if ($sysAddr !== null && $payload !== '') {
    $needle = pack('P', hexdec(preg_replace('/^0x/', '', $sysAddr)));
    for ($o = 0; $o + 8 <= min(strlen($payload), $READ_LIMIT); $o += 8) {
        if (substr($payload, $o, 8) === $needle) { $sysOff = $o; break; }
    }
    if ($sysOff !== null) {
        $aligned = (((($sysOff - $retOff) % 16) + 16) % 16) === 8;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>ret2libc</title>
  <link rel="stylesheet" href="pico.min.css"/>
  <script src="remember-form-input.js"></script>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <hgroup>
       <h1>ret2libc</h1>
       <p>No <code>win()</code> this time — reuse what's already in the binary.</p>
      </hgroup>
      <nav>
       <ul></ul>
       <ul>
        <li><?php debug_switch(); ?></li>
        <li><a href="fix.php<?php echo $debugSuffix; ?>" role="button">Fix</a></li>
       </ul>
      </nav>
     </div>
    </header>

    <p>Same overflow as <a href="../ret2win/">ret2win</a>, but there is no
       <code>win()</code> and the stack is non-executable.
       <a href="ret2libc" download>Download the binary</a> and analyse it.</p>

    <form method="POST" action="./<?php echo $debugSuffix; ?>">
     <label>Payload (text and <code>\xNN</code> escapes)
      <textarea id="esc" rows="2" style="font-family:monospace" placeholder="e.g. hi\x41\x42\x0a"></textarea>
     </label>
     <label>Payload as hex (this is what is sent)
      <textarea id="hex" name="payload_hex" rows="2" style="font-family:monospace" placeholder="e.g. 686941420a"><?php echo htmlspecialchars($payloadHex); ?></textarea>
     </label>
     <label>…or load a payload file
      <input type="file" id="up"/>
     </label>
     <input type="submit" value="Send bytes"/>
    </form>

    <?php if ($ran): ?>
    <hr/>
    <?php if ($won): ?>
    <p><ins>You got code execution, a shell ran your command and printed the flag. 🎉</ins></p>
    <?php elseif ($run['timedout']): ?>
    <p><mark>The program was killed (timeout / resource limit).</mark></p>
    <?php elseif (($run['signal'] ?? 0) === 6): ?>
    <p><mark>The program aborted (signal 6), a runtime check caught the overflow.</mark></p>
    <?php elseif ($run['crashed']): ?>
    <p><mark>Segmentation fault (signal <?php echo (int)($run['signal'] ?? 11); ?>),
       your chain is not landing yet.</mark></p>
    <?php else: ?>
    <p>The program returned normally. Your bytes did not reach the return address.</p>
    <?php endif; ?>
    <strong>Program output</strong>
    <pre style="white-space:pre-wrap"><?php echo htmlspecialchars($run['stdout'] !== '' ? $run['stdout'] : '(no output)'); ?></pre>
    <?php if (trim($run['stderr']) !== ''): ?>
    <strong>Error output</strong>
    <pre style="white-space:pre-wrap"><?php echo htmlspecialchars($run['stderr']); ?></pre>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($debugLevel >= 1): ?>
    <hr/>
    <article style="margin-bottom:var(--pico-spacing)">
     <small><strong>Endianness helper.</strong> Type a hex address on one side to get the little-endian bytes for your payload (and back).</small>
     <div class="grid">
      <label>big-endian (as written)
       <input type="text" id="be-conv" placeholder="e.g. 1234" style="font-family:monospace"/>
      </label>
      <label>little-endian (payload bytes)
       <input type="text" id="le-conv" placeholder="e.g. 3412000000000000" style="font-family:monospace"/>
      </label>
     </div>
    </article>
    <?php
    $activeTab = 'stack';
    // Level 1 (Hints) shows the learner's own bytes on the frame plus the static
    // protections and disassembly they need to orient. Level 2 (Debug) adds the
    // live captured frame and the deeper target internals (the gadget/function
    // addresses, memory map, program source) a real attacker extracts themselves.
    $dbgTabs = ['stack' => 'Your bytes'];
    if ($debugLevel >= 1) {
        $dbgTabs += [
            'chain'    => 'Chain',
            'checksec' => 'checksec',
            'disasm'   => 'Disassembly',
        ];
    }
    if ($debugLevel >= 2) {
        $dbgTabs += [
            'rop'  => 'ROP ingredients',
            'maps' => 'Memory map',
            'prog' => 'Program',
        ];
    }
    ?>
    <div role="group" id="dbg-tabs">
     <?php foreach ($dbgTabs as $id => $label): ?>
     <button type="button" data-tab="<?php echo $id; ?>"<?php echo $id === $activeTab ? '' : ' class="outline"'; ?>><?php echo htmlspecialchars($label); ?></button>
     <?php endforeach; ?>
    </div>

    <section data-panel="stack">
     <?php if ($ran): ?>
     <p><small>Hexdump of the <?php echo strlen($payload); ?> bytes the server received
        (only the first 64 are read onto the stack; the rest feed the shell):</small></p>
     <pre style="overflow-x:auto"><?php echo htmlspecialchars(nrun_hexdump($payload)); ?></pre>
     <?php $live = $debugLevel >= 2 ? nrun_gdb_frame($BIN, $payload, $BUFSIZE) : null; ?>
     <?php if ($live): ?>
     <p><small>The <strong>real</strong> frame, captured from the running binary with
        <code>gdb</code>: <strong>before</strong> the <code>read()</code> (the actual saved
        return address and saved RBP, plus the buffer's own uninitialized contents) and
        <strong>after</strong> it (your chain over them). Compare the two columns to see
        which slots your chain overwrote; your gadget addresses land in and above the
        saved return address.</small></p>
     <?php echo nrun_stack_table_live($BIN, $BUFSIZE, $live, ['annotations' => $chainRoles]); ?>
     <?php else: ?>
     <?php if ($debugLevel >= 2): ?>
     <p><small>Live capture is unavailable in this environment; showing the payload-derived
        model instead (the challenge is unaffected).</small></p>
     <?php endif; ?>
     <?php echo nrun_stack_table($BIN, $payload, $BUFSIZE, [
        'origRet'     => nrun_return_addr($BIN),
        'hasCanary'   => $hasCanary,
        'readLimit'   => $READ_LIMIT,
        'annotations' => $chainRoles,
     ]); ?>
     <?php endif; ?>
     <?php else: ?>
     <p><small>The stack frame your input runs off the end of. Fill the buffer and the
        saved RBP; the highlighted <strong>saved return address</strong> is where your
        chain begins. Build it from the gadget and function addresses (turn the dial to
        <strong>Debug</strong> for the <strong>ROP ingredients</strong> panel, or find them
        yourself with <code>ROPgadget</code> / <code>objdump</code>); <code>system</code>
        needs the stack 16-byte aligned, so you may need a bare <code>ret</code> gadget
        first. Send, and your bytes appear laid onto this frame.</small></p>
     <?php echo nrun_frame_diagram($BUFSIZE); ?>
     <?php endif; ?>
    </section>

    <section data-panel="chain" hidden>
     <?php if ($ran): ?>
     <?php
       // Interpret the words from the saved return address up to the read boundary as
       // a ROP chain. Only values the learner submitted are labelled, so no new target
       // address is disclosed here.
       $chainRows = '';
       for ($o = $retOff; $o + 8 <= min(strlen($payload), $READ_LIMIT); $o += 8) {
           $w = substr($payload, $o, 8);
           $vhex = '0x';
           for ($j = 7; $j >= 0; $j--) { $vhex .= sprintf('%02x', ord($w[$j])); }
           $nk = '0x' . (ltrim(strtolower(substr($vhex, 2)), '0') ?: '0');
           $role = $chainRoles[$nk] ?? htmlspecialchars(nrun_resolve_addr($BIN, $vhex));
           $where = $o === $retOff ? ' (the CPU jumps here first)' : '';
           $chainRows .= '<tr><td><code>+' . $o . '</code></td><td><code>'
               . htmlspecialchars($vhex) . '</code></td><td>' . $role . $where . '</td></tr>';
       }
     ?>
     <?php if ($chainRows !== ''): ?>
     <p><small>Your submitted chain, read from the saved return address
        (<code>+<?php echo $retOff; ?></code>) up to the <?php echo $READ_LIMIT; ?>-byte
        read boundary. Each link ends in <code>ret</code>, which pops the next.</small></p>
     <figure><table>
      <thead><tr><th>offset</th><th>value (LE)</th><th>role</th></tr></thead>
      <tbody><?php echo $chainRows; ?></tbody>
     </table></figure>
     <?php if ($sysOff !== null && $aligned): ?>
     <p><ins><strong>Alignment OK.</strong> <code>system</code> is reached 16-byte
        aligned: <code>(<?php echo $sysOff; ?> − <?php echo $retOff; ?>) mod 16 = 8</code>,
        so its <code>movaps</code> will not fault.</ins></p>
     <?php elseif ($sysOff !== null): ?>
     <p><mark><strong>Misaligned.</strong> <code>system</code> is reached with the stack
        off by 8: <code>(<?php echo $sysOff; ?> − <?php echo $retOff; ?>) mod 16 =
        <?php echo (((($sysOff - $retOff) % 16) + 16) % 16); ?></code>, so glibc faults in a
        <code>movaps</code> (a segfault inside <code>system</code>). Prepend one extra
        bare <code>ret</code> (the byte right after your <code>pop rdi; ret</code>
        gadget) before the gadget to shift the stack by 8.</mark></p>
     <?php else: ?>
     <p><small>No <code>system</code> address appears in your chain yet, so there is
        nothing to align-check. Point the last link at <code>system</code> (find its
        address on the <strong>Debug</strong> dial's <strong>ROP ingredients</strong>
        panel, or with <code>ROPgadget</code> / <code>nm</code>).</small></p>
     <?php endif; ?>
     <?php else: ?>
     <p><small>Your input did not reach the saved return address
        (<code>+<?php echo $retOff; ?></code>) yet, so there is no chain to read.</small></p>
     <?php endif; ?>
     <?php else: ?>
     <p><small>Send a payload and this tab reads it back as a ROP chain: each link's
        role, and whether it reaches <code>system</code> with the 16-byte stack
        alignment that call needs.</small></p>
     <?php endif; ?>
    </section>

    <?php if ($debugLevel >= 2): ?>
    <section data-panel="rop" hidden>
     <figure><table>
      <tbody>
       <tr><th><code>system</code></th><td><code><?php echo htmlspecialchars($sysAddr ?? 'not found'); ?></code></td><td>call target, runs a shell command string in RDI</td></tr>
       <tr><th><code>"/bin/sh"</code> string</th><td><code><?php echo htmlspecialchars($binshAddr ?? 'not found'); ?></code></td><td>put this in RDI</td></tr>
       <tr><th><code>pop rdi; ret</code></th><td><code><?php echo htmlspecialchars($popAddr ?? 'not found'); ?></code></td><td>loads the next stack value into RDI</td></tr>
       <tr><th>bare <code>ret</code></th><td><code><?php echo htmlspecialchars($popAddr ? '0x' . dechex(hexdec($popAddr) + 1) : 'not found'); ?></code></td><td>alignment padding (the byte after <code>pop rdi</code>)</td></tr>
      </tbody>
     </table></figure>
     <p><small>These are all at fixed addresses, the binary is <code>-no-pie</code>,
        so no leak is needed. On a real target you would find them with
        <code>ROPgadget</code> / <code>objdump</code> and a libc leak.</small></p>
    </section>
    <?php endif; ?>

    <section data-panel="checksec" hidden>
     <figure><table>
      <tbody>
      <?php foreach (nrun_checksec($BIN) as $k => $v): ?>
       <tr><th><?php echo htmlspecialchars($k); ?></th><td><?php echo htmlspecialchars($v); ?></td></tr>
      <?php endforeach; ?>
      </tbody>
     </table></figure>
     <p><small><strong>NX is enabled</strong>, that is exactly why you cannot inject
        shellcode and must reuse existing code (ROP).</small></p>
    </section>

    <section data-panel="disasm" hidden>
     <pre style="overflow-x:auto"><?php echo htmlspecialchars(nrun_disasm($BIN, ['vuln', 'main'])); ?></pre>
    </section>

    <?php if ($debugLevel >= 2): ?>
    <section data-panel="maps" hidden>
     <?php $maps = nrun_maps($BIN); ?>
     <?php if ($maps): ?>
     <figure><table>
      <thead><tr><th>region</th><th>address range</th><th>perms</th></tr></thead>
      <tbody>
      <?php foreach ($maps as $r): ?>
       <tr><td><?php echo htmlspecialchars($r['label']); ?></td><td><code><?php echo htmlspecialchars($r['range']); ?></code></td><td><code><?php echo htmlspecialchars($r['perms']); ?></code></td></tr>
      <?php endforeach; ?>
      </tbody>
     </table></figure>
     <?php else: ?>
     <p><small>Memory map unavailable in this environment.</small></p>
     <?php endif; ?>
     <p><small>Where this program is loaded in memory this run. With
        <code>-no-pie</code> the base is fixed, which is why <code>system</code>,
        the gadget, and the <code>"/bin/sh"</code> string sit at constant addresses
        and need no leak. Enable PIE (<code>-fpie -pie</code>) and, with the host's
        ASLR on, they move each run, that is when a real ret2libc needs an info leak.</small></p>
    </section>

    <section data-panel="prog" hidden>
     <p><small>The full program (<code>main.c</code>). You edit only <code>critical.c</code>.</small></p>
     <pre style="overflow-x:auto"><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/main.c')); ?></pre>
    </section>
    <?php endif; ?>
    <?php endif; ?>
   </article>
  </main>

  <script>
  (function () {
    var esc = document.getElementById('esc');
    var hex = document.getElementById('hex');
    var up = document.getElementById('up');

    function hexToBytes(h) {
      h = h.replace(/[^0-9a-fA-F]/g, '');
      if (h.length % 2) h = h.slice(0, -1);
      var a = [];
      for (var i = 0; i < h.length; i += 2) a.push(parseInt(h.substr(i, 2), 16));
      return a;
    }
    function bytesToHex(b) {
      return b.map(function (x) { return (x & 0xff).toString(16).padStart(2, '0'); }).join('');
    }
    function escToBytes(s) {
      var b = [];
      for (var i = 0; i < s.length; i++) {
        if (s[i] === '\\' && i + 1 < s.length) {
          var n = s[i + 1];
          if (n === 'x') { b.push(parseInt(s.substr(i + 2, 2), 16) || 0); i += 3; }
          else if (n === 'n') { b.push(10); i++; }
          else if (n === 't') { b.push(9); i++; }
          else if (n === 'r') { b.push(13); i++; }
          else if (n === '0') { b.push(0); i++; }
          else if (n === '\\') { b.push(92); i++; }
          else { b.push(s.charCodeAt(i)); }
        } else {
          b.push(s.charCodeAt(i) & 0xff);
        }
      }
      return b;
    }
    function bytesToEsc(b) {
      return b.map(function (x) {
        return (x >= 0x20 && x < 0x7f && x !== 0x5c) ? String.fromCharCode(x) : ('\\x' + x.toString(16).padStart(2, '0'));
      }).join('');
    }

    if (esc) esc.addEventListener('input', function () { hex.value = bytesToHex(escToBytes(esc.value)); });
    if (hex) hex.addEventListener('input', function () { esc.value = bytesToEsc(hexToBytes(hex.value)); });
    if (hex && hex.value) esc.value = bytesToEsc(hexToBytes(hex.value));

    if (up) up.addEventListener('change', function () {
      var f = up.files[0];
      if (!f) return;
      var r = new FileReader();
      r.onload = function () {
        var b = Array.from(new Uint8Array(r.result));
        hex.value = bytesToHex(b);
        esc.value = bytesToEsc(b);
      };
      r.readAsArrayBuffer(f);
    });
  })();

  // Debug tabs: a click shows one panel and hides the rest.
  (function () {
    var bar = document.getElementById('dbg-tabs');
    if (!bar) return;
    var buttons = bar.querySelectorAll('button');
    var panels = document.querySelectorAll('[data-panel]');
    buttons.forEach(function (b) {
      b.addEventListener('click', function () {
        buttons.forEach(function (x) { x.classList.add('outline'); });
        b.classList.remove('outline');
        var id = b.getAttribute('data-tab');
        panels.forEach(function (p) { p.hidden = (p.getAttribute('data-panel') !== id); });
      });
    });
  })();

  // Big-endian <-> little-endian helper (live sync).
  (function () {
    var be = document.getElementById('be-conv');
    var le = document.getElementById('le-conv');
    if (!be || !le) return;
    function norm(h) { return h.replace(/[^0-9a-fA-F]/g, '').toLowerCase(); }
    function revBytes(h) {
      if (h.length % 2) h = '0' + h;
      var out = '';
      for (var i = h.length - 2; i >= 0; i -= 2) out += h.substr(i, 2);
      return out;
    }
    be.addEventListener('input', function () {
      var h = norm(be.value);
      if (h === '') { le.value = ''; return; }
      if (h.length < 16) h = h.padStart(16, '0');
      le.value = revBytes(h);
    });
    le.addEventListener('input', function () {
      var h = norm(le.value);
      if (h === '') { be.value = ''; return; }
      var b = revBytes(h).replace(/^(?:00)+/, '');
      be.value = b === '' ? '0' : b;
    });
  })();
  </script>
 </body>
</html>
