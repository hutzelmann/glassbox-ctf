<?php
$debugSuffix = (isset($_GET['debug']) && $_GET['debug'] === '1') ? '?debug=1' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        setcookie('session', '5uper5ecret5ession5trin9', 0, '/');
    } elseif ($_POST['action'] === 'logout') {
        setcookie('session', '', time() - 3600, '/');
    }
    header('Location: search.php' . ($debugSuffix ?: ''));
    exit;
}

$sessionCookie = $_COOKIE['session'] ?? null;
if ($sessionCookie === '1tW0rk5!4real') {
    $greeting = 'Hello Admin';
    $loggedIn = true;
} elseif ($sessionCookie === '5uper5ecret5ession5trin9') {
    $greeting = 'Hello Alice';
    $loggedIn = true;
} elseif ($sessionCookie === '0123456789abcdef') {
    $greeting = 'Hello 0ldFri3nd';
    $loggedIn = true;
} elseif ($sessionCookie !== null) {
    $greeting = 'Hello Stranger';
    $loggedIn = true;
} else {
    $greeting = 'Guest User Access';
    $loggedIn = false;
}
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
        <li><a href="index.php<?php echo $debugSuffix; ?>" role="button" class="secondary">Home</a></li>
       </ul>
      </nav>
     </div>
    </header>
    <div class="grid" style="grid-template-columns:1fr auto;align-items:center">
     <h2><?php echo htmlspecialchars($greeting); ?></h2>
     <form method="POST" action="search.php<?php echo $debugSuffix; ?>">
      <input type="hidden" name="action" value="<?php echo $loggedIn ? 'logout' : 'login'; ?>"/>
      <button type="submit"><?php echo $loggedIn ? 'Logout' : 'Login'; ?></button>
     </form>
    </div>
    <form action="search.php" method="GET">
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
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
    <hr/>
    <p><strong>Debug:</strong> session cookie = <code><?php echo $sessionCookie !== null ? htmlspecialchars($sessionCookie) : '(not set)'; ?></code></p>
    <?php endif; ?>
   </article>
  </main>
 </body>
</html>
