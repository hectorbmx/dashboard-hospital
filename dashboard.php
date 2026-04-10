<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';
$pdo = getDB();
$empresa_id = $_SESSION['empresa_id'];

// KPIs principales
$kpis = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(lead_temperatura = 'Caliente') as calientes,
        SUM(lead_temperatura = 'Tibio') as tibios,
        SUM(lead_temperatura = 'Frio') as frios,
        SUM(requiere_humano = 1) as requieren_humano,
        SUM(conversacion_activa = 1) as activos
    FROM leads_hospital
    WHERE empresa_id = ?
");
$kpis->execute([$empresa_id]);
$k = $kpis->fetch(PDO::FETCH_ASSOC);

// Leads recientes
$recientes = $pdo->prepare("
    SELECT 
        id, nombre, whatsapp_id, fecha_contacto,
        semanas_embarazo, tipo_atencion, tipo_cobertura,
        datos_completos, lead_temperatura, requiere_humano,
        ultima_actualizacion
    FROM leads_hospital
    WHERE empresa_id = ?
    ORDER BY ultima_actualizacion DESC
    LIMIT 50
");
$recientes->execute([$empresa_id]);
$leadsDB = $recientes->fetchAll(PDO::FETCH_ASSOC);

// Distribución tipo de parto
$partos = $pdo->prepare("
    SELECT COALESCE(tipo_atencion, 'Sin capturar') as tipo_atencion, COUNT(*) as total
    FROM leads_hospital
    WHERE empresa_id = ?
    GROUP BY tipo_atencion
");
$partos->execute([$empresa_id]);
$distPartos = $partos->fetchAll(PDO::FETCH_ASSOC);

// Distribución cobertura
$coberturas = $pdo->prepare("
    SELECT COALESCE(tipo_cobertura, 'Sin capturar') as tipo_cobertura, COUNT(*) as total
    FROM leads_hospital
    WHERE empresa_id = ?
    GROUP BY tipo_cobertura
");
$coberturas->execute([$empresa_id]);
$distCobertura = $coberturas->fetchAll(PDO::FETCH_ASSOC);

// Distribución semanas embarazo
$semanas = $pdo->prepare("
    SELECT 
        SUM(semanas_embarazo BETWEEN 1 AND 12) as primer_trimestre,
        SUM(semanas_embarazo BETWEEN 13 AND 26) as segundo_trimestre,
        SUM(semanas_embarazo BETWEEN 27 AND 40) as tercer_trimestre,
        SUM(semanas_embarazo IS NULL) as sin_capturar
    FROM leads_hospital
    WHERE empresa_id = ?
");
$semanas->execute([$empresa_id]);
$distSemanas = $semanas->fetch(PDO::FETCH_ASSOC);

// Total para calcular porcentajes en barras
$totalPartos  = array_sum(array_column($distPartos, 'total')) ?: 1;
$totalCob     = array_sum(array_column($distCobertura, 'total')) ?: 1;
$totalSem     = ($distSemanas['primer_trimestre'] + $distSemanas['segundo_trimestre'] + $distSemanas['tercer_trimestre'] + $distSemanas['sin_capturar']) ?: 1;

// Preparar leads para JS
$leadsJS = array_map(function($l) {
    return [
        'id'         => 'L' . str_pad($l['id'], 3, '0', STR_PAD_LEFT),
        'fecha'      => date('d-M', strtotime($l['fecha_contacto'])),
        'nombre'     => $l['nombre'] ?? 'Sin nombre',
        'semanas'    => $l['semanas_embarazo'],
        'parto'      => $l['tipo_atencion'] ?? '—',
        'servicio'   => $l['tipo_cobertura'] ?? '—',
        'datos'      => (int)$l['datos_completos'],
        'temp'       => $l['lead_temperatura'] ?? 'Frio',
        'contactado' => $l['requiere_humano'] ? 'Sí' : 'No',
        'tresp'      => '—'
    ];
}, $leadsDB);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maternity Lead Pro — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#0f1117;--surface:#181c27;--surface2:#1e2435;--border:#252b3b;--text:#e8eaf0;--muted:#6b7280;--accent:#4f8ef7;--accent2:#7c5ff7;--green:#22c55e;--yellow:#f59e0b;--red:#ef4444;--caliente:#ef4444;--tibio:#f59e0b;--frio:#4f8ef7}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:14px}
.sidebar{width:220px;min-height:100vh;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:24px 0;position:fixed;top:0;left:0;bottom:0}
.sidebar-logo{padding:0 20px 28px;border-bottom:1px solid var(--border);margin-bottom:20px}
.logo-avatar{width:36px;height:36px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;margin-bottom:10px}
.logo-name{font-weight:600;font-size:13px;color:var(--text)}.logo-sub{font-size:11px;color:var(--muted);margin-top:2px}
.nav-section{padding:0 12px;margin-bottom:24px}
.nav-label{font-size:10px;color:var(--muted);letter-spacing:.1em;text-transform:uppercase;padding:0 8px;margin-bottom:6px}
.nav-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;color:var(--muted);cursor:pointer;font-size:13px;transition:all .15s;margin-bottom:2px;text-decoration:none}
.nav-item:hover{background:var(--surface2);color:var(--text)}.nav-item.active{background:var(--accent);color:#fff}
.sidebar-footer{margin-top:auto;padding:0 12px}
.logout-btn{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;color:var(--muted);font-size:12px;cursor:pointer;width:100%;background:transparent;border:none;font-family:inherit;transition:all .15s}
.logout-btn:hover{background:rgba(239,68,68,.1);color:var(--red)}
.user-info{padding:0 20px;margin-bottom:16px;font-size:11px;color:var(--muted)}
.user-name{font-size:12px;font-weight:600;color:var(--text);margin-bottom:2px}
.empresa-selector{margin:0 12px 20px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:10px 12px}
.empresa-name{font-weight:600;color:var(--text);font-size:12px}.empresa-type{color:var(--muted);font-size:11px}
.main{margin-left:220px;flex:1;padding:28px 32px}
.header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:28px}
.header-title{font-size:22px;font-weight:600;letter-spacing:-.3px}.header-sub{color:var(--muted);font-size:13px;margin-top:3px}
.header-actions{display:flex;gap:10px}
.btn{padding:8px 16px;border-radius:8px;border:none;font-family:inherit;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--muted)}.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:#3d7be8}
.kpi-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:14px;margin-bottom:20px}
.kpi-grid2{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.kpi-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px}
.kpi-card.featured{background:linear-gradient(135deg,#1a2544,#1e2d5a);border-color:var(--accent)}
.kpi-label{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px}
.kpi-value{font-size:32px;font-weight:600;letter-spacing:-1px;font-family:'DM Mono',monospace}
.kpi-card.featured .kpi-value{color:#fff}
.kpi-sub{font-size:11px;color:var(--muted);margin-top:4px}
.kpi2-value{font-size:26px;font-weight:600;font-family:'DM Mono',monospace}
.kpi2-value.yellow{color:var(--yellow)}.kpi2-value.green{color:var(--green)}.kpi2-value.accent{color:var(--accent)}.kpi2-value.red{color:var(--red)}
.charts-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:24px}
.chart-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px}
.chart-title{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:16px}
.bar-row{margin-bottom:12px}.bar-meta{display:flex;justify-content:space-between;margin-bottom:5px;font-size:12px}
.bar-label{color:var(--text)}.bar-count{color:var(--muted);font-family:'DM Mono',monospace;font-size:11px}
.bar-track{height:6px;background:var(--surface2);border-radius:99px;overflow:hidden}
.bar-fill{height:100%;border-radius:99px;transition:width .6s ease}
.bar-fill.blue{background:var(--accent)}.bar-fill.green{background:var(--green)}.bar-fill.yellow{background:var(--yellow)}.bar-fill.purple{background:var(--accent2)}.bar-fill.muted{background:var(--border)}.bar-fill.red{background:var(--red)}
.table-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden}
.table-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.table-title{font-size:13px;font-weight:600}.table-filters{display:flex;gap:8px;flex-wrap:wrap}
.filter-btn{padding:5px 12px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11px;font-family:inherit;cursor:pointer;transition:all .15s}
.filter-btn.active{background:var(--accent);border-color:var(--accent);color:#fff}.filter-btn:hover:not(.active){border-color:var(--accent);color:var(--accent)}
.search-input{background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:5px 12px;border-radius:6px;font-family:inherit;font-size:11px;outline:none;width:160px}
.search-input::placeholder{color:var(--muted)}
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:10px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);border-bottom:1px solid var(--border);font-weight:500}
td{padding:11px 14px;border-bottom:1px solid var(--border);font-size:12px}
tr:last-child td{border-bottom:none}tr:hover td{background:var(--surface2)}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:5px;font-size:10px;font-weight:500}
.badge.caliente{background:rgba(239,68,68,.15);color:var(--caliente)}.badge.tibio{background:rgba(245,158,11,.15);color:var(--tibio)}.badge.frio{background:rgba(79,142,247,.15);color:var(--frio)}
.badge.si{background:rgba(34,197,94,.15);color:var(--green)}.badge.no{background:rgba(239,68,68,.1);color:var(--red)}
.dots{display:flex;gap:3px}.dot{width:7px;height:7px;border-radius:50%}
.dot.filled.c{background:var(--caliente)}.dot.filled.t{background:var(--tibio)}.dot.filled.f{background:var(--accent)}.dot.empty{background:var(--border)}
.id-cell{font-family:'DM Mono',monospace;color:var(--muted);font-size:11px}
.sem-tag{background:var(--surface2);border:1px solid var(--border);padding:2px 7px;border-radius:5px;font-size:10px;font-family:'DM Mono',monospace}
.dash{color:var(--border)}
.pagination{padding:14px 20px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border)}
.page-info{font-size:11px;color:var(--muted)}.page-btns{display:flex;gap:4px}
.page-btn{width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--muted);font-size:11px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;font-family:'DM Mono',monospace}
.page-btn.active{background:var(--accent);border-color:var(--accent);color:#fff}.page-btn:hover:not(.active){border-color:var(--accent);color:var(--accent)}
.loading{position:fixed;inset:0;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:999;transition:opacity .4s}
.loading.hidden{opacity:0;pointer-events:none}
.loading-logo{width:52px;height:52px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;margin-bottom:16px;animation:pulse 1.5s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.05);opacity:.8}}
.loading-text{color:var(--muted);font-size:13px}
.alerta-banner{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:var(--red);padding:8px 16px;border-radius:8px;font-size:12px;font-weight:500;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.empty-state{padding:40px;text-align:center;color:var(--muted);font-size:13px}
</style>
</head>
<body>
<div class="loading" id="loader"><div class="loading-logo">ML</div><div class="loading-text">Cargando dashboard…</div></div>

<nav class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-avatar">ML</div>
    <div class="logo-name">Maternity Lead Pro</div>
    <div class="logo-sub">Panel de control</div>
  </div>
  <div class="user-info">
    <div class="user-name"><?= htmlspecialchars($_SESSION['nombre']) ?></div>
    <div><?= htmlspecialchars($_SESSION['empresa_nombre'] ?? '') ?></div>
  </div>
  <div class="nav-section">
    <div class="nav-label">Principal</div>
    <a class="nav-item active" href="dashboard.php">⬛ Dashboard</a>
    <a class="nav-item" href="#">👥 Leads</a>
    <a class="nav-item" href="#">🤖 Chatbot</a>
  </div>
  <div class="nav-section">
    <div class="nav-label">Seguimiento</div>
    <a class="nav-item" href="#">📅 Agenda</a>
    <a class="nav-item" href="#">🧑‍💼 Agentes</a>
  </div>
  <div class="nav-section">
    <div class="nav-label">Reportes</div>
    <a class="nav-item" href="#">📊 Analítica</a>
    <a class="nav-item" href="#">📣 Campañas</a>
  </div>
  <div class="sidebar-footer">
    <form method="POST" action="logout.php">
      <button class="logout-btn" type="submit">⏏ Cerrar sesión</button>
    </form>
  </div>
</nav>

<main class="main">

  <?php if ($k['requieren_humano'] > 0): ?>
  <div class="alerta-banner">
    🔔 <?= $k['requieren_humano'] ?> lead<?= $k['requieren_humano'] > 1 ? 's requieren' : ' requiere' ?> atención humana
  </div>
  <?php endif; ?>

  <div class="header">
    <div>
      <div class="header-title">Panel de control de leads</div>
      <div class="header-sub">Resumen operativo — <?= date('M Y') ?></div>
    </div>
    <div class="header-actions">
      <button class="btn btn-outline" onclick="exportarCSV()">Exportar CSV</button>
    </div>
  </div>

  <!-- KPIs fila 1 -->
  <div class="kpi-grid">
    <div class="kpi-card featured">
      <div class="kpi-label">Total Leads</div>
      <div class="kpi-value"><?= number_format($k['total']) ?></div>
      <div class="kpi-sub"><?= $k['activos'] ?> conversaciones activas</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Calientes</div>
      <div class="kpi-value" style="color:var(--caliente)"><?= (int)$k['calientes'] ?></div>
      <div class="kpi-sub">3 datos completos</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Tibios</div>
      <div class="kpi-value" style="color:var(--tibio)"><?= (int)$k['tibios'] ?></div>
      <div class="kpi-sub">1–2 datos</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Fríos</div>
      <div class="kpi-value" style="color:var(--frio)"><?= (int)$k['frios'] ?></div>
      <div class="kpi-sub">sin datos</div>
    </div>
  </div>

  <!-- KPIs fila 2 -->
  <div class="kpi-grid2">
    <div class="kpi-card">
      <div class="kpi-label">Requieren humano</div>
      <div class="kpi2-value red"><?= (int)$k['requieren_humano'] ?></div>
      <div class="kpi-sub">pendientes de agente</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Activos ahora</div>
      <div class="kpi2-value green"><?= (int)$k['activos'] ?></div>
      <div class="kpi-sub">conversación viva</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Tasa calientes</div>
      <div class="kpi2-value accent"><?= $k['total'] > 0 ? number_format(($k['calientes']/$k['total'])*100, 1) : '0' ?>%</div>
      <div class="kpi-sub">del total de leads</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Cerrados</div>
      <div class="kpi2-value"><?= $k['total'] - (int)$k['activos'] ?></div>
      <div class="kpi-sub">conversación cerrada</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="charts-row">

    <!-- Tipo de parto -->
    <div class="chart-card">
      <div class="chart-title">Tipo de atención</div>
      <?php
        $coloresParto = ['blue','green','yellow','purple','red','muted'];
        $i = 0;
        foreach ($distPartos as $p):
          $pct = round(($p['total'] / $totalPartos) * 100);
          $color = $coloresParto[$i % count($coloresParto)];
          $i++;
      ?>
      <div class="bar-row">
        <div class="bar-meta">
          <span class="bar-label"><?= htmlspecialchars($p['tipo_atencion']) ?></span>
          <span class="bar-count"><?= $p['total'] ?></span>
        </div>
        <div class="bar-track"><div class="bar-fill <?= $color ?>" style="width:<?= $pct ?>%"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($distPartos)): ?><div class="empty-state">Sin datos aún</div><?php endif; ?>
    </div>

    <!-- Tipo de cobertura -->
    <div class="chart-card">
      <div class="chart-title">Tipo de cobertura</div>
      <?php
        $coloresCob = ['green','yellow','blue','purple','muted'];
        $i = 0;
        foreach ($distCobertura as $c):
          $pct = round(($c['total'] / $totalCob) * 100);
          $color = $coloresCob[$i % count($coloresCob)];
          $i++;
      ?>
      <div class="bar-row">
        <div class="bar-meta">
          <span class="bar-label"><?= htmlspecialchars($c['tipo_cobertura']) ?></span>
          <span class="bar-count"><?= $c['total'] ?></span>
        </div>
        <div class="bar-track"><div class="bar-fill <?= $color ?>" style="width:<?= $pct ?>%"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($distCobertura)): ?><div class="empty-state">Sin datos aún</div><?php endif; ?>
    </div>

    <!-- Semanas de embarazo -->
    <div class="chart-card">
      <div class="chart-title">Semanas de embarazo</div>
      <?php
        $semData = [
          '1–12 sem'      => ['val' => (int)$distSemanas['primer_trimestre'],  'color' => 'blue'],
          '13–26 sem'     => ['val' => (int)$distSemanas['segundo_trimestre'], 'color' => 'accent'],
          '27–40 sem'     => ['val' => (int)$distSemanas['tercer_trimestre'],  'color' => 'green'],
          'Sin capturar'  => ['val' => (int)$distSemanas['sin_capturar'],      'color' => 'muted'],
        ];
        foreach ($semData as $label => $s):
          $pct = round(($s['val'] / $totalSem) * 100);
      ?>
      <div class="bar-row">
        <div class="bar-meta">
          <span class="bar-label"><?= $label ?></span>
          <span class="bar-count"><?= $s['val'] ?></span>
        </div>
        <div class="bar-track"><div class="bar-fill <?= $s['color'] ?>" style="width:<?= $pct ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- Tabla de leads -->
  <div class="table-card">
    <div class="table-header">
      <div class="table-title">Leads recientes</div>
      <div class="table-filters">
        <button class="filter-btn active" onclick="filtrar(this,'todos')">Todos</button>
        <button class="filter-btn" onclick="filtrar(this,'Caliente')">Caliente</button>
        <button class="filter-btn" onclick="filtrar(this,'Tibio')">Tibio</button>
        <button class="filter-btn" onclick="filtrar(this,'Frio')">Frío</button>
        <button class="filter-btn" onclick="filtrar(this,'sin')">Sin atender</button>
        <input class="search-input" placeholder="Buscar nombre o ID…" oninput="buscar(this.value)">
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>#ID</th><th>Fecha</th><th>Nombre</th><th>Semanas</th>
          <th>Atención</th><th>Cobertura</th><th>Datos</th>
          <th>Lead</th><th>Humano</th>
        </tr>
      </thead>
      <tbody id="tabla-body"></tbody>
    </table>
    <div class="pagination">
      <div class="page-info" id="page-info"></div>
      <div class="page-btns" id="page-btns"></div>
    </div>
  </div>

