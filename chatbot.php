<?php
    include 'send_message.php';
    include 'logging.php';
    include 'users.php';
    
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $requestString = file_get_contents('php://input');
        $json_message = (object)json_decode($requestString);
        add_log($requestString);

        // if (property_exists($json_message, 'pre_checkout_query')) {
        //     $order_id = $json_message->pre_checkout_query->invoice_payload;
        //     $pre_checkout_query_id = $json_message->pre_checkout_query->id;
        //     $order = get_order($order_id);
        //     $data_to_send = new stdClass;
        //     $data_to_send->pre_checkout_query_id = $pre_checkout_query_id;
        //     $data_to_send->ok = true;
        //     $response = (object)json_decode(file_get_contents(
        //         'https://api.telegram.org/bot'.getenv('chat_bot_token').'/answerPreCheckoutQuery?'.http_build_query($data_to_send, '', '&')
        //     ));
        //     $response = SendMessageToChatBot('e', 'Вы можете приступить к выполнению заказа ('.$order['name'].') ибо заказчик уже оплатил его', $order);
        //     $response = SendMessageToChatBot('c', 'Исполнитель заказа '.$order['name'].' получил сообщение, что может приступать к работе', $order);
        //     exit(0);
        // }
        if (property_exists($json_message, 'message') && property_exists($json_message->message, 'successful_payment')) {
            exit(0);
        }
        if (property_exists($json_message, 'callback_query')) {
            $callback_query_id = $json_message->callback_query->id;
            $msg_chatid = $json_message->callback_query->message->chat->id;
            $user_id = $json_message->callback_query->from->id;
            $choise_data = $json_message->callback_query->data;
            $msg_id = $json_message->callback_query->message->message_id;

            if (strpos($choise_data, 's_') === 0) {
                file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/answerCallbackQuery?'.
                http_build_query((object)array(
                    'callback_query_id' => $callback_query_id,
                    'text' => 'Ждите, пока админы это не подтвердят'
                )));
                $order_id = substr($choise_data, 2, strlen($choise_data)-2);
                $order = get_order($order_id);

                $data_to_send->chat_id = getenv('admin_chat');
                $data_to_send->text = 'Должно было прийти '.round($order['customer_price']*1.01, 2).' грн с комментарием "'.$order['id'].'". Подтвердите это.';
                $data_to_send->parse_mode = 'markdown';
                $data_to_send->disable_web_page_preview = true;
                $data_to_send->reply_markup = json_encode((object)(array(
                    'inline_keyboard' => array(array((object)(array(
                        'text' => 'Подтверждаю',
                        'callback_data' => 'a_'.$order['id']
                    ))))
                )));
                $response = (object)json_decode(file_get_contents(
                    'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                ));

                file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/editMessageReplyMarkup?'.
                http_build_query((object)array(
                    'chat_id' => $json_message->callback_query->message->chat->id,
                    'message_id' => $json_message->callback_query->message->message_id,
                    'reply_markup' => ''
                )));
                exit(0);
            }
            if (strpos($choise_data, 'a_') === 0) {
                file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/answerCallbackQuery?'.
                http_build_query((object)array(
                    'callback_query_id' => $callback_query_id,
                    'text' => 'Подтверждено'
                )));
                $order_id = substr($choise_data, 2, strlen($choise_data)-2);
                $order = get_order($order_id);
                $response = SendMessageToChatBot('e', 'Вы можете приступить к выполнению заказа ('.$order['name'].') ибо заказчик уже оплатил его', $order);
                $response = SendMessageToChatBot('c', 'Исполнитель заказа '.$order['name'].' получил сообщение, что может приступать к работе', $order);
    

                file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/editMessageReplyMarkup?'.
                http_build_query((object)array(
                    'chat_id' => $json_message->callback_query->message->chat->id,
                    'message_id' => $json_message->callback_query->message->message_id,
                    'reply_markup' => ''
                )));
                exit(0);
            }

            if ($json_message->callback_query->message->chat->id == getenv('admin_chat')) {
                file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/answerCallbackQuery?'.
                http_build_query((object)array(
                    'callback_query_id' => $callback_query_id,
                    'text' => 'океу'
                )));

                $order_id = $choise_data;
                $order = get_order($order_id);
                $order_name = $order['name'];
                $customer_name = get_user($order['customer_id'])['name'];
                $executor_name = get_user($order['executor_id'])['name'];

                $response = SendMessageToChatBot('e', 'Деньги перевели', $order);
                delete_order($order_id);

                $data_to_send = new stdClass;
                $data_to_send->chat_id = getenv('admin_chat');
                $data_to_send->message_id = $msg_id;
                $data_to_send->text = "Заказ ".$order['name']." был полностью закрыт";
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
                $data_to_send->text = "Оцените, пожалуйста, решалу $executor_name, заказ \"$order_name\" (1 - очень плохо, 5 - очень хорошо)";
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
                $data_to_send->text = "Оцените, пожалуйста, заказчика $customer_name, заказ \"$order_name\" (1 - очень плохо, 5 - очень хорошо)";
                $data_to_send->reply_markup = json_encode((object)(array(
                    'keyboard' => array(array("1", "2", "3", "4", "5"))
                )));
                $response = json_decode(file_get_contents(
                    'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                ));

                exit(0);
            }

                        
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
                SendMessageToChatBotWithNoOrder($msg_chatid, 'Вы не зарегистрированы. Напишите @reshalybot чтобы сделать это');
                exit(0);
            } else if ($user['name'] == null && $user['step'] != 5 && $user['step'] != 6) {
                SendMessageToChatBotWithNoOrder($msg_chatid, 'Вы не зарегистрированы. Напишите @reshalybot чтобы сделать это');
                exit(0);
            } else if ($user['univ'] == null && $user['step'] != 5 && $user['step'] != 6) {
                SendMessageToChatBotWithNoOrder($msg_chatid, 'Вы не зарегистрированы. Напишите @reshalybot чтобы сделать это');
                exit(0);
            }

            if ($msg == '/start') {
                SendMessageToChatBotWithNoOrder($msg_chatid, 'Хорошо, но сначала напишите @reshalybot чтобы сделать это');
            } else if (strpos($msg, '/start') === 0) {
                $choise_data = explode(" ", $msg)[1]; // id of order he's taking
                $user_id = $msg_chatid;

                $order = get_order($choise_data);
                $executor_name = get_user($order['executor_id'])['name'];
                $customer_name = get_user($order['customer_id'])['name'];

                if ($order === false) {
                    SendMessageToChatBotWithNoOrder($msg_chatid, 'Нельзя использовать этого бота не имея заказов');
                    exit(0);
                }


                if ($user_id == $order['customer_id']) {
                    // $text = "ккеу, сбщ, которые ты отправишь мне, отвечая на это сбщ, я отправлю исполнителю заказа \"".$order['name']."\". И все сбщ, которые ты отправишь, отвечая на его сбщ, так же отправятся ему. ответь сообщением /done что б завершить заказ со своей стороны. когда заказ буит закрыт с двух сторон, исполнитель получить лв, а статус заказа перейдёт в выполненный";
                    $text = "[ ](https://i.ibb.co/sWcp04X/photo-2020-05-31-12-32-58.jpg) Вы в анонимном чате с $executor_name по заказу “".$order['name']."”. 
- Для общения  каждый раз отправляйте текст используя функцию “Ответить” (СВАЙП сообщения влево) на сообщение вашего собеседника (ИЛИ НА ЭТО СООБЩЕНИЕ).
- Когда цена согласована, ответьте на сообщение собеседника /price и введите сумму (Например /price 100) и после подтверждения цены собеседником следуйте дальнейшим указаниям. 
- После успешного выполнения задания, ответьте на сообщение собеседника (/done) и сделка будет закрыта, после чего деньги будут перечислены исполнителю. 
ВАЖНО! Для отправки сообщений используйте функцию “Ответить” (Свайп сообщения влево).
Полная инструкция: [тыц](https://telegra.ph/Gajd-po-servisu-Reshala-05-26)
";
                    $data_to_send = new stdClass;
                    $data_to_send->chat_id = $order['customer_id'];
                    $data_to_send->text = $text;
                    $data_to_send->parse_mode = 'markdown';
                    $data_to_send->disable_web_page_preview = false;
                    $response = json_decode(file_get_contents(
                        'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                    ));
                    add_row_to_chat_messages_table($user_id, $response->result->message_id, $order['executor_id'], $choise_data);
                    SendMessageToChatBot($order['executor_id'], "Заказчик ($customer_name заказ \"".$order['name']."\") зашёл в чат", $order);
                } else if ($user_id == $order['executor_id']) {
                    // $text = "ккеу, сбщ, которые ты отправишь мне, отвечая на это сбщ, я отправлю заказчику заказа \"".$order['name']."\". И все сбщ, которые ты отправишь, отвечая на его сбщ, так же отправятся ему. ответь сообщением /done что б завершить заказ со своей стороны. когда заказ буит закрыт с двух сторон, исполнитель получить лв, а статус заказа перейдёт в выполненный";
                    $text = "[ ](https://i.ibb.co/sWcp04X/photo-2020-05-31-12-32-58.jpg) Вы в анонимном чате с $customer_name по заказу “".$order['name']."”. 
- Для общения  каждый раз отправляйте текст используя функцию “Ответить” (СВАЙП сообщения влево) на сообщение вашего собеседника (ИЛИ НА ЭТО СООБЩЕНИЕ).
- Когда цена согласована, ответьте на сообщение собеседника /price и введите сумму (Например /price 100) и после подтверждения цены собеседником следуйте дальнейшим указаниям. 
- После успешного выполнения задания, ответьте на сообщение собеседника (/done) и сделка будет закрыта, после чего деньги будут перечислены исполнителю. 
ВАЖНО! Для отправки сообщений используйте функцию “Ответить” (Свайп сообщения влево).
Полная инструкция: [тыц](https://telegra.ph/Gajd-po-servisu-Reshala-05-26)
";                    $data_to_send = new stdClass;
                    $data_to_send->chat_id = $order['executor_id'];
                    $data_to_send->text = $text;
                    $data_to_send->parse_mode = 'markdown';
                    $data_to_send->disable_web_page_preview = false;
                    $response = json_decode(file_get_contents(
                        'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                    ));
                    add_row_to_chat_messages_table($user_id, $response->result->message_id, $order['customer_id'], $choise_data);
                    SendMessageToChatBot($order['customer_id'], "Решала ($executor_name, заказ \"".$order['name']."\") зашёл в чат", $order);
                } else {
                    SendMessageToChatBotWithNoOrder($msg_chatid, 'Нельзя использовать этот бот без заказа');
                    exit(0);
                }

                
            } else {
                if (property_exists($json_message->message, 'reply_to_message')) {
                    $reply_to_message_id = $json_message->message->reply_to_message->message_id;
                    $chat_message = get_row_from_chat_messages_table($msg_chatid, $reply_to_message_id);
                    if ($chat_message === false) {
                        SendMessageToChatBotWithNoOrder($msg_chatid, 'Вы ответили на сообщение, не имеющее отношения к какому-либо заказу');
                        exit(0);
                    }

                    $order = get_order($chat_message['order_id']);
                   
                    if (isset($msg)) {
                        if ($msg == '') add_row_to_chat_messages_table_with_text($msg_chatid, $json_message->message->message_id, $chat_message['destination_chat_id'], $chat_message['order_id'], $msg);
                        else add_row_to_chat_messages_table($msg_chatid, $json_message->message->message_id, $chat_message['destination_chat_id'], $chat_message['order_id']);
                    }
                    
                    
                    $order_name = $order['name'];
                    $executor_name = get_user($order['executor_id'])['name'];
                    $customer_name = get_user($order['customer_id'])['name'];

                    if ($msg == '/done') {
                        if ($msg_chatid == $order['customer_id']) {
                            change_order($order['id'], 'customer_done', 'true');
                            if ($order["executor_done"] === "t") {
                                $response = SendMessageToChatBot("c", "Заказ \"$order_name\" закрыт", $order);
                                $response = SendMessageToChatBot("e", "Заказ \"$order_name\" закрыт. Пришлите мне сообщение в формате /card 4242424242424242 чтобы получить свои деньги", $order);
                            } else {
                                $response = SendMessageToChatBot("c", "Ждём пока исполнитель тоже закроет заказ \"$order_name\"", $order);
                                $response = SendMessageToChatBot("e", "Заказчик ($customer_name, заказ \"$order_name\") предложил закрыть заказ. Пришлите мне /done чтобы подтвердить это", $order);
                            }
                            exit(0);
                        } else if ($msg_chatid == $order["executor_id"]) {
                            change_order($order["id"], "executor_done", "true");
                            if ($order["customer_done"] === "t") {
                                $response = SendMessageToChatBot("e", "Заказ \"$order_name\" закрыт. Пришлите мне сообщение в формате /card 4242424242424242 чтобы получить свои деньги", $order);

                                $response = SendMessageToChatBot("c", "Заказ \"$order_name\" закрыт", $order);
                            } else {
                                $response = SendMessageToChatBot("e", "Ждём пока заказчик тоже закроет заказ \"$order_name\"", $order);

                                $response = SendMessageToChatBot("c", "Исполнитель ($executor_name, заказ \"$order_name\") предложил закрыть заказ. Пришлите мне /done чтобы подтвердить это", $order);
                            }
                            exit(0);
                        } else {
                            SendMessageToChatBotWithNoOrder($msg_chatid, 'Нельзя использовать этого бота без заказа');
                            exit(0);
                        }
                    } else if (strpos($msg, '/price ') === 0) {
                        $price = substr($msg, strlen('/price '), strlen($msg)-strlen('/price '));
                        $order = get_order($chat_message['order_id']);
                        if (!is_numeric($price) || strpos($price, "," !== false) || strpos($price, "." !== false) || strpos($price, "-" !== false)) {
                            $response = SendMessageToChatBot($msg_chatid, 'Цена должна быть положительным целым числом', $order);
                            exit(0);
                        }
                        if ($order['customer_price'] !== null && $order['customer_price'] === $order['executor_price']) {
                            $response = SendMessageToChatBot($msg_chatid, 'Уже нельзя изменять цену', $order);
                            exit(0);
                        }
                        if ($msg_chatid == $order['customer_id']) {
                            change_order($order['id'], 'customer_price', $price);
                            if ($order['executor_price'] !== null) {
                                if ($order['executor_price'] != $price) {
                                    $response = SendMessageToChatBot($msg_chatid, 'Предложенные цены должны быть одинаковыми', $order);
                                    exit(0);
                                }
                                $data_to_send = new stdClass;
                                // $data_to_send->chat_id = $order['customer_id'];
                                // $data_to_send->title = "Order";
                                // $data_to_send->description = $order['name'];
                                // $data_to_send->payload = $order['id'];
                                // $data_to_send->provider_token = getenv('pay_token');
                                // $data_to_send->start_parameter = '15';
                                // $data_to_send->currency = "UAH";
                                // $data_to_send->prices = '[{"label":"'.$price.' uah", "amount": '.round($price*100/.96).'}]';
                                // $response = (object)json_decode(file_get_contents(
                                //     'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendInvoice?'.http_build_query($data_to_send, '', '&')
                                // ));
                                $data_to_send->chat_id = $order['customer_id'];
                                $data_to_send->text = 'Пришлите, пожалуйста '.round($price*1.01, 2).' грн на карту `'.getenv('admin_card_mono').'` (монобанк) или `'.getenv('admin_card_privat').'` (приватбанк) с комментарием "'.$order['id'].'", после чего нажмите "Прислал", дабы админы могли это подтвердить';
                                $data_to_send->parse_mode = 'markdown';
                                $data_to_send->disable_web_page_preview = true;
                                $data_to_send->reply_markup = json_encode((object)(array(
                                    'inline_keyboard' => array(array((object)(array(
                                        'text' => 'Прислал',
                                        'callback_data' => 's_'.$order['id']
                                    ))))
                                )));
                                $response = (object)json_decode(file_get_contents(
                                    'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                                ));
                                add_row_to_chat_messages_table($msg_chatid, $response->result->message_id, ($msg_chatid == $order['customer_id'] ? $order['executor_id'] : $order['customer_id']), $order['id']);
                                $response = SendMessageToChatBot('e', 'Цена была подтверждена, ждите сообщения', $order);
                            } else {
                                $response = SendMessageToChatBot('c', 'Твоя цена была установлена, ждём, когда исполнитель её подтвердит', $order);
                                $response = SendMessageToChatBot('e', "Заказчик ($customer_name, заказ \"$order_name\") предложил цену ".$price.' грн', $order);
                            }
                            exit(0);
                        } else if ($msg_chatid == $order['executor_id']) {
                            change_order($order['id'], 'executor_price', $price);
                            if ($order['customer_price'] !== null) {
                                if ($order['customer_price'] != $price) {
                                    $response = SendMessageToChatBot('e', 'Предложенные цены должны быть одинаковыми', $order);
                                    exit(0);
                                }
                                $data_to_send = new stdClass;
                                // $data_to_send->chat_id = $order['customer_id'];
                                // $data_to_send->title = "Order";
                                // $data_to_send->description = $order['name'];
                                // $data_to_send->payload = $order['id'];
                                // $data_to_send->provider_token = getenv('pay_token');
                                // $data_to_send->start_parameter = '15';
                                // $data_to_send->currency = "UAH";
                                // $data_to_send->prices = '[{"label":"'.$price.' uah", "amount": '.round($price*100/.96).'}]';
                                // $response = (object)json_decode(file_get_contents(
                                //     'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendInvoice?'.http_build_query($data_to_send, '', '&')
                                // ));
                                $data_to_send->chat_id = $order['customer_id'];
                                $data_to_send->text = 'Пришлите, пожалуйста '.round($price*1.01, 2).' грн на карту `'.getenv('admin_card_mono').'` (монобанк) или `'.getenv('admin_card_privat').'` (приватбанк) с комментарием "'.$order['id'].'", после чего нажмите "Прислал", дабы админы могли это подтвердить';
                                $data_to_send->parse_mode = 'markdown';
                                $data_to_send->disable_web_page_preview = true;
                                $data_to_send->reply_markup = json_encode((object)(array(
                                    'inline_keyboard' => array(array((object)(array(
                                        'text' => 'Прислал',
                                        'callback_data' => 's_'.$order['id']
                                    ))))
                                )));
                                $response = (object)json_decode(file_get_contents(
                                    'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                                ));
                                add_row_to_chat_messages_table($order['customer_id'], $response->result->message_id, $order['executor_id'], $order['id']);
                                $response = SendMessageToChatBot('e', 'Цена была подтверждена, ждите сообщения', $order);
                            } else {
                                $response = SendMessageToChatBot('e', 'Ваша цена была установлена, ждём, когда заказчик её подтвердит', $order);
                                $response = SendMessageToChatBot('c', "Исполнитель ($executor_name, заказ \"$order_name\") предложил цену ".$price.' грн', $order);
                            }
                            exit(0);
                        } else {
                            SendMessageToChatBotWithNoOrder($msg_chatid, 'Нельзя использовать этого бота без заказа');
                            exit(0);
                        }
                    } else if (strpos($msg, '/card ') === 0) {
                        $cardnum = substr($msg, strlen('/card '), strlen($msg)-strlen('/card '));
                        
                        $order = get_order($chat_message['order_id']);
                        
                        if ($order['customer_done'] !== 't' || $order['executor_done'] !== 't') {
                            $response = SendMessageToChatBot($msg_chatid, 'Сначала выполните заказ', $order);
                            exit(0);
                        }

                        if ($msg_chatid == $order['customer_id']) {
                            $response = SendMessageToChatBot('c', 'Вы не исполнитель, чтобы это делать', $order);
                            exit(0);
                        } else if ($msg_chatid == $order['executor_id']) {
                            $data_to_send = new stdClass;
                            $data_to_send->chat_id = getenv('admin_chat');
                            $data_to_send->text = 'Админы, пришлите '.round($order['customer_price']*0.99, 2).' грн на карту `'.$cardnum.'`.';
                            $data_to_send->parse_mode = 'markdown';
                            $data_to_send->disable_web_page_preview = true;
                            $data_to_send->reply_markup = json_encode((object)(array(
                                'inline_keyboard' => array(array((object)(array(
                                    'text' => 'Прислали',
                                    'callback_data' => $order['id']
                                ))))
                            )));
                            $response = file_get_contents(
                                'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                            );
                            $response = SendMessageToChatBot('e', 'Ожидайте сообщение об отправке денег', $order);
                            exit(0);
                        } else {
                            SendMessageToChatBotWithNoOrder($msg_chatid, 'Нельзя использовать этого бота без заказа');
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
                            $data_to_send->voice = $voice->file_id;
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
                            $data_to_send->photo = $photo->file_id;
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
                            $data_to_send->document = $document->file_id;
                            $data_to_send->caption = '(от '.$user['name'].')';
                            $response = json_decode(file_get_contents(
                                'https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendDocument?'.http_build_query($data_to_send, '', '&')
                            ));
                            add_row_to_chat_messages_table_with_text($chat_message['destination_chat_id'], $response->result->message_id, $msg_chatid, $chat_message['order_id'], 'document:'.$json_message->message->document->file_id);
                            add_row_to_chat_messages_table_with_text($msg_chatid, $json_message->message->message_id, $chat_message['destination_chat_id'], $chat_message['order_id'], 'document:'.$json_message->message->voice->file_id);
                            exit(0);
                        }
                        $order = get_order($chat_message['order_id']);
                        $response = SendMessageToChatBot($msg_chatid, 'Вы можете отправить только текст, фото, файл или голосовое сообщение', $order);
                        exit(0);
                    }
                } else {
                    SendMessageToChatBotWithNoOrder($msg_chatid, 'Сообщение не доставлено, пожалуйста, используйте функцию "Ответить" на сообщение собеседника');
                    exit(0);
                }
            }
        }
    } else {
        echo('Прожектор Перестройки');
    }
?>