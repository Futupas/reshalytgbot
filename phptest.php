<?php
$msg = '/price 26';
$price = substr($msg, strlen('/price '), strlen($msg)-strlen('/price '));
echo $price;
?>