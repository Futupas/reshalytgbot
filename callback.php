<?php

function handle_callback($json_message) {
    $callback_query_id = $json_message->callback_query->id;
    $msg_chatid = $json_message->callback_query->message->chat->id;
    $user_id = $json_message->callback_query->from->id;
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
                'text' => 'океу'
            )));
            change_order($order_id, 'executor_id', $executor_id);
            delete_executors_from_table($order_id);
            
            $user_executor = get_user($executor_id);
            $user_customer = get_user($order['customer_id']);

            SendMessageWithMarkdown($customer_id, "[Нажмите на эту ссылку](https://t.me/reshalychatbot?start=".$order['id'].") для общения с исполнителем заказа [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].") (его зовут ".$user_executor['name'].")");
            SendMessageWithMarkdown($executor_id, "[Нажмите на эту ссылку](https://t.me/reshalychatbot?start=".$order['id'].") для общения с заказчиком заказа [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].") (его зовут ".$user_customer['name'].")");


            $file = "";
            if ($order['file_id'] != null) $file = "[ ](https://t.me/reshalymedia/".$order['file_id'].")";
            $rating = "";
            if ($user_customer['rating_votes_quantity'] >= 3) $rating = "\nРейтинг заказчика: ".round($user_customer['rating'], 1)."/5 (отзывов: ".$user_customer['rating_votes_quantity'].")";

                $data_to_send = new stdClass;
                $data_to_send->chat_id = -1001271762698;
                $data_to_send->message_id = $order['post_id'];
                $data_to_send->text =
"🟡Выполняется

*".$order['name']."*

".$order['description']."

Цена: ".$order['price']."$file $rating";
                $data_to_send->parse_mode = 'markdown';
                $data_to_send->disable_web_page_preview = false;
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
                'text' => 'Нельзя разрешить один заказ дважды'
            )));
        }
    }

    
}

?>
