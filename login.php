<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = hash('sha256', $_POST['password']);
    
    $stmt = $pdo->prepare("SELECT u.*, e.nombre as empresa_nombre FROM users u JOIN empresas e ON u.empresa_id = e.id WHERE u.email = ? AND u.password = ? AND u.activo = 1");
    $stmt->execute([$email, $password]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['empresa_id'] = $user['empresa_id'];
        $_SESSION['empresa_nombre'] = $user['empresa_nombre'];
        $_SESSION['rol'] = $user['rol'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Credenciales incorrectas';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maternity Lead Pro — Login</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#0f1117;--surface:#181c27;--border:#252b3b;--text:#e8eaf0;--muted:#6b7280;--accent:#4f8ef7;--accent2:#7c5ff7;--red:#ef4444}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:40px;width:100%;max-width:400px}
.logo{width:48px;height:48px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:14px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;color:#fff;margin:0 auto 20px}
h1{font-size:20px;font-weight:600;text-align:center;margin-bottom:6px}
.sub{color:var(--muted);font-size:13px;text-align:center;margin-bottom:28px}
.field{margin-bottom:16px}
label{display:block;font-size:12px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
input{width:100%;background:#1e2435;border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-family:inherit;font-size:14px;outline:none;transition:border .15s}
input:focus{border-color:var(--accent)}
.btn{width:100%;background:var(--accent);color:#fff;border:none;padding:12px;border-radius:8px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;margin-top:8px;transition:background .15s}
.btn:hover{background:#3d7be8}
.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">ML</div>
  <h1>Maternity Lead Pro</h1>
  <div class="sub">Ingresa a tu panel de control</div>
  <?php if($error): ?>
  <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="field">
      <label>Email</label>
      <input type="email" name="email" placeholder="tu@email.com" required>
    </div>
    <div class="field">
      <label>Contraseña</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button class="btn" type="submit">Entrar</button>
  </form>
</div>
</body>
</html>