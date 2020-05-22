<?php

function handle($json_message) {
    
    if (property_exists($json_message, 'callback_query')) {
        handle_callback($json_message);
        exit(0);
    }
    if ($json_message->chat->id == getenv('admin_chat')) exit(0);

    $sender_is_bot = $json_message->message->from->is_bot;
    $msg_senderid = $json_message->message->from->id;
    $msg_chatid = $json_message->message->chat->id;
    $msg_sendername = $json_message->message->from->first_name;
    $msg = $json_message->message->text;
    $msg_id = $json_message->message->message_id;
    
    if ($sender_is_bot) {
        SendMessage($msg_chatid, 'bots are not allowed.');
    } else {
        //check for user 
        $user = get_user($msg_chatid);
        if ($user === false) {
            SendMessage($msg_chatid, 'u are not registered. send me your name');
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 5);
            exit(0);
        } else if ($user['name'] == null && $user['step'] != 5 && $user['step'] != 6) {
            SendMessage($msg_chatid, 'u are not registered. send me your name');
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 5);
            exit(0);
        } else if ($user['univ'] == null && $user['step'] != 5 && $user['step'] != 6) {
            SendMessage($msg_chatid, 'u are not registered. send me your university');
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 6);
            exit(0);
        }

        if ($msg == '/start') {
            SendMessage($msg_chatid, 'u have already started');
        } else if (strpos($msg, '/start') === 0) {
            $choise_data = explode(" ", $msg)[1]; // id of order he's taking
            $user_id = $msg_chatid;

            if (is_executor_in_table($choise_data, $msg_chatid)) {
                SendMessage($msg_chatid, 'u can not press "i can do it" button more than once');
                exit(0);
            }

            add_executor_in_table($choise_data, $msg_chatid);
            $order = get_order($choise_data);
            if ($order['customer_id'] == $user_id) {
                SendMessage($msg_chatid, 'u can not be an executor of ur order');
                exit(0);
            }
            
            $user_executor = get_user($user_id);
            $text = $user_executor['name']." wants to do ur order [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].").";
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
            SendMessage($msg_chatid, 'kkey, wait until customer will accept u');
        } else if ($msg == '/add_order') {
            // if (!is_user_in_db($msg_chatid)) {
            //     SendMessage($msg_chatid, 'press /start to start working with me');
            // } else {
                //create order, get its id
                $order_id = create_order($msg_chatid);
                //set user step
                set_user_step($msg_chatid, 1);
                //set user current order fill
                set_user_current_order_fill($msg_chatid, $order_id);
                //send message
                SendMessage($msg_chatid, 'kkey, now send me name for ur order (32 chars max)');
            // }

        } else if ($msg == '/my_orders') {
            $my_orders_as_executor = get_orders_as_executor($user['id']);
            $my_orders_as_customer = get_orders_as_customer($user['id']);

            $text = "";

            if ($my_orders_as_executor === false && $my_orders_as_customer == false) {
                $text = "u have no orders";
            }

            if ($my_orders_as_executor !== false) {
                $text .= "I'm executor in theese orders: \n";
                foreach ($my_orders_as_executor as $line) {
                    $text .= "[".$line['name']."](https://t.me/reshalychannel/".$line['post_id'].")\n";
                }
            }
            if ($my_orders_as_customer !== false) {
                $text .= "I'm customer in theese orders: \n";
                foreach ($my_orders_as_customer as $line) {
                    $text .= "[".$line['name']."](https://t.me/reshalychannel/".$line['post_id'].")\n";
                }
            }

            SendMessageWithMarkdown($msg_chatid, $text);

        } else if ($msg == '/info') {

            SendMessageWithMarkdown($msg_chatid, "info about this bot");

        } else if ($msg == '/feedback') {

            SendMessageWithMarkdown($msg_chatid, "kkey, send me ur anonymous feedback");
            set_user_step($msg_chatid, 8);

        } else {
            $user = get_user($msg_chatid);
            $step = $user['step'];
            $msg_len = strlen($msg);
            switch ($step) {
                case 1:
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'send me text from 1 to 32 chars');
                        exit(0);
                    }
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
                    if ($msg_len < 1 || $msg_len > 256) {
                        SendMessage($msg_chatid, 'send me text from 1 to 256 chars');
                        exit(0);
                    }
                    break;
                case 3:
                    if ($msg_len < 1 || $msg_len > 16) {
                        SendMessage($msg_chatid, 'send me text from 1 to 16 chars');
                        exit(0);
                    }
                    $order_id = $user['current_order_fill'];
                    change_order($order_id, 'price', "'$msg'");
                    set_user_step($msg_chatid, 7);
                    SendMessage($msg_chatid, "kkey, now send me a (ONE) file that will be added to ur order. if u dont wanna add any files, send me \"xyu\"");
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
                            // SendMessage($msg_chatid, "kkey, here's ur order link: https://t.me/reshalychannel/$post_id");
                            
                            $data_to_send = new stdClass;
                            $data_to_send->chat_id = $msg_chatid;
                            $data_to_send->text = "kkey, here's ur order link: https://t.me/reshalychannel/$post_id";
                            $data_to_send->parse_mode = 'markdown';
                            $data_to_send->disable_web_page_preview = true;
                            $data_to_send->reply_markup = json_encode((object)(array(
                                'remove_keyboard' => true
                            )));
                            $response = file_get_contents(
                                'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                            );
                            SendMessage($msg_chatid, "now u can add one more order by sending me /add_order command");
                        }
                    } else if ($msg == 'Отменить') {
                        set_user_step($msg_chatid, 0);
                        delete_order($order_id);
                        // SendMessage($msg_chatid, "your order was successfully deleted");
                        $data_to_send = new stdClass;
                        $data_to_send->chat_id = $msg_chatid;
                        $data_to_send->text = "your order was successfully deleted";
                        $data_to_send->parse_mode = 'markdown';
                        $data_to_send->disable_web_page_preview = true;
                        $data_to_send->reply_markup = json_encode((object)(array(
                            'remove_keyboard' => true
                        )));
                        $response = file_get_contents(
                            'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                        );
                    } else {
                        SendMessage($msg_chatid, "incorrect command");
                    }
                    break;
                
                case 5: 
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'send me text from 1 to 32 chars');
                        exit(0);
                    }
                    change_user($msg_chatid, 'name', "'$msg'");
                    set_user_step($msg_chatid, 6);
                    SendMessage($msg_chatid, "kkey, now send me ur university");
                break;
                case 6: 
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'send me text from 1 to 32 chars');
                        exit(0);
                    }
                    change_user($msg_chatid, 'univ', "'$msg'");
                    set_user_step($msg_chatid, 0);
                    SendMessage($msg_chatid, "kkey, now u can add an order by sending me /add_order command");
                break;
                case 7: 
                    $order_id = $user['current_order_fill'];
                    if (property_exists($json_message->message, 'document')) {
                        $file_id = $json_message->message->document->file_id;


                        $data_to_send = new stdClass;
                        $data_to_send->chat_id = "@reshalymedia";
                        $data_to_send->document = $file_id;
                        $response = (object)json_decode(file_get_contents(
                            'https://api.telegram.org/bot'.getenv('bot_token').'/sendDocument?'.http_build_query($data_to_send, '', '&')
                        ));

                        if (!$response->ok) {
                            SendMessage($msg_chatid, 'error processing ur file');
                            exit(0);
                        } 
                        else {
                            $post_id = $response->result->message_id;
                        }

                        change_order($order_id, 'file_id', "'".$post_id."'");
                        SendMessage($msg_chatid, "kkey, this document was added to ur order");
                    }

                    set_user_step($msg_chatid, 4);
                    $line = get_order($order_id);

                    $file = "";
                    if ($line['file_id'] != null) $file = "[.](https://t.me/reshalymedia/".$line['file_id'].")";

$text = 
"Order
*".$line['name']."*
".$line['description']."
Price: ".$line['price']."$file";
                    $data_to_send = new stdClass;
                        $data_to_send->chat_id = $msg_chatid;
                        $data_to_send->text = $text;
                        $data_to_send->parse_mode = 'markdown';
                        $data_to_send->disable_web_page_preview = false;
                        $data_to_send->reply_markup = json_encode((object)(array(
                            'keyboard' => array(array("Публиковать", "Отменить"))
                        )));
                        $response = file_get_contents(
                            'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                        );
                break;
                case 8: 
                    $data_to_send = new stdClass;
                    $data_to_send->chat_id = getenv('admin_chat');
                    $data_to_send->from_chat_id = $msg_chatid;
                    $data_to_send->message_id = $msg_id;
                    $response = file_get_contents(
                        'https://api.telegram.org/bot'.getenv('bot_token').'/forwardMessage?'.http_build_query($data_to_send, '', '&')
                    );
                            
                    SendMessageWithMarkdown($msg_chatid, "kkey, thanks u 4 ur feedback");
                    set_user_step($msg_chatid, 0);
                break;
                    default:
                SendMessage($msg_chatid, 'send me a command');
                    break;
            }
        }
    }
}


?>