<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/db.php';
$pdo        = getDB();
$empresa_id = (int)$_SESSION['empresa_id'];

// KPIs globales
$kpis = $pdo->query("
    SELECT
        COUNT(DISTINCT e.id)          AS total_empresas,
        SUM(e.activo = 1)             AS empresas_activas,
        COUNT(DISTINCT u.id)          AS total_agentes,
        COUNT(DISTINCT l.id)          AS total_leads
    FROM empresas e
    LEFT JOIN users u          ON u.empresa_id = e.id
    LEFT JOIN leads_hospital l ON l.empresa_id = e.id
")->fetch(PDO::FETCH_ASSOC);

// Lista empresas con métricas
$empresas = $pdo->query("
    SELECT
        e.id,
        e.nombre,
        e.slug,
        e.telefono,
        e.activo,
        e.created_at,
        COUNT(DISTINCT u.id)                            AS total_agentes,
        COUNT(DISTINCT l.id)                            AS total_leads,
        SUM(l.lead_temperatura = 'Caliente')            AS leads_calientes,
        SUM(l.requiere_humano = 1)                      AS requieren_humano,
        MAX(l.ultima_actualizacion)                     AS ultima_actividad
    FROM empresas e
    LEFT JOIN users u          ON u.empresa_id = e.id
    LEFT JOIN leads_hospital l ON l.empresa_id = e.id
    GROUP BY e.id
    ORDER BY e.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Empresas — Maternity Lead Pro</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
<style>
.emp-avatar{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0}
.emp-info{display:flex;align-items:center;gap:10px}
.emp-nombre{font-weight:500;font-size:13px}
.emp-slug{font-size:11px;color:var(--muted);font-family:'DM Mono',monospace}
.badge-activo{background:rgba(34,197,94,.1);color:var(--green);border:1px solid rgba(34,197,94,.2);padding:2px 8px;border-radius:5px;font-size:10px;font-weight:500}
.badge-inactivo{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.2);padding:2px 8px;border-radius:5px;font-size:10px;font-weight:500}
.metric-num{font-family:'DM Mono',monospace;font-size:13px;font-weight:600}
.metric-num.green{color:var(--green)}
.metric-num.red{color:var(--red)}
.metric-num.yellow{color:var(--yellow)}
.action-btn{background:transparent;border:1px solid var(--border);color:var(--muted);padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px;font-family:inherit;transition:all .15s}
.action-btn:hover{border-color:var(--accent);color:var(--accent)}
.action-btn.danger:hover{border-color:var(--red);color:var(--red)}
.action-btn.success:hover{border-color:var(--green);color:var(--green)}
/* Modal detalle */
.detalle-section{margin-bottom:20px}
.detalle-title{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:10px}
.detalle-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.detalle-item{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 14px}
.detalle-label{font-size:10px;color:var(--muted);margin-bottom:4px}
.detalle-value{font-size:14px;font-weight:600;font-family:'DM Mono',monospace}
.mini-table{width:100%;border-collapse:collapse;font-size:12px}
.mini-table th{text-align:left;padding:6px 10px;font-size:10px;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)}
.mini-table td{padding:8px 10px;border-bottom:1px solid var(--border)}
.mini-table tr:last-child td{border-bottom:none}
</style>
</head>
<body>

<?php $paginaActual = 'empresas'; include 'includes/nav.php'; ?>

