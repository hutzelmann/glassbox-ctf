<?php
// Just init the database connection
$db = new mysqli("127.0.0.1", "hacky", "Ju5TRE4D1t", "hacky");
if ($db->connect_errno) {
    echo "Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error;
    exit();
}
?><!DOCTYPE html><html>
 <head>
  <title>Admin Area</title>
 </head>
 <body>
  <?php if (empty($_POST)):?>
  <h1>Login</h1>
  <p>Please login before you can use the admin area</p>
  <form action="./<?php if(!empty($_GET["debug"])) {echo "?debug=1";}?>" method="POST">
   <input type="text" name="username" placeholder="Username"/>
   <input type="text" name="password" placeholder="Password"/>
   <input type="submit" value="login!"/>
  </form>
 <?php else:?>
 <?php
 $username = "";
if (!empty($_POST["username"]) && !empty($_POST["password"])) {
  // That is definitely not the way to build SQL queries
  $user = $_POST["username"];
  $pass = $_POST["password"];
  $sql = "SELECT * FROM `users` WHERE username = '$user' AND password = '$pass'";

  // for debug
  if (!empty($_GET["debug"])) {
    echo "<pre>" . $sql . "</pre><br/>";
  }

  try {
    $result = $db->query($sql);
  } catch(Exception $e) {
    $result = null;
  }

  if (!$result) {
    if (!empty($_GET["debug"])) {
      echo "<pre>". $db->error  . "</pre>";
    }
  } else {
    $row = $result->fetch_assoc();
    if (!empty($row)) {
      $username = $row["username"];
    }
    $result->close();
  }
}
 ?>

 <?php if(!empty($username)):?>
  <h1>Welcome <?php echo $username ?></h1>
  <p>TODO: Actually build an admin Panel</p>
 <?php elseif (empty($_POST["username"]) || empty($_POST["password"])):?>
  <p>You have not provided complete login credentials!</p>
 <?php elseif (!$result):?>
  <p>Unfortunately, something went wrong with the database query :X</p>
 <?php else:?>
  <p>I am sorry, but your login data is wrong ;(</p>
 <?php endif;?><?php endif;?>
 </body>
</html>
<?php
$db->close();
?>
