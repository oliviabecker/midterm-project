<?php
header('Content-Type: application/json');

$mysqli = new mysqli('localhost', 'root', 'root', 'quotesdb');
if ($mysqli->connect_error) {
    die(json_encode(['message' => 'DB Connection Failed']));
}

$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? $_GET['route'] : 'quotes';
$input = json_decode(file_get_contents('php://input'), true);

$response = [];

switch ($method) {
    // --------------------- GET ---------------------
    case 'GET':
        $author_id = isset($_GET['author_id']) ? intval($_GET['author_id']) : null;
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
        $random = isset($_GET['random']) ? true : false;

        switch ($route) {
            case 'quotes':
                $query = "SELECT q.id, q.quote, q.author_id, q.category_id FROM quotes q";
                $conditions = [];
                if ($author_id) $conditions[] = "q.author_id = $author_id";
                if ($category_id) $conditions[] = "q.category_id = $category_id";
                if (count($conditions) > 0) $query .= " WHERE " . implode(' AND ', $conditions);
                if ($random) $query .= " ORDER BY RAND()";
                else $query .= " ORDER BY q.id";
                $query .= " LIMIT 10";
                $result = $mysqli->query($query);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) $response[] = $row;
                } else {
                    $response = ['message' => 'No Quotes Found'];
                }
                break;

            case 'authors':
                $query = "SELECT id, author FROM authors";
                if ($author_id) $query .= " WHERE id = $author_id";
                $result = $mysqli->query($query);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) $response[] = $row;
                } else {
                    $response = ['message' => 'author_id Not Found'];
                }
                break;

            case 'categories':
                $query = "SELECT id, category FROM categories";
                if ($category_id) $query .= " WHERE id = $category_id";
                $result = $mysqli->query($query);
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) $response[] = $row;
                } else {
                    $response = ['message' => 'category_id Not Found'];
                }
                break;

            default:
                $response = ['message' => 'Invalid Route'];
        }
        break;

    // --------------------- POST ---------------------
    case 'POST':
        switch ($route) {
            case 'quotes':
                if (!isset($input['quote'], $input['author_id'], $input['category_id'])) {
                    $response = ['message' => 'Missing Required Parameters'];
                    break;
                }
                $author_check = $mysqli->query("SELECT id FROM authors WHERE id=".$input['author_id']);
                $category_check = $mysqli->query("SELECT id FROM categories WHERE id=".$input['category_id']);
                if ($author_check->num_rows === 0) { $response = ['message' => 'author_id Not Found']; break; }
                if ($category_check->num_rows === 0) { $response = ['message' => 'category_id Not Found']; break; }
                $stmt = $mysqli->prepare("INSERT INTO quotes (quote, author_id, category_id) VALUES (?, ?, ?)");
                $stmt->bind_param("sii", $input['quote'], $input['author_id'], $input['category_id']);
                $stmt->execute();
                $response = [
                    'id' => $stmt->insert_id,
                    'quote' => $input['quote'],
                    'author_id' => $input['author_id'],
                    'category_id' => $input['category_id']
                ];
                break;

            case 'authors':
                if (!isset($input['author'])) { $response = ['message' => 'Missing Required Parameters']; break; }
                $stmt = $mysqli->prepare("INSERT INTO authors (author) VALUES (?)");
                $stmt->bind_param("s", $input['author']);
                $stmt->execute();
                $response = ['id' => $stmt->insert_id, 'author' => $input['author']];
                break;

            case 'categories':
                if (!isset($input['category'])) { $response = ['message' => 'Missing Required Parameters']; break; }
                $stmt = $mysqli->prepare("INSERT INTO categories (category) VALUES (?)");
                $stmt->bind_param("s", $input['category']);
                $stmt->execute();
                $response = ['id' => $stmt->insert_id, 'category' => $input['category']];
                break;

            default:
                $response = ['message' => 'Invalid Route'];
        }
        break;

    // --------------------- PUT ---------------------
    case 'PUT':
        switch ($route) {
            case 'quotes':
                if (!isset($input['id'], $input['quote'], $input['author_id'], $input['category_id'])) {
                    $response = ['message' => 'Missing Required Parameters'];
                    break;
                }
                $check = $mysqli->query("SELECT id FROM quotes WHERE id=".$input['id']);
                if ($check->num_rows === 0) { $response = ['message' => 'No Quotes Found']; break; }
                $author_check = $mysqli->query("SELECT id FROM authors WHERE id=".$input['author_id']);
                $category_check = $mysqli->query("SELECT id FROM categories WHERE id=".$input['category_id']);
                if ($author_check->num_rows === 0) { $response = ['message' => 'author_id Not Found']; break; }
                if ($category_check->num_rows === 0) { $response = ['message' => 'category_id Not Found']; break; }
                $stmt = $mysqli->prepare("UPDATE quotes SET quote=?, author_id=?, category_id=? WHERE id=?");
                $stmt->bind_param("siii", $input['quote'], $input['author_id'], $input['category_id'], $input['id']);
                $stmt->execute();
                $response = $input;
                break;

            case 'authors':
                if (!isset($input['id'], $input['author'])) { $response = ['message' => 'Missing Required Parameters']; break; }
                $check = $mysqli->query("SELECT id FROM authors WHERE id=".$input['id']);
                if ($check->num_rows === 0) { $response = ['message' => 'No Authors Found']; break; }
                $stmt = $mysqli->prepare("UPDATE authors SET author=? WHERE id=?");
                $stmt->bind_param("si", $input['author'], $input['id']);
                $stmt->execute();
                $response = $input;
                break;

            case 'categories':
                if (!isset($input['id'], $input['category'])) { $response = ['message' => 'Missing Required Parameters']; break; }
                $check = $mysqli->query("SELECT id FROM categories WHERE id=".$input['id']);
                if ($check->num_rows === 0) { $response = ['message' => 'No Categories Found']; break; }
                $stmt = $mysqli->prepare("UPDATE categories SET category=? WHERE id=?");
                $stmt->bind_param("si", $input['category'], $input['id']);
                $stmt->execute();
                $response = $input;
                break;

            default:
                $response = ['message' => 'Invalid Route'];
        }
        break;

    // --------------------- DELETE ---------------------
    case 'DELETE':
        if (!isset($input['id'])) { $response = ['message' => 'Missing Required Parameters']; break; }
        switch ($route) {
            case 'quotes':
                $check = $mysqli->query("SELECT id FROM quotes WHERE id=".$input['id']);
                if ($check->num_rows === 0) { $response = ['message' => 'No Quotes Found']; break; }
                $mysqli->query("DELETE FROM quotes WHERE id=".$input['id']);
                $response = ['id' => $input['id']];
                break;

            case 'authors':
                $check = $mysqli->query("SELECT id FROM authors WHERE id=".$input['id']);
                if ($check->num_rows === 0) { $response = ['message' => 'No Authors Found']; break; }
                $mysqli->query("DELETE FROM authors WHERE id=".$input['id']);
                $response = ['id' => $input['id']];
                break;

            case 'categories':
                $check = $mysqli->query("SELECT id FROM categories WHERE id=".$input['id']);
                if ($check->num_rows === 0) { $response = ['message' => 'No Categories Found']; break; }
                $mysqli->query("DELETE FROM categories WHERE id=".$input['id']);
                $response = ['id' => $input['id']];
                break;

            default:
                $response = ['message' => 'Invalid Route'];
        }
        break;

    default:
        $response = ['message' => 'Unsupported HTTP Method'];
}

echo json_encode($response, JSON_PRETTY_PRINT);
$mysqli->close();
?>