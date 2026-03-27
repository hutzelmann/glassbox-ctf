<!DOCTYPE html><html>
 <head>
  <title>Chat</title>
  <style>
   div {margin-top: 10px;}
  </style>
 </head>
<body>

 <h1>Admin-Chat</h1>

 <div><strong>You:</strong> Hi Admin</div>
 <div><strong>Admin:</strong> Hello! What's up?</div>
 <div><strong>You:</strong> I found a really interesting link. You absolutely have to check it out!
 <?php if (empty($_POST) || empty($_POST["link"])):?>
 <form action="./chat.php<?php if(!empty($_GET["debug"])) {echo "?debug=1";}?>" method="post">
  <input style="margin-left: 40px; width: 500px;" type="url" name="link"/> <input type="submit" value="Submit!"/>
 </form>
 <?php else:?>
    <a href="<?php echo $_POST["link"];?>"><?php echo $_POST["link"];?></a>
 </div>
  <?php if (filter_var($_POST["link"], FILTER_VALIDATE_URL) !== FALSE):?>
    <?php $link = $_POST["link"];?>
    <?php $answer = shell_exec("python3 adminclicks.py $link"); if (empty($answer)) {$answer = "(empty)";}?>
    <?php if(!empty($_GET['debug'])) {echo "<pre>". htmlspecialchars($answer) ."</pre>";}?>
   <div><strong>Admin:</strong> Great, thanks! The page looks really interesting!</div>
<?php else:?>
 <div><strong>Admin:</strong> That is supposed to be a link?!? No way I am clicking on that ...</div>
  <?php endif;?>
 <?php endif;?>
</body>
</html>
