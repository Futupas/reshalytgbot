<?php

function add_log($msg) {
    file_put_contents('logs.txt', date("Y-m-d H:i:s")."   ".$msg."\n\n", FILE_APPEND);
}
function add_to_channel($msg) {
    // $outputchannel = getenv('outputchannel_id');
    //SendMessage($outputchannel, $msg);
}

?>
