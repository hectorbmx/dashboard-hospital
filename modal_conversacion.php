
<!-- Overlay -->
<div id="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;backdrop-filter:blur(2px)" onclick="cerrarModal()"></div>

<!-- Modal -->
<div id="modal-conv" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:480px;max-width:95vw;max-height:90vh;background:#181c27;border:1px solid #252b3b;border-radius:16px;z-index:1001;display:none;flex-direction:column;overflow:hidden">

  <!-- Header lead info -->
  <div id="modal-header" style="padding:18px 20px;border-bottom:1px solid #252b3b;background:#1e2435">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div style="display:flex;align-items:center;gap:10px">
        <div id="modal-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#4f8ef7,#7c5ff7);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#fff;flex-shrink:0"></div>
        <div>
          <div id="modal-nombre" style="font-weight:600;font-size:15px;color:#e8eaf0"></div>
          <div id="modal-whatsapp" style="font-size:11px;color:#6b7280;font-family:'DM Mono',monospace"></div>
        </div>
      </div>
      <button onclick="cerrarModal()" style="background:transparent;border:none;color:#6b7280;font-size:20px;cursor:pointer;padding:4px;line-height:1">✕</button>
    </div>
    <!-- Tags de info -->
    <div id="modal-tags" style="display:flex;flex-wrap:wrap;gap:6px"></div>
  </div>

  <!-- Chat WhatsApp -->
  <div id="modal-chat" style="flex:1;overflow-y:auto;padding:16px;background:#0f1117;display:flex;flex-direction:column;gap:8px;min-height:300px;max-height:50vh">
    <div id="chat-loading" style="text-align:center;color:#6b7280;font-size:13px;padding:20px">Cargando conversación…</div>
  </div>

  <!-- Footer -->
  <div style="padding:12px 16px;border-top:1px solid #252b3b;background:#1e2435;display:flex;align-items:center;justify-content:space-between">
    <div id="modal-status" style="font-size:11px;color:#6b7280"></div>
    <div style="display:flex;gap:8px">
      <button id="btn-tomar" onclick="tomarLead()" style="padding:7px 14px;border-radius:8px;border:none;background:#22c55e;color:#fff;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;display:none">
        ✋ Tomar lead
      </button>
      <button onclick="cerrarModal()" style="padding:7px 14px;border-radius:8px;border:1px solid #252b3b;background:transparent;color:#6b7280;font-size:12px;cursor:pointer;font-family:inherit">
        Cerrar
      </button>
    </div>
  </div>

</div>

<style>
/* Burbujas chat */
.burbuja-wrap { display:flex; margin-bottom:2px; }
.burbuja-wrap.user     { justify-content:flex-end; }
.burbuja-wrap.assistant{ justify-content:flex-start; }

.burbuja {
  max-width: 78%;
  padding: 9px 13px;
  border-radius: 12px;
  font-size: 13px;
  line-height: 1.45;
  position: relative;
  word-break: break-word;
}
.burbuja-wrap.user .burbuja {
  background: #1a3a5c;
  color: #e8eaf0;
  border-bottom-right-radius: 3px;
}
.burbuja-wrap.assistant .burbuja {
  background: #1e2435;
  color: #e8eaf0;
  border-bottom-left-radius: 3px;
  border: 1px solid #252b3b;
}
.burbuja-time {
  font-size: 10px;
  color: #6b7280;
  margin-top: 3px;
  text-align: right;
  font-family: 'DM Mono', monospace;
}
.burbuja-wrap.assistant .burbuja-time { text-align:left; }

.chat-fecha-sep {
  text-align: center;
  font-size: 10px;
  color: #6b7280;
  margin: 8px 0 4px;
  background: #1a1e2e;
  border: 1px solid #252b3b;
  border-radius: 99px;
  padding: 2px 10px;
  align-self: center;
}

/* Scrollbar del chat */
#modal-chat::-webkit-scrollbar { width: 4px; }
#modal-chat::-webkit-scrollbar-track { background: transparent; }
#modal-chat::-webkit-scrollbar-thumb { background: #252b3b; border-radius: 4px; }

