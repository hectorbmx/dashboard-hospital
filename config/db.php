<?php
$host = '64.23.184.137';
$port = '3306';
$db   = 'bot_database';
$user = 'root';
$pass = 'R1c2systems+';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['error' => 'DB connection failed']));
}