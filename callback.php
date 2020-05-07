<?php

function handle_callback($json_message) {
    $callback_query_id = $json_message->callback_query->id;
    $msg_chatid = $json_message->callback_query->message->chat->id;
    $user_id = $json_message->callback_query->from->id;
    // $user_name = $json_message->callback_query->from->id;
    $choise_data = $json_message->callback_query->data;
    $msg_id = $json_message->callback_query->message->message_id;

    if ($msg_chatid == -1001271762698) { //order
    } else { //allow
        $customer_id = $msg_chatid;
        $executor_id = explode("/", $choise_data)[0];
        $order_id = explode("/", $choise_data)[1];
        $order = get_order($order_id);
        if ($order['executor_id'] == null) {
            file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/answerCallbackQuery?'.
            http_build_query((object)array(
                'callback_query_id' => $callback_query_id,
                'text' => 'kkey'
            )));
            change_order($order_id, 'executor_id', $executor_id);
            delete_executors_from_table($order_id);
            SendMessageWithMarkdown($customer_id, "[This executor](tg://user?id=$user_id) will do your order [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].")");
            SendMessageWithMarkdown($executor_id, "You will do this order [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].") for [this customer](tg://user?id=$user_id)");

                $data_to_send = new stdClass;
                $data_to_send->chat_id = -1001271762698;
                $data_to_send->message_id = $order['post_id'];
                $data_to_send->text =
"Order
*".$order['name']."*
".$order['description']."
Price: ".$order['price']." uah
Done.";
                $data_to_send->parse_mode = 'markdown';
                $data_to_send->disable_web_page_preview = true;
                $data_to_send->reply_markup = '';
                $response = file_get_contents(
                    'https://api.telegram.org/bot'.getenv('bot_token').'/editMessageText?'.http_build_query($data_to_send, '', '&')
                );
            //send messages to customer and executor
            //change message in channel
        } else {
            file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/answerCallbackQuery?'.
            http_build_query((object)array(
                'callback_query_id' => $callback_query_id,
                'text' => 'u cant accept one order twice'
            )));
        }
    }

    
}

?>
