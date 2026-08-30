<?php
require 'debug.php';
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Hello Hacker</title>
  <link rel="stylesheet" href="pico.min.css"/>
  <?php if ($debugLevel >= 1): ?>
  <script src="codemirror-php-view.js" defer></script>
  <?php endif; ?>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <hgroup>
       <h1>Hello Hacker</h1>
       <p>Your first challenge</p>
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
    <?php if ($debugLevel >= 1): ?>
    <textarea data-codemirror="php-view" hidden><?php echo htmlspecialchars(file_get_contents('critical.php')); ?></textarea>
    <?php else: ?>
    <?php require 'critical.php'; ?>
    <?php endif; ?>
    <?php if ($debugLevel >= 2): ?>
    <hr/>
    <p><strong>The request, as PHP parsed it.</strong> On the real challenges this
    is where your payload arrives — and where you find out what the server made
    of it.</p>
    <figure><table>
     <thead><tr><th>Source</th><th>Name</th><th>Value</th></tr></thead>
     <tbody>
      <?php
        $requestRows = [];
        foreach ($_GET as $k => $v) {
            $requestRows[] = ['$_GET', $k, is_array($v) ? print_r($v, true) : $v];
        }
        foreach ($_POST as $k => $v) {
            $requestRows[] = ['$_POST', $k, is_array($v) ? print_r($v, true) : $v];
        }
        foreach (['REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING', 'HTTP_USER_AGENT', 'HTTP_COOKIE'] as $k) {
            $requestRows[] = ['$_SERVER', $k, $_SERVER[$k] ?? '(not set)'];
        }
      ?>
      <?php foreach ($requestRows as [$source, $name, $value]): ?>
      <tr>
       <td><code><?php echo htmlspecialchars($source); ?></code></td>
       <td><?php echo htmlspecialchars($name); ?></td>
       <td><code><?php echo htmlspecialchars((string)$value); ?></code></td>
      </tr>
      <?php endforeach; ?>
     </tbody>
    </table></figure>
    <?php endif; ?>
   </article>
  </main>
 </body>
</html>