<main class="main">

  <div class="header">
    <div>
      <div class="header-title">Empresas</div>
      <div class="header-sub">Gestión de cuentas registradas en la plataforma</div>
    </div>
    <div class="header-actions">
      <button class="btn btn-primary" onclick="abrirModalNuevo()">+ Nueva empresa</button>
    </div>
  </div>

  <!-- KPIs -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
    <div class="kpi-card">
      <div class="kpi-label">Total empresas</div>
      <div class="kpi2-value accent"><?= (int)$kpis['total_empresas'] ?></div>
      <div class="kpi-sub">registradas</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Activas</div>
      <div class="kpi2-value green"><?= (int)$kpis['empresas_activas'] ?></div>
      <div class="kpi-sub">en operación</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Total agentes</div>
      <div class="kpi2-value" style="color:var(--text)"><?= (int)$kpis['total_agentes'] ?></div>
      <div class="kpi-sub">en todas las empresas</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Total leads</div>
      <div class="kpi2-value yellow"><?= (int)$kpis['total_leads'] ?></div>
      <div class="kpi-sub">en la plataforma</div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">Lista de empresas</div>
      <input class="search-input" placeholder="Buscar empresa…" oninput="filtrarTabla(this.value)">
    </div>
    <table>
      <thead>
        <tr>
          <th>Empresa</th>
          <th>Teléfono</th>
          <th>Estado</th>
          <th>Agentes</th>
          <th>Leads</th>
          <th>Calientes</th>
          <th>Req. humano</th>
          <th>Última actividad</th>
          <th>Registrada</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tabla-body">
        <?php foreach ($empresas as $e):
          $iniciales   = strtoupper(substr($e['nombre'], 0, 2));
          $ultimaAct   = $e['ultima_actividad'] ? date('d-M H:i', strtotime($e['ultima_actividad'])) : '—';
          $createdAt   = $e['created_at'] ? date('d-M-Y', strtotime($e['created_at'])) : '—';
        ?>
        <tr class="fila-empresa">
          <td>
            <div class="emp-info">
              <div class="emp-avatar"><?= $iniciales ?></div>
              <div>
                <div class="emp-nombre"><?= htmlspecialchars($e['nombre']) ?></div>
                <div class="emp-slug"><?= htmlspecialchars($e['slug']) ?></div>
              </div>
            </div>
          </td>
          <td style="color:var(--muted);font-size:12px"><?= htmlspecialchars($e['telefono'] ?? '—') ?></td>
          <td><span class="badge-<?= $e['activo'] ? 'activo' : 'inactivo' ?>"><?= $e['activo'] ? 'Activa' : 'Inactiva' ?></span></td>
          <td><span class="metric-num"><?= (int)$e['total_agentes'] ?></span></td>
          <td><span class="metric-num"><?= (int)$e['total_leads'] ?></span></td>
          <td><span class="metric-num yellow"><?= (int)$e['leads_calientes'] ?></span></td>
          <td><span class="metric-num red"><?= (int)$e['requieren_humano'] ?></span></td>
          <td style="color:var(--muted);font-size:11px;font-family:'DM Mono',monospace"><?= $ultimaAct ?></td>
          <td style="color:var(--muted);font-size:11px;font-family:'DM Mono',monospace"><?= $createdAt ?></td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="action-btn" onclick="verDetalle(<?= $e['id'] ?>)">🔍 Ver</button>
              <button class="action-btn" onclick="editarEmpresa(<?= $e['id'] ?>)">✏ Editar</button>
              <button class="action-btn danger" onclick="toggleEmpresa(<?= $e['id'] ?>, <?= $e['activo'] ?>)">
                <?= $e['activo'] ? '⏸ Desactivar' : '▶ Activar' ?>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</main>

<!-- ===== MODAL CREAR / EDITAR ===== -->
<div id="overlay-emp" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;backdrop-filter:blur(2px)" onclick="cerrarModalEmp()"></div>
<div id="modal-emp" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:440px;max-width:95vw;background:#181c27;border:1px solid #252b3b;border-radius:16px;z-index:1001;overflow:hidden">
  <div style="padding:20px;border-bottom:1px solid #252b3b;display:flex;justify-content:space-between;align-items:center">
    <div style="font-weight:600;font-size:15px" id="modal-emp-titulo">Nueva empresa</div>
    <button onclick="cerrarModalEmp()" style="background:transparent;border:none;color:var(--muted);font-size:20px;cursor:pointer">✕</button>
  </div>
  <div style="padding:24px">
    <input type="hidden" id="emp-id">
    <div style="margin-bottom:16px">
      <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Nombre</label>
      <input type="text" id="emp-nombre" style="width:100%;background:#1e2435;border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-family:inherit;font-size:14px;outline:none" placeholder="Hospital Maternidad">
    </div>
    <div style="margin-bottom:16px">
      <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Slug <span style="color:var(--muted);font-size:10px">(identificador único)</span></label>
      <input type="text" id="emp-slug" style="width:100%;background:#1e2435;border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-family:inherit;font-size:14px;outline:none;font-family:'DM Mono',monospace" placeholder="hospital-maternidad">
    </div>
    <div style="margin-bottom:24px">
      <label style="display:block;font-size:11px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Teléfono</label>
      <input type="text" id="emp-telefono" style="width:100%;background:#1e2435;border:1px solid var(--border);color:var(--text);padding:10px 14px;border-radius:8px;font-family:inherit;font-size:14px;outline:none" placeholder="+52 33 1234 5678">
    </div>
    <div id="modal-emp-error" style="display:none;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red);padding:10px 14px;border-radius:8px;font-size:12px;margin-bottom:16px"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="cerrarModalEmp()">Cancelar</button>
      <button class="btn btn-primary" onclick="guardarEmpresa()">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== MODAL DETALLE ===== -->
