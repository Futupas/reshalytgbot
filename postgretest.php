<?php
/*
Host: ec2-176-34-97-213.eu-west-1.compute.amazonaws.com
Database: dqgjdn987m200
User: umsfvokedwaxub
Port: 5432
Password: c543a242bf844d0c09479beb46bd448e9c88f3ac0146705c9c4020593d26bf6f
*/


// Соединение, выбор базы данных
$dbconn = pg_connect(
"host=ec2-176-34-97-213.eu-west-1.compute.amazonaws.com 
dbname=dqgjdn987m200 
user=umsfvokedwaxub 
password=c543a242bf844d0c09479beb46bd448e9c88f3ac0146705c9c4020593d26bf6f 
port=5432")
    or die('Не удалось соединиться: ' . pg_last_error());

// Выполнение SQL-запроса
$query = 'delete from orders where true';
$result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

// Вывод результатов в HTML
// echo "<table>\n";
// while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
//     echo "\t<tr>\n";
//     foreach ($line as $col_value) {
//         echo "\t\t<td>$col_value</td>\n";
//     }
//     echo "\t</tr>\n";
// }
// echo "</table>\n";
// INSERT INTO "Table1" ("id", "name") VALUES (1, 'name1'),(2,'name2')

// Очистка результата
pg_free_result($result);

// Закрытие соединения
pg_close($dbconn);

?>