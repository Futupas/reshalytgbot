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
            SendMessage($msg_chatid, "Это наш Решала бот, через него вы можете сделать заказ, который будет размещен на канале.
Что бы зарегистрироваться, введите своё имя");
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 5);
            exit(0);
        } else if ($user['name'] == null && $user['step'] != 5 && $user['step'] != 6) {
            SendMessage($msg_chatid, "Это наш Решала бот, через него Вы можете сделать заказ, который будет размещен на канале. Пройдите регистрацию чтобы иметь доступ к рейтингам. Таким образом вы будете повышать доверие к себе среди пользователей.\nПожалуйста, введите свое имя");
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 5);
            exit(0);
        } else if ($user['univ'] == null && $user['step'] != 5 && $user['step'] != 6) {
            SendMessage($msg_chatid, 'Вы не зарегистрированы. Пришлите мне название своего универа и специальность');
            add_user_to_db($msg_chatid);
            set_user_step($msg_chatid, 6);
            exit(0);
        }

        if ($msg == '/start') {
            SendMessage($msg_chatid, 'Вы уже начали');
        } else if (strpos($msg, '/start') === 0) {
            $choise_data = explode(" ", $msg)[1]; // id of order he's taking
            $user_id = $msg_chatid;

            if (is_executor_in_table($choise_data, $msg_chatid)) {
                SendMessage($msg_chatid, 'Нельзя дважды взяться за один заказ');
                exit(0);
            }

            add_executor_in_table($choise_data, $msg_chatid);
            $order = get_order($choise_data);
            if ($order['customer_id'] == $user_id) {
                SendMessage($msg_chatid, 'Нельзя быть исполнителем собственного заказа');
                exit(0);
            }
            
            $user_executor = get_user($user_id);
            $text = $user_executor['name']." (рейтинг: ".round($user_executor['rating'], 1)."/5) хочет взяться за ваш заказ [\"".$order['name']."\"](https://t.me/reshalychannel/".$order['post_id'].").";
            $data_to_send = new stdClass;
            $data_to_send->chat_id = $order['customer_id'];
            $data_to_send->text = $text;
            $data_to_send->parse_mode = 'markdown';
            $data_to_send->disable_web_page_preview = true;
            $data_to_send->reply_markup = json_encode((object)(array(
                'inline_keyboard' => array(array((object)(array(
                    'text' => 'Принять',
                    'callback_data' => $user_id."/".$order['id']
                ))))
            )));
            $response = file_get_contents(
                'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
            );
            SendMessage($msg_chatid, 'Подождите пока заказчик согласиться на работу с вами.
Если сообщение не пришло, это значит что заказчик начал работу с другим исполнителем');
        } else if ($msg == '/add_order') {
                //create order, get its id
                $order_id = create_order($msg_chatid);
                //set user step
                set_user_step($msg_chatid, 1);
                //set user current order fill
                set_user_current_order_fill($msg_chatid, $order_id);
                //send message
                SendMessage($msg_chatid, 'Напишите название предмета или задания');

        } else if ($msg == '/my_orders') {
            $my_orders_as_executor = get_orders_as_executor($user['id']);
            $my_orders_as_customer = get_orders_as_customer($user['id']);

            $text = "";

            if ($my_orders_as_executor === false && $my_orders_as_customer == false) {
                $text = "У вас нет заказов. Опубликуйте командой /add_order или возьмитесь за существующий";
                SendMessage($msg_chatid, $text);
                exit(0);
            }

            if ($my_orders_as_executor !== false) {
                $text .= "Я исполнитель в заказах: \n";
                foreach ($my_orders_as_executor as $line) {
                    $text .= "[".$line['name']."](https://t.me/reshalychannel/".$line['post_id'].")\n";
                }
            }
            if ($my_orders_as_customer !== false) {
                $text .= "Я заказчик в заказах: \n";
                foreach ($my_orders_as_customer as $line) {
                    $text .= "[".$line['name']."](https://t.me/reshalychannel/".$line['post_id'].")\n";
                }
            }

            SendMessageWithMarkdown($msg_chatid, $text);

        } else if ($msg == '/info') {

            SendMessageWithMarkdown($msg_chatid, "Детальнее про работу бота: https://bit.ly/2AdZQko");

        } else if ($msg == '/my_rating') {
            $rating = round($user['rating'], 1);
            SendMessageWithMarkdown($msg_chatid, "Ваш рейтинг: $rating/5");

        } else if ($msg == '/feedback') {

            SendMessageWithMarkdown($msg_chatid, "Теперь пришлите мне анонимный отзыв");
            set_user_step($msg_chatid, 8);

        } else {
            $user = get_user($msg_chatid);
            $step = $user['step'];
            $msg_len = strlen($msg) / 2;
            switch ($step) {
                case 1:
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'Название должно быть не меньше 5 символов и не больше 32 символов. Попробуйте ещё раз');
                        exit(0);
                    }
                    $order_id = $user['current_order_fill'];
                    change_order($order_id, 'name', "'$msg'");
                    set_user_step($msg_chatid, 2);
                    SendMessage($msg_chatid, 'Максимально подробно опишите задание');
                    break;
                case 2:
                    $order_id = $user['current_order_fill'];
                    if ($msg_len < 1 || $msg_len > 256) {
                        SendMessage($msg_chatid, 'Описание должно быть не меньше 10 символов и не больше 256 символов. Попробуйте ещё раз');
                        exit(0);
                    }
                    change_order($order_id, 'description', "'$msg'");
                    set_user_step($msg_chatid, 3);
                    SendMessage($msg_chatid, 'Введите цену заказа указывая валюту. (минимальная цена - 30 грн). Также можете указать что цена договорная');
                    break;
                case 3:
                    if ($msg_len < 1 || $msg_len > 16) {
                        SendMessage($msg_chatid, 'Цена должна быть не меньше 1 символа и не больше 16 символов. Попробуйте ещё раз');
                        exit(0);
                    }
                    $order_id = $user['current_order_fill'];
                    change_order($order_id, 'price', "'$msg'");
                    set_user_step($msg_chatid, 7);
                    SendMessage($msg_chatid, "Пришлите ОДИН файл (если вам нужно добавить фотографию - отправьте её файлом), касающийся вашего заказа. Если не хотите - пришлите \"не\"");
                    break;
                case 4:
                    $order_id = $user['current_order_fill'];
                    if ($msg == 'Публиковать') {
                        set_user_step($msg_chatid, 0);
                        $publish_return = publish_order($order_id);
                        if (!$publish_return->ok) SendMessage($msg_chatid, 'Не удалось опубликовать заказ');
                        else {
                            $post_id = $publish_return->result->message_id;
                            set_user_current_order_fill($msg_chatid, 'null');
                            set_user_step($msg_chatid, 0);
                            change_order($order_id, 'post_id', $post_id);
                            
                            $data_to_send = new stdClass;
                            $data_to_send->chat_id = $msg_chatid;
                            $data_to_send->text = "[Ваш заказ](https://t.me/reshalychannel/$post_id) был успешно опубликован";
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
                        $data_to_send->text = "Заказ был успешно удалён";
                        $data_to_send->parse_mode = 'markdown';
                        $data_to_send->disable_web_page_preview = true;
                        $data_to_send->reply_markup = json_encode((object)(array(
                            'remove_keyboard' => true
                        )));
                        $response = file_get_contents(
                            'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?'.http_build_query($data_to_send, '', '&')
                        );
                    } else {
                        SendMessage($msg_chatid, "Неверная команда");
                    }
                    break;
                
                case 5: 
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'Имя должно быть от 1 до 32 символов. Попробуйте ещё раз');
                        exit(0);
                    }
                    change_user($msg_chatid, 'name', "'$msg'");
                    set_user_step($msg_chatid, 6);
                    SendMessage($msg_chatid, "Пришлите мне название своего универа и специальности");
                break;
                case 6: 
                    if ($msg_len < 1 || $msg_len > 32) {
                        SendMessage($msg_chatid, 'Универ и специальность должны быть от 1 до 32 символов. Попробуйте ещё раз');
                        exit(0);
                    }
                    change_user($msg_chatid, 'univ', "'$msg'");
                    set_user_step($msg_chatid, 0);
                    SendMessage($msg_chatid, "Отлично! Для добавления заказа отправьте мне команду /add_order");
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
                            SendMessage($msg_chatid, 'Не удалось опубликовать файл');
                            exit(0);
                        } 
                        else {
                            $post_id = $response->result->message_id;
                        }

                        change_order($order_id, 'file_id', "'".$post_id."'");
                        SendMessage($msg_chatid, "Файл был успешно прикреплён к заказу");
                    }

                    set_user_step($msg_chatid, 4);
                    $line = get_order($order_id);
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
                            
                    SendMessageWithMarkdown($msg_chatid, "Спасибо за отзыв!");
                    set_user_step($msg_chatid, 0);
                break;
                case 9: 
                    if ($msg == '1' || $msg == '2' || $msg == '3' || $msg == '4' || $msg == '5') {
                        $data_to_send = new stdClass;
                        $data_to_send->chat_id = $msg_chatid;
                        $data_to_send->text = 'Спасибо за вашу оценку';
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
                        SendMessage($msg_chatid, "Ваша оценка не была учтена");
                        set_user_step($msg_chatid, 0);
                    }
                break;
                    default:
                SendMessage($msg_chatid, 'Пришлите мне команду');
                    break;
            }
        }
    }
}


?>