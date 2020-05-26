<?php
    include 'send_message.php';
    include 'logging.php';
    include 'users.php';
    
    
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
            $response = SendMessageToChatBot('e', 'ты можешь приступить к выполнению заказа ('.$order['name'].') ибо заказчик уже заплатил за него', $order);
            $response = SendMessageToChatBot('c', 'исполнитель заказа '.$order['name'].' получил сообщение, что может приступать к работе', $order);
            exit(0);
        }
        if (property_exists($json_message, 'message') && property_exists($json_message->message, 'successful_payment')) {
            exit(0);
        }
        if (property_exists($json_message, 'callback_query') && $json_message->callback_query->message->chat->id == getenv('admin_chat')) {
            $callback_query_id = $json_message->callback_query->id;
            $msg_chatid = $json_message->callback_query->message->chat->id;
            $user_id = $json_message->callback_query->from->id;
            $choise_data = $json_message->callback_query->data;
            $msg_id = $json_message->callback_query->message->message_id;

            file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/answerCallbackQuery?'.
            http_build_query((object)array(
                'callback_query_id' => $callback_query_id,
                'text' => 'океу'
            )));

            $order_id = $choise_data;
            $order = get_order($order_id);

            $response = SendMessageToChatBot('e', 'деньги перевели', $order);
            delete_order($order_id);

            $data_to_send = new stdClass;
            $data_to_send->chat_id = getenv('admin_chat');
            $data_to_send->message_id = $msg_id;
            $data_to_send->text = "заказ ".$order['name']." был полностью закрыт";
            $data_to_send->parse_mode = 'markdown';
            $data_to_send->disable_web_page_preview = false;
            $data_to_send->reply_markup = '';
            $response = file_get_contents(
                'https://api.telegram.org/bot'.getenv('chat_bot_token').'/editMessageText?'.http_build_query($data_to_send, '', '&')
            );

            // rating
            set_user_current_order_fill($order['customer_id'], $order['executor_id']);
            set_user_step($order['customer_id'], 9);
            $data_to_send = new stdClass;
            $data_to_send->chat_id = $order['customer_id'];
            $data_to_send->text = "оцени, пожалуйста твоего исполнителя заказа (1 - очень плохо, 5 - очень хорошо)";
            $data_to_send->reply_markup = json_encode((object)(array(
                'keyboard' => array(array("1", "2", "3", "4", "5"))
            )));
            $response = json_decode(file_get_contents(
                'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
            ));
            set_user_current_order_fill($order['executor_id'], $order['customer_id']);
            set_user_step($order['executor_id'], 9);
            $data_to_send = new stdClass;
            $data_to_send->chat_id = $order['executor_id'];
            $data_to_send->text = "оцени, пожалуйста твоего заказчика (1 - очень плохо, 5 - очень хорошо)";
            $data_to_send->reply_markup = json_encode((object)(array(
                'keyboard' => array(array("1", "2", "3", "4", "5"))
            )));
            $response = json_decode(file_get_contents(
                'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
            ));
                            
        }

        
        if ($json_message->message->chat->id == getenv('admin_chat')) exit(0);
    
        $sender_is_bot = $json_message->message->from->is_bot;
        $msg_senderid = $json_message->message->from->id;
        $msg_chatid = $json_message->message->chat->id;
        $msg_sendername = $json_message->message->from->first_name;
        $msg = $json_message->message->text;
        $msg_id = $json_message->message->message_id;
        
        if ($sender_is_bot) {
            SendMessageToChatBotWithNoOrder($msg_chatid, 'боты запрещены.');
        } else {
            //check for user
            $user = get_user($msg_chatid);
            if ($user === false) {
                SendMessageToChatBotWithNoOrder($msg_chatid, 'ты не заристрирован. напиши @reshalybot что б сделать это');
                exit(0);
            } else if ($user['name'] == null && $user['step'] != 5 && $user['step'] != 6) {
                SendMessageToChatBotWithNoOrder($msg_chatid, 'ты не заристрирован. напиши @reshalybot что б сделать это');
                exit(0);
            } else if ($user['univ'] == null && $user['step'] != 5 && $user['step'] != 6) {
                SendMessageToChatBotWithNoOrder($msg_chatid, 'ты не заристрирован. напиши @reshalybot что б сделать это');
                exit(0);
            }

            if ($msg == '/start') {
                SendMessageToChatBotWithNoOrder($msg_chatid, 'хорошо, но сначала напиши @reshalybot что б сделать это');
            } else if (strpos($msg, '/start') === 0) {
                $choise_data = explode(" ", $msg)[1]; // id of order he's taking
                $user_id = $msg_chatid;

                $order = get_order($choise_data);
                $executor_name = get_user($order['executor_id'])['name'];
                $customer_name = get_user($order['customer_id'])['name'];

                if ($order === false) {
                    SendMessageToChatBotWithNoOrder($msg_chatid, 'нельзя использовать этот бот, не имея заказов');
                    exit(0);
                }


                if ($user_id == $order['customer_id']) {
                    // $text = "ккеу, сбщ, которые ты отправишь мне, отвечая на это сбщ, я отправлю исполнителю заказа \"".$order['name']."\". И все сбщ, которые ты отправишь, отвечая на его сбщ, так же отправятся ему. ответь сообщением /done что б завершить заказ со своей стороны. когда заказ буит закрыт с двух сторон, исполнитель получить лв, а статус заказа перейдёт в выполненный";
                    $text = "ты в анонимном чате с $executor_name по заказу “".$order['name']."”. 
- Для общения  каждый раз отправляйте текст отвечая (СВАЙП влево) на сообщение вашего собеседника (ИЛИ НА ЭТО СООБЩЕНИЕ).
- Когда цена согласована, ответьте на это сообщение /price и введите сумму (Например /price 100) и после подтверждения цены собеседником следуйте дальнейшим указаниям. 
- После успешного выполнения задания, ответьте на это сообщение (/done) и сделка будет закрыта, после чего деньги будут перечислены исполнителю. 
ВАЖНО! Для отправки сообщений используйте функцию “Ответить” (Свайп влево).
Полная инструкция: *тыц*
";
                    $data_to_send = new stdClass;
                    $data_to_send->chat_id = $order['customer_id'];
                    $data_to_send->text = $text;
                    $data_to_send->parse_mode = 'markdown';
                    $data_to_send->disable_web_page_preview = true;
                    $response = json_decode(file_get_contents(
                        'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                    ));
                    add_row_to_chat_messages_table($user_id, $response->result->message_id, $order['executor_id'], $choise_data);
                    SendMessageToChatBot($order['executor_id'], "заказчик ($customer_name заказ \"".$order['name']."\") зашёл в чат", $order);
                } else if ($user_id == $order['executor_id']) {
                    // $text = "ккеу, сбщ, которые ты отправишь мне, отвечая на это сбщ, я отправлю заказчику заказа \"".$order['name']."\". И все сбщ, которые ты отправишь, отвечая на его сбщ, так же отправятся ему. ответь сообщением /done что б завершить заказ со своей стороны. когда заказ буит закрыт с двух сторон, исполнитель получить лв, а статус заказа перейдёт в выполненный";
                    $text = "ты в анонимном чате с $customer_name по заказу “".$order['name']."”. 
- Для общения  каждый раз отправляйте текст отвечая (СВАЙП влево) на сообщение вашего собеседника (ИЛИ НА ЭТО СООБЩЕНИЕ).
- Когда цена согласована, ответьте на это сообщение /price и введите сумму (Например /price 100) и после подтверждения цены собеседником следуйте дальнейшим указаниям. 
- После успешного выполнения задания, ответьте на это сообщение (/done) и сделка будет закрыта, после чего деньги будут перечислены исполнителю. 
ВАЖНО! Для отправки сообщений используйте функцию “Ответить” (Свайп влево).
Полная инструкция: *тыц*
";                    $data_to_send = new stdClass;
                    $data_to_send->chat_id = $order['executor_id'];
                    $data_to_send->text = $text;
                    $data_to_send->parse_mode = 'markdown';
                    $data_to_send->disable_web_page_preview = true;
                    $response = json_decode(file_get_contents(
                        'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                    ));
                    add_row_to_chat_messages_table($user_id, $response->result->message_id, $order['customer_id'], $choise_data);
                    SendMessageToChatBot($order['customer_id'], "заказчик ($executor_name, заказ \"".$order['name']."\") зашёл в чат", $order);
                } else {
                    SendMessageToChatBotWithNoOrder($msg_chatid, 'нельзя использовать этот бот без заказа');
                    exit(0);
                }

                
            } else {
                if (property_exists($json_message->message, 'reply_to_message')) {
                    $reply_to_message_id = $json_message->message->reply_to_message->message_id;
                    $chat_message = get_row_from_chat_messages_table($msg_chatid, $reply_to_message_id);
                    if ($chat_message === false) {
                        SendMessageToChatBotWithNoOrder($msg_chatid, 'ты ответил на сообщение, не имеющее отношения к какому-либо заказу');
                        exit(0);
                    }
                    $order = get_order($chat_message['order_id']);
                   
                    if (isset($msg) && $msg != '')
                    add_row_to_chat_messages_table_with_text($msg_chatid, $json_message->message->message_id, $chat_message['destination_chat_id'], $chat_message['order_id'], $msg);
                    
                    $order_name = $order['name'];
                    $executor_name = get_user($order['executor_id'])['name'];
                    $customer_name = get_user($order['customer_id'])['name'];

                    if ($msg == '/done') {
                        if ($msg_chatid == $order['customer_id']) {
                            change_order($order['id'], 'customer_done', 'true');
                            if ($order["executor_done"] === "t") {
                                $response = SendMessageToChatBot("c", "заказ \"$order_name\" закрыт", $order);
                                $response = SendMessageToChatBot("e", "заказ \"$order_name\" закрыт. пришли мне сообщение в формате /card 4242424242424242 что б получить свои деньги", $order);
                            } else {
                                $response = SendMessageToChatBot("c", "ждём, пока исполнитель тоже не закроет заказ \"$order_name\"", $order);
                                $response = SendMessageToChatBot("e", "заказчик ($customer_name, заказ \"$order_name\") предложил закрыть заказ. пришли мне /done что б подтвердить это", $order);
                            }
                            exit(0);
                        } else if ($msg_chatid == $order["executor_id"]) {
                            change_order($order["id"], "executor_done", "true");
                            if ($order["customer_done"] === "t") {
                                $response = SendMessageToChatBot("e", "заказ \"$order_name\" закрыт. пришли мне сообщение в формате /card 4242424242424242 что б получить свои деньги", $order);

                                $response = SendMessageToChatBot("c", "заказ \"$order_name\" закрыт", $order);
                            } else {
                                $response = SendMessageToChatBot("e", "ждём, пока заказчик тоже не закроет заказ \"$order_name\"", $order);

                                $response = SendMessageToChatBot("c", "исполнитель ($executor_name, заказ \"$order_name\") предложил закрыть заказ. пришли мне /done что б подтвердить это", $order);
                            }
                            exit(0);
                        } else {
                            SendMessageToChatBotWithNoOrder($msg_chatid, 'нельзя использовать этого бота без заказа');
                            exit(0);
                        }
                    } else if (strpos($msg, '/price ') === 0) {
                        $price = substr($msg, strlen('/price '), strlen($msg)-strlen('/price '));
                        $order = get_order($chat_message['order_id']);
                        if (!is_numeric($price) || strpos($price, "," !== false) || strpos($price, "." !== false) || strpos($price, "-" !== false)) {
                            $response = SendMessageToChatBot($msg_chatid, 'цена должна быть положительным целым числом', $order);
                            exit(0);
                        }
                        if ($order['customer_price'] !== null && $order['customer_price'] === $order['executor_price']) {
                            $response = SendMessageToChatBot($msg_chatid, 'уже нельзя изменить цену', $order);
                            exit(0);
                        }
                        if ($msg_chatid == $order['customer_id']) {
                            change_order($order['id'], 'customer_price', $price);
                            if ($order['executor_price'] !== null) {
                                if ($order['executor_price'] != $price) {
                                    $response = SendMessageToChatBot($msg_chatid, 'предложенные цены должны быть одинаковыми', $order);
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
                                $data_to_send->prices = '[{"label":"'.$price.' uah", "amount": '.round($price*100/.96).'}]';
                                $response = (object)json_decode(file_get_contents(
                                    'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendInvoice?'.http_build_query($data_to_send, '', '&')
                                ));
                                add_row_to_chat_messages_table($msg_chatid, $response->result->message_id, ($msg_chatid == $order['customer_id'] ? $order['executor_id'] : $order['customer_id']), $order['id']);
                                $response = SendMessageToChatBot('e', 'цена была подтверждена, жди сообщения', $order);
                            } else {
                                $response = SendMessageToChatBot('c', 'твоя цена была установлена, ждём, когда исполнитель её подтвердит', $order);
                                $response = SendMessageToChatBot('e', "заказчик ($customer_name, заказ \"$order_name\") предложил цену ".$price.' грн', $order);
                            }
                            exit(0);
                        } else if ($msg_chatid == $order['executor_id']) {
                            change_order($order['id'], 'executor_price', $price);
                            if ($order['customer_price'] !== null) {
                                if ($order['customer_price'] != $price) {
                                    $response = SendMessageToChatBot('e', 'предложенные цены должны быть одинаковыми', $order);
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
                                $data_to_send->prices = '[{"label":"'.$price.' uah", "amount": '.round($price*100/.96).'}]';
                                $response = (object)json_decode(file_get_contents(
                                    'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendInvoice?'.http_build_query($data_to_send, '', '&')
                                ));
                                add_row_to_chat_messages_table($order['customer_id'], $response->result->message_id, $order['executor_id'], $order['id']);
                                $response = SendMessageToChatBot('e', 'цена была подтверждена, жди сообщения', $order);
                            } else {
                                $response = SendMessageToChatBot('e', 'твоя цена была установлена, ждём, когда заказчик её подтвердит', $order);
                                $response = SendMessageToChatBot('c', "исполнитель ($executor_name, заказ \"$order_name\") предложил цену ".$price.' грн', $order);
                            }
                            exit(0);
                        } else {
                            SendMessageToChatBotWithNoOrder($msg_chatid, 'нельзя использовать этого бота без заказа');
                            exit(0);
                        }
                    } else if (strpos($msg, '/card ') === 0) {
                        $cardnum = substr($msg, strlen('/card '), strlen($msg)-strlen('/card '));
                        
                        $order = get_order($chat_message['order_id']);
                        
                        if ($order['customer_done'] !== 't' || $order['executor_done'] !== 't') {
                            $response = SendMessageToChatBot($msg_chatid, 'сначала выполни заказ', $order);
                            exit(0);
                        }

                        if ($msg_chatid == $order['customer_id']) {
                            $response = SendMessageToChatBot('c', 'ты не исполнитель, что б это делать', $order);
                            exit(0);
                        } else if ($msg_chatid == $order['executor_id']) {
                            $data_to_send = new stdClass;
                            $data_to_send->chat_id = getenv('admin_chat');
                            $data_to_send->text = 'админы, пришлите '.$order['customer_price'].' грн на карту `'.$cardnum.'`.';
                            $data_to_send->parse_mode = 'markdown';
                            $data_to_send->disable_web_page_preview = true;
                            $data_to_send->reply_markup = json_encode((object)(array(
                                'inline_keyboard' => array(array((object)(array(
                                    'text' => 'прислали',
                                    'callback_data' => $order['id']
                                ))))
                            )));
                            $response = file_get_contents(
                                'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                            );
                            $response = SendMessageToChatBot('e', 'жди сообщения, что тебе отправили деньги', $order);
                            exit(0);
                        } else {
                            SendMessageToChatBotWithNoOrder($msg_chatid, 'нельзя использовать этого бота без заказа');
                            exit(0);
                        }
                    } else {
                        if (property_exists($json_message->message, 'text')) {
                            $order = get_order($chat_message['order_id']);
                            $text = "*".$user['name']."*:\n$msg";
                            $response = SendMessageWithMarkdownToChatBot($chat_message['destination_chat_id'], $text, $order);
                            // add_row_to_chat_messages_table_with_text($msg_chatid, $json_message->message->message_id, $chat_message['destination_chat_id'], $chat_message['order_id'], $msg);
                            exit(0);
                        }
                        if (property_exists($json_message->message, 'voice')) {
                            $voice = $json_message->message->voice;
                            $data_to_send = new stdClass;
                            $data_to_send->chat_id = $chat_message['destination_chat_id'];
                            $data_to_send->voice = $voice;
                            $data_to_send->caption = '(от '.$user['name'].')';
                            $response = json_decode(file_get_contents(
                                'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendVoice?'.http_build_query($data_to_send, '', '&')
                            ));
                            add_row_to_chat_messages_table_with_text($chat_message['destination_chat_id'], $response->result->message_id, $msg_chatid, $chat_message['order_id'], 'voice:'.$json_message->message->voice->file_id);
                            add_row_to_chat_messages_table_with_text($msg_chatid, $json_message->message->message_id, $chat_message['destination_chat_id'], $chat_message['order_id'], 'voice:'.$json_message->message->voice->file_id);
                            exit(0);
                        }
                        if (property_exists($json_message->message, 'photo')) {
                            $photos = (array)($json_message->message->photo);
                            $photo = $photos[count($photos)-1];
                            $data_to_send = new stdClass;
                            $data_to_send->chat_id = $chat_message['destination_chat_id'];
                            $data_to_send->photo = $photo['file_id'];
                            $data_to_send->caption = '(от '.$user['name'].')';
                            $response = json_decode(file_get_contents(
                                'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendPhoto?'.http_build_query($data_to_send, '', '&')
                            ));
                            add_row_to_chat_messages_table_with_text($chat_message['destination_chat_id'], $response->result->message_id, $msg_chatid, $chat_message['order_id'], 'voice:'.$json_message->message->voice->file_id);
                            add_row_to_chat_messages_table_with_text($msg_chatid, $json_message->message->message_id, $chat_message['destination_chat_id'], $chat_message['order_id'], 'voice:'.$json_message->message->voice->file_id);
                            exit(0);
                        }
                        if (property_exists($json_message->message, 'document')) {
                            $document = $json_message->message->document;
                            $data_to_send = new stdClass;
                            $data_to_send->chat_id = $chat_message['destination_chat_id'];
                            $data_to_send->document = $document;
                            $data_to_send->caption = '(от '.$user['name'].')';
                            $response = json_decode(file_get_contents(
                                'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendDocument?'.http_build_query($data_to_send, '', '&')
                            ));
                            add_row_to_chat_messages_table_with_text($chat_message['destination_chat_id'], $response->result->message_id, $msg_chatid, $chat_message['order_id'], 'document:'.$json_message->message->document->file_id);
                            add_row_to_chat_messages_table_with_text($msg_chatid, $json_message->message->message_id, $chat_message['destination_chat_id'], $chat_message['order_id'], 'document:'.$json_message->message->voice->file_id);
                            exit(0);
                        }
                        $order = get_order($chat_message['order_id']);
                        $response = SendMessageToChatBot($msg_chatid, 'ты можешь отправить только текст, фото, файл или голосовое сообщение', $order);
                        exit(0);
                    }
                } else {
                    SendMessageToChatBotWithNoOrder($msg_chatid, 'сообщение не доставлено, пожалуйста ответь на сообщение собеседника');
                    exit(0);
                }
            }
        }
    } else {
        echo('Прожектор Перестройки');
    }
?>