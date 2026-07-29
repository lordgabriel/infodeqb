/* DSD – Distribuição (pages/distribuicao.php)
   Depende dos globals definidos inline na página antes deste ficheiro:
   rowData, ocorData, docSnap, balData, preOcor, savedScroll, distAnoLetivoId, distBootstrap */
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
  renderInfoPanel(oc.designacao, body, 'ocorrencia-form.php?id=' + ocorId + '&back_scroll=' + Math.round(window.scrollY), e);
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
  renderInfoPanel(doc.nome, body, 'docente-form.php?id=' + docId + '&back_scroll=' + Math.round(window.scrollY), e);
}

// Close panel on click outside
document.addEventListener('click', e => {
  if (!e.target.closest('#panel-info,[onclick*="showUcInfo"],[onclick*="showDocInfo"]')) {
    document.getElementById('panel-info').style.display = 'none';
  }
});

let pendingLines = [];
let editingIdx   = -1;
let deletedIds   = [];

const g   = id => document.getElementById(id);
const fmtN = (v, d=2) => (parseFloat(v)||0).toFixed(d);

// ── Filters ───────────────────────────────────────────────────
function filterOcs() {
  const plano = g('f-plano-filter').value;
  const cur   = g('f-ocor').value;
  let visible = 0;
  Array.from(g('f-ocor').options).forEach(o => {
    if (!o.value) return;
    const show = !plano || o.dataset.plano === plano;
    o.hidden = !show;
    if (show) visible++;
  });
  if (g('f-ocor').selectedOptions[0]?.hidden) {
    g('f-ocor').value = ''; onOcorChange();
  }
}

function filterDocentes() {
  const car = g('f-carreira').value;
  const cur = g('f-doc').value;
  Array.from(g('f-doc').options).forEach(o => {
    if (!o.value) return;
    o.hidden = !!(car && o.dataset.carreira !== car);
  });
  if (g('f-doc').selectedOptions[0]?.hidden) g('f-doc').value = '';
}

// ── UC change ─────────────────────────────────────────────────
function onOcorChange() {
  const ocId = g('f-ocor').value;
  const oc   = ocorData[ocId];
  const bal  = balData[ocId];
  if (oc) {
    const need = bal ? parseFloat(bal.need) : 0;
    const done = bal ? parseFloat(bal.done) : 0;
    const diff = need - done;
    const color = Math.abs(diff) < 0.01 ? 'var(--green)' : diff > 0 ? 'var(--orange)' : 'var(--red)';
    g('oc-info').innerHTML =
      `F SLEf: <b>${fmtN(oc.f_slef,2)}</b> | Semanas: <b>${fmtN(oc.semanas,1)}</b> | Estudantes: <b>${oc.estudantes}</b>` +
      ` &nbsp;|&nbsp; Necessário: <b>${fmtN(need,2)}</b> h/sem &nbsp; Atribuído: <b>${fmtN(done,2)}</b> h/sem` +
      ` &nbsp; <span style="color:${color};font-weight:600">${diff > 0.01 ? 'Falta: '+fmtN(diff,2) : diff < -0.01 ? 'Excesso: '+fmtN(-diff,2) : '<i class="fas fa-check-circle me-1"></i>OK'}</span>`;
    // Pre-fill horas from ocorrencia
    ['T','TP','L','Sem','OT'].forEach(k => {
      if ((parseFloat(g('f-t'+k).value)||0) === 0) {
        const nT = parseFloat(oc['n_turmas_'+k]) || 0;
        const hO = parseFloat(oc['horas_'+k])   || 0;
        if (hO > 0) g('f-h'+k).value = hO;
      }
    });
  } else {
    g('oc-info').innerHTML = '';
  }
  refreshLineCalc();
}

