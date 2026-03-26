<?php
// config/Database.php


$databaseUrl = getenv('DATABASE_URL');

try {
    if (!$databaseUrl) {
        throw new Exception("DATABASE_URL is not set in Render environment variables.");
    }

   
    $conn = new PDO($databaseUrl);
    
  
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (Exception $e) {
    
    error_log("Connection failed: " . $e->getMessage());
    die("Database connection error. Please check your logs.");
}
