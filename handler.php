<?php

function handle($json_message) {
    
    if (property_exists($json_message, 'callback_query')) {
        handle_callback($json_message);
        exit(0);
    }
    if ($json_message->message->chat->id == getenv('admin_chat')) exit(0);

    $sender_is_bot = $json_message->message->from->is_bot;
    $msg_senderid = $json_message->message->from->id;
    $msg_chatid = $json_message->message->chat->id;
    $msg_sendername = $json_message->message->from->first_name;
    $msg = $json_message->message->text;
    $msg_id = $json_message->message->message_id;
    
    if ($sender_is_bot) {
        SendMessage($msg_chatid, 'боты запрещены.');
    } else {
        //check for user 
        $user = get_user($msg_chatid);
        if ($user === false) {
            SendMessage($msg_chatid, "При нажатии кнопки старт:
Это наш Решала бот, через него ты можешь сделать заказ, который будет размещен на канале.
Мы советуем вам пройти регистрацию чтобы иметь доступ к рейтингам. Таким образом вы будете повышать доверие к себе среди пользователей.
Введите свое имя
Введите свой универ и специальность
Регистрация окончена, для размещения первого заказа нажмите /add_order");
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 5);
            exit(0);
        } else if ($user['name'] == null && $user['step'] != 5 && $user['step'] != 6) {
            SendMessage($msg_chatid, 'ты не зарегистрирован. пришли мне своё имя');
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 5);
            exit(0);
        } else if ($user['univ'] == null && $user['step'] != 5 && $user['step'] != 6) {
            SendMessage($msg_chatid, 'ты не зарегистрирован. пришли мне свой универ и специальность');
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 6);
            exit(0);
        }

        if ($msg == '/start') {
            SendMessage($msg_chatid, 'ты уже начал');
        } else if (strpos($msg, '/start') === 0) {
            $choise_data = explode(" ", $msg)[1]; // id of order he's taking
            $user_id = $msg_chatid;

            if (is_executor_in_table($choise_data, $msg_chatid)) {
                SendMessage($msg_chatid, 'нельзя дважды взяться за один заказ');
                exit(0);
            }

            add_executor_in_table($choise_data, $msg_chatid);
            $order = get_order($choise_data);
            if ($order['customer_id'] == $user_id) {
                SendMessage($msg_chatid, 'нельзя быть исполнителем собственного заказа');
                exit(0);
            }
            
            $user_executor = get_user($user_id);
            $text = $user_executor['name']." (рейтинг: ".round($user_executor['rating'], 1)."/5) хочет взяться за твой заказ [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].").";
            $data_to_send = new stdClass;
            $data_to_send->chat_id = $order['customer_id'];
            $data_to_send->text = $text;
            $data_to_send->parse_mode = 'markdown';
            $data_to_send->disable_web_page_preview = true;
            $data_to_send->reply_markup = json_encode((object)(array(
                'inline_keyboard' => array(array((object)(array(
                    'text' => 'принять',
                    'callback_data' => $user_id."/".$order['id']
                ))))
            )));
            $response = file_get_contents(
                'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
            );
            SendMessage($msg_chatid, 'жди, пока заказчик согласится на работу с тобой');
        } else if ($msg == '/add_order') {
                //create order, get its id
                $order_id = create_order($msg_chatid);
                //set user step
                set_user_step($msg_chatid, 1);
                //set user current order fill
                set_user_current_order_fill($msg_chatid, $order_id);
                //send message
                SendMessage($msg_chatid, 'напиши название предмета или задания');

        } else if ($msg == '/my_orders') {
            $my_orders_as_executor = get_orders_as_executor($user['id']);
            $my_orders_as_customer = get_orders_as_customer($user['id']);

            $text = "";

            if ($my_orders_as_executor === false && $my_orders_as_customer == false) {
                $text = "у тебя нет заказов. опубликуй командой /add_order или возьмись за существующий";
                SendMessage($msg_chatid, $text);
                exit(0);
            }

            if ($my_orders_as_executor !== false) {
                $text .= "я исполнитель в заказах: \n";
                foreach ($my_orders_as_executor as $line) {
                    $text .= "[".$line['name']."](https://t.me/reshalychannel/".$line['post_id'].")\n";
                }
            }
            if ($my_orders_as_customer !== false) {
                $text .= "я заказчик в заказах: \n";
                foreach ($my_orders_as_customer as $line) {
                    $text .= "[".$line['name']."](https://t.me/reshalychannel/".$line['post_id'].")\n";
                }
            }

            SendMessageWithMarkdown($msg_chatid, $text);

        } else if ($msg == '/info') {

            SendMessageWithMarkdown($msg_chatid, "информация про бота");

        } else if ($msg == '/my_rating') {
            $rating = round($user['rating'], 1);
            SendMessageWithMarkdown($msg_chatid, "твой рейтинг: $rating/5");

        } else if ($msg == '/feedback') {

            SendMessageWithMarkdown($msg_chatid, "теперь пришли мне анонимный отзыв");
            set_user_step($msg_chatid, 8);

        } else {
            $user = get_user($msg_chatid);
            $step = $user['step'];
            $msg_len = strlen($msg) / 2;
            switch ($step) {
                case 1:
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'название должно быть не меньше 5 символов и не больше 32 символов. попробуй ещё раз');
                        exit(0);
                    }
                    $order_id = $user['current_order_fill'];
                    change_order($order_id, 'name', "'$msg'");
                    set_user_step($msg_chatid, 2);
                    SendMessage($msg_chatid, 'максимально подробно опиши задание');
                    break;
                case 2:
                    $order_id = $user['current_order_fill'];
                    if ($msg_len < 1 || $msg_len > 256) {
                        SendMessage($msg_chatid, 'описание должно быть не меньше 10 символов и не больше 256 символов. попробуй ещё раз');
                        exit(0);
                    }
                    change_order($order_id, 'description', "'$msg'");
                    set_user_step($msg_chatid, 3);
                    SendMessage($msg_chatid, 'введи цену заказа указывая валюту. (минимальная цена - 30 грн). также можешь указать что цена договорная');
                    break;
                case 3:
                    if ($msg_len < 1 || $msg_len > 16) {
                        SendMessage($msg_chatid, 'цена должна быть не меньше 1 символа и не больше 16 символов. попробуй ещё раз');
                        exit(0);
                    }
                    $order_id = $user['current_order_fill'];
                    change_order($order_id, 'price', "'$msg'");
                    set_user_step($msg_chatid, 7);
                    SendMessage($msg_chatid, "пришли один файл (именно один и именно файл), касающийся твоего заказа. если не хочешь - пришли \"не\"");
                    break;
                case 4:
                    $order_id = $user['current_order_fill'];
                    if ($msg == 'Публиковать') {
                        set_user_step($msg_chatid, 0);
                        $publish_return = publish_order($order_id);
                        if (!$publish_return->ok) SendMessage($msg_chatid, 'не удалось опубликовать заказ');
                        else {
                            $post_id = $publish_return->result->message_id;
                            set_user_current_order_fill($msg_chatid, 'null');
                            set_user_step($msg_chatid, 0);
                            change_order($order_id, 'post_id', $post_id);
                            
                            $data_to_send = new stdClass;
                            $data_to_send->chat_id = $msg_chatid;
                            $data_to_send->text = "[твой заказ](https://t.me/reshalychannel/$post_id) был успешно опубликован";
                            $data_to_send->parse_mode = 'markdown';
                            $data_to_send->disable_web_page_preview = true;
                            $data_to_send->reply_markup = json_encode((object)(array(
                                'remove_keyboard' => true
                            )));
                            $response = file_get_contents(
                                'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                            );
                            // SendMessage($msg_chatid, "тепе /add_order command");
                        }
                    } else if ($msg == 'Отменить') {
                        set_user_step($msg_chatid, 0);
                        delete_order($order_id);
                        $data_to_send = new stdClass;
                        $data_to_send->chat_id = $msg_chatid;
                        $data_to_send->text = "заказ был успешно удалён";
                        $data_to_send->parse_mode = 'markdown';
                        $data_to_send->disable_web_page_preview = true;
                        $data_to_send->reply_markup = json_encode((object)(array(
                            'remove_keyboard' => true
                        )));
                        $response = file_get_contents(
                            'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                        );
                    } else {
                        SendMessage($msg_chatid, "неверная команда");
                    }
                    break;
                
                case 5: 
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'имя должно быть от 1 до 32 символов. попробуй ещё раз');
                        exit(0);
                    }
                    change_user($msg_chatid, 'name', "'$msg'");
                    set_user_step($msg_chatid, 6);
                    SendMessage($msg_chatid, "пришли мне название своего универа и специальности");
                break;
                case 6: 
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'универ и специальность должны быть от 1 до 32 символов. попробуй ещё раз');
                        exit(0);
                    }
                    change_user($msg_chatid, 'univ', "'$msg'");
                    set_user_step($msg_chatid, 0);
                    SendMessage($msg_chatid, "отлично! для добавления заказа отправь мне команду /add_order");
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
                            SendMessage($msg_chatid, 'не удалось опубликовать файл');
                            exit(0);
                        } 
                        else {
                            $post_id = $response->result->message_id;
                        }

                        change_order($order_id, 'file_id', "'".$post_id."'");
                        SendMessage($msg_chatid, "файл был успешно прикреплён к твоему заказу");
                    }

                    set_user_step($msg_chatid, 4);
                    $line = get_order($order_id);

                    $file = "";
                    if ($line['file_id'] != null) $file = "[.](https://t.me/reshalymedia/".$line['file_id'].")";

$text = 
"🔵Активно

*".$line['name']."*

".$line['description']."

Цена: ".$line['price']."
Рейтинг заказчика: ".round($user['rating'], 1)."/5$file";
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
                            
                    SendMessageWithMarkdown($msg_chatid, "спасибо за отзыв!");
                    set_user_step($msg_chatid, 0);
                break;
                case 9: 
                    if ($msg == '1' || $msg == '2' || $msg == '3' || $msg == '4' || $msg == '5') {
                        $data_to_send = new stdClass;
                        $data_to_send->chat_id = $msg_chatid;
                        $data_to_send->text = 'спасибо за твою оценку';
                        $data_to_send->reply_markup = json_encode((object)(array(
                            'remove_keyboard' => true
                        )));
                        $response = file_get_contents(
                            'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                        );
                        change_user_rating($user['current_order_fill'], $msg);
                        set_user_current_order_fill($msg_chatid, 'NULL');
                        set_user_step($msg_chatid, 0);
                    } else {
                        SendMessage($msg_chatid, "твоя оценка не была учтена");
                        set_user_step($msg_chatid, 0);
                    }
                break;
                    default:
                SendMessage($msg_chatid, 'пришли мне команду');
                    break;
            }
        }
    }
}


?>