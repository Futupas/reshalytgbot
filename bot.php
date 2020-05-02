<?php
include 'send_message.php';
include 'logging.php';
include 'users.php';
include 'handler.php';
include 'callback.php';


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $requestString = file_get_contents('php://input');
    $requestData = json_decode($requestString);
    add_log($requestString);

    handle($requestData);

} else {
    echo('Prozhektor Perestroyki');
}
?>
