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
        'tresp'      => '—',
        'raw_id' => $l['id']
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

</style>
<link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="loading" id="loader">
  <div class="loading-logo">ML</div>
        <div class="loading-text">Cargando dashboard…</div>
    </div>

<!-- <nav class="sidebar">
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
</nav> -->
<?php $paginaActual = 'dashboard'; include 'includes/nav.php'; ?>
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
          <th>#ID</th>
          <th>Fecha</th>
          <th>Nombre</th>
          <th>Semanas</th>
          <th>Atención</th>
          <th>Cobertura</th>
          <th>Datos</th>
          <th>Lead</th>
          <th>Humano</th>
          <th>Acciones</th>
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
        
<td>
    <button 
      onclick="abrirConversacion(${l.raw_id})" 
      style="background:transparent;border:1px solid #252b3b;color:#6b7280;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px;transition:all .15s"
      onmouseover="this.style.borderColor='#4f8ef7';this.style.color='#4f8ef7'"
      onmouseout="this.style.borderColor='#252b3b';this.style.color='#6b7280'">
      💬 Ver
    </button>
</td>
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
<?php include 'modal_conversacion.php'; ?>

</body>
</html>