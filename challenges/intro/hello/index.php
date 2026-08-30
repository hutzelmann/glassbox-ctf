<?php
$debugSuffix = (isset($_GET['debug']) && $_GET['debug'] === '1') ? '?debug=1' : '';
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Hello Hacker</title>
  <link rel="stylesheet" href="pico.min.css"/>
  <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
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
        <li>
         <label>
          <input type="checkbox" role="switch"<?php echo isset($_GET['debug']) && $_GET['debug'] === '1' ? ' checked' : ''; ?> onchange="var p=new URLSearchParams(window.location.search);this.checked?p.set('debug','1'):p.delete('debug');var s=p.toString();window.location.replace(s?'?'+s:window.location.pathname)"/>
         </label>
        </li>
        <li><a href="fix.php<?php echo $debugSuffix; ?>" role="button">Fix</a></li>
       </ul>
      </nav>
     </div>
    </header>
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
    <textarea data-codemirror="php-view" hidden><?php echo htmlspecialchars(file_get_contents('critical.php')); ?></textarea>
    <?php else: ?>
    <?php require 'critical.php'; ?>
    <?php endif; ?>
   </article>
  </main>
 </body>
</html>
