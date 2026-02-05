<pre>
<?php
  echo "Server: ".$_SERVER["SERVER_ADDR"].':'.$_SERVER["SERVER_PORT"]." (".$_SERVER["SERVER_NAME"].")\n";
  echo "Message: ".$_ENV["MESSAGE"]."\n";
  echo "Username: ".$_ENV["USERNAME"]."\n";
  echo "Password: ".$_ENV["PASSWORD"]."\n";

  if (isset($_GET["url"])) {
    echo "\nResponse from ".$_GET["url"]."...\n";
  }
?>
</pre>
<?php
  if (isset($_GET["url"])) {
    echo file_get_contents($_GET["url"]);
  }
?>

