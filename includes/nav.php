<?php
// $paginaActual debe estar definida antes del include
// Ej: $paginaActual = 'dashboard';
?>
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
    <a class="nav-item <?= $paginaActual === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">⬛ Dashboard</a>
    <a class="nav-item <?= $paginaActual === 'leads'     ? 'active' : '' ?>" href="#">👥 Leads</a>
    <a class="nav-item <?= $paginaActual === 'chatbot'   ? 'active' : '' ?>" href="#">🤖 Chatbot</a>
  </div>
  <div class="nav-section">
    <div class="nav-label">Seguimiento</div>
    <a class="nav-item <?= $paginaActual === 'agenda'  ? 'active' : '' ?>" href="#">📅 Agenda</a>
    <a class="nav-item <?= $paginaActual === 'agentes' ? 'active' : '' ?>" href="agentes.php">🧑‍💼 Agentes</a>
  </div>
  <div class="nav-section">
    <div class="nav-label">Reportes</div>
    <a class="nav-item <?= $paginaActual === 'analitica'  ? 'active' : '' ?>" href="#">📊 Analítica</a>
    <a class="nav-item <?= $paginaActual === 'campanas'   ? 'active' : '' ?>" href="#">📣 Campañas</a>
  </div>
  <div class="sidebar-footer">
    <form method="POST" action="logout.php">
      <button class="logout-btn" type="submit">⏏ Cerrar sesión</button>
    </form>
  </div>
</nav>