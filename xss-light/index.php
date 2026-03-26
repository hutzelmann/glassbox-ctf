<?php
// Set the cookie
setcookie("session", "5uper5ecret5ession5trin9");
?><!DOCTYPE html><html>
 <head>
 <title>Search for Content</title>
 </head>
 <body>
 <h1>Search All Content</h1>
 <p>Simply enter a search term and get started</p>
 <form action="./" method="GET">
   <input type="text" name="q" placeholder="Search term"/>
   <input type="submit" value="Search!"/>
 </form>
 <?php if (!empty($_GET) && !empty($_GET["q"])):?>
      <h3>Unfortunately, the search for <i><?php echo $_GET["q"];?></i> returned no results ...</h3>
 <?php endif;?>
 </body>
</html>
