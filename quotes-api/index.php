<?php
// 1. HEADERS & CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

require __DIR__ . '/config/Database.php';


try {
    $qCount = $conn->query("SELECT COUNT(*) FROM quotes")->fetchColumn();
    if ($qCount < 25) {
        $conn->exec("INSERT INTO authors (id, author) VALUES 
            (1, 'Albert Einstein'), (2, 'Mark Twain'), (3, 'Maya Angelou'), 
            (4, 'Oscar Wilde'), (5, 'J.K. Rowling') ON CONFLICT DO NOTHING");
        $conn->exec("INSERT INTO categories (id, category) VALUES 
            (1, 'Life'), (2, 'Success'), (3, 'Philosophy'), (4, 'Wisdom'),
            (5, 'Humor'), (6, 'Inspiration'), (7, 'Education') ON CONFLICT DO NOTHING");
        $conn->exec("INSERT INTO quotes (quote, author_id, category_id) VALUES 
            ('Imagination is more important than knowledge.', 1, 7),
            ('It is better to remain silent and be thought a fool.', 2, 5),
            ('People will never forget how you made them feel.', 3, 4),
            ('I can resist everything except temptation.', 4, 3),
            ('The important thing is not to stop questioning.', 1, 7),
            ('If you tell the truth, you dont have to remember anything.', 2, 5),
            ('Everything in moderation, including moderation.', 4, 3),
            ('Success is not final, failure is not fatal.', 2, 2),
            ('A person who never made a mistake never tried anything new.', 1, 7),
            ('The best way to cheer yourself up is to cheer somebody else up.', 2, 5),
            ('Be the change that you wish to see in the world.', 3, 6),
            ('Darkness cannot drive out darkness; only light can do that.', 3, 6),
            ('The only source of knowledge is experience.', 1, 7),
            ('The silence of our friends is what hurts.', 3, 6),
            ('Life is what happens when you’re making other plans.', 1, 1),
            ('Get busy living or get busy dying.', 4, 1),
            ('You only live once, but if you do it right, once is enough.', 4, 3),
            ('To be great is to be misunderstood.', 1, 3),
            ('The only way to do great work is to love what you do.', 2, 2),
            ('Stay hungry, stay foolish.', 1, 2),
            ('Whatever you are, be a good one.', 2, 3),
            ('The best way to predict your future is to create it.', 3, 2),
            ('Do what you can, with what you have.', 2, 6),
            ('The mind is everything. What you think you become.', 1, 3),
            ('An unexamined life is not worth living.', 4, 3)
            ON CONFLICT DO NOTHING");
    }
} catch (Exception $e) { /* Silent fail */ }


$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));
$route = end($path_parts);

if (!$route || $route === 'api') {
    $route = isset($_GET['route']) ? $_GET['route'] : 'quotes';
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            ini_set('display_errors', 0);
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            $author_id = isset($_GET['author_id']) ? intval($_GET['author_id']) : null;
            $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

            if ($route === 'quotes') {
                if ($id) {
                    $check = $conn->prepare("SELECT q.id, q.quote, a.author, c.category FROM quotes q 
                                            JOIN authors a ON q.author_id = a.id 
                                            JOIN categories c ON q.category_id = c.id WHERE q.id = ?");
                    $check->execute([$id]);
                    $quote = $check->fetch(PDO::FETCH_ASSOC);
                    echo $quote ? json_encode($quote, JSON_PRETTY_PRINT) : json_encode(['message' => 'No Quotes Found']);
                    exit;
                }
                $sql = "SELECT q.id, q.quote, a.author, c.category FROM quotes q JOIN authors a ON q.author_id = a.id JOIN categories c ON q.category_id = c.id";
                $where = [];
                if ($author_id) $where[] = "q.author_id = $author_id";
                if ($category_id) $where[] = "q.category_id = $category_id";
                if ($where) $sql .= " WHERE " . implode(' AND ', $where);
                $sql .= isset($_GET['random']) ? " ORDER BY RANDOM()" : " ORDER BY q.id";
                $sql .= " LIMIT $limit";
                $stmt = $conn->query($sql);
                $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo empty($response) ? json_encode(['message' => 'No Quotes Found']) : json_encode($response, JSON_PRETTY_PRINT);
                exit;
            } 
            elseif ($route === 'authors' || $route === 'categories') {
                $isAuthor = ($route === 'authors');
                $table = $isAuthor ? 'authors' : 'categories';
                $col = $isAuthor ? 'author' : 'category';
                if ($id) {
                    $stmt = $conn->prepare("SELECT id, $col FROM $table WHERE id = ?");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo $row ? json_encode($row, JSON_PRETTY_PRINT) : json_encode(['message' => ($isAuthor ? 'author_id Not Found' : 'category_id Not Found')]);
                    exit;
                }
                $stmt = $conn->query("SELECT id, $col FROM $table ORDER BY id");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
                exit;
            }
            break;

        case 'POST':
        case 'PUT':
            if ($route === 'quotes' && isset($input['quote'], $input['author_id'], $input['category_id'])) {
                $auth = $conn->prepare("SELECT id FROM authors WHERE id = ?");
                $auth->execute([$input['author_id']]);
                if (!$auth->fetch()) { echo json_encode(['message' => 'author_id Not Found']); exit; }

                $cat = $conn->prepare("SELECT id FROM categories WHERE id = ?");
                $cat->execute([$input['category_id']]);
                if (!$cat->fetch()) { echo json_encode(['message' => 'category_id Not Found']); exit; }

                if ($method === 'POST') {
                    $stmt = $conn->prepare("INSERT INTO quotes (quote, author_id, category_id) VALUES (?, ?, ?)");
                    $stmt->execute([$input['quote'], $input['author_id'], $input['category_id']]);
                    echo json_encode(['id' => $conn->lastInsertId(), 'quote' => $input['quote'], 'author_id' => $input['author_id'], 'category_id' => $input['category_id']]);
                } else {
                    if (!isset($input['id'])) { echo json_encode(['message' => 'Missing Required Parameters']); exit; }
                    $stmt = $conn->prepare("UPDATE quotes SET quote=?, author_id=?, category_id=? WHERE id=?");
                    $stmt->execute([$input['quote'], $input['author_id'], $input['category_id'], $input['id']]);
                    echo json_encode(['id' => $input['id'], 'quote' => $input['quote'], 'author_id' => $input['author_id'], 'category_id' => $input['category_id']]);
                }
                exit;
            } else {
                echo json_encode(['message' => 'Missing Required Parameters']); exit;
            }
            break;

        case 'DELETE':
          
            $table = '';
            if ($route === 'quotes') { $table = 'quotes'; }
            elseif ($route === 'authors') { $table = 'authors'; }
            elseif ($route === 'categories') { $table = 'categories'; }

        
            if ($table && isset($input['id'])) {
                $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$input['id']]);
                
            
                echo json_encode(['id' => $input['id']]);
                exit;
            } 
           
            elseif ($route === 'quotes') {
                echo json_encode(['message' => 'No Quotes Found']);
                exit;
            }
         
            else {
                echo json_encode(['message' => 'Missing Required Parameters']);
                exit;
            }
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['message' => $e->getMessage()]);
}
