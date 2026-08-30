<?php
require 'debug.php';
setcookie("session", "5uper5ecret5ession5trin9");
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Search for Content</title>
  <link rel="stylesheet" href="pico.min.css"/>
  <script src="remember-form-input.js"></script>
  <?php if ($debugLevel >= 1): ?>
  <script src="codemirror-html-edit.js" defer></script>
  <?php endif; ?>
  <?php if ($debugLevel >= 2): ?>
  <script src="codemirror-php-view.js" defer></script>
  <?php endif; ?>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <hgroup>
       <h1>Search for Content</h1>
       <p>Simply enter a search term and hit search</p>
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
    <form action="./" method="GET">
     <?php if ($debugLevel > 0): ?>
     <input type="hidden" name="debug" value="<?php echo $debugLevel; ?>"/>
     <?php endif; ?>
     <?php if ($debugLevel >= 1): ?>
     <textarea name="q" data-codemirror="html-edit" rows="4"><?php echo htmlspecialchars($_GET['q'] ?? ''); ?></textarea>
     <?php else: ?>
     <input type="text" name="q" placeholder="Search term" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"/>
     <?php endif; ?>
     <input type="submit" value="Search!"/>
    </form>
    <?php require 'critical.php'; ?>
    <?php if ($debugLevel >= 2): ?>
    <hr/>
    <p><strong>What the server received.</strong> <code>$_GET["q"]</code> after
    URL-decoding, exactly as PHP handed it to the code below:</p>
    <pre><code><?php echo htmlspecialchars($_GET['q'] ?? '(not set)'); ?></code></pre>
    <p><strong>The code that printed it.</strong> This is <code>critical.php</code>,
    the snippet the Fix button edits:</p>
    <textarea data-codemirror="php-view" hidden><?php echo htmlspecialchars(file_get_contents('critical.php')); ?></textarea>
    <?php endif; ?>
   </article>
  </main>
 </body>
</html>
