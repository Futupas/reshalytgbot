<?php

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

    $query = 'INSERT INTO "users" ("id", "step", "current_order_fill") VALUES ('.$user_id.', 0, null)';
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}
function change_user_rating($user_id, $rating) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "UPDATE users SET rating=((rating*rating_votes_quantity+$rating)/(rating_votes_quantity+1)), rating_votes_quantity=rating_votes_quantity+1 WHERE id=$user_id";
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
    $order = get_order($order_id);
    $customer = get_user($order['customer_id']);
    

    $file = "";
    if ($order['file_id'] != null) $file = "[ ](https://t.me/reshalymedia/".$order['file_id'].")";
    $rating = "";
    if ($customer['rating_votes_quantity'] >= 3) $rating = "\nРейтинг заказчика: ".round($customer['rating'], 1)."/5 (отзывов: ".$customer['rating_votes_quantity'].")";
    
        $data_to_send = new stdClass;
    $data_to_send->chat_id = -1001271762698;
    $data_to_send->message_id = $order['post_id'];
    $data_to_send->text =
"🟢Выполнен

*".$order['name']."*

".$order['description']."

Цена: ".$order['price']." $file $rating";
    $data_to_send->parse_mode = 'markdown';
    $data_to_send->disable_web_page_preview = false;
    $data_to_send->reply_markup = '';
    $response = file_get_contents(
        'https://api.telegram.org/bot'.getenv('bot_token').'/editMessageText?'.http_build_query($data_to_send, '', '&')
    );

    //delete order
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "DELETE FROM \"orders\" WHERE id=$order_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);

    //delete messages
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "DELETE FROM \"chat_messages\" WHERE order_id=$order_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);

    //delete order_executors
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "DELETE FROM \"order_executors\" WHERE order_id=$order_id";
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

    $customer = get_user($line['customer_id']);


    $file = "";
    if ($line['file_id'] != null) $file = "[ ](https://t.me/reshalymedia/".$line['file_id'].")";
    $rating = "";
    if ($customer['rating_votes_quantity'] >= 3) $rating = "\nРейтинг заказчика: ".round($customer['rating'], 1)."/5 (отзывов: ".$customer['rating_votes_quantity'].")";

    $text = 
"🔵Активно

*".$line['name']."*

".$line['description']."

Цена: ".$line['price']." $file $rating";

    $data_to_send = new stdClass;
    $data_to_send->chat_id = -1001271762698;
    $data_to_send->text = $text;
    $data_to_send->parse_mode = 'markdown';
    $data_to_send->disable_web_page_preview = false;
    $data_to_send->reply_markup = json_encode((object)(array(
        'inline_keyboard' => array(array((object)(array(
            'text' => 'Беру 🤝',
            'url' => 'https://t.me/reshalybot?start='.$line['id']
        ))))
    )));

    $response = file_get_contents(
        'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
    );
    return json_decode($response);
}


function is_executor_in_table($order_id, $executor_id) {
    //order_executors
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM \"order_executors\" WHERE order_id=$order_id AND executor_id=$executor_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $rows = pg_num_rows($result);

    if ($rows < 1) {
        pg_free_result($result);
        pg_close($dbconn);
        return false;
        //no order in db
    }


    pg_free_result($result);
    pg_close($dbconn);
    return true;
}
function add_executor_in_table($order_id, $executor_id) {
    //order_executors
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "INSERT INTO \"order_executors\" (order_id,executor_id) VALUES ($order_id , $executor_id)";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}
function delete_executors_from_table($order_id) {
    //order_executors
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "DELETE FROM \"order_executors\" WHERE order_id=$order_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}
 


function add_row_to_chat_messages_table($chat_id, $message_id, $destination_chat_id, $order_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "INSERT INTO \"chat_messages\" (chat_id, message_id, destination_chat_id, order_id) VALUES ($chat_id, $message_id, $destination_chat_id, $order_id)";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}
function add_row_to_chat_messages_table_with_text($chat_id, $message_id, $destination_chat_id, $order_id, $text) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "INSERT INTO \"chat_messages\" (chat_id, message_id, destination_chat_id, order_id, message_text) VALUES ($chat_id, $message_id, $destination_chat_id, $order_id, '$text')";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    pg_free_result($result);
    pg_close($dbconn);
}

function get_row_from_chat_messages_table($chat_id, $message_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM \"chat_messages\" WHERE chat_id=$chat_id AND message_id=$message_id";
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

function get_orders_as_executor($user_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM orders WHERE executor_id=$user_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $rows = pg_num_rows($result);

    if ($rows < 1) {
        pg_free_result($result);
        pg_close($dbconn);
        return false;
        //no order in db
    }

    $line = pg_fetch_all($result, PGSQL_ASSOC);

    pg_free_result($result);
    pg_close($dbconn);
    return $line;
}

function get_orders_as_customer($user_id) {
    $dbconn = pg_connect($GLOBALS['connection_string'])
    or die('Не удалось соединиться: ' . pg_last_error());

    $query = "SELECT * FROM orders WHERE customer_id=$user_id";
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $rows = pg_num_rows($result);

    if ($rows < 1) {
        pg_free_result($result);
        pg_close($dbconn);
        return false;
        //no order in db
    }

    $line = pg_fetch_all($result, PGSQL_ASSOC);

    pg_free_result($result);
    pg_close($dbconn);
    return $line;
}

?>
