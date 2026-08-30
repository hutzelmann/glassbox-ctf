<?php
$debugSuffix = (isset($_GET['debug']) && $_GET['debug'] === '1') ? '?debug=1' : '';
// Just init the database connection
$db = new mysqli("127.0.0.1", "hacky", "Ju5TRE4D1t", "hacky");
if ($db->connect_errno) {
    echo "Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error;
    exit();
}

$username = "";
$result = null;
$debugDbError = null;
$debugRows = [];
$debugFields = [];
if (!empty($_POST) && !empty($_POST["username"]) && !empty($_POST["password"])) {
  require 'critical.php';
  if (!$result) {
    $debugDbError = $db->error;
  } else {
    $row = $result->fetch_assoc();
    if (!empty($row)) {
      $username = $row["username"];
    }
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
      $debugFields = array_map(fn($f) => $f->name, $result->fetch_fields());
      $result->data_seek(0);
      while ($r = $result->fetch_assoc()) {
        $debugRows[] = $r;
      }
    }
    $result->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin Login</title>
  <link rel="stylesheet" href="pico.min.css"/>
  <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
  <script src="codemirror-sql-edit.js" defer></script>
  <?php endif; ?>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <?php if (!empty($username)): ?>
      <hgroup>
       <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
       <p>You are logged in to the admin area.</p>
      </hgroup>
      <?php else: ?>
      <hgroup>
       <h1>Admin Login</h1>
       <p>Please login before you can use the admin area</p>
      </hgroup>
      <?php endif; ?>
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
    <?php if (empty($_POST)):?>
    <form action="./<?php echo $debugSuffix; ?>" method="POST">
     <label>Username<input type="text" name="username" placeholder="Username"/></label>
     <label>Password<input type="text" name="password" placeholder="Password"/></label>
     <input type="submit" value="Login"/>
    </form>
 <script>
 document.querySelector('form').addEventListener('submit', function() {
   sessionStorage.setItem('loginForm', JSON.stringify({
     username: document.querySelector('[name=username]').value,
     password: document.querySelector('[name=password]').value
   }));
 });
 document.addEventListener('DOMContentLoaded', function() {
   var saved = sessionStorage.getItem('loginForm');
   if (!saved) return;
   sessionStorage.removeItem('loginForm');
   var data = JSON.parse(saved);
   document.querySelector('[name=username]').value = data.username || '';
   document.querySelector('[name=password]').value = data.password || '';
 });
 </script>
    <?php else:?>
    <?php if (!empty($username)):?>
     <div class="grid">
      <article>
       <header><strong>User Management</strong></header>
       <p><small>Coming soon</small></p>
      </article>
      <article>
       <header><strong>Content</strong></header>
       <p><small>Coming soon</small></p>
      </article>
      <article>
       <header><strong>Settings</strong></header>
       <p><small>Coming soon</small></p>
      </article>
     </div>
    <?php elseif (empty($_POST["username"]) || empty($_POST["password"])):?>
     <p><mark>You have not provided complete login credentials!</mark></p>
    <?php elseif (!$result):?>
     <p><mark>Unfortunately, something went wrong with the database query.</mark></p>
    <?php else:?>
     <p><mark>I am sorry, but your login data is wrong.</mark></p>
    <?php endif;?>
    <a href="./<?php echo $debugSuffix; ?>" role="button" class="secondary">Back to Login</a>
    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1' && isset($sql)):?>
    <hr/>
    <textarea data-codemirror="sql-edit" hidden><?php echo htmlspecialchars($sql); ?></textarea>
    <?php if ($debugDbError !== null):?>
    <p><mark>DB Error: <?php echo htmlspecialchars($debugDbError); ?></mark></p>
    <?php else:?>
    <figure><table>
     <thead><tr><?php foreach ($debugFields as $f):?><th><?php echo htmlspecialchars($f); ?></th><?php endforeach;?></tr></thead>
     <tbody>
      <?php if (empty($debugRows)):?>
      <tr><td colspan="<?php echo count($debugFields); ?>">No results</td></tr>
      <?php else: foreach ($debugRows as $i => $r):?>
      <tr><?php foreach ($debugFields as $f):?><td><?php if ($i === 0 && $f === 'username'):?><mark><?php echo htmlspecialchars((string)$r[$f]); ?></mark><?php else: echo htmlspecialchars((string)$r[$f]); endif;?></td><?php endforeach;?></tr>
      <?php endforeach; endif;?>
     </tbody>
    </table></figure>
    <?php endif;?>
    <?php endif;?>
    <?php endif;?>
   </article>
  </main>
 </body>
</html>
<?php
$db->close();
?>
