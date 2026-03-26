<?php
header('Content-Type: application/json');
require 'Database.php'; // This uses the $conn variable we set up earlier

// --------------------------------------------------------
// 1. AUTO-IMPORT (Builds your tables and data on Render)
// --------------------------------------------------------
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS authors (id SERIAL PRIMARY KEY, author VARCHAR(255) NOT NULL);
        CREATE TABLE IF NOT EXISTS categories (id SERIAL PRIMARY KEY, category VARCHAR(255) NOT NULL);
        CREATE TABLE IF NOT EXISTS quotes (
            id SERIAL PRIMARY KEY, 
            quote TEXT NOT NULL, 
            author_id INT REFERENCES authors(id) ON DELETE CASCADE, 
            category_id INT REFERENCES categories(id) ON DELETE CASCADE
        );
    ");

    $count = $conn->query("SELECT COUNT(*) FROM quotes")->fetchColumn();
    if ($count == 0) {
        $conn->exec("INSERT INTO authors (id, author) VALUES (1, 'Albert Einstein'), (2, 'Mark Twain'), (3, 'Maya Angelou'), (4, 'Oscar Wilde'), (5, 'J.K. Rowling') ON CONFLICT DO NOTHING");
        $conn->exec("INSERT INTO categories (id, category) VALUES (1, 'Life'), (2, 'Success'), (3, 'Philosophy'), (4, 'Wisdom') ON CONFLICT DO NOTHING");
        $conn->exec("INSERT INTO quotes (id, quote, author_id, category_id) VALUES 
            (1, 'Life is like riding a bicycle. To keep your balance you must keep moving.', 1, 1),
            (2, 'The secret of getting ahead is getting started.', 2, 2),
            (3, 'Try to be a rainbow in someone’s cloud.', 3, 4),
            (4, 'Be yourself; everyone else is already taken.', 4, 3),
            (10, 'It does not do to dwell on dreams and forget to live.', 5, 4),
            (11, 'We do not need magic to transform our world; we carry all the power we need inside ourselves already.', 5, 4)
            ON CONFLICT DO NOTHING");
    }
} catch (PDOException $e) {
    // Silent fail for setup so it doesn't break the API output
}

// --------------------------------------------------------
// 2. API LOGIC
// --------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? $_GET['route'] : 'quotes';
$input = json_decode(file_get_contents('php://input'), true);
$response = [];

switch ($method) {
    case 'GET':
        $author_id = isset($_GET['author_id']) ? intval($_GET['author_id']) : null;
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
        $random = isset($_GET['random']);

        if ($route === 'quotes') {
            $sql = "SELECT id, quote, author_id, category_id FROM quotes";
            $where = [];
            if ($author_id) $where[] = "author_id = $author_id";
            if ($category_id) $where[] = "category_id = $category_id";
            if ($where) $sql .= " WHERE " . implode(' AND ', $where);
            $sql .= $random ? " ORDER BY RANDOM()" : " ORDER BY id";
            $sql .= " LIMIT 10";
            $response = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($route === 'authors') {
            $sql = "SELECT id, author FROM authors" . ($author_id ? " WHERE id = $author_id" : "");
            $response = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($route === 'categories') {
            $sql = "SELECT id, category FROM categories" . ($category_id ? " WHERE id = $category_id" : "");
            $response = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
        break;

    case 'POST':
        if ($route === 'quotes') {
            $stmt = $conn->prepare("INSERT INTO quotes (quote, author_id, category_id) VALUES (?, ?, ?)");
            $stmt->execute([$input['quote'], $input['author_id'], $input['category_id']]);
            $response = ['id' => $conn->lastInsertId(), 'message' => 'Quote Created'];
        }
        break;

    case 'PUT':
        if ($route === 'quotes') {
            $stmt = $conn->prepare("UPDATE quotes SET quote=?, author_id=?, category_id=? WHERE id=?");
            $stmt->execute([$input['quote'], $input['author_id'], $input['category_id'], $input['id']]);
            $response = ['message' => 'Quote Updated'];
        }
        break;

    case 'DELETE':
        if (isset($input['id'])) {
            $stmt = $conn->prepare("DELETE FROM $route WHERE id = ?");
            $stmt->execute([$input['id']]);
            $response = ['message' => 'Deleted ID ' . $input['id']];
        }
        break;
}

echo json_encode($response ?: ['message' => 'No Data Found'], JSON_PRETTY_PRINT);
?>
