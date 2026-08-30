<?php
require 'debug.php';
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>XSS Cookie Challenge</title>
  <link rel="stylesheet" href="pico.min.css"/>
 </head>
 <body>
  <main class="container">
   <article>
    <header>
     <div class="grid" style="grid-template-columns:1fr auto">
      <hgroup>
       <h1>XSS Cookie Challenge</h1>
       <p>Steal the admin session cookie via cross-site scripting</p>
      </hgroup>
      <nav>
       <ul></ul>
       <ul>
        <li><?php debug_switch(); ?></li>
       </ul>
      </nav>
     </div>
    </header>
    <div class="grid">
     <a href="search.php<?php echo $debugSuffix; ?>" role="button">Vulnerable Page</a>
     <a href="chat.php<?php echo $debugSuffix; ?>" role="button" class="secondary">Chat with the Admin</a>
     <a href="log.php<?php echo $debugSuffix; ?>" role="button" class="secondary">Web Analytics</a>
    </div>
   </article>
  </main>
 </body>
</html>
