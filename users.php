<?php


// $db_servername = getenv('db_server');
// $db_port = getenv('db_port');
// $db_username = getenv('db_username');
// $db_password = getenv('db_password');
// $db_name = getenv('db_dbname');

$connection_string = "host=ec2-176-34-97-213.eu-west-1.compute.amazonaws.com 
dbname=dqgjdn987m200 
user=umsfvokedwaxub 
password=c543a242bf844d0c09479beb46bd448e9c88f3ac0146705c9c4020593d26bf6f 
port=5432";




function is_user_in_db($userid) {
    $dbconn = pg_connect($connection_string)
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM \"users\" WHERE id=$userid";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $rows = pg_num_rows($result);

    pg_free_result($result);
    pg_close($dbconn);

    if($rows == 0) return false;
    else return true;
}

function add_user_to_db($userid) {
    $dbconn = pg_connect($connection_string)
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = 'INSERT INTO "users" ("id", "step", "rating", "current_order_fill") VALUES ('.$userid.', 0, 0, null)';
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}

?>
