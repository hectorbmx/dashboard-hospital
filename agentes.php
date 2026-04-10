<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';
$pdo        = getDB();
$empresa_id = (int)$_SESSION['empresa_id'];

// KPIs
$kpis = $pdo->prepare("
    SELECT
        COUNT(*)                                        AS total_agentes,
        SUM(activo = 1)                                 AS agentes_activos,
        SUM(activo = 0)                                 AS agentes_inactivos
    FROM users
    WHERE empresa_id = ? AND rol = 'agente'
");
$kpis->execute([$empresa_id]);
$k = $kpis->fetch(PDO::FETCH_ASSOC);

// Leads sin asignar que requieren humano
$sinAsignar = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM leads_hospital
    WHERE empresa_id = ? AND requiere_humano = 1 AND agente_id IS NULL
");
$sinAsignar->execute([$empresa_id]);
$leadsSinAsignar = $sinAsignar->fetchColumn();

// Lista de agentes con métricas
$agentes = $pdo->prepare("
    SELECT
        u.id,
        u.nombre,
        u.email,
        u.rol,
        u.activo,
        u.created_at,
        COUNT(l.id)                                         AS leads_asignados,
        SUM(l.lead_status = 'cerrado')                      AS leads_cerrados,
        SUM(l.lead_temperatura = 'Caliente')                AS leads_calientes,
        SUM(l.requiere_humano = 1)                          AS pendientes,
        MAX(l.asignado_en)                                  AS ultimo_asignado
    FROM users u
    LEFT JOIN leads_hospital l ON l.agente_id = u.id AND l.empresa_id = ?
    WHERE u.empresa_id = ?
    GROUP BY u.id
    ORDER BY leads_asignados DESC
");
$agentes->execute([$empresa_id, $empresa_id]);
$listaAgentes = $agentes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agentes — Maternity Lead Pro</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
<style>
.avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff;flex-shrink:0}
.agente-info{display:flex;align-items:center;gap:10px}
.agente-nombre{font-weight:500;font-size:13px}
.agente-email{font-size:11px;color:var(--muted)}
.badge-rol-admin{background:rgba(124,95,247,.15);color:var(--accent2);border:1px solid rgba(124,95,247,.2);padding:2px 8px;border-radius:5px;font-size:10px;font-weight:500}
.badge-rol-agente{background:rgba(79,142,247,.1);color:var(--accent);border:1px solid rgba(79,142,247,.2);padding:2px 8px;border-radius:5px;font-size:10px;font-weight:500}
.badge-activo{background:rgba(34,197,94,.1);color:var(--green);border:1px solid rgba(34,197,94,.2);padding:2px 8px;border-radius:5px;font-size:10px;font-weight:500}
.badge-inactivo{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2);padding:2px 8px;border-radius:5px;font-size:10px;font-weight:500}
.metric-num{font-family:'DM Mono',monospace;font-size:13px;font-weight:600}
.metric-num.green{color:var(--green)}
.metric-num.red{color:var(--red)}
.metric-num.yellow{color:var(--yellow)}
.progress-bar{height:4px;background:var(--surface2);border-radius:99px;overflow:hidden;margin-top:4px;width:80px}
.progress-fill{height:100%;border-radius:99px;background:var(--accent)}
.action-btn{background:transparent;border:1px solid var(--border);color:var(--muted);padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px;font-family:inherit;transition:all .15s}
.action-btn:hover{border-color:var(--accent);color:var(--accent)}
.action-btn.danger:hover{border-color:var(--red);color:var(--red)}
</style>
</head>
<body>

<?php $paginaActual = 'agentes'; include 'includes/nav.php'; ?>