// ── Line calc ─────────────────────────────────────────────────
function refreshLineCalc() {
  const sem   = parseFloat(g('f-semanas').value)||13;
  const ocId  = g('f-ocor').value;
  const oc    = ocorData[ocId];
  const fslef = oc ? parseFloat(oc.f_slef)||1 : 1;
  let hs=0, hslef=0;
  ['T','TP','L','Sem','OT'].forEach(k => {
    const t = parseFloat(g('f-t'+k).value)||0;
    const h = parseFloat(g('f-h'+k).value)||0;
    const contrib = t*h*sem/13;
    hs += contrib;
    if (k !== 'OT') hslef += contrib;
  });
  hslef *= fslef;
  const bal = balData[g('f-ocor').value];
  let balHtml = '';
  if (bal) {
    const need = parseFloat(bal.need)||0;
    // Calculate done from pending lines (live) instead of stale balData.done
    const ocId2 = g('f-ocor').value;
    const pendDone = pendingLines
      .filter(l => String(l.ocorrencia_id) === String(ocId2))
      .reduce((s, l) => {
        const sem2 = parseFloat(l.semanas)||13;
        return s + ['T','TP','L','Sem'].reduce((ss,k) =>
          ss + (parseFloat(l['t'+k])||0)*(parseFloat(l['h'+k])||0)*sem2/13, 0);
      }, 0);
    const done = pendingLines.filter(l => String(l.ocorrencia_id) === String(ocId2)).length > 0
      ? pendDone * (oc ? parseFloat(oc.f_slef)||1 : 1)
      : parseFloat(bal.done)||0;
    const diff = need - done;
    const col  = Math.abs(diff)<0.01 ? 'var(--green)' : diff>0 ? 'var(--red)' : 'var(--blue)';
    balHtml = ` | UC: Nec <b>${fmtN(need,2)}</b> Atr <b>${fmtN(done,2)}</b> <span style="color:${col};font-weight:600">${Math.abs(diff)<0.01?'<i class="fas fa-check-circle"></i>':diff>0?'−'+fmtN(diff,2):'+'+fmtN(-diff,2)}</span>`;
  }
  g('line-calc').innerHTML = `H/s: <b>${fmtN(hs,2)}</b> | H SLEf: <b style="color:var(--blue)">${fmtN(hslef,2)}</b>${balHtml}`;
  // Also update the oc-info balance if there are pending lines
  if (pendingLines.length > 0) updateBalFromPending();
}

// ── Line editor ───────────────────────────────────────────────
function resetLineEditor() {
  g('f-doc').value     = '';
  g('f-carreira').value= '';
  g('f-semanas').value = 13;
  g('f-dsd').checked   = true;
  g('f-reg').checked   = false;
  ['T','TP','L','Sem','OT'].forEach(k => { g('f-t'+k).value=0; g('f-h'+k).value=0; });
  g('f-htese').value = 0;
  editingIdx = -1;
  g('line-mode-label').textContent = 'Nova linha';
  g('btn-add-line').textContent    = '+ Adicionar linha';
  g('btn-cancel-edit').style.display = 'none';
  filterDocentes();
}

function getCurrentLine() {
  return {
    ocorrencia_id: g('f-ocor').value,
    docente_id:    g('f-doc').value,
    docente_nome:  g('f-doc').selectedOptions[0]?.text || '?',
    semanas:       g('f-semanas').value,
    dsd:           g('f-dsd').checked ? 1 : 0,
    reg:           g('f-reg').checked ? 1 : 0,
    tT: g('f-tT').value,  hT: g('f-hT').value,
    tTP:g('f-tTP').value, hTP:g('f-hTP').value,
    tL: g('f-tL').value,  hL: g('f-hL').value,
    tSem:g('f-tSem').value, hSem:g('f-hSem').value,
    tOT:g('f-tOT').value, hOT:g('f-hOT').value,
    htese:g('f-htese').value,
    edit_id: 0,
  };
}

function addLine() {
  const ocId = g('f-ocor').value;
  const docId= g('f-doc').value;
  if (!ocId)  { alert('Seleciona uma UC.'); return; }
  if (!docId) { alert('Seleciona um docente.'); return; }
  const line = getCurrentLine();
  if (editingIdx >= 0) {
    line.edit_id = pendingLines[editingIdx].edit_id || 0;
    pendingLines[editingIdx] = line;
  } else {
    pendingLines.push(line);
  }
  resetLineEditor();
  renderLines();
  updateBalFromPending();
  onOcorChange();
}

function cancelEditLine() { resetLineEditor(); }

