<?php

function handle_callback($json_message) {
    // $callback_query_id = $json_message->callback_query->id;
    // $msg_chatid = $json_message->callback_query->message->chat->id;
    // $choise_data = $json_message->callback_query->data;
    // $msg_id = $json_message->callback_query->message->message_id;

    // $choise_data_array = explode('_', $choise_data); // 0 - showed profile id, 1 - like (0 or 1)

    // $response1 = file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/answerCallbackQuery?'.
    //     http_build_query((object)array(
    //         'callback_query_id' => $callback_query_id,
    //         'text' => 'you '.($choise_data_array[1] ? '' : 'dis').'liked.'
    //     )));
    // $response2 = file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/editMessageReplyMarkup?'.
    // http_build_query((object)array(
    //     'chat_id' => $msg_chatid,
    //     'message_id' => $msg_id,
    //     'reply_markup' => '{"inline_keyboard": [[]]}'
    // )));

    // $conn = new mysqli(
    //     $GLOBALS['db_servername'], 
    //     $GLOBALS['db_username'], 
    //     $GLOBALS['db_password'], 
    //     $GLOBALS['db_name'], 
    //     $GLOBALS['db_port']);
    // if ($conn->connect_error) {
    //     add_log('Connection failed: '.$conn->connect_error);
    //     add_to_channel('Connection to DB failed: '.$conn->connect_error);
    //     die();
    // }
    
    // $sql = 'INSERT INTO `Matches`(`choise_profile_id`, `showed_profile_id`, `matching`) VALUES 
    // ('.$msg_chatid.', '.$choise_data_array[0].', '.$choise_data_array[1].')';
    
    // $result = $conn->query($sql);
    // $conn->close();

    // are_profiles_match_each_other($msg_chatid, $choise_data_array[0]);

    // send_matching_profile($msg_chatid);

    // SendMessage($msg_chatid, 'ok, you did ur choise');

    // add_to_channel($callback_query_id.'%0A'.$msg_chatid.'%0A'.$choise_data.'%0A'.$msg_id);
}

?>