<div id="overlay-det" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;backdrop-filter:blur(2px)" onclick="cerrarDetalle()"></div>
<div id="modal-detalle" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:620px;max-width:95vw;max-height:90vh;background:#181c27;border:1px solid #252b3b;border-radius:16px;z-index:1001;overflow:hidden;flex-direction:column">
  <div style="padding:20px;border-bottom:1px solid #252b3b;display:flex;justify-content:space-between;align-items:center;background:#1e2435">
    <div>
      <div style="font-weight:600;font-size:15px" id="det-nombre">—</div>
      <div style="font-size:11px;color:var(--muted);font-family:'DM Mono',monospace" id="det-slug">—</div>
    </div>
    <button onclick="cerrarDetalle()" style="background:transparent;border:none;color:var(--muted);font-size:20px;cursor:pointer">✕</button>
  </div>
  <div style="overflow-y:auto;padding:20px;flex:1" id="det-body">
    <div style="text-align:center;color:var(--muted);padding:30px">Cargando…</div>
  </div>
</div>

<script>
// ---- Filtro ----
function filtrarTabla(val) {
  document.querySelectorAll('.fila-empresa').forEach(f => {
    const nombre = f.querySelector('.emp-nombre')?.textContent ?? '';
    const slug   = f.querySelector('.emp-slug')?.textContent ?? '';
    f.style.display = (nombre + slug).toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
  });
}

// ---- Modal crear/editar ----
function abrirModalNuevo() {
  document.getElementById('emp-id').value       = '';
  document.getElementById('emp-nombre').value   = '';
  document.getElementById('emp-slug').value     = '';
  document.getElementById('emp-telefono').value = '';
  document.getElementById('modal-emp-titulo').textContent = 'Nueva empresa';
  document.getElementById('modal-emp-error').style.display = 'none';
  document.getElementById('overlay-emp').style.display = 'block';
  document.getElementById('modal-emp').style.display    = 'block';

  // Auto-slug desde nombre
  document.getElementById('emp-nombre').oninput = function() {
    if (!document.getElementById('emp-id').value) {
      document.getElementById('emp-slug').value = this.value
        .toLowerCase().trim()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '');
    }
  };
}

