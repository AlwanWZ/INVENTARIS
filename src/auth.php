<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function login($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];
            return true;
        }
    }
    return false;
}

function checkLogin() {
    if (!isset($_SESSION['user'])) {
        header('Location: /Inventaris/public/login.php');
        exit;
    }
}

function checkRole($role) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== $role) {
        header('Location: /Inventaris/public/login.php');
        exit;
    }
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: /Inventaris/public/login.php');
    exit;
}

// Cek apakah username sudah ada
function user_exists($username) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    return $stmt->fetch() !== false;
}

// Tambahkan user baru
function register_user($username, $password, $role) {
    global $pdo;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
    return $stmt->execute([$username, $hash, $role]);
}

// Update password user
function update_password($user_id, $new_password) {
    global $pdo;
    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    return $stmt->execute([$hash, $user_id]);
}
