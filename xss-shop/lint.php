<?php
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
exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $rc);
unlink($tmpFile);

if ($rc === 0) {
    echo '[]';
    exit;
}

$diagnostics = [];
foreach ($output as $line) {
    if (preg_match('/^(?:Parse|Fatal) error:\s*(.+?)\s+in\s+\S+\s+on line\s+(\d+)/i', $line, $m)) {
        $diagnostics[] = [
            'line'     => (int) $m[2],
            'severity' => 'error',
            'message'  => trim($m[1]),
        ];
    }
}

echo json_encode($diagnostics);
