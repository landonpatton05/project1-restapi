<?php
// code/src/auth.php
require_once __DIR__ . '/db.php';

function create_user($username, $password, $email = null, $role = 'user') {
    global $pdo;
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)");
    try {
        $stmt->execute([$username, $password_hash, $email, $role]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function verify_user($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return false;
}

function issue_token($user_id, $ttl_seconds = 3600) {
    global $pdo;
    $token = bin2hex(random_bytes(32)); // 64 hex chars
    $token_hash = hash('sha256', $token);
    $expires_at = (new DateTime("+{$ttl_seconds} seconds"))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $token_hash, $expires_at]);

    return ['token' => $token, 'expires_at' => $expires_at];
}

function validate_bearer($bearer) {
    global $pdo;
    if (!$bearer) return false;
    $token_hash = hash('sha256', $bearer);
    $stmt = $pdo->prepare("SELECT user_id FROM tokens WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token_hash]);
    $row = $stmt->fetch();
    if ($row) return (int)$row['user_id'];
    return false;
}