function editLine(idx) {
  const line = pendingLines[idx];
  g('f-ocor').value    = line.ocorrencia_id;
  g('f-doc').value     = line.docente_id;
  g('f-semanas').value = line.semanas;
  g('f-dsd').checked   = line.dsd == 1;
  g('f-reg').checked   = line.reg == 1;
  resetCheckboxStyles();
  g('f-tT').value=line.tT;   g('f-hT').value=line.hT;
  g('f-tTP').value=line.tTP; g('f-hTP').value=line.hTP;
  g('f-tL').value=line.tL;   g('f-hL').value=line.hL;
  g('f-tSem').value=line.tSem; g('f-hSem').value=line.hSem;
  g('f-tOT').value=line.tOT; g('f-hOT').value=line.hOT;
  g('f-htese').value=line.htese;
  editingIdx = idx;
  g('line-mode-label').textContent = 'A editar linha ' + (idx+1);
  g('btn-add-line').textContent    = '<i class="fas fa-check me-1"></i>Actualizar linha';
  g('btn-cancel-edit').style.display = '';
  onOcorChange(); refreshLineCalc();
}

function removeLine(idx) {
  const line = pendingLines[idx];
  if (line.edit_id) deletedIds.push(line.edit_id);
  pendingLines.splice(idx, 1);
  if (editingIdx === idx) resetLineEditor();
  else if (editingIdx > idx) editingIdx--;
  renderLines();
  updateBalFromPending();
}

function renderLines() {
  const tbody = g('lines-body');
  tbody.innerHTML = '';
  if (pendingLines.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:var(--gray-400);padding:16px">Sem linhas — adiciona acima</td></tr>';
    g('btn-save-all').disabled = deletedIds.length === 0;
    return;
  }
  pendingLines.forEach((line, idx) => {
    const tr = document.createElement('tr');
    if (idx === editingIdx) tr.style.background = 'var(--blue-light)';
    const c = (v) => `<td style="text-align:center;font-size:12px">${v}</td>`;
    const f1 = v => (parseFloat(v)||0) > 0 ? fmtN(v,1) : '–';
    const f2 = v => (parseFloat(v)||0) > 0 ? fmtN(v,1) : '–';
    tr.innerHTML =
      `<td style="font-size:12px">${line.docente_nome}</td>` +
      c(fmtN(line.semanas,0)) +
      c(f1(line.tT)+'/'+f2(line.hT)) +
      c(f1(line.tTP)+'/'+f2(line.hTP)) +
      c(f1(line.tL)+'/'+f2(line.hL)) +
      c(f1(line.tSem)+'/'+f2(line.hSem)) +
      c(f1(line.tOT)+'/'+f2(line.hOT)) +
      c(line.dsd ? '<i class="fas fa-check"></i>' : '–') +
      c(line.reg ? 'R' : '–') +
      `<td style="white-space:nowrap;padding:2px 6px">
        <button type="button" class="btn btn-secondary btn-xs" onclick="editLine(${idx})"><i class="fas fa-edit"></i></button>
        <button type="button" class="btn btn-danger btn-xs" onclick="removeLine(${idx})"><i class="fas fa-trash"></i></button>
      </td>`;
    tbody.appendChild(tr);
  });
  g('btn-save-all').disabled = false;
}

// ── Save ──────────────────────────────────────────────────────
function saveAllLines() {
  if (!pendingLines.length && !deletedIds.length) { alert('Sem alterações para guardar.'); return; }
  g('f-batch-json').value = JSON.stringify(pendingLines);
  g('f-delete-ids').value = JSON.stringify(deletedIds);
  g('dist-form').submit();
}

