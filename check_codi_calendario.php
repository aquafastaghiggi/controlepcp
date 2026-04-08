<?php
$pdo = new PDO('mysql:host=127.0.0.1:3306;dbname=controlepcp_sandbox;charset=utf8mb4', 'root', 'k7m2y9u4');
$result = $pdo->query('DESCRIBE codi_calendario');
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . PHP_EOL;
}
