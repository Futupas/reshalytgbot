<?php

function handle_callback($json_message) {
    $callback_query_id = $json_message->callback_query->id;
    $msg_chatid = $json_message->callback_query->message->chat->id;
    $user_id = $json_message->callback_query->from->id;
    // $user_name = $json_message->callback_query->from->id;
    $choise_data = $json_message->callback_query->data;
    $msg_id = $json_message->callback_query->message->message_id;

    if ($msg_chatid == -1001271762698) { //order
        if (!is_user_in_db($user_id)) {
            file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/answerCallbackQuery?'.
                http_build_query((object)array(
                    'callback_query_id' => $callback_query_id,
                    'text' => 'u have to be registered in reshalybot to do this'
            )));
        } else {
            file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/answerCallbackQuery?'.
                http_build_query((object)array(
                    'callback_query_id' => $callback_query_id,
                    'text' => 'kkey, wait for response from customer'
            )));
            $order = get_order($choise_data);
            $text = "[Executor](tg://user?id=$user_id) wants to do ur order [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].").";
            $data_to_send = new stdClass;
            $data_to_send->chat_id = $order['customer_id'];
            $data_to_send->text = $text;
            $data_to_send->parse_mode = 'markdown';
            $data_to_send->disable_web_page_preview = true;
            $data_to_send->reply_markup = json_encode((object)(array(
                inline_keyboard => array(array((object)(array(
                    text => 'accept',
                    callback_data => $user_id."/".$order['id']
                ))))
            )));
            $response = file_get_contents(
                'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
            );
        }
    } else { //allow
        $customer_id = $msg_chatid;
        $executor_id = explode("/", $choise_data)[0];
        $order_id = explode("/", $choise_data)[1];
        $order = get_order($choise_data);
        if ($order['executor_id'] == null) {
            change_order($order_id, 'executor_id', $executor_id);
            SendMessage($customer_id, "[This executor](tg://user?id=$user_id) will do your order [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].")");
            SendMessage($customer_id, "You will do this order [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].") for [this customer](tg://user?id=$user_id)");
                $text = "[Executor](tg://user?id=$user_id) wants to do ur order [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].").";
                $data_to_send = new stdClass;
                $data_to_send->chat_id = -1001271762698;
                $data_to_send->text =
"Order
*".$order['name']."*
".$order['description']."
Price: ".$order['price']." uah
Done.";
                $data_to_send->parse_mode = 'markdown';
                $data_to_send->disable_web_page_preview = true;
                $data_to_send->reply_markup = json_encode((object)(array(
                    inline_keyboard => array(array((object)(array(
                        // text => 'accept',
                        // callback_data => $user_id."/".$order['id']
                    ))))
                )));
                $response = file_get_contents(
                    'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                );
            //send messages to customer and executor
            //change message in channel
        } else {
            SendMessage($msg_chatid, 'u cant accept one order twice');
        }
    }

    $response1 = file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/answerCallbackQuery?'.
        http_build_query((object)array(
            'callback_query_id' => $callback_query_id,
            'text' => 'kkey'
        )));
    // $response2 = file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/editMessageReplyMarkup?'.
    // http_build_query((object)array(
    //     'chat_id' => $msg_chatid,
    //     'message_id' => $msg_id,
    //     'reply_markup' => '{"inline_keyboard": [[]]}'
    // )));

    // $conn = new mysqli(
    //     $GLOBALS['db_servername'], 
    //     $GLOBALS['db_username'], 
    //     $GLOBALS['db_password'], 
    //     $GLOBALS['db_name'], 
    //     $GLOBALS['db_port']);
    // if ($conn->connect_error) {
    //     add_log('Connection failed: '.$conn->connect_error);
    //     add_to_channel('Connection to DB failed: '.$conn->connect_error);
    //     die();
    // }
    
    // $sql = 'INSERT INTO `Matches`(`choise_profile_id`, `showed_profile_id`, `matching`) VALUES 
    // ('.$msg_chatid.', '.$choise_data_array[0].', '.$choise_data_array[1].')';
    
    // $result = $conn->query($sql);
    // $conn->close();

    // are_profiles_match_each_other($msg_chatid, $choise_data_array[0]);

    // send_matching_profile($msg_chatid);

    // SendMessage($msg_chatid, 'ok, you did ur choise');

    // add_to_channel($callback_query_id.'%0A'.$msg_chatid.'%0A'.$choise_data.'%0A'.$msg_id);
}

?>
