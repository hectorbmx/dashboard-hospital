<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once '../config/db.php';

$lead_id    = (int)($_GET['lead_id'] ?? 0);
$empresa_id = (int)$_SESSION['empresa_id'];

if (!$lead_id) {
    echo json_encode(['error' => 'lead_id requerido']);
    exit;
}

$pdo = getDB();

// Datos del lead
$stmt = $pdo->prepare("
    SELECT id, nombre, whatsapp_id, semanas_embarazo, lead_temperatura,
           tipo_atencion, tipo_cobertura, fecha_contacto, requiere_humano,
           estado_conversacion, lead_status, interes_principal
    FROM leads_hospital
    WHERE id = ? AND empresa_id = ?
");
$stmt->execute([$lead_id, $empresa_id]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    echo json_encode(['error' => 'Lead no encontrado']);
    exit;
}

// Mensajes de la conversación
$stmt2 = $pdo->prepare("
    SELECT role, content, fecha, message_type
    FROM conversaciones
    WHERE lead_id = ? AND empresa_id = ?
    ORDER BY fecha ASC
");
$stmt2->execute([$lead_id, $empresa_id]);
$mensajes = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'lead'     => $lead,
    'mensajes' => $mensajes
], JSON_UNESCAPED_UNICODE);