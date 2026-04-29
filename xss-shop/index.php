<?php
$items = [
    "Apple" => ["price" => 1.99, "description" => "A juicy green apple."],
    "Banana" => ["price" => 2.99, "description" => "A ripe yellow banana."],
    "Cherry" => ["price" => 0.99, "description" => "A sweet red cherry."]
];
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Shopping Cart</title>
  <link rel="stylesheet" href="pico.min.css"/>
 </head>
 <body>
  <main class="container">
  <?php if (empty($_POST) || empty($_POST["qty"])):?>
  <article>
   <header>
    <h1>Shopping Cart</h1>
    <p>Manage your items here</p>
   </header>
   <form action="./" method="POST">
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
         <td><input type="number" name="qty[<?php echo htmlspecialchars($name); ?>]" min="0" max="3" step="1" value="0" style="width:auto"/></td>
       </tr>
       <?php endforeach; ?>
     </tbody>
   </table>
   <label for="comment">Comment:</label>
   <textarea id="comment" name="comment" rows="4" placeholder="Any special wishes?"></textarea>
   <input type="submit" value="Order and Pay"/>
   </form>
  </article>
 <script>
 document.querySelector('form').addEventListener('submit', function() {
   var cart = {};
   document.querySelectorAll('input[name^="qty["]').forEach(function(el) {
     cart[el.name] = el.value;
   });
   cart['comment'] = document.getElementById('comment').value;
   sessionStorage.setItem('shopCart', JSON.stringify(cart));
 });
 document.addEventListener('DOMContentLoaded', function() {
   var saved = sessionStorage.getItem('shopCart');
   if (!saved) return;
   sessionStorage.removeItem('shopCart');
   var cart = JSON.parse(saved);
   document.querySelectorAll('input[name^="qty["]').forEach(function(el) {
     if (cart[el.name] !== undefined) el.value = cart[el.name];
   });
   if (cart['comment'] !== undefined)
     document.getElementById('comment').value = cart['comment'];
 });
 </script>
  <?php else:?>
  <article>
   <header><h1>Package Instructions for Order 1337</h1></header>
  <?php
    $exceeded = array_filter($_POST["qty"], fn($qty) => (int)$qty > 3);
    $ordered = array_filter($_POST["qty"], fn($qty, $name) => isset($items[$name]) && (int)$qty > 0, ARRAY_FILTER_USE_BOTH);
  ?>
  <?php if (!empty($exceeded)):?>
    <p>Error: You cannot order more than 3 of any item.</p>
  <?php elseif (empty($ordered)):?>
    <p>Error: no items selected.</p>
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
  <div style="display:flex;gap:1rem">
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
