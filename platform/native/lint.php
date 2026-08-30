<?php
/*
 * lint.php (native family), live C linting for the Fix editor.
 *
 * The editor POSTs the critical.c snippet; we assemble it into the challenge's
 * real translation unit (its main.c #include's critical.c behind a `#line 1
 * "critical.c"` directive) and run `gcc -fsyntax-only`, so what lints is exactly
 * what the Save-time build compiles. Diagnostics inside the snippet report
 * `critical.c:N` thanks to the #line directive, which we map back to editor lines.
 */
error_reporting(0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['code'])) {
    echo '[]';
    exit;
}

$code = $_POST['code'];
if (strlen($code) > 1048576) {
    echo '[]';
    exit;
}

$mainTemplate = __DIR__ . '/main.c';
if (!is_file($mainTemplate)) {
    echo '[]';
    exit;
}

$tmp = sys_get_temp_dir() . '/clint_' . bin2hex(random_bytes(6));
if (!@mkdir($tmp)) {
    echo '[]';
    exit;
}

copy($mainTemplate, $tmp . '/main.c');
foreach (glob(__DIR__ . '/*.h') ?: [] as $h) {
    copy($h, $tmp . '/' . basename($h));
}
file_put_contents($tmp . '/critical.c', $code);

$out = [];
exec('gcc -fsyntax-only ' . escapeshellarg($tmp . '/main.c') . ' 2>&1', $out);

@unlink($tmp . '/main.c');
@unlink($tmp . '/critical.c');
foreach (glob($tmp . '/*.h') ?: [] as $h) {
    @unlink($h);
}
@rmdir($tmp);

$diagnostics = [];
foreach ($out as $line) {
    if (preg_match('/critical\.c:(\d+):(?:\d+:)?\s*(fatal error|error|warning):\s*(.*)$/', $line, $m)) {
        $diagnostics[] = [
            'line'     => (int) $m[1],
            'severity' => ($m[2] === 'warning') ? 'warning' : 'error',
            'message'  => trim($m[3]),
        ];
    }
}

echo json_encode($diagnostics);
