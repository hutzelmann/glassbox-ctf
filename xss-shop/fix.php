<?php
$error = null;
$debugSuffix = (isset($_GET['debug']) && $_GET['debug'] === '1') ? '?debug=1' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['restore'])) {
        if (@file_put_contents('critical.php', file_get_contents('critical.orig.php')) === false) {
            $error = 'Failed to restore: permission denied.';
        } else {
            header('Location: fix.php?restored=1' . ($debugSuffix ? '&debug=1' : ''));
            exit;
        }
    } elseif (isset($_POST['save']) && isset($_POST['content'])) {
        if (@file_put_contents('critical.php', $_POST['content']) === false) {
            $error = 'Failed to save: permission denied.';
        } else {
            header('Location: fix.php?saved=1' . ($debugSuffix ? '&debug=1' : ''));
            exit;
        }
    }
}
$content = file_get_contents('critical.php');
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Edit the critical code</title>
  <script src="codemirror-bundle.js" defer></script>
  <link rel="stylesheet" href="pico.min.css"/>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <hgroup>
       <h1>Edit the critical code</h1>
       <p>Repair the vulnerability</p>
      </hgroup>
      <nav>
       <ul></ul>
       <ul>
        <li><a href="./<?php echo $debugSuffix; ?>" role="button">Back</a></li>
       </ul>
      </nav>
     </div>
    </header>
    <?php if ($error): ?><p><mark><?php echo htmlspecialchars($error); ?></mark></p><?php endif; ?>
    <?php if (!$error && isset($_GET['saved'])): ?><p><ins>File saved successfully.</ins></p><?php endif; ?>
    <?php if (!$error && isset($_GET['restored'])): ?><p><ins>Original code restored.</ins></p><?php endif; ?>
    <form method="POST">
     <textarea name="content" rows="16" style="font-family:monospace"><?php echo htmlspecialchars($content); ?></textarea>
     <div class="grid">
      <input type="submit" name="save" value="Save"/>
      <input type="submit" name="restore" value="Restore Original" class="secondary"/>
     </div>
    </form>
   </article>
  </main>
 </body>
</html>
