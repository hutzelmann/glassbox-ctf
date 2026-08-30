<?php
$debugSuffix = (isset($_GET['debug']) && $_GET['debug'] === '1') ? '?debug=1' : '';
$logFile = __DIR__ . '/log.txt';
$maxEntries = 25;

if (($_GET['action'] ?? '') === 'clear') {
    @unlink($logFile);
    header('Location: log.php' . ($debugSuffix ?: ''));
    exit;
}

// Record this GET request
$entry = json_encode([
    'ts'  => date('c'),
    'url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
    'ua'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
]);
$lines = file_exists($logFile) ? array_filter(explode("\n", file_get_contents($logFile)), 'strlen') : [];
$lines[] = $entry;
if (count($lines) > $maxEntries) {
    $lines = array_slice($lines, -$maxEntries);
}
file_put_contents($logFile, implode("\n", $lines) . "\n");

// Read for display (newest first)
$entries = array_reverse($lines);
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Web Analytics</title>
  <link rel="stylesheet" href="pico.min.css"/>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <hgroup>
       <h1>Web Analytics</h1>
       <p>Visitor and request log</p>
      </hgroup>
      <nav>
       <ul></ul>
       <ul>
        <li>
         <label>
          <input type="checkbox" role="switch"<?php echo isset($_GET['debug']) && $_GET['debug'] === '1' ? ' checked' : ''; ?> onchange="var p=new URLSearchParams(window.location.search);this.checked?p.set('debug','1'):p.delete('debug');var s=p.toString();window.location.replace(s?'?'+s:window.location.pathname)"/>
         </label>
        </li>
        <li><a href="log.php?action=clear<?php echo $debugSuffix ? '&debug=1' : ''; ?>" role="button">Clear Log</a></li>
        <li><a href="index.php<?php echo $debugSuffix; ?>" role="button" class="secondary">Home</a></li>
       </ul>
      </nav>
     </div>
    </header>
    <?php if (empty($entries)): ?>
    <p>No entries yet.</p>
    <?php else: ?>
    <?php foreach ($entries as $line):
        $e = json_decode($line, true);
        if (!$e) continue;
    ?>
    <details>
     <summary><time datetime="<?php echo htmlspecialchars($e['ts']); ?>"><?php echo htmlspecialchars($e['ts']); ?></time>: <?php $q = parse_url($e['url'], PHP_URL_QUERY); echo $q ? '?' . htmlspecialchars($q) : '-'; ?></summary>
     <dl>
      <dt>Timestamp</dt>
      <dd><time datetime="<?php echo htmlspecialchars($e['ts']); ?>"><?php echo htmlspecialchars($e['ts']); ?></time></dd>
      <dt>URL</dt>
      <dd><?php echo htmlspecialchars($e['url']); ?></dd>
      <dt>User-Agent</dt>
      <dd><?php echo htmlspecialchars($e['ua']); ?></dd>
     </dl>
    </details>
    <?php endforeach; ?>
    <?php endif; ?>
   </article>
  </main>
 <script>
  document.querySelectorAll('time[datetime]').forEach(function(el) {
   el.textContent = new Date(el.getAttribute('datetime')).toLocaleString();
  });
 </script>
 </body>
</html>
