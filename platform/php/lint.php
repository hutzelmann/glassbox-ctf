<?php
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

$tmpFile = tempnam(sys_get_temp_dir(), 'php_lint_');
if ($tmpFile === false) {
    echo '[]';
    exit;
}

file_put_contents($tmpFile, $code);

// Phase 1: syntax check (~20ms), skip Psalm entirely when syntax is broken
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $phpOut, $phpRc);
if ($phpRc !== 0) {
    unlink($tmpFile);
    $diagnostics = [];
    foreach ($phpOut as $line) {
        if (preg_match('/^(?:Parse|Fatal) error:\s*(.+?)\s+in\s+\S+\s+on line\s+(\d+)/i', $line, $m)) {
            $diagnostics[] = [
                'line'     => (int) $m[2],
                'severity' => 'error',
                'message'  => trim($m[1]),
            ];
        }
    }
    echo json_encode($diagnostics);
    exit;
}

// Phase 2: Psalm static analysis, detects undefined functions, classes, constants, too-few-args
$psalmConf = __DIR__ . '/psalm.xml';
$cmd = 'psalm --config=' . escapeshellarg($psalmConf)
     . ' --output-format=json --no-progress --no-suggestions '
     . escapeshellarg($tmpFile) . ' 2>/dev/null';
exec($cmd, $psalmOut, $psalmRc);
unlink($tmpFile);

$psalmData = json_decode(implode('', $psalmOut), true);
if (!is_array($psalmData)) {
    echo '[]';
    exit;
}

$allowed = ['UndefinedFunction', 'UndefinedClass', 'UndefinedMethod', 'UndefinedConstant', 'TooFewArguments'];
$diagnostics = [];
foreach ($psalmData as $issue) {
    if (!in_array($issue['type'] ?? '', $allowed, true)) {
        continue;
    }
    $line = (int) ($issue['line_from'] ?? 0);
    if ($line < 1) {
        continue;
    }
    $diagnostics[] = [
        'line'     => $line,
        'severity' => 'error',
        'message'  => $issue['message'] ?? $issue['type'],
    ];
}

echo json_encode($diagnostics);
