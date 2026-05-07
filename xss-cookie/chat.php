<?php
$debugSuffix = (isset($_GET['debug']) && $_GET['debug'] === '1') ? '?debug=1' : '';
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Chat with the Admin</title>
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
       <h1>Chat with the Admin</h1>
       <p>Send a message to the admin</p>
      </hgroup>
      <nav>
       <ul></ul>
       <ul>
        <li>
         <label>
          <input type="checkbox" role="switch"<?php echo isset($_GET['debug']) && $_GET['debug'] === '1' ? ' checked' : ''; ?> onchange="var p=new URLSearchParams(window.location.search);this.checked?p.set('debug','1'):p.delete('debug');var s=p.toString();window.location.replace(s?'?'+s:window.location.pathname)"/>
         </label>
        </li>
        <li><a href="index.php<?php echo $debugSuffix; ?>" role="button" class="secondary">Home</a></li>
       </ul>
      </nav>
     </div>
    </header>
    <p><strong>You:</strong> Hey, I noticed our search page is showing some weird error. Can you take a look?</p>
    <p><strong>Admin:</strong> Sure! Send me the link and I'll check it out.</p>
    <p><strong>You:</strong> Here you go:
    <?php if (empty($_POST) || empty($_POST['link'])): ?>
    <form action="chat.php<?php echo $debugSuffix; ?>" method="post">
     <input type="url" name="link" placeholder="http://..."/>
     <input type="submit" value="Send"/>
    </form>
    <?php else: ?>
    <a href="<?php echo $_POST['link']; ?>"><?php echo $_POST['link']; ?></a></p>
    <?php if (filter_var($_POST['link'], FILTER_VALIDATE_URL) !== false): ?>
     <?php $link = $_POST['link']; ?>
     <?php $answer = shell_exec('python3 ' . escapeshellarg(__DIR__ . '/adminclicks.py') . ' ' . escapeshellarg($link) . ' 2>&1'); if (empty($answer)) { $answer = '(empty)'; } ?>
     <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
     <?php $parsed = json_decode($answer, true); $scriptError = ($parsed === null); $jsErrors = $parsed['js_errors'] ?? []; $pageSource = $parsed['page_source'] ?? ''; ?>
     <?php endif; ?>
    <p><strong>Admin:</strong> No, I had a look and the page seems fine to me.</p>
    <a href="chat.php<?php echo $debugSuffix; ?>" role="button" class="secondary">Restart Chat</a>
    <?php else: ?>
    <p><strong>Admin:</strong> That doesn't look like a valid URL to me.</p>
    <a href="chat.php<?php echo $debugSuffix; ?>" role="button" class="secondary">Restart Chat</a>
    <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1' && isset($jsErrors) && isset($pageSource)): ?>
    <hr/>
    <?php if (isset($scriptError) && $scriptError): ?>
    <h2>Script Error</h2>
    <pre><?php echo htmlspecialchars($answer); ?></pre>
    <?php else: ?>
    <h2>JavaScript Errors in Admin's Browser</h2>
    <?php if (empty($jsErrors)): ?>
    <p>No JavaScript errors detected.</p>
    <?php else: ?>
    <pre><?php foreach ($jsErrors as $e) { echo htmlspecialchars('[' . $e['level'] . '] ' . $e['message']) . "\n"; } ?></pre>
    <?php endif; ?>
    <h2>Page Seen by Admin</h2>
    <textarea data-codemirror="html-edit" rows="20"><?php echo htmlspecialchars($pageSource); ?></textarea>
    <?php endif; ?>
    <?php endif; ?>
   </article>
  </main>
 </body>
</html>