// ── Modal ─────────────────────────────────────────────────────
// Carrega TODAS as linhas de uma UC para o editor (o modal tem sempre âmbito UC).
// highlightEditId (opcional): id de uma linha existente a pré-carregar no formulário de edição.
function loadUcLines(ocorId, highlightEditId) {
  pendingLines = []; editingIdx = -1; deletedIds = [];
  g('lines-body').innerHTML = '<tr id="lines-empty"><td colspan="10" style="text-align:center;color:var(--gray-400);padding:16px">A carregar…</td></tr>';
  g('btn-save-all').disabled = true;

  return fetch('ajax-dist.php?edit_lines=1&ocor_id=' + ocorId + '&_=' + Date.now())
    .then(function(r) { return r.json(); })
    .then(function(data) {
      pendingLines = [];
      (data.rows || []).forEach(function(reg) {
        pendingLines.push({
          ocorrencia_id: ocorId,
          docente_id:    reg.docente_id,
          docente_nome:  reg.docente_nome,
          semanas:       reg.semanas,
          dsd:           reg.dsd_por_docente,
          reg:           reg.regente,
          tT:  reg.turmas_T,  hT:  reg.horas_T,
          tTP: reg.turmas_TP, hTP: reg.horas_TP,
          tL:  reg.turmas_L,  hL:  reg.horas_L,
          tSem:reg.turmas_Sem,hSem:reg.horas_Sem,
          tOT: reg.turmas_OT, hOT: reg.horas_OT,
          htese: reg.h_tese || 0,
          edit_id: reg.id,
        });
      });
      renderLines();
      updateBalFromPending();
      if (highlightEditId) {
        const idx = pendingLines.findIndex(function(l) { return String(l.edit_id) === String(highlightEditId); });
        if (idx >= 0) editLine(idx);
      }
    })
    .catch(function() {
      g('lines-body').innerHTML = '<tr><td colspan="10" style="text-align:center;color:var(--red);padding:16px">Erro ao carregar linhas.</td></tr>';
    });
}

// Chamado quando o select de UC muda (dropdown do modal)
function handleUcSelected() {
  onOcorChange();
  const ocId = g('f-ocor').value;
  if (ocId) {
    loadUcLines(ocId);
  } else {
    pendingLines = []; editingIdx = -1; deletedIds = [];
    resetLineEditor();
    renderLines();
  }
}

// Abre o modal sempre com âmbito de UC: mostra todas as linhas dessa ocorrência.
// editId (opcional): destaca essa linha específica no formulário de edição.
// ocorId (opcional): UC a pré-selecionar; se omitido, usa a do registo (editId) ou preOcor.
function openModal(editId, ocorId) {
  pendingLines = []; editingIdx = -1; deletedIds = [];
  resetLineEditor();

  let targetOcorId = ocorId ? String(ocorId) : '';
  if (editId) {
    const reg = rowData[String(editId)];
    if (!reg) { alert('Registo não encontrado.'); return; }
    targetOcorId = String(reg.ocorrencia_id || reg.ocor_id || '');
  } else if (!targetOcorId && preOcor) {
    targetOcorId = String(preOcor);
  }

  g('modal-uc-section').style.display = '';
  g('f-plano-filter').value = '';
  filterOcs();
  g('f-ocor').value = targetOcorId; // limpa seleção anterior quando targetOcorId é ''

  if (targetOcorId) {
    onOcorChange();
    const oc = ocorData[targetOcorId];
    g('modal-title').textContent = '<i class="fas fa-clipboard-list me-1"></i>'
      + (oc ? '['+(oc.plano_sigla||'')+'] '+(oc.designacao||'') : 'Serviço da UC');
  } else {
    g('modal-title').textContent = '<i class="fas fa-plus me-1"></i>Adicionar Serviço';
  }

  resetCheckboxStyles();
  g('modal-dist').style.display = 'flex';
  document.body.style.overflow  = 'hidden';

  if (targetOcorId) {
    loadUcLines(targetOcorId, editId);
  } else {
    renderLines();
  }
}

