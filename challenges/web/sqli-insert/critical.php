<?php
// $db = new mysqli(...) was called earlier
// $regUser = username from POST
// $regPassHash = password_hash of POST password

$sql = "INSERT INTO `users` (username, password) VALUES ('$regUser', '$regPassHash')";

try {
  $insertOk = $db->query($sql);
} catch(Exception $e) {
  $insertOk = false;
}
?>
