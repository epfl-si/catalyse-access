<?php
header("Location: https://catalyse-test.epfl.ch" . $_SERVER['REQUEST_URI'], true, 301); /* Redirect browser */

/* Make sure that code below does not get executed when we redirect. */
exit;
?>
