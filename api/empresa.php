<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';
$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// GET — obtener empresa (simple o con detalle)
if ($method === 'GET') {
    $id      = (int)($_GET['id'] ?? 0);
    $detalle = !empty($_GET['detalle']);

    $stmt = $pdo->prepare("
        SELECT e.*,
            COUNT(DISTINCT l.id)                         AS total_leads,
            SUM(l.lead_temperatura = 'Caliente')         AS leads_calientes,
            SUM(l.requiere_humano = 1)                   AS requieren_humano
        FROM empresas e
        LEFT JOIN leads_hospital l ON l.empresa_id = e.id
        WHERE e.id = ?
        GROUP BY e.id
    ");
    $stmt->execute([$id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        echo json_encode(['error' => 'No encontrada']);
        exit;
    }

    if (!$detalle) {
        echo json_encode($empresa);
        exit;
    }

    // Agentes de la empresa
    $agentes = $pdo->prepare("
        SELECT u.id, u.nombre, u.email, u.rol, u.activo,
               COUNT(l.id) AS leads_asignados
        FROM users u
        LEFT JOIN leads_hospital l ON l.agente_id = u.id
        WHERE u.empresa_id = ?
        GROUP BY u.id
        ORDER BY u.nombre
    ");
    $agentes->execute([$id]);

    // Últimos 5 leads
    $leads = $pdo->prepare("
        SELECT nombre, lead_temperatura, semanas_embarazo, fecha_contacto
        FROM leads_hospital
        WHERE empresa_id = ?
        ORDER BY fecha_contacto DESC
        LIMIT 5
    ");
    $leads->execute([$id]);

    echo json_encode([
        'empresa' => $empresa,
        'agentes' => $agentes->fetchAll(PDO::FETCH_ASSOC),
        'leads_recientes' => $leads->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST — crear, editar o toggle
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    // Toggle activo
    if (!empty($body['toggle'])) {
        $stmt = $pdo->prepare("UPDATE empresas SET activo = ? WHERE id = ?");
        $stmt->execute([(int)$body['activo'], (int)$body['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $nombre   = trim($body['nombre']   ?? '');
    $slug     = trim($body['slug']     ?? '');
    $telefono = trim($body['telefono'] ?? '');
    $id       = (int)($body['id'] ?? 0);

    if (!$nombre || !$slug) {
        echo json_encode(['error' => 'Nombre y slug son obligatorios']);
        exit;
    }

    // Slug único
    $check = $pdo->prepare("SELECT id FROM empresas WHERE slug = ? AND id != ?");
    $check->execute([$slug, $id]);
    if ($check->fetch()) {
        echo json_encode(['error' => 'El slug ya está en uso']);
        exit;
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE empresas SET nombre=?, slug=?, telefono=? WHERE id=?");
        $stmt->execute([$nombre, $slug, $telefono ?: null, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO empresas (nombre, slug, telefono, activo) VALUES (?,?,?,1)");
        $stmt->execute([$nombre, $slug, $telefono ?: null]);
    }

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Método no permitido']);