<?php
header('Content-Type: application/json');
require __DIR__ . '/config/Database.php';

// --------------------------------------------------------
// API LOGIC
// --------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? $_GET['route'] : 'quotes';
$input = json_decode(file_get_contents('php://input'), true);
$response = [];

try {
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
                
                $stmt = $conn->query($sql);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($route === 'authors') {
                $sql = "SELECT id, author FROM authors" . ($author_id ? " WHERE id = $author_id" : "");
                $stmt = $conn->query($sql);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($route === 'categories') {
                $sql = "SELECT id, category FROM categories" . ($category_id ? " WHERE id = $category_id" : "");
                $stmt = $conn->query($sql);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'POST':
            if ($route === 'quotes' && isset($input['quote'], $input['author_id'], $input['category_id'])) {
                $stmt = $conn->prepare("INSERT INTO quotes (quote, author_id, category_id) VALUES (?, ?, ?)");
                $stmt->execute([$input['quote'], $input['author_id'], $input['category_id']]);
                $response = ['id' => $conn->lastInsertId(), 'message' => 'Quote Created'];
            }
            break;

        case 'PUT':
            if ($route === 'quotes' && isset($input['id'], $input['quote'], $input['author_id'], $input['category_id'])) {
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
} catch (PDOException $e) {
    $response = ['error' => 'Database error: ' . $e->getMessage()];
}


echo json_encode($response ?: ['message' => 'No Data Found'], JSON_PRETTY_PRINT);
