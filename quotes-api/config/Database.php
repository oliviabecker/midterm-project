<?php
// config/Database.php


$databaseUrl = getenv('DATABASE_URL');

try {
    if (!$databaseUrl) {
        die("Error: DATABASE_URL is not set in Render Environment Variables.");
    }

    
    $pdoUrl = str_replace("postgresql://", "pgsql:", $databaseUrl);

    // Create the connection
    $conn = new PDO($pdoUrl);
    
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
   
    echo "Connection failed: " . $e->getMessage();
    exit;
}
