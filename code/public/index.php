<?php
// Main REST API router

header('Content-Type: application/json');

require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../src/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// === USERS ===

// POST /users
if ($uri === '/users' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'missing fields']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email) VALUES (?, ?, ?)");
        $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['email'] ?? ''
        ]);
        echo json_encode(['id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'could not create user (maybe exists)']);
    }
    exit;
}

// POST /login
if ($uri === '/login' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    $user = $stmt->fetch();

    if ($user && password_verify($data['password'], $user['password_hash'])) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))")
            ->execute([$user['id'], $token]);
        echo json_encode(['token' => $token]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'invalid credentials']);
    }
    exit;
}

// GET /users/{id}
if (preg_match('#^/users/(\d+)$#', $uri, $m) && $method === 'GET') {
    $token = get_bearer_token();
    $uid = validate_bearer($token);
    if (!$uid) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

    $id = (int)$m[1];
    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) echo json_encode($row);
    else { http_response_code(404); echo json_encode(['error' => 'user not found']); }
    exit;
}

// === ITEMS ===

// GET /items
if ($uri === '/items' && $method === 'GET') {
    $stmt = $pdo->query("SELECT items.*, users.username AS owner FROM items JOIN users ON users.id = items.owner_id ORDER BY items.id DESC");
    echo json_encode($stmt->fetchAll());
    exit;
}

// POST /items
if ($uri === '/items' && $method === 'POST') {
    $token = get_bearer_token();
    $uid = validate_bearer($token);
    if (!$uid) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO items (owner_id, title, description, price) VALUES (?, ?, ?, ?)");
    $stmt->execute([$uid, $data['title'], $data['description'], $data['price']]);
    echo json_encode(['id' => $pdo->lastInsertId()]);
    exit;
}

// GET /items/{id}
if (preg_match('#^/items/(\d+)$#', $uri, $m) && $method === 'GET') {
    $id = (int)$m[1];
    $stmt = $pdo->prepare("SELECT items.*, users.username AS owner FROM items JOIN users ON users.id = items.owner_id WHERE items.id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) echo json_encode($row);
    else { http_response_code(404); echo json_encode(['error' => 'not found']); }
    exit;
}

// PUT /items/{id}
if (preg_match('#^/items/(\d+)$#', $uri, $m) && $method === 'PUT') {
    $token = get_bearer_token();
    $uid = validate_bearer($token);
    if (!$uid) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

    $id = (int)$m[1];
    $stmt = $pdo->prepare("SELECT owner_id FROM items WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    if ($row['owner_id'] != $uid) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("UPDATE items SET title=?, description=?, price=? WHERE id=?");
    $stmt->execute([$data['title'], $data['description'], $data['price'], $id]);
    echo json_encode(['message'=>'updated']);
    exit;
}

// DELETE /items/{id}
if (preg_match('#^/items/(\d+)$#', $uri, $m) && $method === 'DELETE') {
    $token = get_bearer_token();
    $uid = validate_bearer($token);
    if (!$uid) { http_response_code(401); echo json_encode(['error' => 'unauthorized']); exit; }

    $id = (int)$m[1];
    $stmt = $pdo->prepare("SELECT owner_id FROM items WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }

    $stmt2 = $pdo->prepare("SELECT role FROM users WHERE id=?");
    $stmt2->execute([$uid]);
    $u = $stmt2->fetch();
    $isAdmin = $u && $u['role'] === 'admin';
    if ($row['owner_id'] != $uid && !$isAdmin) {
        http_response_code(403); echo json_encode(['error'=>'forbidden']); exit;
    }

    $pdo->prepare("DELETE FROM items WHERE id=?")->execute([$id]);
    echo json_encode(['message'=>'deleted']);
    exit;
}

// GET /search?q=keyword
if ($uri === '/search' && $method === 'GET') {
    $q = $_GET['q'] ?? '';
    $stmt = $pdo->prepare("SELECT items.*, users.username AS owner FROM items JOIN users ON users.id = items.owner_id WHERE title LIKE ? OR description LIKE ?");
    $like = "%$q%";
    $stmt->execute([$like, $like]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// === Fallback ===
http_response_code(404);
echo json_encode(['error' => 'unknown endpoint']);
exit;
?>