// Recalcula balanço por tipologia a partir das linhas pendentes
function updateBalFromPending() {
  const ocId = g('f-ocor').value;
  if (!ocId || !ocorData[ocId]) return;
  const oc = ocorData[ocId];
  const tipos = ['T','TP','L','Sem','OT'];

  // Sum pending lines for this UC
  const pendDone = {};
  tipos.forEach(k => pendDone[k] = 0);
  pendingLines.filter(l => String(l.ocorrencia_id) === String(ocId)).forEach(l => {
    const sem = parseFloat(l.semanas) || 13;
    tipos.forEach(k => {
      pendDone[k] += (parseFloat(l['t'+k])||0) * (parseFloat(l['h'+k])||0) * sem / 13;
    });
  });

  // Need per tipo
  let html = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px">';
  let totalNeed = 0, totalDone = 0;
  tipos.forEach(k => {
    const need = (parseFloat(oc['n_turmas_'+k])||0) * (parseFloat(oc['horas_'+k])||0);
    if (need <= 0 && pendDone[k] <= 0) return;
    const done = pendDone[k];
    const diff = need - done;
    const col = Math.abs(diff) < 0.01 ? 'var(--green)' : diff > 0 ? 'var(--orange)' : 'var(--red)';
    html += `<span style="font-size:11px;background:var(--gray-100);padding:2px 8px;border-radius:4px">
      <b>${k}</b>: ${fmtN(need,1)}→<b style="color:${col}">${fmtN(done,1)}</b>
      <span style="color:${col}">${Math.abs(diff)<0.01?'<i class="fas fa-check-circle"></i>':diff>0?'−'+fmtN(diff,1):'+'+fmtN(-diff,1)}</span>
    </span>`;
    totalNeed += need; totalDone += done;
  });
  html += '</div>';

  const totalDiff = totalNeed - totalDone;
  const totalCol = Math.abs(totalDiff)<0.01?'var(--green)':totalDiff>0?'var(--orange)':'var(--red)';
  g('oc-info').innerHTML =
    `F SLEf: <b>${fmtN(oc.f_slef,2)}</b> | Semanas: <b>${fmtN(oc.semanas||13,1)}</b> | Estudantes: <b>${oc.estudantes||0}</b>`
    + ` &nbsp;|&nbsp; Nec: <b>${fmtN(totalNeed,2)}</b> Atr (pendente): <b>${fmtN(totalDone,2)}</b>`
    + ` <span style="color:${totalCol};font-weight:600">${Math.abs(totalDiff)<0.01?'<i class="fas fa-check-circle me-1"></i>OK':totalDiff>0?'Falta: '+fmtN(totalDiff,2):'Excesso: '+fmtN(-totalDiff,2)}</span>`
    + html;
}

function updateDsdStyle() {
  const on = g("f-dsd").checked;
  const lbl = g("lbl-dsd");
  lbl.style.background = on ? "var(--blue-light)" : "var(--gray-100)";
  lbl.style.borderColor = on ? "var(--blue)" : "var(--gray-300)";
  lbl.style.color = on ? "var(--blue-dark)" : "var(--gray-400)";
}
function updateRegStyle() {
  const on = g("f-reg").checked;
  const lbl = g("lbl-reg");
  lbl.style.background = on ? "#fef9c3" : "var(--gray-100)";
  lbl.style.borderColor = on ? "#ca8a04" : "var(--gray-300)";
  lbl.style.color = on ? "#92400e" : "var(--gray-400)";
}
function resetCheckboxStyles() {
  updateDsdStyle(); updateRegStyle();
}

function openCopyPanel() {
  const ocId = g('f-ocor').value;
  if (!ocId || !pendingLines.length) {
    alert('Guarda primeiro as linhas antes de copiar.'); return;
  }
  const panel = g('copy-panel');
  const list  = g('copy-uc-list');
  panel.style.display = '';

  // Load other occurrences from same year, excluding current
  list.innerHTML = '<div style="color:var(--gray-400);font-size:12px">A carregar…</div>';
  fetch('ajax-dist.php?list_ocors=1&exclude=' + ocId + '&_=' + Date.now())
    .then(r => r.json())
    .then(data => {
      if (!data.ocors || !data.ocors.length) {
        list.innerHTML = '<div style="color:var(--gray-400);font-size:12px">Sem outras ocorrências neste ano.</div>';
        return;
      }
      // Populate plano filter
      const planos = [...new Set(data.ocors.map(o => o.plano).filter(Boolean))].sort();
      const sel = document.getElementById('copy-plano-filter');
      sel.innerHTML = '<option value="">Todos os planos</option>' +
        planos.map(p => `<option value="${p}">${p}</option>`).join('');
      // Store all ocors for filtering
      window._copyOcors = data.ocors;
      renderCopyList();
    })
    .catch(() => { document.getElementById('copy-uc-list').innerHTML = '<div style="color:var(--red)">Erro ao carregar.</div>'; });
}

function filterCopyList() {
  // Save checked values before re-render
  const checked = new Set([...document.querySelectorAll('.copy-dest:checked')].map(x => x.value));
  renderCopyList();
  // Restore checked state
  document.querySelectorAll('.copy-dest').forEach(cb => {
    if (checked.has(cb.value)) cb.checked = true;
  });
}