<main class="main">

  <div class="header">
    <div>
      <div class="header-title">Gestión de agentes</div>
      <div class="header-sub">Equipo de atención — <?= htmlspecialchars($_SESSION['empresa_nombre'] ?? '') ?></div>
    </div>
    <div class="header-actions">
      <button class="btn btn-primary" onclick="abrirModalNuevo()">+ Nuevo agente</button>
    </div>
  </div>

  <!-- KPIs -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
    <div class="kpi-card">
      <div class="kpi-label">Total agentes</div>
      <div class="kpi2-value accent"><?= (int)$k['total_agentes'] ?></div>
      <div class="kpi-sub">en el equipo</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Activos</div>
      <div class="kpi2-value green"><?= (int)$k['agentes_activos'] ?></div>
      <div class="kpi-sub">disponibles</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Inactivos</div>
      <div class="kpi2-value" style="color:var(--muted)"><?= (int)$k['agentes_inactivos'] ?></div>
      <div class="kpi-sub">sin acceso</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Sin asignar</div>
      <div class="kpi2-value red"><?= (int)$leadsSinAsignar ?></div>
      <div class="kpi-sub">leads requieren humano</div>
    </div>
  </div>

  <!-- Tabla agentes -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">Equipo de agentes</div>
      <input class="search-input" placeholder="Buscar agente…" oninput="filtrarTabla(this.value)">
    </div>
    <table id="tabla-agentes">
      <thead>
        <tr>
          <th>Agente</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Asignados</th>
          <th>Cerrados</th>
          <th>Tasa cierre</th>
          <th>Calientes</th>
          <th>Pendientes</th>
          <th>Último asignado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($listaAgentes as $a):
          $asignados = (int)$a['leads_asignados'];
          $cerrados  = (int)$a['leads_cerrados'];
          $tasa      = $asignados > 0 ? round(($cerrados / $asignados) * 100) : 0;
          $iniciales = implode('', array_map(fn($p) => strtoupper($p[0]), array_slice(explode(' ', $a['nombre']), 0, 2)));
          $ultimoAsig = $a['ultimo_asignado'] ? date('d-M H:i', strtotime($a['ultimo_asignado'])) : '—';
        ?>
        <tr class="fila-agente">
          <td>
            <div class="agente-info">
              <div class="avatar"><?= $iniciales ?></div>
              <div>
                <div class="agente-nombre"><?= htmlspecialchars($a['nombre']) ?></div>
                <div class="agente-email"><?= htmlspecialchars($a['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge-rol-<?= $a['rol'] ?>"><?= ucfirst($a['rol']) ?></span>
          </td>
          <td>
            <span class="badge-<?= $a['activo'] ? 'activo' : 'inactivo' ?>">
              <?= $a['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td><span class="metric-num"><?= $asignados ?></span></td>
          <td><span class="metric-num green"><?= $cerrados ?></span></td>
          <td>
            <span class="metric-num <?= $tasa >= 50 ? 'green' : ($tasa >= 25 ? 'yellow' : 'red') ?>">
              <?= $tasa ?>%
            </span>
            <div class="progress-bar">
              <div class="progress-fill" style="width:<?= $tasa ?>%"></div>
            </div>
          </td>
          <td><span class="metric-num yellow"><?= (int)$a['leads_calientes'] ?></span></td>
          <td><span class="metric-num red"><?= (int)$a['pendientes'] ?></span></td>
          <td style="color:var(--muted);font-size:11px;font-family:'DM Mono',monospace"><?= $ultimoAsig ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="action-btn" onclick="editarAgente(<?= $a['id'] ?>)">✏ Editar</button>
              <button class="action-btn danger" onclick="toggleAgente(<?= $a['id'] ?>, <?= $a['activo'] ?>)">
                <?= $a['activo'] ? '⏸ Desactivar' : '▶ Activar' ?>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</main>

<!-- Modal nuevo/editar agente -->
<div id="modal-overlay-ag" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;backdrop-filter:blur(2px)" onclick="cerrarModalAg()"></div>

<div id="modal-agente" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;max-width:95vw;background:#181c27;border:1px solid #252b3b;border-radius:16px;z-index:1001;overflow:hidden">
  <div style="padding:20px;border-bottom:1px solid #252b3b;display:flex;justify-content:space-between;align-items:center">
    <div style="font-weight:600;font-size:15px" id="modal-ag-titulo">Nuevo agente</div>
    <button onclick="cerrarModalAg()" style="background:transparent;border:none;color:var(--muted);font-size:20px;cursor:pointer">✕</button>
  </div>
  <div style="padding:24px">
    <input type="hidden" id="ag-id">
    <div class="field" style="margin-bottom:16px">
      <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Nombre completo</label>
      <input type="text" id="ag-nombre" style="width:100%;background:#1e2435;border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-family:inherit;font-size:14px;outline:none" placeholder="Ej: Laura Mendoza">
    </div>
    <div class="field" style="margin-bottom:16px">
      <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Email</label>
      <input type="email" id="ag-email" style="width:100%;background:#1e2435;border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-family:inherit;font-size:14px;outline:none" placeholder="agente@hospital.com">
    </div>
    <div class="field" style="margin-bottom:16px">
      <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Contraseña <span id="pass-hint" style="color:var(--muted);font-size:10px">(dejar vacío para no cambiar)</span></label>
      <input type="password" id="ag-password" style="width:100%;background:#1e2435;border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-family:inherit;font-size:14px;outline:none" placeholder="••••••••">
    </div>
    <div class="field" style="margin-bottom:24px">
      <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Rol</label>
      <select id="ag-rol" style="width:100%;background:#1e2435;border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-family:inherit;font-size:14px;outline:none">
        <option value="agente">Agente</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div id="modal-ag-error" style="display:none;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red);padding:10px 14px;border-radius:8px;font-size:12px;margin-bottom:16px"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="cerrarModalAg()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarAgente()">Guardar</button>
    </div>
  </div>
</div>

<script>
function filtrarTabla(val) {
  const filas = document.querySelectorAll('.fila-agente');
  filas.forEach(f => {
    f.style.display = f.textContent.toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
  });
}

function abrirModalNuevo() {
  document.getElementById('ag-id').value       = '';
  document.getElementById('ag-nombre').value   = '';
  document.getElementById('ag-email').value    = '';
  document.getElementById('ag-password').value = '';
  document.getElementById('ag-rol').value      = 'agente';
  document.getElementById('modal-ag-titulo').textContent = 'Nuevo agente';
  document.getElementById('pass-hint').style.display = 'none';
  document.getElementById('modal-ag-error').style.display = 'none';
  document.getElementById('modal-overlay-ag').style.display = 'block';
  document.getElementById('modal-agente').style.display = 'block';
}

function editarAgente(id) {
  fetch(`api/agente.php?id=${id}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) return;
      document.getElementById('ag-id').value     = data.id;
      document.getElementById('ag-nombre').value = data.nombre;
      document.getElementById('ag-email').value  = data.email;
      document.getElementById('ag-rol').value    = data.rol;
      document.getElementById('ag-password').value = '';
      document.getElementById('modal-ag-titulo').textContent = 'Editar agente';
      document.getElementById('pass-hint').style.display = 'inline';
      document.getElementById('modal-ag-error').style.display = 'none';
      document.getElementById('modal-overlay-ag').style.display = 'block';
      document.getElementById('modal-agente').style.display = 'block';
    });
}

function cerrarModalAg() {
  document.getElementById('modal-overlay-ag').style.display = 'none';
  document.getElementById('modal-agente').style.display     = 'none';
}

function guardarAgente() {
  const id       = document.getElementById('ag-id').value;
  const nombre   = document.getElementById('ag-nombre').value.trim();
  const email    = document.getElementById('ag-email').value.trim();
  const password = document.getElementById('ag-password').value;
  const rol      = document.getElementById('ag-rol').value;
  const errDiv   = document.getElementById('modal-ag-error');

  if (!nombre || !email) {
    errDiv.textContent = 'Nombre y email son obligatorios';
    errDiv.style.display = 'block';
    return;
  }

  fetch('api/agente.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, nombre, email, password, rol })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      errDiv.textContent = data.error;
      errDiv.style.display = 'block';
      return;
    }
    cerrarModalAg();
    location.reload();
  });
}

function toggleAgente(id, activo) {
  const accion = activo ? 'desactivar' : 'activar';
  if (!confirm(`¿Confirmas ${accion} este agente?`)) return;
  fetch('api/agente.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, toggle: true, activo: activo ? 0 : 1 })
  })
  .then(r => r.json())
  .then(data => {
    if (!data.error) location.reload();
  });
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModalAg(); });
</script>
</body>
</html>