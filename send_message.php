<?php


function SendMessage($chatid, $text) {
    $response = file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text));
};
function ReplyToMessage($chatid, $text, $msgtoreply) {
    $response = file_get_contents(
        'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&reply_to_message_id='.$msgtoreply
    );
};



?>