function renderCopyList() {
  const list   = document.getElementById('copy-uc-list');
  const plano  = document.getElementById('copy-plano-filter').value;
  const search = (document.getElementById('copy-search')?.value || '').toLowerCase();
  const checked = new Set([...document.querySelectorAll('.copy-dest:checked')].map(x => x.value));

  const selected = (window._copyOcors || []).filter(o => checked.has(String(o.id)));
  const filtered = (window._copyOcors || []).filter(o =>
    !checked.has(String(o.id)) &&
    (!plano  || o.plano === plano) &&
    (!search || o.nome.toLowerCase().includes(search))
  );

  if (!selected.length && !filtered.length) {
    list.innerHTML = '<div style="color:var(--gray-400);font-size:12px">Sem UCs encontradas.</div>';
    return;
  }

  const renderItem = (o, isSel) =>
    `<label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:5px 8px;
            background:${isSel ? '#eff6ff' : '#fff'};
            border:1px solid ${isSel ? 'var(--blue)' : 'var(--gray-200)'};
            border-radius:6px;font-size:13px">
      <input type="checkbox" class="copy-dest" value="${o.id}" ${isSel ? 'checked' : ''} onchange="renderCopyList()">
      <span class="badge badge-blue" style="font-size:10px">${o.plano}</span>
      ${o.nome}
      ${o.n_dist > 0 ? '<span style="font-size:10px;color:var(--orange);margin-left:4px"><i class="fas fa-exclamation-triangle me-1"></i>tem '+o.n_dist+' linha(s)</span>' : ''}
    </label>`;

  list.innerHTML =
    selected.map(o => renderItem(o, true)).join('') +
    (selected.length && filtered.length ? '<hr style="margin:4px 0;border-color:var(--gray-200)">' : '') +
    filtered.map(o => renderItem(o, false)).join('');
}

function executeCopy() {
  const ocId = g('f-ocor').value;
  const dests = [...document.querySelectorAll('.copy-dest:checked')].map(c => c.value);
  if (!dests.length) { alert('Selecciona pelo menos uma UC destino.'); return; }
  if (!confirm('Substituir toda a distribuição nas ' + dests.length + ' UC(s) seleccionadas?\nAs linhas existentes serão apagadas.')) return;

  // Build copy payload: pendingLines with dsd=0
  const copyLines = pendingLines.map(l => ({...l, dsd: 0}));

  g('save-status').textContent = 'A copiar…';
  fetch('ajax-copy-dist.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({src_ocor: ocId, dest_ocors: dests, lines: copyLines,
                          ano_letivo_id: distAnoLetivoId})
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      g('save-status').textContent = '<i class="fas fa-check-circle me-1"></i>Copiado para ' + d.copied + ' UC(s)';
      setTimeout(() => window.location.reload(), 800);
    } else {
      g('save-status').textContent = '<i class="fas fa-times-circle me-1"></i>' + d.error;
    }
  })
  .catch(() => g('save-status').textContent = '<i class="fas fa-times-circle me-1"></i>Erro ao copiar');
}

function closeModal() {
  g('modal-dist').style.display = 'none';
  document.body.style.overflow  = '';
  pendingLines = []; editingIdx = -1;
}

// ── Input listeners ───────────────────────────────────────────
['f-tT','f-hT','f-tTP','f-hTP','f-tL','f-hL','f-tSem','f-hSem','f-tOT','f-hOT','f-semanas'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', refreshLineCalc);
});

// ── Scroll position ─────────────────────────────────────────
if (savedScroll > 0) window.scrollTo({top: savedScroll, behavior: 'instant'});

// Save scroll position before submitting forms
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
  // Show/hide the sem-SD block
  // Treeview is always visible - no action needed
  const el = document.getElementById('search-count');
  if (el) el.textContent = (q && !semSD) ? ucVisible + ' UC(s)' : '';
}

// ── Bootstrap (equivalente às 3 branches PHP-condicionais que existiam inline) ──
if (distBootstrap) {
  window.addEventListener('DOMContentLoaded', () => {
    if (distBootstrap.type === 'edit')     openModal(distBootstrap.id);
    else if (distBootstrap.type === 'new')     openModal(null, distBootstrap.ocorId);
    else if (distBootstrap.type === 'editUc')  openModal(null, distBootstrap.ocorId);
  });
}