function editarEmpresa(id) {
  fetch(`api/empresa.php?id=${id}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) return;
      document.getElementById('emp-id').value       = data.id;
      document.getElementById('emp-nombre').value   = data.nombre;
      document.getElementById('emp-slug').value     = data.slug;
      document.getElementById('emp-telefono').value = data.telefono ?? '';
      document.getElementById('modal-emp-titulo').textContent = 'Editar empresa';
      document.getElementById('modal-emp-error').style.display = 'none';
      document.getElementById('overlay-emp').style.display = 'block';
      document.getElementById('modal-emp').style.display    = 'block';
    });
}

function cerrarModalEmp() {
  document.getElementById('overlay-emp').style.display = 'none';
  document.getElementById('modal-emp').style.display   = 'none';
}

function guardarEmpresa() {
  const id       = document.getElementById('emp-id').value;
  const nombre   = document.getElementById('emp-nombre').value.trim();
  const slug     = document.getElementById('emp-slug').value.trim();
  const telefono = document.getElementById('emp-telefono').value.trim();
  const errDiv   = document.getElementById('modal-emp-error');

  if (!nombre || !slug) {
    errDiv.textContent = 'Nombre y slug son obligatorios';
    errDiv.style.display = 'block';
    return;
  }

  fetch('api/empresa.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, nombre, slug, telefono })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      errDiv.textContent = data.error;
      errDiv.style.display = 'block';
      return;
    }
    cerrarModalEmp();
    location.reload();
  });
}

function toggleEmpresa(id, activo) {
  const accion = activo ? 'desactivar' : 'activar';
  if (!confirm(`¿Confirmas ${accion} esta empresa?`)) return;
  fetch('api/empresa.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, toggle: true, activo: activo ? 0 : 1 })
  })
  .then(r => r.json())
  .then(data => { if (!data.error) location.reload(); });
}

// ---- Modal detalle ----
function verDetalle(id) {
  document.getElementById('det-body').innerHTML = '<div style="text-align:center;color:var(--muted);padding:30px">Cargando…</div>';
  document.getElementById('overlay-det').style.display  = 'block';
  document.getElementById('modal-detalle').style.display = 'flex';

  fetch(`api/empresa.php?id=${id}&detalle=1`)
    .then(r => r.json())
    .then(data => {
      if (data.error) return;
      document.getElementById('det-nombre').textContent = data.empresa.nombre;
      document.getElementById('det-slug').textContent   = data.empresa.slug;
      renderDetalle(data);
    });
}

function renderDetalle(data) {
  const e = data.empresa;
  const agentes = data.agentes;
  const leads   = data.leads_recientes;

  let html = `
    <div class="detalle-section">
      <div class="detalle-title">Métricas generales</div>
      <div class="detalle-grid">
        <div class="detalle-item"><div class="detalle-label">Total leads</div><div class="detalle-value">${e.total_leads ?? 0}</div></div>
        <div class="detalle-item"><div class="detalle-label">Leads calientes</div><div class="detalle-value" style="color:var(--red)">${e.leads_calientes ?? 0}</div></div>
        <div class="detalle-item"><div class="detalle-label">Agentes</div><div class="detalle-value">${agentes.length}</div></div>
        <div class="detalle-item"><div class="detalle-label">Req. humano</div><div class="detalle-value" style="color:var(--yellow)">${e.requieren_humano ?? 0}</div></div>
      </div>
    </div>

    <div class="detalle-section">
      <div class="detalle-title">Agentes (${agentes.length})</div>
      <table class="mini-table">
        <thead><tr><th>Nombre</th><th>Rol</th><th>Estado</th><th>Leads asignados</th></tr></thead>
        <tbody>
          ${agentes.length ? agentes.map(a => `
            <tr>
              <td>${a.nombre}</td>
              <td style="color:var(--muted)">${a.rol}</td>
              <td><span class="badge-${a.activo ? 'activo' : 'inactivo'}">${a.activo ? 'Activo' : 'Inactivo'}</span></td>
              <td style="font-family:'DM Mono',monospace">${a.leads_asignados ?? 0}</td>
            </tr>`).join('') : '<tr><td colspan="4" style="color:var(--muted);text-align:center;padding:16px">Sin agentes</td></tr>'}
        </tbody>
      </table>
    </div>

    <div class="detalle-section">
      <div class="detalle-title">Últimos leads</div>
      <table class="mini-table">
        <thead><tr><th>Nombre</th><th>Temperatura</th><th>Semanas</th><th>Fecha</th></tr></thead>
        <tbody>
          ${leads.length ? leads.map(l => `
            <tr>
              <td>${l.nombre ?? 'Sin nombre'}</td>
              <td><span class="badge ${l.lead_temperatura?.toLowerCase()}">${l.lead_temperatura ?? '—'}</span></td>
              <td style="font-family:'DM Mono',monospace">${l.semanas_embarazo ? l.semanas_embarazo + ' sem' : '—'}</td>
              <td style="color:var(--muted);font-size:11px">${l.fecha_contacto?.slice(0,10) ?? '—'}</td>
            </tr>`).join('') : '<tr><td colspan="4" style="color:var(--muted);text-align:center;padding:16px">Sin leads</td></tr>'}
        </tbody>
      </table>
    </div>`;

  document.getElementById('det-body').innerHTML = html;
}

function cerrarDetalle() {
  document.getElementById('overlay-det').style.display   = 'none';
  document.getElementById('modal-detalle').style.display = 'none';
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { cerrarModalEmp(); cerrarDetalle(); }
});
</script>
</body>
</html>