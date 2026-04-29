<h6>Customer Comment</h6>

<?php if (!empty($_POST["comment"])):?>
  <blockquote><?php echo $_POST["comment"]; ?></blockquote>
<?php else:?>
  <blockquote><em>No comment provided.</em></blockquote>
<?php endif;?>