/* Tag colores */
.info-tag {
  padding: 3px 9px;
  border-radius: 5px;
  font-size: 10px;
  font-weight: 500;
  border: 1px solid;
}
.tag-caliente { background:rgba(239,68,68,.15);  color:#ef4444; border-color:rgba(239,68,68,.3); }
.tag-tibio    { background:rgba(245,158,11,.15); color:#f59e0b; border-color:rgba(245,158,11,.3); }
.tag-frio     { background:rgba(79,142,247,.15); color:#4f8ef7; border-color:rgba(79,142,247,.3); }
.tag-neutral  { background:rgba(107,114,128,.1); color:#9ca3af; border-color:rgba(107,114,128,.2); }
.tag-requiere { background:rgba(239,68,68,.1);   color:#ef4444; border-color:rgba(239,68,68,.2); }
</style>

<script>
let leadActivo = null;

function abrirConversacion(leadId) {
  leadActivo = leadId;
  document.getElementById('modal-overlay').style.display = 'block';
  const modal = document.getElementById('modal-conv');
  modal.style.display = 'flex';

  // Reset
  document.getElementById('chat-loading').style.display = 'block';
  document.getElementById('modal-chat').innerHTML = '<div id="chat-loading" style="text-align:center;color:#6b7280;font-size:13px;padding:20px">Cargando conversación…</div>';
  document.getElementById('modal-tags').innerHTML = '';
  document.getElementById('modal-nombre').textContent = '';
  document.getElementById('modal-whatsapp').textContent = '';
  document.getElementById('btn-tomar').style.display = 'none';

  fetch(`api/conversacion.php?lead_id=${leadId}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        document.getElementById('modal-chat').innerHTML = `<div style="text-align:center;color:#ef4444;font-size:13px;padding:20px">${data.error}</div>`;
        return;
      }
      renderModal(data);
    })
    .catch(() => {
      document.getElementById('modal-chat').innerHTML = '<div style="text-align:center;color:#ef4444;font-size:13px;padding:20px">Error al cargar la conversación</div>';
    });
}

function renderModal(data) {
  const lead = data.lead;
  const msgs = data.mensajes;

  // Avatar inicial
  const iniciales = (lead.nombre ?? '?').split(' ').slice(0,2).map(p => p[0]).join('').toUpperCase();
  document.getElementById('modal-avatar').textContent = iniciales;
  document.getElementById('modal-nombre').textContent = lead.nombre ?? 'Sin nombre';
  document.getElementById('modal-whatsapp').textContent = '+' + lead.whatsapp_id;

  // Tags info
  const tempClass = { 'Caliente':'tag-caliente', 'Tibio':'tag-tibio', 'Frio':'tag-frio' };
  const tags = [
    { label: lead.lead_temperatura ?? 'Sin temp', cls: tempClass[lead.lead_temperatura] ?? 'tag-neutral' },
    lead.semanas_embarazo ? { label: lead.semanas_embarazo + ' semanas', cls: 'tag-neutral' } : null,
    lead.tipo_atencion    ? { label: lead.tipo_atencion,   cls: 'tag-neutral' } : null,
    lead.tipo_cobertura   ? { label: lead.tipo_cobertura,  cls: 'tag-neutral' } : null,
    lead.requiere_humano == 1 ? { label: '⚠ Requiere agente', cls: 'tag-requiere' } : null,
  ].filter(Boolean);

  document.getElementById('modal-tags').innerHTML = tags
    .map(t => `<span class="info-tag ${t.cls}">${t.label}</span>`)
    .join('');

  // Status footer
  document.getElementById('modal-status').textContent =
    `${msgs.length} mensaje${msgs.length !== 1 ? 's' : ''} · ${lead.lead_status ?? ''}`;

  // Botón tomar lead
  if (lead.requiere_humano == 1) {
    document.getElementById('btn-tomar').style.display = 'inline-flex';
  }

  // Render mensajes
  if (!msgs.length) {
    document.getElementById('modal-chat').innerHTML =
      '<div style="text-align:center;color:#6b7280;font-size:13px;padding:30px">Sin mensajes registrados</div>';
    return;
  }

  let html = '';
  let lastFecha = '';

  msgs.forEach(m => {
    const fecha = m.fecha ? m.fecha.slice(0, 10) : '';
    const hora  = m.fecha ? m.fecha.slice(11, 16) : '';

    if (fecha !== lastFecha) {
      html += `<div class="chat-fecha-sep">${formatFecha(fecha)}</div>`;
      lastFecha = fecha;
    }

    const rol = m.role === 'user' ? 'user' : 'assistant';
    const quien = rol === 'user' ? '👤' : '🤖';

    html += `
      <div class="burbuja-wrap ${rol}">
        <div>
          <div class="burbuja">
            <div style="font-size:9px;color:${rol === 'user' ? '#4f8ef7' : '#7c5ff7'};margin-bottom:4px;font-weight:600">
              ${rol === 'user' ? (lead.nombre ?? 'Usuario') : '🤖 Bot'}
            </div>
            ${escHtml(m.content)}
          </div>
          <div class="burbuja-time">${hora}</div>
        </div>
      </div>`;
  });

  document.getElementById('modal-chat').innerHTML = html;

  // Scroll al fondo
  const chat = document.getElementById('modal-chat');
  chat.scrollTop = chat.scrollHeight;
}

function cerrarModal() {
  document.getElementById('modal-overlay').style.display = 'none';
  document.getElementById('modal-conv').style.display = 'none';
  leadActivo = null;
}

function tomarLead() {
  if (!leadActivo) return;
  if (!confirm('¿Confirmas que tomarás este lead?')) return;
  // TODO: endpoint para marcar requiere_humano = 0 y asignar agente
  alert('Funcionalidad próximamente');
}

function formatFecha(fechaStr) {
  if (!fechaStr) return '';
  const [y, m, d] = fechaStr.split('-');
  const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  return `${parseInt(d)} ${meses[parseInt(m)-1]} ${y}`;
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/\n/g,'<br>');
}

// Cerrar con Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });
</script>