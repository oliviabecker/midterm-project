<?php
// config/Database.php

$databaseUrl = getenv('DATABASE_URL');

try {
    if (!$databaseUrl) {
        die("Error: DATABASE_URL is not set.");
    }

    $dbparts = parse_url($databaseUrl);

    $host = $dbparts['host'];
    $user = $dbparts['user'];
    $pass = $dbparts['pass'];
    $db   = ltrim($dbparts['path'], '/');

   
    $dsn = "pgsql:host=$host;dbname=$db;user=$user;password=$pass";

   
    $conn = new PDO($dsn);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
   
    echo "Connection failed: " . $e->getMessage();
    exit;
}
