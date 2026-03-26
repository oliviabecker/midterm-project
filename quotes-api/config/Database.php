<?php
// Database.php

$databaseUrl = getenv('INTERNAL_URL') ?: getenv('DATABASE_URL');

try {
    $conn = new PDO($databaseUrl);
    
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // If the connection fails
    error_log("Connection failed: " . $e->getMessage());
    die("Database connection error. Please check your logs.");
}

function runQuery($sql, $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
?>
