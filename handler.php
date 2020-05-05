<?php

function handle($json_message) {
    
    if (property_exists($json_message, 'callback_query')) {
        handle_callback($json_message);
        exit(0);
    }

    $sender_is_bot = $json_message->message->from->is_bot;
    $msg_senderid = $json_message->message->from->id;
    $msg_chatid = $json_message->message->chat->id;
    $msg_sendername = $json_message->message->from->first_name;
    $msg = $json_message->message->text;
    $msg_id = $json_message->message->message_id;
    
    if ($sender_is_bot) {
        SendMessage($msg_chatid, 'bots are not allowed.');
    } else {
        if ($msg == '/start') {
            // SendMessage($msg_chatid, urlencode(""));
            if (is_user_in_db($msg_chatid)) {
                SendMessage($msg_chatid, 'u are already in db');
            } else {
                add_user_to_db($msg_chatid);
                SendMessage($msg_chatid, 'kkey, now u can add an order by sending me /add_order command');
            }
        } else if (strpos($msg, '/start') === 0) {
            $choise_data = explode(" ", $msg)[1]; // id of order he's taking
            if (!is_user_in_db($msg_chatid)) {
                // user is not registered
                SendMessage($msg_chatid, 'let\'s register u...');
                add_user_to_db($msg_chatid);
                set_user_current_order_fill($msg_chatid, $choise_data);
            } else {
                $user_id = $msg_chatid;
                $order = get_order($choise_data);
                $text = "[Executor](tg://user?id=$user_id) wants to do ur order [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].").";
                $data_to_send = new stdClass;
                $data_to_send->chat_id = $order['customer_id'];
                $data_to_send->text = $text;
                $data_to_send->parse_mode = 'markdown';
                $data_to_send->disable_web_page_preview = true;
                $data_to_send->reply_markup = json_encode((object)(array(
                    'inline_keyboard' => array(array((object)(array(
                        'text' => 'accept',
                        'callback_data' => $user_id."/".$order['id']
                    ))))
                )));
                $response = file_get_contents(
                    'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                );
            }

        } else if ($msg == '/add_order') {
            if (!is_user_in_db($msg_chatid)) {
                SendMessage($msg_chatid, 'press /start to start working with me');
            } else {
                //create order, get its id
                $order_id = create_order($msg_chatid);
                //set user step
                set_user_step($msg_chatid, 1);
                //set user current order fill
                set_user_current_order_fill($msg_chatid, $order_id);
                //send message
                SendMessage($msg_chatid, 'kkey, now send me name for ur order (32 chars max)');
            }

        } else {
            $user = get_user($msg_chatid);
            $step = $user['step'];
            switch ($step) {
                case 1:
                    $order_id = $user['current_order_fill'];
                    change_order($order_id, 'name', "'$msg'");
                    set_user_step($msg_chatid, 2);
                    SendMessage($msg_chatid, 'kkey, now send me description for ur order (256 chars max)');
                    break;
                case 2:
                    $order_id = $user['current_order_fill'];
                    change_order($order_id, 'description', "'$msg'");
                    set_user_step($msg_chatid, 3);
                    SendMessage($msg_chatid, 'kkey, now send me price of ur order');
                    break;
                case 3:
                    $order_id = $user['current_order_fill'];
                    if (is_string_a_number($msg) && (int)$msg > 0) {
                        change_order($order_id, 'price', $msg);
                        set_user_step($msg_chatid, 4);
                        $line = get_order($order_id);

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
                                'keyboard' => array(array("Публиковать", "Отменить"))
                            )));
                    } else
                        SendMessage($msg_chatid, 'price must be a positive int');
                    break;
                case 4:
                    $order_id = $user['current_order_fill'];
                    if ($msg == 'Публиковать') {
                        set_user_step($msg_chatid, 0);
                        $publish_return = publish_order($order_id);
                        if (!$publish_return->ok) SendMessage($msg_chatid, 'error publishing ur order');
                        else {
                            $post_id = $publish_return->result->message_id;
                            set_user_current_order_fill($msg_chatid, 'null');
                            set_user_step($msg_chatid, 0);
                            change_order($order_id, 'post_id', $post_id);
                            SendMessage($msg_chatid, "kkey, here's ur order link: https://t.me/reshalychannel/$post_id");
                            SendMessage($msg_chatid, "now u can add one more order by sending me /add_order command");
                        }
                    } else if ($msg == 'Отменить') {
                        set_user_step($msg_chatid, 0);
                        delete_order($order_id);
                        SendMessage($msg_chatid, "your order was successfully deleted");
                    } else {
                        SendMessage($msg_chatid, "incorrect command");
                    }
                    break;
                
                default:
                SendMessage($msg_chatid, 'send me a command');
                    break;
            }
        }
    }
}


function is_string_a_number($str) {
    return true;
}

?>