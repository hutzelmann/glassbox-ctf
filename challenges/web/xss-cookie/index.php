<?php
$debugSuffix = (isset($_GET['debug']) && $_GET['debug'] === '1') ? '?debug=1' : '';
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
        <li>
         <label>
          <input type="checkbox" role="switch"<?php echo isset($_GET['debug']) && $_GET['debug'] === '1' ? ' checked' : ''; ?> onchange="var p=new URLSearchParams(window.location.search);this.checked?p.set('debug','1'):p.delete('debug');var s=p.toString();window.location.replace(s?'?'+s:window.location.pathname)"/>
         </label>
        </li>
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
