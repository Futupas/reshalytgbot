<?php
    include 'send_message.php';
    include 'logging.php';
    include 'users.php';
    // include 'handler.php';
    // include 'callback.php';
    
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $requestString = file_get_contents('php://input');
        $json_message = json_decode($requestString);
        add_log($requestString);

        if (property_exists($json_message, 'pre_checkout_query')) {
            $order_id = $json_message->pre_checkout_query->invoice_payload;
            $pre_checkout_query_id = $json_message->pre_checkout_query->id;
            $order = get_order($order_id);
            $data_to_send = new stdClass;
            $data_to_send->pre_checkout_query_id = $pre_checkout_query_id;
            $data_to_send->ok = true;
            $response = (object)json_decode(file_get_contents(
                'https://api.telegram.org/bot'.getenv('chat_bot_token').'/answerPreCheckoutQuery?'.http_build_query($data_to_send, '', '&')
            ));
            SendMessageToChatBot($order['executor_id'], 'u can now do this order ('.$order['name'].') because customer had paid for it');
            SendMessageToChatBot($order['customer_id'], 'executor of order '.$order['name'].' got a msg that he can now do this order');
            exit(0);
        }
        if (property_exists($json_message, 'message') && property_exists($json_message->message, 'successful_payment')) {
            exit(0);
        }
    
        $sender_is_bot = $json_message->message->from->is_bot;
        $msg_senderid = $json_message->message->from->id;
        $msg_chatid = $json_message->message->chat->id;
        $msg_sendername = $json_message->message->from->first_name;
        $msg = $json_message->message->text;
        $msg_id = $json_message->message->message_id;
        
        if ($sender_is_bot) {
            SendMessageToChatBot($msg_chatid, 'bots are not allowed.');
        } else {
            //check for user
            $user = get_user($msg_chatid);
            if ($user === false) {
                SendMessageToChatBot($msg_chatid, 'u are not registered. go to @reshalybot to do it');
                exit(0);
            } else if ($user['name'] == null && $user['step'] != 5 && $user['step'] != 6) {
                SendMessageToChatBot($msg_chatid, 'u are not registered. go to @reshalybot to do it');
                exit(0);
            } else if ($user['univ'] == null && $user['step'] != 5 && $user['step'] != 6) {
                SendMessageToChatBot($msg_chatid, 'u are not registered. go to @reshalybot to do it');
                exit(0);
            }

            if ($msg == '/start') {
                SendMessageToChatBot($msg_chatid, 'kkey, but u have to go to @reshalybot to do it');
            } else if (strpos($msg, '/start') === 0) {
                $choise_data = explode(" ", $msg)[1]; // id of order he's taking
                $user_id = $msg_chatid;

                $order = get_order($choise_data);

                if ($order === false) {
                    SendMessageToChatBot($msg_chatid, 'u can not use this bot with no order');
                    exit(0);
                }


                if ($user_id == $order['customer_id']) {
                    $text = "ккеу, сбщ, которые ты отправишь мне, отвечая на это сбщ, я отправлю исполнителю заказа \"".$order['name']."\". И все сбщ, которые ты отправишь, отвечая на его сбщ, так же отправятся ему. ответь сообщением /done что б завершить заказ со своей стороны. когда заказ буит закрыт с двух сторон, исполнитель получить лв, а статус заказа перейдёт в выполненный";
                    $data_to_send = new stdClass;
                    $data_to_send->chat_id = $order['customer_id'];
                    $data_to_send->text = $text;
                    $data_to_send->parse_mode = 'markdown';
                    $data_to_send->disable_web_page_preview = true;
                    $response = json_decode(file_get_contents(
                        'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                    ));
                    add_row_to_chat_messages_table($user_id, $response->result->message_id, $order['executor_id'], $choise_data);
                } else if ($user_id == $order['executor_id']) {
                    $text = "ккеу, сбщ, которые ты отправишь мне, отвечая на это сбщ, я отправлю заказчику заказа \"".$order['name']."\". И все сбщ, которые ты отправишь, отвечая на его сбщ, так же отправятся ему. ответь сообщением /done что б завершить заказ со своей стороны. когда заказ буит закрыт с двух сторон, исполнитель получить лв, а статус заказа перейдёт в выполненный";
                    $data_to_send = new stdClass;
                    $data_to_send->chat_id = $order['executor_id'];
                    $data_to_send->text = $text;
                    $data_to_send->parse_mode = 'markdown';
                    $data_to_send->disable_web_page_preview = true;
                    $response = json_decode(file_get_contents(
                        'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                    ));
                    add_row_to_chat_messages_table($user_id, $response->result->message_id, $order['customer_id'], $choise_data);
                } else {
                    SendMessageToChatBot($msg_chatid, 'u can not use this bot with no order');
                    exit(0);
                }

                
            } else {
                if (property_exists($json_message->message, 'reply_to_message')) {
                    $reply_to_message_id = $json_message->message->reply_to_message->message_id;
                    $chat_message = get_row_from_chat_messages_table($msg_chatid, $reply_to_message_id);
                    if ($chat_message === false) {
                        SendMessageToChatBot($msg_chatid, 'u replied on a wrong message');
                        exit(0);
                    }

                    if ($msg == '/done') {
                        $order = get_order($chat_message['order_id']);
                        // add_log(print_r($order, true));
                        if ($msg_chatid == $order['customer_id']) {
                            change_order($order['id'], 'customer_done', 'true');
                            if ($order['executor_done'] === 't') {
                                delete_order($order['id']);
                                SendMessageToChatBot($msg_chatid, 'kkey, order was deleted');
                                SendMessageToChatBot($order['executor_id'], 'order was deleted');
                            } else {
                                SendMessageToChatBot($msg_chatid, 'kkey, wait until executor will stop order to');
                            }
                            exit(0);
                        } else if ($msg_chatid == $order['executor_id']) {
                            change_order($order['id'], 'executor_done', 'true');
                            if ($order['customer_done'] === 't') {
                                delete_order($order['id']);
                                SendMessageToChatBot($msg_chatid, 'kkey, order was deleted');
                                SendMessageToChatBot($order['customer_id'], 'order was deleted');
                            } else {
                                SendMessageToChatBot($msg_chatid, 'kkey, wait until customer will stop order to');
                            }
                            exit(0);
                        } else {
                            SendMessageToChatBot($msg_chatid, 'u can not use this bot with no order');
                            exit(0);
                        }
                    } else if (strpos($msg, '/price ') === 0) {
                        $price = substr($msg, strlen('/price '), strlen($msg)-strlen('/price '));
                        if (!is_numeric($price) || strpos($price, "," !== false) || strpos($price, "." !== false) || strpos($price, "-" !== false)) {
                            SendMessageToChatBot($msg_chatid, 'ur price is fucking bad, send me a fucking positive integer');
                            exit(0);
                        }
                        $order = get_order($chat_message['order_id']);
                        // add_log(print_r($order, true));
                        if ($msg_chatid == $order['customer_id']) {
                            change_order($order['id'], 'customer_price', $price);
                            if ($order['executor_price'] !== null) {
                                if ($order['executor_price'] != $price) {
                                    SendMessageToChatBot($msg_chatid, 'offered prices must be equal');
                                    exit(0);
                                }
                                // SendMessageToChatBot($msg_chatid, 'pay!');
                                $data_to_send = new stdClass;
                                $data_to_send->chat_id = $msg_chatid;
                                $data_to_send->title = "Order";
                                $data_to_send->description = $order['name'];
                                $data_to_send->payload = $order['id'];
                                $data_to_send->provider_token = getenv('pay_token');
                                $data_to_send->start_parameter = '15';
                                $data_to_send->currency = "UAH";
                                $data_to_send->prices = '[{"label":"'.$price.' uah", "amount": '.$price.'00}]';
                                $response = (object)json_decode(file_get_contents(
                                    'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendInvoice?'.http_build_query($data_to_send, '', '&')
                                ));
                                SendMessageToChatBot($order['executor_id'], 'kkey, price was confirmed, wait 4 a msg');
                            } else {
                                SendMessageToChatBot($msg_chatid, 'kkey, ur price was set, wait until executor will accept ur price');
                                SendMessageToChatBot($order['executor_id'], 'customer offered price '.$price.' uah');
                            }
                            exit(0);
                        } else if ($msg_chatid == $order['executor_id']) {
                            change_order($order['id'], 'executor_price', $price);
                            if ($order['customer_price'] !== null) {
                                if ($order['customer_price'] != $price) {
                                    SendMessageToChatBot($msg_chatid, 'offered prices must be equal');
                                    exit(0);
                                }
                                $data_to_send = new stdClass;
                                $data_to_send->chat_id = $order['customer_id'];
                                $data_to_send->title = "Order";
                                $data_to_send->description = $order['name'];
                                $data_to_send->payload = $order['id'];
                                $data_to_send->provider_token = getenv('pay_token');
                                $data_to_send->start_parameter = '15';
                                $data_to_send->currency = "UAH";
                                $data_to_send->prices = '[{"label":"'.$price.' uah", "amount": '.$price.'00}]';
                                $response = (object)json_decode(file_get_contents(
                                    'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendInvoice?'.http_build_query($data_to_send, '', '&')
                                ));
                                SendMessageToChatBot($msg_chatid, 'kkey, price was confirmed, wait 4 a msg');
                            } else {
                                SendMessageToChatBot($msg_chatid, 'kkey, ur price was set, wait until customer will accept ur price');
                                SendMessageToChatBot($order['customer_id'], 'executor offered price '.$price.' uah');
                            }
                            exit(0);
                        } else {
                            SendMessageToChatBot($msg_chatid, 'u can not use this bot with no order');
                            exit(0);
                        }
                    } else {
                        $text = "*".$user['name']."*:\n$msg";
                        $response = SendMessageWithMarkdownToChatBot($chat_message['destination_chat_id'], $text);
                        add_row_to_chat_messages_table($chat_message['destination_chat_id'], $response->result->message_id, $msg_chatid, $chat_message['order_id']);
                    }
                } else {
                    SendMessageToChatBot($msg_chatid, 'u can not send me msg that is not a reply');
                    exit(0);
                }
            }
        }
    } else {
        echo('Prozhektor Perestroyki');
    }
?>