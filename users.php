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




function is_user_in_db($user_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM \"users\" WHERE id=$user_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $rows = pg_num_rows($result);

    pg_free_result($result);
    pg_close($dbconn);

    if($rows == 0) return false;
    else return true;
}

function add_user_to_db($user_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = 'INSERT INTO "users" ("id", "step", "rating", "current_order_fill") VALUES ('.$user_id.', 0, 0, null)';
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}

function create_order($customer_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = 'INSERT INTO "orders" ("customer_id") VALUES ('.$customer_id.') RETURNING "id"';
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $row = pg_fetch_row($result);
    $new_id = $row['0'];

    pg_free_result($result);
    pg_close($dbconn);

    return $new_id;
}

function set_user_step($user_id, $step) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = 'UPDATE "users" SET "step"='.$step.' WHERE "id"='.$user_id.';';
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}

function set_user_current_order_fill($user_id, $current_order_fill) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = 'UPDATE "users" SET "current_order_fill"='.$current_order_fill.' WHERE "id"='.$user_id.';';
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}

function get_user($user_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM \"users\" WHERE id=$user_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $rows = pg_num_rows($result);

    if ($rows < 1) {
        pg_free_result($result);
        pg_close($dbconn);
        return false;
        //no user in db
    }

    $line = pg_fetch_array($result, 0, PGSQL_ASSOC);

    pg_free_result($result);
    pg_close($dbconn);
    return $line;
}

/**
 * returns order by id
 */
function get_order($order_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM \"orders\" WHERE id=$order_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $rows = pg_num_rows($result);

    if ($rows < 1) {
        pg_free_result($result);
        pg_close($dbconn);
        return false;
        //no order in db
    }

    $line = pg_fetch_array($result, 0, PGSQL_ASSOC);

    pg_free_result($result);
    pg_close($dbconn);
    return $line;
}
function delete_order($order_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "DELETE FROM \"orders\" WHERE id=$order_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}

/**
 * if new value is a string, it has to be in ''
 */
function change_order($order_id, $field, $new_value) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = 'UPDATE "orders" SET "'.$field.'"='.$new_value.' WHERE "id"='.$order_id.';';
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}

/**
 * if new value is a string, it has to be in ''
 */
function change_user($order_id, $field, $new_value) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = 'UPDATE "users" SET "'.$field.'"='.$new_value.' WHERE "id"='.$order_id.';';
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}

function publish_order($order_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM \"orders\" WHERE id=$order_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $rows = pg_num_rows($result);

    if ($rows < 1) {
        g_free_result($result);
        pg_close($dbconn);
        return false;
        //no user in db
    }

    $line = pg_fetch_array($result, 0, PGSQL_ASSOC);

    pg_free_result($result);
    pg_close($dbconn);

    // return $line;

    $text = 
"Order
*".$line['name']."*
".$line['description']."
Price: ".$line['price']." uah";

    $data_to_send = new stdClass;
    $data_to_send->chat_id = -1001271762698;
    $data_to_send->text = $text;
    $data_to_send->parse_mode = 'markdown';
    $data_to_send->disable_web_page_preview = true;
    $data_to_send->reply_markup = json_encode((object)(array(
        'inline_keyboard' => array(array((object)(array(
            'text' => 'i can do it',
            'url' => 'https://t.me/reshalybot?start='.$line['id']
        ))))
    )));

    add_log('request: '.'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&'));
    // return PublishOrderToChannel($text);
    $response = file_get_contents(
        'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
    );
    // $response = file_get_contents(
    //     'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?chat_id=-1001271762698&text='.urlencode($text).'&parse_mode=markdown&disable_web_page_preview=true'
    // );

    return json_decode($response);
}
 
?>
