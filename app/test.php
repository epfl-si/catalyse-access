<?php

require './utils.php';
echo '<h3 name="phpinfo">phpinfo</h3>';
phpinfo();
echo '<h3 name="getenv">getenv</h3>';
echo "<pre>";
var_dump(getenv());
echo "</pre>";
echo '<h3 name="server">$_SERVER</h3>';
echo "<pre>";
var_dump($_SERVER);
echo "</pre>";
