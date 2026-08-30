<?php
$debugSuffix = (isset($_GET['debug']) && $_GET['debug'] === '1') ? '?debug=1' : '';
setcookie("session", "5uper5ecret5ession5trin9");
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Search for Content</title>
  <link rel="stylesheet" href="pico.min.css"/>
  <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
  <script src="codemirror-html-edit.js" defer></script>
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
    <form action="./" method="GET">
     <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
     <input type="hidden" name="debug" value="1"/>
     <?php endif; ?>
     <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
     <textarea name="q" data-codemirror="html-edit" rows="4"><?php echo htmlspecialchars($_GET['q'] ?? ''); ?></textarea>
     <?php else: ?>
     <input type="text" name="q" placeholder="Search term" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"/>
     <?php endif; ?>
     <input type="submit" value="Search!"/>
    </form>
    <?php require 'critical.php'; ?>
   </article>
  </main>
 </body>
</html>
