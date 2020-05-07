<?php


function SendMessage($chatid, $text) {
    $response = file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&disable_web_page_preview=true');
};
function SendMessageWithMarkdown($chatid, $text) {
    $response = file_get_contents('https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&disable_web_page_preview=true&parse_mode=markdown');
};
function ReplyToMessage($chatid, $text, $msgtoreply) {
    $response = file_get_contents(
        'https://api.telegram.org/bot'.getenv('bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&reply_to_message_id='.$msgtoreply
    );
};

function SendMessageToChatBot($chatid, $text) {
    $response = file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&disable_web_page_preview=true');
};
function SendMessageWithMarkdownToChatBot($chatid, $text) {
    $response = file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&disable_web_page_preview=true&parse_mode=markdown');
};

?>