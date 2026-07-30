/* DSD – Distribuição (pages/distribuicao.php)
   Página de consulta/filtro; a edição de linhas de serviço vive em ocorrencia-form.php.
   Depende dos globals definidos inline na página antes deste ficheiro:
   rowData, ocorData, docSnap, balData, savedScroll */
'use strict';

// Cascade carreira → docente no form de filtro da página
function fltCascadeDocente() {
  var carrId  = document.getElementById('flt-dist-carr').value;
  var sel     = document.getElementById('flt-dist-doc');
  var current = sel.value;

  // Mostrar/esconder conforme carreira
  Array.from(sel.options).forEach(function(o) {
    if (!o.value) return;
    o.hidden = carrId !== '' && o.dataset.carr !== carrId;
  });

  // Se a opção seleccionada ficou escondida, reset
  var curOpt = sel.querySelector('option[value="' + current + '"]');
  if (curOpt && curOpt.hidden) sel.value = '';

  // Reordenar as options visíveis alfabeticamente
  var opts = Array.from(sel.options).filter(function(o) { return !!o.value; });
  opts.sort(function(a, b) {
    return a.text.localeCompare(b.text, 'pt', { sensitivity: 'base' });
  });
  opts.forEach(function(o) { sel.appendChild(o); }); // move para o fim (mantém ordem)

  // Restaurar selecção
  sel.value = curOpt && !curOpt.hidden ? current : '';
}
// Aplicar cascade no carregamento (caso carreira venha por GET)
fltCascadeDocente();

function togglePlanoSD(id) {
  const el = document.getElementById(id);
  const icon = document.getElementById('icon-' + id);
  const open = el.style.display === 'none';
  el.style.display = open ? '' : 'none';
  if (icon) icon.textContent = open ? '▼' : '▶';
}

function positionPanel(panel, e) {
  panel.style.display = 'block';
  const r = panel.getBoundingClientRect();
  let x = e.clientX + 10, y = e.clientY + 10;
  if (x + r.width  > window.innerWidth)  x = e.clientX - r.width - 10;
  if (y + r.height > window.innerHeight) y = e.clientY - r.height - 10;
  panel.style.left = x + 'px';
  panel.style.top  = y + 'px';
}

const fmtN = (v, d=2) => (parseFloat(v)||0).toFixed(d);

// Painel flutuante único, partilhado entre info de UC e info de docente
function renderInfoPanel(title, bodyHtml, editHref, e) {
  document.getElementById('panel-info-title').textContent = title;
  document.getElementById('panel-info-body').innerHTML = bodyHtml;
  document.getElementById('panel-info-edit').href = editHref;
  positionPanel(document.getElementById('panel-info'), e);
}

function showUcInfo(ocorId, e) {
  e.preventDefault();
  const oc = ocorData[ocorId];
  if (!oc) return;
  const bal = balData[ocorId];
  const need = bal ? parseFloat(bal.need) : 0;
  const done = bal ? parseFloat(bal.done) : 0;
  const diff = need - done;
  const col = Math.abs(diff)<0.01 ? 'var(--green)' : diff>0 ? 'var(--orange)' : 'var(--red)';
  const tipos = [['T','T'],['TP','TP'],['L','L'],['Sem','Sem'],['OT','OT']];
  let rows = tipos.map(([k,l]) => {
    const nT = parseFloat(oc['n_turmas_'+k])||0;
    const hT = parseFloat(oc['horas_'+k])||0;
    return nT||hT ? `<tr><td style="color:var(--gray-500)">${l}</td><td>${fmtN(nT,1)} turmas</td><td>${fmtN(hT,2)} h/sem</td></tr>` : '';
  }).join('');
  const body =
    `<table style="width:100%;border-collapse:collapse">${rows}</table>`
    + `<div style="margin-top:8px">Estudantes: <b>${oc.estudantes}</b> | F SLEf: <b>${fmtN(oc.f_slef,2)}</b> | Semanas: <b>${fmtN(oc.semanas,1)}</b></div>`
    + `<div style="margin-top:4px;color:${col};font-weight:600">Nec: ${fmtN(need,2)} | Atr: ${fmtN(done,2)} | ${Math.abs(diff)<0.01?'<i class="fas fa-check-circle me-1"></i>OK':diff>0?'Falta '+fmtN(diff,2):'Excesso '+fmtN(-diff,2)}</div>`;
  renderInfoPanel(oc.designacao, body, 'ocorrencia-form.php?id=' + ocorId, e);
}

function showDocInfo(docId, e) {
  e.preventDefault();
  const doc = docSnap[docId];
  if (!doc) return;
  // Get dist rows for this docente
  const distRows = Object.values(rowData).filter(r => r.docente_id == docId);
  let total_slef = distRows.reduce((s,r) => s + (parseFloat(r.h_slef_uc)||0), 0);
  const body =
    `<div>Carreira: <b>${doc.carreira||'–'}</b></div>`
    + `<div>UCs neste ano: <b>${distRows.length}</b></div>`
    + `<div>Total H SLEf (ano): <b style="color:var(--blue)">${fmtN(total_slef,2)}</b> h/sem</div>`;
  renderInfoPanel(doc.nome, body, 'docente-form.php?id=' + docId, e);
}

// Close panel on click outside
document.addEventListener('click', e => {
  if (!e.target.closest('#panel-info,[onclick*="showUcInfo"],[onclick*="showDocInfo"]')) {
    document.getElementById('panel-info').style.display = 'none';
  }
});

// ── Scroll position (preservado ao remover uma linha) ──────────
if (savedScroll > 0) window.scrollTo({top: savedScroll, behavior: 'instant'});

document.addEventListener('submit', e => {
  const form = e.target;
  let inp = form.querySelector('[name=scroll_y]');
  if (!inp) { inp = document.createElement('input'); inp.type='hidden'; inp.name='scroll_y'; form.appendChild(inp); }
  inp.value = Math.round(window.scrollY);
});

// ── UC search ─────────────────────────────────────────────────
function searchUC(q) {
  q = q.toLowerCase();
  const semSD = document.getElementById('filtro-sem-sd')?.checked;
  const rows = document.querySelectorAll('#tbl-dist tbody tr');
  let ucVisible = 0, headerVisible = false;
  rows.forEach(tr => {
    if (tr.dataset.ucName !== undefined) {
      // UC header row — has service (data-has-sd="1")
      const matchQ = !q || tr.dataset.ucName.includes(q);
      // If semSD filter: hide UCs that HAVE service
      headerVisible = matchQ && !semSD;
      tr.style.display = headerVisible ? '' : 'none';
      if (headerVisible) ucVisible++;
    } else {
      tr.style.display = headerVisible ? '' : 'none';
    }
  });
  const el = document.getElementById('search-count');
  if (el) el.textContent = (q && !semSD) ? ucVisible + ' UC(s)' : '';
}
