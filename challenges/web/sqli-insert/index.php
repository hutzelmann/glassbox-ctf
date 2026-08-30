<?php
session_start();
require 'debug.php';

$db = new mysqli("127.0.0.1", "hacky", "Ju5TRE4D1t", "hacky");
if ($db->connect_errno) {
    echo "Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error;
    exit();
}

if (($_GET['action'] ?? '') === 'reset') {
    $db->query("DELETE FROM users WHERE uid != 1");
    $db->query("ALTER TABLE users AUTO_INCREMENT = 2");
    session_destroy();
    header('Location: ./' . $debugSuffix);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: ./' . $debugSuffix);
        exit();
    }
}

$loggedInUser = $_SESSION['username'] ?? '';
$action = '';
$errorMsg = '';
$justRegistered = false;
$sql = null;
$debugAllUsers = [];
$debugDbError = null;

if (!empty($_POST) && isset($_POST['login_submit'])) {
    $action = 'login';
    $loginUser = $_POST['username'] ?? '';
    $loginPass = $_POST['password'] ?? '';
    if (empty($loginUser) || empty($loginPass)) {
        $errorMsg = 'Please provide both username and password.';
    } else {
        $stmt = $db->prepare("SELECT uid, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $loginUser);
        $stmt->execute();
        $result = $stmt->get_result();
        $matched = null;
        while ($row = $result->fetch_assoc()) {
            if (password_verify($loginPass, $row['password'])) {
                $matched = $row;
                break;
            }
        }
        $stmt->close();
        if ($matched) {
            $_SESSION['username'] = $matched['username'];
            $loggedInUser = $matched['username'];
        } else {
            $errorMsg = 'Invalid username or password.';
        }
    }
} elseif (!empty($_POST) && isset($_POST['register_submit'])) {
    $action = 'register';
    $regUser = $_POST['reg_username'] ?? '';
    $regPass = $_POST['reg_password'] ?? '';
    if (empty($regUser) || empty($regPass)) {
        $errorMsg = 'Please provide both username and password.';
    } else {
        $regPassHash = password_hash($regPass, PASSWORD_DEFAULT);
        require 'critical.php';
        if (!$insertOk) {
            $debugDbError = $db->error;
            if ($db->errno === 1062) {
                $errorMsg = 'This username is already taken. Please choose a different one.';
            } else {
                $errorMsg = 'Registration failed due to a database error.';
            }
        } else {
            $_SESSION['username'] = $regUser;
            $loggedInUser = $regUser;
            $justRegistered = true;
        }
        if ($debugLevel >= 2) {
            $usersResult = $db->query("SELECT uid, username, password FROM users");
            if ($usersResult) {
                while ($r = $usersResult->fetch_assoc()) {
                    $debugAllUsers[] = $r;
                }
                $usersResult->close();
            }
        }
    }
}

if ($debugLevel >= 2 && empty($debugAllUsers)) {
    $usersResult = $db->query("SELECT uid, username, password FROM users");
    if ($usersResult) {
        while ($r = $usersResult->fetch_assoc()) {
            $debugAllUsers[] = $r;
        }
        $usersResult->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>User Registration</title>
  <link rel="stylesheet" href="pico.min.css"/>
  <?php if ($debugLevel >= 1): ?>
  <script src="codemirror-sql-input.js" defer></script>
  <?php endif; ?>
  <?php if ($debugLevel >= 2): ?>
  <script src="codemirror-sql-edit.js" defer></script>
  <?php endif; ?>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <?php if (!empty($loggedInUser)): ?>
      <hgroup>
       <h1>Welcome, <?php echo htmlspecialchars($loggedInUser); ?>!</h1>
       <p>You are now logged in.</p>
      </hgroup>
      <?php else: ?>
      <hgroup>
       <h1>User Registration</h1>
       <p>Register a new account or sign in below.</p>
      </hgroup>
      <?php endif; ?>
      <nav>
       <ul></ul>
       <ul>
        <li><?php debug_switch(); ?></li>
        <li><a href="./?action=reset<?php echo $debugLevel > 0 ? '&debug=' . $debugLevel : ''; ?>" role="button">Reset</a></li>
        <li><a href="fix.php<?php echo $debugSuffix; ?>" role="button">Fix</a></li>
       </ul>
      </nav>
     </div>
    </header>
    <?php if (!empty($errorMsg)): ?>
    <p><mark><?php echo htmlspecialchars($errorMsg); ?></mark></p>
    <a href="./<?php echo $debugSuffix; ?>" role="button" class="secondary">Back</a>
    <?php elseif (!empty($loggedInUser)): ?>
    <?php if ($justRegistered): ?><p><ins>Registration successful. Welcome aboard!</ins></p><?php endif; ?>
    <p>Coming soon.</p>
    <form method="POST" action="./<?php echo $debugSuffix; ?>">
     <button type="submit" name="logout" value="1" class="secondary">Logout</button>
    </form>
    <?php else: ?>
    <div class="grid">
     <article>
      <header><strong>Login</strong></header>
      <form action="./<?php echo $debugSuffix; ?>" method="POST">
       <label>Username<input type="text" name="username" placeholder="Username"/></label>
       <label>Password<input type="password" name="password" placeholder="Password"/></label>
       <input type="submit" name="login_submit" value="Login"/>
      </form>
     </article>
     <article>
      <header><strong>Register</strong></header>
      <form action="./<?php echo $debugSuffix; ?>" method="POST">
       <?php if ($debugLevel >= 1): ?>
       <label>Username<textarea name="reg_username" data-codemirror="sql-input" rows="1" placeholder="Username"></textarea></label>
       <?php else: ?>
       <label>Username<input type="text" name="reg_username" placeholder="Username"/></label>
       <?php endif; ?>
       <label>Password<input type="password" name="reg_password" placeholder="Password"/></label>
       <input type="submit" name="register_submit" value="Register"/>
      </form>
     </article>
    </div>
    <?php endif; ?>
    <?php if ($debugLevel >= 1 && $debugDbError !== null): ?>
    <hr/>
    <p><mark>DB Error: <?php echo htmlspecialchars($debugDbError); ?></mark></p>
    <?php endif; ?>
    <?php if ($debugLevel >= 2): ?>
    <hr/>
    <article>
    <?php if ($sql !== null): ?>
    <textarea data-codemirror="sql-edit" hidden><?php echo htmlspecialchars($sql); ?></textarea>
    <?php endif; ?>
    <figure><table>
     <thead><tr><th>uid</th><th>username</th><th>password</th></tr></thead>
     <tbody>
      <?php if (empty($debugAllUsers)): ?>
      <tr><td colspan="3">No results</td></tr>
      <?php else: foreach ($debugAllUsers as $r): ?>
      <tr><td><?php echo htmlspecialchars((string)$r['uid']); ?></td><td><?php echo htmlspecialchars((string)$r['username']); ?></td><td><?php echo htmlspecialchars((string)$r['password']); ?></td></tr>
      <?php endforeach; endif; ?>
     </tbody>
    </table></figure>
    </article>
    <?php endif; ?>
   </article>
  </main>
 </body>
</html>
<?php
$db->close();
?>
