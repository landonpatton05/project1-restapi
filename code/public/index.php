<?php
// code/public/index.php
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$parts = explode('/', trim($uri, '/'));

function get_bearer_token() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/', $auth, $m)) return $m[1];
    if (!empty($_GET['token'])) return $_GET['token'];
    return null;
}

if ($uri === '/ping' && $method === 'GET') {
    echo json_encode(['ok' => true, 'time' => date('c')]);
    exit;
}

if ($uri === '/users' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($body['username']) || empty($body['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'username and password required']);
        exit;
    }
    $id = create_user($body['username'], $body['password'], $body['email'] ?? null);
    if ($id) {
        http_response_code(201);
        echo json_encode(['id' => (int)$id]);
    } else {
        http_response_code(409);
        echo json_encode(['error' => 'could not create user (maybe exists)']);
    }
    exit;
}

if ($uri === '/login' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($body['username']) || empty($body['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'username and password required']);
        exit;
    }
    $user = verify_user($body['username'], $body['password']);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'invalid credentials']);
        exit;
    }
    $token = issue_token($user['id']);
    echo json_encode($token);
    exit;
}

if (preg_match('#^/users/(\d+)$#', $uri, $m) && $method === 'GET') {
    $token = get_bearer_token();
    $user_id = validate_bearer($token);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }
    $target_id = (int)$m[1];
    $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$target_id]);
    $row = $stmt->fetch();
    if ($row) echo json_encode($row);
    else {
        http_response_code(404);
        echo json_encode(['error' => 'user not found']);
    }
    exit;
}

if ($uri === '/items' && $method === 'GET') {
    $stmt = $pdo->query("SELECT items.*, users.username as owner FROM items JOIN users ON users.id = items.owner_id ORDER BY created_at DESC");
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
    exit;
}

if ($uri === '/items' && $method === 'POST') {
    $token = get_bearer_token();
    $user_id = validate_bearer($token);
    if (!$user_id) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($body['title'])) { http_response_code(400); echo json_encode(['error'=>'title required']); exit; }
    $stmt = $pdo->prepare("INSERT INTO items (owner_id, title, description, price) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $body['title'], $body['description'] ?? null, $body['price'] ?? 0.00]);
    http_response_code(201);
    echo json_encode(['id' => (int)$pdo->lastInsertId()]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'not found']);