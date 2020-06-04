<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $query = urldecode(file_get_contents('php://input'));
    $dbconn = pg_connect(
        "host=ec2-176-34-97-213.eu-west-1.compute.amazonaws.com 
dbname=dqgjdn987m200 
user=umsfvokedwaxub 
password=c543a242bf844d0c09479beb46bd448e9c88f3ac0146705c9c4020593d26bf6f 
port=5432")
            or die('Не удалось соединиться: ' . pg_last_error());

        
    $result = pg_query($query) or die('Ошибка запроса: ' . pg_last_error());

    $num_rows = pg_num_rows($result);
    $affected_rows = pg_affected_rows($result);
    $fetch_all = pg_fetch_all($result);

    pg_free_result($result);
    pg_close($dbconn);


    echo json_encode((object)array(
        'num_rows' => $num_rows,
        'affected_rows' => $affected_rows,
        'fetch_all' => $fetch_all
    ));
    exit(0);
} 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>access to db</title>
    <style>
        #query{
            width: 100%;
            resize: none;
        }
        #sendBtn{
            width: 100%;
            display: block;
        }
        #rows_affected{
            margin-top: 30px;
        }
        #num_rows{

        }
        table{
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        #table{
            margin-top: 10px;
        }
        td{
            padding: 2px;
            text-align: center;
        }
        thead td{
            font-weight: 700;
        }
    </style>
</head>
<body>
    <textarea name="" id="query" cols="30" rows="10" placeholder="query"></textarea>
    <button id="sendBtn">send</button>
    <div id="result">
        <div id="rows_affected">
        </div>
        <div id="num_rows">
        </div>
        <div id="table">
        </div>
    </div>

    <script>
        'use strict';
        document.getElementById('sendBtn').onclick = function(e) {
            (async function(){
                let btnText = document.getElementById('sendBtn').innerText;
                document.getElementById('sendBtn').innerText = 'sending...';
                let response = await fetch('/db.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json;charset=utf-8'
                },
                body: encodeURI(document.getElementById('query').value)
                });
                let result = await response.text();
                let json_result;
                try {
                    json_result = JSON.parse(result);
                } catch (e) {
                    alert('incorrect query');
                    console.log(result);
                    document.getElementById('result').innerHTML = '<pre>'+result+'</pre>';
                    document.getElementById('sendBtn').innerText = btnText;
                    return;
                }
                
                document.getElementById('rows_affected').innerText = 'rows affected: '+json_result.affected_rows;
                document.getElementById('num_rows').innerText = 'rows returned: '+json_result.num_rows;
                if (json_result.fetch_all.length < 1) {
                    document.getElementById('table').innerText = 'no data returned to show';
                } else {
                    document.getElementById('table').innerHTML = '';
                    let table = createElementWithInnerText('table', '');
                    let thead = createElementWithInnerText('thead', '');
                    let tbody = createElementWithInnerText('tbody', '');
                    table.appendChild(thead);
                    table.appendChild(tbody);
                    document.getElementById('table').appendChild(table);
                    let fields = Object.keys(json_result.fetch_all[0]);
                    let tr = createElementWithInnerText('tr', '');
                    thead.appendChild(tr);
                    for (let i = 0; i < fields.length; i-=-1) {
                        tr.appendChild(createElementWithInnerText('td', fields[i]));
                    }
                    for (let row = 0; row < json_result.fetch_all.length; row-=-1) {
                        let tr = createElementWithInnerText('tr', '');
                        tbody.appendChild(tr);
                        for (let i = 0; i < fields.length; i-=-1) {
                            tr.appendChild(createElementWithInnerText('td', json_result.fetch_all[row][fields[i]]));
                        }
                    }
                }
                document.getElementById('sendBtn').innerText = btnText;
            })();
        }

        function createElementWithInnerText(tagName, innerText) {
            let element = document.createElement(tagName);
            element.innerText = innerText;
            return element;
        }
    </script>
</body>
</html>