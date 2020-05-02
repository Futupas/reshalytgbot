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
                        $publish_return = publish_order($order_id);
                        add_log("p_return: ".$publish_return);
                        add_log("json encode: ".json_encode($publish_return));
                        add_log("var dump: ".var_dump($publish_return));
                        if (!$publish_return['ok']) SendMessage($msg_chatid, 'error publishing ur order');
                        else {
                            $post_id = $publish_return->result->message_id;
                            set_user_current_order_fill($msg_chatid, 'null');
                            set_user_step($msg_chatid, 0);
                            change_order($order_id, 'post_id', $post_id);
                            SendMessage($msg_chatid, "kkey, here's ur order link: https://t.me/reshalychannel/$post_id");
                            SendMessage($msg_chatid, "now u can add one more order by sending me /add_order command");
                        }
                    } else
                        SendMessage($msg_chatid, 'price must be a positive int');
                    break;
                
                default:
                SendMessage($msg_chatid, 'send me a command');
                    break;
            }

            // $user = get_my_profile($msg_chatid);
            // if ($user->error) {
            //      SendMessage($msg_chatid, 'cannot find you in db');
            //     // add_to_channel('Error. Can not find user in DB%0AUser id='.$msg_chatid);
            // } else {
            //     switch($user->fill_action) {
            //         case 0:
            //             SendMessage($msg_chatid, 'unknown command');
            //         break;
            //         case 1: //change name
            //             set_new_name($msg_chatid, $msg);
            //             set_user_fill_step($msg_chatid, 0);
            //             SendMessage($msg_chatid, 'ur name was changed');
            //         break;
            //         case 2: //change description
            //             set_new_description($msg_chatid, $msg);
            //             set_user_fill_step($msg_chatid, 0);
            //             SendMessage($msg_chatid, 'ur about text was changed');
            //         break;
            //         case 3: //change photos
            //             if (property_exists($json_message->message, 'photo')) {
            //                 $photos = $json_message->message->photo;
            //                 $photo_id = $photos[count($photos)-1]->file_id;
            //                 set_new_profile_photo($msg_chatid, $photo_id);
            //                 set_user_fill_step($msg_chatid, 0);
            //                 SendMessage($msg_chatid, 'profile photo was updated');
            //             } else {
            //                 SendMessage($msg_chatid, 'send me photo');
            //             }
            //         break;
            //         case 4: //change sex
            //             //$msg
            //             $sex_id = get_sex_id_by_name($msg);
            //             if ($sex_id == -1) {
            //                 // set_user_fill_step($msg_chatid, 4);
            //                 SendMessage($msg_chatid, 'unknown sex, try again hackerman');
            //             } else {
            //                 set_new_sex($msg_chatid, $sex_id);
            //                 set_user_fill_step($msg_chatid, 0);
            //                 SendMessage($msg_chatid, 'ur sex was changed&reply_markup='.json_encode((object)array("remove_keyboard" => true)));
            //             }
            //         break;
            //         case 5: //change faculty
            //             //$msg
            //             $faculty_id = get_faculty_id_by_name($msg);
            //             if ($faculty_id == -1) {
            //                 // set_user_fill_step($msg_chatid, 5);
            //                 SendMessage($msg_chatid, 'unknown faculty, try again hackerman');
            //             } else {
            //                 set_new_faculty($msg_chatid, $faculty_id);
            //                 set_user_fill_step($msg_chatid, 0);
            //                 SendMessage($msg_chatid, 'ur faculty was changed&reply_markup='.json_encode((object)array("remove_keyboard" => true)));
            //             }
            //         break;
            //         case 6: //change studying year
            //             //$msg
            //             $studyr_id = get_studyr_id_by_name($msg);
            //             if ($studyr_id == -1) {
            //                 // set_user_fill_step($msg_chatid, 6);
            //                 SendMessage($msg_chatid, 'unknown studying year, try again hackerman');
            //             } else {
            //                 set_new_studyr($msg_chatid, $studyr_id);
            //                 set_user_fill_step($msg_chatid, 0);
            //                 SendMessage($msg_chatid, 'ur studying year was changed&reply_markup='.json_encode((object)array("remove_keyboard" => true)));
            //             }
            //         break;
            //         case 7: //change living place
            //             //$msg
            //             $livplace_id = get_livplace_id_by_name($msg);
            //             if ($livplace_id == -1) {
            //                 // set_user_fill_step($msg_chatid, 7);
            //                 SendMessage($msg_chatid, 'unknown living place, try again hackerman');
            //             } else {
            //                 set_new_livplace($msg_chatid, $livplace_id);
            //                 set_user_fill_step($msg_chatid, 0);
            //                 SendMessage($msg_chatid, 'ur living place was changed&reply_markup='.json_encode((object)array("remove_keyboard" => true)));
            //             }
            //         break;
            //         case 8: //change hobbies
            //             sql_query_toggle_profile_hobbie($msg_chatid, $msg);
            //             SendMessage($msg_chatid, 'ok, send me /done to finish&reply_markup='.get_keyboard_for_hobbies($msg_chatid));
            //         break;
            //         case 9: //change matching sexes
            //             sql_query_toggle_profile_search($msg_chatid, $msg, 'Sexes', 'search_sexes');
            //             SendMessage($msg_chatid, 'ok, send me /done to finish&reply_markup='.
            //             get_keyboard_for_search($msg_chatid, 'Sexes', 'search_sexes', 'sex'));
            //         break;
            //         // case 10: //change matching hobbies
            //         //     sql_query_toggle_profile_search($msg_chatid, $msg, 'Hobbies', 'search_hobbies');
            //         //     SendMessage($msg_chatid, 'ok, send me /done to finish&reply_markup='.
            //         //     get_keyboard_for_search($msg_chatid, 'Hobbies', 'search_hobbies', 'hobbie'));
            //         // break;
            //         case 11: //change matching faculties
            //             sql_query_toggle_profile_search($msg_chatid, $msg, 'Faculties', 'search_faculties');
            //             SendMessage($msg_chatid, 'ok, send me /done to finish&reply_markup='.
            //             get_keyboard_for_search($msg_chatid, 'Faculties', 'search_faculties', 'faculty'));
            //         break;
            //         case 12: //change matching studying years
            //             sql_query_toggle_profile_search($msg_chatid, $msg, 'StudyingYears', 'search_studying_years');
            //             SendMessage($msg_chatid, 'ok, send me /done to finish&reply_markup='.
            //             get_keyboard_for_search($msg_chatid, 'StudyingYears', 'search_studying_years', 'studying year'));
            //         break;
            //         case 13: //change matching living places
            //             sql_query_toggle_profile_search($msg_chatid, $msg, 'LivingPlace', 'search_living_places');
            //             SendMessage($msg_chatid, 'ok, send me /done to finish&reply_markup='.
            //             get_keyboard_for_search($msg_chatid, 'LivingPlace', 'search_living_places', 'living place'));
            //         break;
            //         default:
            //             SendMessage($msg_chatid, 'unknown command');
            //         break;
            //     }
                
            // }

            // SendMessage($msg_chatid, 'u said '.$msg);
        }
    }
}


function is_string_a_number($str) {
    return true;
}

?>