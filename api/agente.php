<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

$pdo        = getDB();
$empresa_id = (int)$_SESSION['empresa_id'];
$method     = $_SERVER['REQUEST_METHOD'];

// GET — obtener un agente por id
if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, nombre, email, rol, activo FROM users WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, $empresa_id]);
    $agente = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($agente ?: ['error' => 'No encontrado']);
    exit;
}

// POST — crear, editar o toggle
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    // Toggle activo/inactivo
    if (!empty($body['toggle'])) {
        $id     = (int)$body['id'];
        $activo = (int)$body['activo'];
        $stmt   = $pdo->prepare("UPDATE users SET activo = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$activo, $id, $empresa_id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $nombre   = trim($body['nombre'] ?? '');
    $email    = trim($body['email']  ?? '');
    $password = trim($body['password'] ?? '');
    $rol      = in_array($body['rol'] ?? '', ['admin','agente']) ? $body['rol'] : 'agente';
    $id       = (int)($body['id'] ?? 0);

    if (!$nombre || !$email) {
        echo json_encode(['error' => 'Nombre y email son obligatorios']);
        exit;
    }

    // Verificar email duplicado
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    if ($check->fetch()) {
        echo json_encode(['error' => 'El email ya está en uso']);
        exit;
    }

    if ($id) {
        // Editar
        if ($password) {
            $stmt = $pdo->prepare("UPDATE users SET nombre=?, email=?, rol=?, password=SHA2(?,256) WHERE id=? AND empresa_id=?");
            $stmt->execute([$nombre, $email, $rol, $password, $id, $empresa_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nombre=?, email=?, rol=? WHERE id=? AND empresa_id=?");
            $stmt->execute([$nombre, $email, $rol, $id, $empresa_id]);
        }
    } else {
        // Nuevo
        if (!$password) {
            echo json_encode(['error' => 'La contraseña es obligatoria para nuevos agentes']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO users (empresa_id, nombre, email, password, rol, activo) VALUES (?,?,?,SHA2(?,256),?,1)");
        $stmt->execute([$empresa_id, $nombre, $email, $password, $rol]);
    }

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Método no permitido']);