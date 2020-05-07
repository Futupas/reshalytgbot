<?php
    function clean_table($table_name) {
        $dbconn = pg_connect(
            "host=ec2-176-34-97-213.eu-west-1.compute.amazonaws.com 
            dbname=dqgjdn987m200 
            user=umsfvokedwaxub 
            password=c543a242bf844d0c09479beb46bd448e9c88f3ac0146705c9c4020593d26bf6f 
            port=5432")
                or die('Не удалось соединиться: ' . pg_last_error());
            $query = "DELETE FROM \"$table_name\" WHERE true";
            $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());
            pg_free_result($result);
            pg_close($dbconn);
    };


    clean_table('users'); echo("users were cleaned\n");
    clean_table('orders'); echo("orders were cleaned\n");
    clean_table('chat_messages'); echo("chat_messages were cleaned\n");
    clean_table('order_executors'); echo("order_executors were cleaned\n");
?>