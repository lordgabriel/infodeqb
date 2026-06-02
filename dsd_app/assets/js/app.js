/* DSD – Main JS */
'use strict';

// ── Filter tables live ──────────────────────────────────────
function filterTable(inputId, tableId, cols) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(tr => {
      const text = (cols || []).map(i => tr.cells[i]?.textContent.toLowerCase()).join(' ');
      tr.style.display = text.includes(q) ? '' : 'none';
    });
  });
}

// ── Confirm delete ──────────────────────────────────────────
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-confirm]');
  if (!btn) return;
  if (!confirm(btn.dataset.confirm || 'Tem a certeza?')) e.preventDefault();
});

// ── Tabs ────────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const group = btn.closest('[data-tabs]') || btn.parentElement.parentElement;
    group.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    group.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const target = document.getElementById(btn.dataset.target);
    if (target) target.classList.add('active');
  });
});



// ── Print ────────────────────────────────────────────────────
function printPage() { window.print(); }

// ── Select filter ────────────────────────────────────────────
document.querySelectorAll('[data-filter-select]').forEach(sel => {
  sel.addEventListener('change', () => {
    const col = parseInt(sel.dataset.filterSelect);
    const val = sel.value.toLowerCase();
    const tableId = sel.dataset.filterTable;
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(tr => {
      const cell = tr.cells[col]?.textContent.trim().toLowerCase() || '';
      tr.style.display = (!val || cell === val) ? '' : 'none';
    });
  });
});

// ── Inline editable distribuicao cells (AJAX) ─────────────

// ── Collapsible sections ─────────────────────────────────────
function doCollapse(trigger, collapse) {
  const id = trigger.dataset.collapseTrigger;
  // collapse/show rows that contain this id in their data-collapse-group
  document.querySelectorAll('[data-collapse-group]').forEach(r => {
    const groups = r.dataset.collapseGroup.split(' ');
    if (groups.includes(id)) r.style.display = collapse ? 'none' : '';
  });
  const summary = trigger.querySelector('.collapse-summary');
  if (summary) summary.style.display = collapse ? 'inline' : 'none';
  const icon = trigger.querySelector('.collapse-icon');
  if (icon) icon.textContent = collapse ? '▸' : '▾';
  trigger.dataset.collapsed = collapse ? '1' : '0';
}

function collapseAll(prefix) {
  document.querySelectorAll('[data-collapse-trigger]').forEach(t => {
    if (!prefix || t.dataset.collapseTrigger.startsWith(prefix)) doCollapse(t, true);
  });
}
function expandAll(prefix) {
  document.querySelectorAll('[data-collapse-trigger]').forEach(t => {
    if (!prefix || t.dataset.collapseTrigger.startsWith(prefix)) doCollapse(t, false);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-collapse-trigger]').forEach(trigger => {
    trigger.style.cursor = 'pointer';
    trigger.addEventListener('click', e => {
      if (e.target.closest('a,button,input,select')) return;
      const isCollapsed = trigger.dataset.collapsed === '1';
      doCollapse(trigger, !isCollapsed);
    });
  });
});