</main>

<script>
const leads = <?= json_encode($leadsJS, JSON_UNESCAPED_UNICODE) ?>;
let filtroActivo = 'todos', busqueda = '', paginaActual = 1;
const POR_PAGINA = 10;

function tempClass(t) {
  return t === 'Caliente' ? 'caliente' : t === 'Tibio' ? 'tibio' : 'frio';
}
function dotsHtml(n, t) {
  const c = tempClass(t);
  let h = '<div class="dots">';
  for (let i = 0; i < 3; i++) h += `<div class="dot ${i < n ? 'filled ' + c : 'empty'}"></div>`;
  return h + '</div>';
}

function renderTabla() {
  let data = leads.filter(l => {
    if (filtroActivo === 'sin') return l.contactado === 'Sí'; // requiere humano
    if (filtroActivo !== 'todos') return l.temp === filtroActivo;
    return true;
  }).filter(l => !busqueda ||
    l.nombre.toLowerCase().includes(busqueda.toLowerCase()) ||
    l.id.toLowerCase().includes(busqueda.toLowerCase())
  );

  const total = data.length;
  const pages = Math.ceil(total / POR_PAGINA);
  const slice = data.slice((paginaActual - 1) * POR_PAGINA, paginaActual * POR_PAGINA);

  document.getElementById('page-info').textContent =
    total === 0 ? 'Sin resultados' :
    `Mostrando ${(paginaActual - 1) * POR_PAGINA + 1}–${Math.min(paginaActual * POR_PAGINA, total)} de ${total}`;

  document.getElementById('tabla-body').innerHTML = slice.length === 0
    ? '<tr><td colspan="9" class="empty-state">Sin leads que mostrar</td></tr>'
    : slice.map(l => `
      <tr>
        <td class="id-cell">${l.id}</td>
        <td style="color:var(--muted)">${l.fecha}</td>
        <td style="font-weight:500">${l.nombre}</td>
        <td>${l.semanas ? `<span class="sem-tag">${l.semanas} sem</span>` : '<span class="dash">—</span>'}</td>
        <td>${l.parto !== '—' ? `<span class="badge ${l.parto === 'Natural' ? 'si' : 'tibio'}">${l.parto}</span>` : '<span class="dash">—</span>'}</td>
        <td>${l.servicio !== '—' ? `<span class="badge frio">${l.servicio}</span>` : '<span class="dash">—</span>'}</td>
        <td>${dotsHtml(l.datos, l.temp)}</td>
        <td><span class="badge ${tempClass(l.temp)}">${l.temp}</span></td>
        <td><span class="badge ${l.contactado === 'Sí' ? 'no' : 'si'}">${l.contactado === 'Sí' ? 'Requiere' : 'Bot OK'}</span></td>
      </tr>`
    ).join('');

  const pb = document.getElementById('page-btns');
  pb.innerHTML = '';
  for (let i = 1; i <= Math.min(pages, 5); i++) {
    const b = document.createElement('button');
    b.className = 'page-btn' + (i === paginaActual ? ' active' : '');
    b.textContent = i;
    b.onclick = () => { paginaActual = i; renderTabla(); };
    pb.appendChild(b);
  }
}

function filtrar(el, val) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  filtroActivo = val;
  paginaActual = 1;
  renderTabla();
}

function buscar(val) {
  busqueda = val;
  paginaActual = 1;
  renderTabla();
}

function exportarCSV() {
  const headers = ['ID','Fecha','Nombre','Semanas','Atención','Cobertura','Temperatura','Humano'];
  const rows = leads.map(l => [l.id, l.fecha, l.nombre, l.semanas ?? '', l.parto, l.servicio, l.temp, l.contactado]);
  const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'leads_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
}

window.addEventListener('load', () => {
  renderTabla();
  setTimeout(() => document.getElementById('loader').classList.add('hidden'), 600);
});
</script>
</body>
</html>