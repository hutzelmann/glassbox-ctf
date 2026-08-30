<?php
$items = [
    "Apple" => ["price" => 1.99, "description" => "A juicy green apple."],
    "Banana" => ["price" => 2.99, "description" => "A ripe yellow banana."],
    "Cherry" => ["price" => 0.99, "description" => "A sweet red cherry."]
];
require 'debug.php';
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Shopping Cart</title>
  <link rel="stylesheet" href="pico.min.css"/>
  <script src="remember-form-input.js"></script>
  <?php if ($debugLevel >= 1): ?>
  <script src="codemirror-html-edit.js" defer></script>
  <?php endif; ?>
  <?php if ($debugLevel >= 2): ?>
  <script src="codemirror-php-view.js" defer></script>
  <?php endif; ?>
 </head>
 <body>
  <main class="container">
  <?php if (empty($_POST) || empty($_POST["qty"])):?>
  <article>
   <header>
    <div class="grid" style="grid-template-columns:1fr auto">
     <hgroup>
      <h1>Shopping Cart</h1>
      <p>Manage your items here</p>
     </hgroup>
     <nav>
      <ul></ul>
      <ul>
        <li><?php debug_switch(); ?></li>
       <li><a href="fix.php<?php echo $debugSuffix; ?>" role="button">Fix</a></li>
      </ul>
     </nav>
    </div>
   </header>
   <form action="./<?php echo $debugSuffix; ?>" method="POST">
   <table>
     <thead>
       <tr><th>Item</th><th>Price</th><th>Description</th><th>Quantity</th></tr>
     </thead>
     <tbody>
       <?php foreach ($items as $name => $info): ?>
       <tr>
         <td><?php echo htmlspecialchars($name); ?></td>
         <td><?php echo number_format($info["price"], 2); ?> $</td>
         <td><?php echo htmlspecialchars($info["description"]); ?></td>
         <td><input type="number" name="qty[<?php echo htmlspecialchars($name); ?>]" min="0" max="3" step="1" style="width:auto"/></td>
       </tr>
       <?php endforeach; ?>
     </tbody>
   </table>
   <label for="comment"><strong>Comment:</strong></label>
   <textarea id="comment" name="comment" data-codemirror="html-edit" rows="4" placeholder="Any special wishes?"></textarea>
   <input type="submit" value="Order and Pay"/>
   </form>
  </article>
  <?php else:?>
  <article>
   <header>
    <div class="grid" style="grid-template-columns:1fr auto">
     <hgroup>
      <h1>Package Instructions for Order 1337</h1>
      <p>Please put these items in a box</p>
     </hgroup>
     <nav>
      <ul></ul>
      <ul>
        <li><?php debug_switch(); ?></li>
       <li><a href="fix.php<?php echo $debugSuffix; ?>" role="button">Fix</a></li>
      </ul>
     </nav>
    </div>
   </header>
  <?php
    $exceeded = array_filter($_POST["qty"], fn($qty) => (int)$qty > 3);
    $ordered = array_filter($_POST["qty"], fn($qty, $name) => isset($items[$name]) && (int)$qty > 0, ARRAY_FILTER_USE_BOTH);
  ?>
  <?php if (!empty($exceeded)):?>
    <p><mark>Error: You cannot order more than 3 of any item.</mark></p>
  <?php elseif (empty($ordered)):?>
    <p><mark>Error: no items selected.</mark></p>
  <?php else:?>
    <table>
      <thead>
        <tr><th>Item</th><th>Quantity</th></tr>
      </thead>
      <tbody>
        <?php foreach ($ordered as $name => $qty): ?>
        <tr>
          <td class="item"><?php echo htmlspecialchars($name); ?></td>
          <td class="quantity"><?php echo (int)$qty; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php require 'critical.php'; ?>
  <?php endif;?>
  <?php if ($debugLevel >= 2): ?>
  <hr/>
  <p><strong>What the server received.</strong> <code>$_POST["comment"]</code>
  exactly as PHP handed it to the code below:</p>
  <pre><code><?php echo htmlspecialchars($_POST['comment'] ?? '(not set)'); ?></code></pre>
  <p><strong>The code that printed it.</strong> This is <code>critical.php</code>,
  the snippet the Fix button edits:</p>
  <textarea data-codemirror="php-view" hidden><?php echo htmlspecialchars(file_get_contents('critical.php')); ?></textarea>
  <?php endif; ?>
  <div class="grid">
    <button onclick="checkOrder()">Packaged and Shipped</button>
    <a href="#" onclick="history.back(); return false;" role="button" class="secondary">Return</a>
  </div>
  <p id="check-result"></p>
  <script>
  function checkOrder() {
    var result = document.getElementById('check-result');
    var allowed = ['Apple', 'Banana', 'Cherry'];
    var items = document.querySelectorAll('.item');
    for (var i = 0; i < items.length; i++) {
      if (allowed.indexOf(items[i].textContent.trim()) === -1) {
        result.innerHTML = 'Success: New item added<br><code>8lackFr1day1984</code>';
        return;
      }
    }
    var quantities = document.querySelectorAll('.quantity');
    for (var i = 0; i < quantities.length; i++) {
      if (parseInt(quantities[i].textContent) > 3) {
        result.innerHTML = 'Success: Quantity manipulated<br><code>G1mmeM0re</code>';
        return;
      }
    }
    result.textContent = 'No manipulation detected';
  }
  </script>
  </article>
 <?php endif;?>
  </main>
 </body>
</html>
