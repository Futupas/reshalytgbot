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

function SendMessageToChatBot($chatid, $text, $order) {
    $response;
    if ($chatid === 'c') {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$order['customer_id'].'&text='.urlencode($text).'&disable_web_page_preview=true'));
        add_row_to_chat_messages_table_with_text($order['customer_id'], $response->result->message_id, $order['executor_id'], $order['id'], $text);
    } else if ($chatid === 'e') {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$order['executor_id'].'&text='.urlencode($text).'&disable_web_page_preview=true'));
        add_row_to_chat_messages_table_with_text($order['executor_id'], $response->result->message_id, $order['customer_id'], $order['id'], $text);
    } else if ($chatid == $order['customer_id']) {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$order['customer_id'].'&text='.urlencode($text).'&disable_web_page_preview=true'));
        add_row_to_chat_messages_table_with_text($order['customer_id'], $response->result->message_id, $order['executor_id'], $order['id'], $text);
    } else if ($chatid == $order['executor_id']) {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$order['executor_id'].'&text='.urlencode($text).'&disable_web_page_preview=true'));
        add_row_to_chat_messages_table_with_text($order['executor_id'], $response->result->message_id, $order['customer_id'], $order['id'], $text);
    } else {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&disable_web_page_preview=true'));
    }
    return $response;
};
function SendMessageWithMarkdownToChatBot($chatid, $text, $order) {
    $response;
    if ($chatid === 'c') {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$order['customer_id'].'&text='.urlencode($text).'&disable_web_page_preview=true&parse_mode=markdown'));
        add_row_to_chat_messages_table_with_text($order['customer_id'], $response->result->message_id, $order['executor_id'], $order['id'], $text);
    } else if ($chatid === 'e') {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$order['executor_id'].'&text='.urlencode($text).'&disable_web_page_preview=true&parse_mode=markdown'));
        add_row_to_chat_messages_table_with_text($order['executor_id'], $response->result->message_id, $order['customer_id'], $order['id'], $text);
    } else if ($chatid == $order['customer_id']) {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$order['customer_id'].'&text='.urlencode($text).'&disable_web_page_preview=true&parse_mode=markdown'));
        add_row_to_chat_messages_table_with_text($order['customer_id'], $response->result->message_id, $order['executor_id'], $order['id'], $text);
    } else if ($chatid == $order['executor_id']) {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$order['executor_id'].'&text='.urlencode($text).'&disable_web_page_preview=true&parse_mode=markdown'));
        add_row_to_chat_messages_table_with_text($order['executor_id'], $response->result->message_id, $order['customer_id'], $order['id'], $text);
    } else {
        $response = json_decode(file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&disable_web_page_preview=true&parse_mode=markdown'));
    }

    return $response;
};
function SendMessageToChatBotWithNoOrder($chatid, $text) {
    $response = file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&disable_web_page_preview=true');
    return json_decode($response);
};
function SendMessageWithMarkdownToChatBotWithNoOrder($chatid, $text) {
    $response = file_get_contents('https://api.telegram.org/bot'.getenv('chat_bot_token').'/sendMessage?chat_id='.$chatid.'&text='.urlencode($text).'&disable_web_page_preview=true&parse_mode=markdown');
    return json_decode($response);
};

?>