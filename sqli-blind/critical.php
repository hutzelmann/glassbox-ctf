<?php
$user = $_POST["username"];
$pass = $_POST["password"];

$sql = "SELECT * FROM `users` WHERE username = '$user' AND password = '$pass'";

try {
  $result = $db->query($sql);
} catch(Exception $e) {
  $result = null;
}
?>