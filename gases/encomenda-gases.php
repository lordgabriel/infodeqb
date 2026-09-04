<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$_iqAdminsGases = $_SESSION['_iq_section_admins']['gases'] ?? array();
$isGasAdmin = $isAdmin || in_array($_iqCurrentUser ?? '', $_iqAdminsGases);

if (!$isGasAdmin) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

$pageTitle = 'Encomenda de Gases — Air Liquide';
$mainClass = 'iq-main';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>
<style>
/* ── Base font size ── */
.main-layout, .main-layout input, .main-layout textarea, .main-layout select, .main-layout button {
  font-size: 1rem;
}
.form-label { font-size: .95rem; }

/* ── Layout ── */
.main-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.25rem;
  padding: 1rem 0 2rem;
  align-items: start;
}
@media (max-width: 820px) {
  .main-layout { grid-template-columns: 1fr; }
  .preview-col { position: static !important; }
}

/* ── Cards ── */
.iq-card {
  background: var(--iq-surface);
  border: 1px solid var(--iq-border);
  border-radius: var(--iq-r);
  box-shadow: 0 1px 3px rgba(0,0,0,.07);
  padding: .9rem 1rem 1rem;
  margin-bottom: .75rem;
}
.iq-card-title {
  font-size: .68rem;
  font-weight: 600;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--iq-muted);
  margin-bottom: .7rem;
  display: flex;
  align-items: center;
  gap: .45rem;
}

/* ── Gas rows ── */
.gas-rows { display: flex; flex-direction: column; gap: .3rem; margin-bottom: .55rem; }
.gas-row {
  display: grid;
  grid-template-columns: 52px 1fr 26px;
  gap: .3rem;
  align-items: center;
}
.gas-row input[type=number] { text-align: center; }

.btn-add-gas {
  width: 100%;
  background: none;
  border: 1px dashed var(--iq-border2, var(--iq-gray-300));
  border-radius: 6px;
  color: var(--iq-muted);
  font-family: inherit;
  font-size: .8rem;
  padding: .28rem .7rem;
  text-align: left;
  cursor: pointer;
  transition: border-color .15s, color .15s, background .15s;
}
.btn-add-gas:hover {
  border-color: var(--iq-blue);
  color: var(--iq-blue);
  background: var(--iq-blue-light);
}

.btn-row-remove {
  background: none;
  border: 1px solid var(--iq-border2, var(--iq-gray-300));
  border-radius: 5px;
  color: var(--iq-gray-400, #9ca3af);
  cursor: pointer;
  font-size: .85rem;
  line-height: 1;
  padding: .18rem .32rem;
  transition: border-color .15s, color .15s;
}
.btn-row-remove:hover { border-color: #ef4444; color: #ef4444; }

/* ── Preview ── */
.preview-col { position: sticky; top: 1rem; }
.preview-box {
  background: var(--iq-surface);
  border: 1px solid var(--iq-border);
  border-radius: var(--iq-r);
  box-shadow: 0 1px 3px rgba(0,0,0,.07);
  overflow: hidden;
}
.preview-head {
  background: var(--iq-nav-bg, #16232b);
  color: rgba(255,255,255,.6);
  padding: .5rem .85rem;
  font-size: .68rem;
  font-weight: 600;
  letter-spacing: .06em;
  text-transform: uppercase;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.pv-count { color: rgba(255,255,255,.38); font-weight: 400; text-transform: none; letter-spacing: 0; }
.preview-meta {
  background: var(--iq-gray-50, #f9fafb);
  border-bottom: 1px solid var(--iq-border);
  padding: .55rem .85rem;
  font-size: .88rem;
  line-height: 2;
}
.preview-meta > div { display: flex; gap: .4rem; }
.pv-lbl {
  font-weight: 600;
  font-size: .75rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--iq-muted);
  min-width: 52px;
  flex-shrink: 0;
  padding-top: 3px;
}
.pv-val { color: var(--iq-text); word-break: break-all; }
.preview-body {
  font-family: ui-monospace, 'Cascadia Mono', Consolas, monospace;
  font-size: .84rem;
  line-height: 1.7;
  padding: .8rem .85rem;
  white-space: pre-wrap;
  color: var(--iq-gray-700, #374151);
  max-height: 430px;
  overflow-y: auto;
}

/* ── Attachment bar (below preview) ── */
.anexo-bar {
  margin-top: .65rem;
  background: var(--iq-blue-light, #dbeafe);
  border: 1px solid #93c5fd;
  border-radius: var(--iq-r);
  padding: .65rem .85rem;
  display: flex;
  align-items: center;
  gap: .55rem;
  font-size: .8rem;
}
.anexo-bar-label {
  font-size: .67rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--iq-blue-dark, #1a5a94);
  white-space: nowrap;
}
.anexo-bar-nome {
  flex: 1;
  font-size: .82rem;
  font-weight: 600;
  color: var(--iq-blue-dark, #1a5a94);
  word-break: break-all;
}

/* ── Gas picker ── */
.gas-picker {
  position: fixed;
  z-index: 1050;
  background: var(--iq-surface);
  border: 1px solid var(--iq-border2, var(--iq-gray-300));
  border-radius: var(--iq-r);
  box-shadow: 0 4px 12px rgba(0,0,0,.1);
  max-height: 280px;
  overflow-y: auto;
  min-width: 240px;
}
.gas-picker-item {
  padding: .38rem .75rem;
  font-size: .82rem;
  color: var(--iq-text);
  cursor: pointer;
  white-space: nowrap;
}
.gas-picker-item:hover { background: var(--iq-blue-light); color: var(--iq-blue); }

/* ── Toast ── */
.iq-toast {
  position: fixed;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%) translateY(8px);
  background: var(--iq-gray-800, #1f2937);
  color: #fff;
  padding: .45rem 1rem;
  border-radius: 20px;
  font-size: .8rem;
  opacity: 0;
  transition: opacity .22s, transform .22s;
  pointer-events: none;
  white-space: nowrap;
  z-index: 2000;
}
.iq-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>

<div class="container-fluid px-3 px-md-4">
  <div class="iq-page-header">
    <div>
      <h1 class="iq-page-title">
        <i class="fas fa-flask me-2" style="color:var(--iq-blue)"></i>Encomenda de Gases
      </h1>
      <p class="iq-page-sub">Gerador de email para Air Liquide</p>
    </div>
  </div>

  <div class="main-layout">

    <!-- ── Coluna esquerda: formulário ── -->
    <div>

      <!-- Tipo de encomenda -->
      <div class="iq-card">
        <div class="iq-card-title"><i class="fas fa-tag"></i> Tipo de Encomenda</div>
        <div class="d-flex gap-2">
          <button type="button" id="btn-tipo-garrafas" class="btn btn-sm btn-primary" onclick="setTipo('garrafas')">
            <i class="fas fa-flask me-1"></i>Garrafas
          </button>
          <button type="button" id="btn-tipo-azoto" class="btn btn-sm btn-outline-secondary" onclick="setTipo('azoto')">
            <i class="fas fa-snowflake me-1"></i>Azoto Líquido
          </button>
        </div>
      </div>

      <!-- Garrafas cheias -->
      <div class="iq-card" id="section-garrafas-cheias">
        <div class="iq-card-title">
          <i class="fas fa-check-circle" style="color:#16a34a"></i> Garrafas Cheias
          <span class="badge rounded-pill ms-1" style="background:#dcfce7;color:#15803d;font-weight:500;font-size:.63rem">CHEIAS</span>
        </div>
        <div id="rows-cheias" class="gas-rows"></div>
        <button class="btn-add-gas" type="button" onclick="showGasPicker('cheias', this)">
          <i class="fas fa-plus fa-xs me-1"></i>Adicionar gás
        </button>
      </div>

      <!-- Garrafas vazias -->
      <div class="iq-card" id="section-garrafas-vazias">
        <div class="iq-card-title">
          <i class="fas fa-circle" style="color:#b45309"></i> Devolução — Garrafas Vazias
          <span class="badge rounded-pill ms-1" style="background:#fef3c7;color:#b45309;font-weight:500;font-size:.63rem">VAZIAS</span>
        </div>
        <div id="rows-vazias" class="gas-rows"></div>
        <button class="btn-add-gas" type="button" onclick="showGasPicker('vazias', this)">
          <i class="fas fa-plus fa-xs me-1"></i>Adicionar gás
        </button>
      </div>

      <!-- Azoto Líquido -->
      <div class="iq-card" id="section-azoto" style="display:none">
        <div class="iq-card-title"><i class="fas fa-snowflake" style="color:#0ea5e9"></i> Azoto Líquido</div>
        <div class="row g-2 mb-2">
          <div class="col-sm-4">
            <label class="form-label">Volume (litros)</label>
            <input type="number" class="form-control form-control-sm" id="az_volume" min="1" placeholder="ex: 25" oninput="updatePreview()">
          </div>
          <div class="col-sm-8">
            <label class="form-label">Laboratório</label>
            <input type="text" class="form-control form-control-sm" id="az_lab" placeholder="ex: E301" oninput="updatePreview()">
          </div>
        </div>
      </div>

      <!-- Detalhes -->
      <div class="iq-card">
        <div class="iq-card-title"><i class="fas fa-file-invoice"></i> Detalhes da Encomenda</div>
        <div class="row g-2 mb-2">
          <div class="col-sm-4">
            <label class="form-label">Conta</label>
            <input type="text" class="form-control form-control-sm" id="conta" placeholder="ex: 12233224">
          </div>
          <div class="col-sm-8">
            <label class="form-label">Contacto</label>
            <input type="text" class="form-control form-control-sm" id="contacto" placeholder="Nome / telemóvel">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">Local de entrega</label>
          <input type="text" class="form-control form-control-sm" id="local" placeholder="ex: Central de gases da FEUP">
        </div>
      </div>

      <!-- Destinatários -->
      <div class="iq-card">
        <div class="iq-card-title"><i class="fas fa-envelope"></i> Destinatários</div>
        <div class="row g-2 mb-2">
          <div class="col-sm-6">
            <label class="form-label">Para (To:)</label>
            <input type="text" class="form-control form-control-sm" id="to" value="encomendagarrafas.pt@airliquide.com">
          </div>
          <div class="col-sm-6">
            <label class="form-label">CC <small class="text-muted">— separar com ;</small></label>
            <input type="text" class="form-control form-control-sm" id="cc" placeholder="email1@exemplo.pt; email2@exemplo.pt">
          </div>
        </div>
        <div>
          <label class="form-label">Assunto</label>
          <input type="text" class="form-control form-control-sm" id="assunto" readonly
            style="background:var(--iq-gray-50,#f9fafb);cursor:default">
        </div>
      </div>

      <!-- hidden: from -->
      <input type="hidden" id="from_name"  value="Luís Martins">
      <input type="hidden" id="from_email" value="gases@fe.up.pt">

      <!-- Acções -->
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-sm btn-primary" type="button" onclick="abrirMailto()">
          <i class="fas fa-envelope me-1"></i>Abrir no cliente de email
        </button>
        <button class="btn btn-sm btn-success" type="button" onclick="downloadEml()">
          <i class="fas fa-download me-1"></i>Download .eml (com anexo)
        </button>
        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="copiarCorpo()">
          <i class="fas fa-copy me-1"></i>Copiar corpo
        </button>
      </div>
      <div class="mt-2" style="font-size:.78rem;color:var(--iq-muted);line-height:1.6">
        <i class="fas fa-info-circle fa-xs me-1"></i>"Abrir no cliente de email" não inclui anexo.<br>
        Para enviar com anexo — usar <strong>Download .eml</strong>:<br>
        &nbsp;· <strong>Thunderbird</strong>: abre o ficheiro → abre directamente para envio<br>
        &nbsp;· <strong>Outlook</strong>: abre o ficheiro → clica <strong>Reencaminhar</strong> → apaga "FW:" do assunto → define destinatário → envia
      </div>

    </div><!-- /coluna esquerda -->

    <!-- ── Coluna direita: pré-visualização + anexo ── -->
    <div class="preview-col">

      <div class="preview-box">
        <div class="preview-head">
          <span>Pré-visualização</span>
          <span class="pv-count" id="pv-badge">—</span>
        </div>
        <div class="preview-meta">
          <div><span class="pv-lbl">Para</span><span class="pv-val" id="pv-to">—</span></div>
          <div><span class="pv-lbl">CC</span><span class="pv-val" id="pv-cc">—</span></div>
          <div><span class="pv-lbl">Assunto</span><span class="pv-val" id="pv-subject">—</span></div>
        </div>
        <div class="preview-body" id="pv-body"></div>
      </div>

      <!-- Anexo (abaixo da pré-visualização) -->
      <div class="anexo-bar">
        <i class="fas fa-paperclip" style="color:var(--iq-blue);font-size:.85rem;flex-shrink:0"></i>
        <span class="anexo-bar-label">Anexo</span>
        <span class="anexo-bar-nome" id="anexo-nome">—</span>
        <label class="btn btn-sm btn-outline-secondary mb-0 flex-shrink-0" style="cursor:pointer;font-size:.75rem">
          <i class="fas fa-exchange-alt fa-xs me-1"></i>Trocar
          <input type="file" id="anexo-file" accept=".pdf" style="display:none" onchange="onAnexoChange(this)">
        </label>
        <button id="btn-repor" class="btn btn-sm btn-outline-secondary flex-shrink-0" style="font-size:.75rem;display:none" onclick="reporAnexoPadrao()" title="Repor ficheiro original incorporado">
          <i class="fas fa-undo fa-xs"></i>
        </button>
      </div>

      <!-- Nota + Info adicional (abaixo do anexo) -->
      <div class="iq-card" style="margin-top:.65rem">
        <div class="mb-2">
          <label class="form-label" style="font-size:.82rem">N.º nota de encomenda</label>
          <input type="text" class="form-control form-control-sm" id="nota" value="I48_278_C26" readonly
            style="background:var(--iq-gray-50,#f9fafb);cursor:default">
        </div>
        <div>
          <div class="d-flex align-items-center justify-content-between mb-1">
            <label class="form-label mb-0" style="font-size:.82rem">Informação adicional</label>
            <button type="button" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .45rem" id="btn-edit-info" onclick="editarInfoExtra()">
              <i class="fas fa-pencil-alt fa-xs me-1"></i>Editar
            </button>
          </div>
          <textarea class="form-control form-control-sm" id="info_extra" rows="3" readonly
            style="background:var(--iq-gray-50,#f9fafb);color:var(--iq-text);cursor:default;resize:none">O valor da nota de encomenda corresponde à despesa previsível do concurso para 2026</textarea>
        </div>
      </div>

    </div><!-- /coluna direita -->

  </div><!-- /main-layout -->
</div>

<div class="iq-toast" id="toast"></div>

<script>
var GASES = [
  'Acetileno 99,5% B20',
  'Acetileno 99,6% B40',
  'AR sintético (N2+O2) 99,999% B50',
  'Árgon 99,999% B50',
  'CO2/Árgon 18%/82% B20',
  'H2/Árgon 5%/95% B50',
  'Azoto 99,999% B10',
  'Azoto 99,999% B50',
  'CO 99,9% B10',
  'CO2 99,998% B50',
  'CO2 com Tubo de Pesca 99,998% B50',
  'Etano 99,95% B20',
  'Hélio 99,999% B50',
  'Hélio 99,9999% B50',
  'Hidrogénio 99,999% B50',
  'Isobutano 99,5% B83',
  'Metano 99,95% B50',
  'Metano 99,995% B10',
  'N2O 99,5% B50',
  'Oxigénio 99,5% B20',
  'Oxigénio 99,995% B50',
  'Propano 99,95% B20',
  'Azoto líquido 99,8%',
];

var _ANEXO_DEFAULT_NOME = 'I48_278_C26.pdf';
var _ANEXO_DEFAULT_B64  = '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ZXMgCi9LaWRzIFsgOCAwIFIgXSAKL0NvdW50IDEgCi9NZWRpYUJveCAzIDAgUiAKL0Nyb3BCb3ggNCAwIFIgCj4+IAplbmRvYmogCjMgMCBvYmogClsgMCAwIDU5NSA4NDEgXSAKZW5kb2JqIAo0IDAgb2JqIApbIDAgMCA1OTUgODQxIF0gCmVuZG9iaiAKNiAwIG9iaiAKPDwgCi9Qcm9jU2V0IDcgMCBSIAovRm9udCA8PCAKLzkgOSAwIFIgIAovZCAxMyAwIFIgIAo+PiAKL1hPYmplY3QgPDwgCi9pbWcwIDEwIDAgUiAgCj4+IAo+PiAKZW5kb2JqIAo3IDAgb2JqIApbIC9QREYgL1RleHQgL0ltYWdlQiAvSW1hZ2VDIC9JbWFnZUkgIF0gCmVuZG9iaiAKOCAwIG9iaiAKPDwgCi9UeXBlIC9QYWdlIAovUGFyZW50IDIgMCBSIAovUmVzb3VyY2VzIDYgMCBSIAovQ29udGVudHMgWyA1IDAgUiBdIAo+PiAKZW5kb2JqIAo5IDAgb2JqIAo8PCAKL1R5cGUgL0ZvbnQgCi9TdWJ0eXBlIC9UcnVlVHlwZSAKL0Jhc2VGb250IC9BQUFBQUIrQ2FsaWJyaSAKL0ZpcnN0Q2hhciAzMiAKL0xhc3RDaGFyIDEwMSAKL1dpZHRocyAxNCAwIFIgCi9Gb250RGVzY3JpcHRvciAxNiAwIFIgCi9Ub1VuaWNvZGUgMTUgMCBSIAo+PiAKZW5kb2JqIAoxMCAwIG9iaiAKPDwgCi9UeXBlIC9YT2JqZWN0IAovU3VidHlwZSAvSW1hZ2UgCi9OYW1lIC9pbWcwIAovTGVuZ3RoIDI0OTcgCi9GaWx0ZXIgWyAvRmxhdGVEZWNvZGUgXSAKL1dpZHRoIDE3NyAKL0hlaWdodCA1OSAKL0JpdHNQZXJDb21wb25lbnQgOCAKL0NvbG9yU3BhY2UgMTEgMCBSIAo+PiAKc3RyZWFtCnic7ZqPa9tIFsdhHApj8OAcg8GIcLbWaZqeTmjF6bRO0lY9nRDxYRG0qXa1Om+z3jreJeAVLrpiKPO333szkq2kP7bxXUlY+tpao5/+zJuv3nszrhBf7Iu9Y7wH1u/Lz15p/eqz39P8uwa8aYRzvss/aNS7a8CbRnmXd7qca1q32+lqYN2Ohke6Gh5m9w64AWBg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Nn3GjBlzZs2eN2/e/mmTlixZsnL5ipUrV65Zs2bTpk3btm3buX3Hrl279u7Zc2Df/ktrVh46dOjYsaOnTh4/efLkuXPnLl68ePXq1Zs3b967c/feg/sfbt35f+/Kw4cPnz59+vL5i1cvXr59/eb9u3f/3zz6/+7p/1ev/r9//f/d2/8fXv3/8Pb/l3efPn78/vnLt2/ffn37/v/z5/9fPv3/8vXH/+//f3/8//vb/79ffv38+evfr5///oGIX3+BFJDJMAoGDgAAg8jKA2VuZHN0cmVhbSAKZW5kb2JqIAoxMyAwIG9iaiAKPDwgCi9UeXBlIC9Gb250IAovU3VidHlwZSAvVHJ1ZVR5cGUgCi9CYXNlRm9udCAvQUFBQUFEK0NhbGlicmksQm9sZCAKL0ZpcnN0Q2hhciAzMiAKL0xhc3RDaGFyIDk0IAovV2lkdGhzIDE5IDAgUiAKL0ZvbnREZXNjcmlwdG9yIDIxIDAgUiAKL1RvVW5pY29kZSAyMCAwIFIgCj4+IAplbmRvYmogCjE0IDAgb2JqIApbIAo1MTcgCjQ3OSAKNDcxIAoyNTIgCjIyNiAKNTA3IAozODYgCjU3OSAKNTQzIAo2NzMgCjY0MiAKMjUyIAo0ODggCjg1NSAKNDU5IAo0MjAgCjY2MiAKNDU5IAo1MDcgCjUwNyAKNTA3IAo1MzMgCjYxNSAKNDg3IAo2MzEgCjI1MiAKMjUwIAo2NDYgCjU0NCAKNTA3IAo0MjIgCjQ4OCAKNDMzIAo3OTkgCjUyNyAKMzAzIAozOTEgCjMwMyAKMzQ5IAozMDYgCjUwNyAKMjY4IAo1MjUgCjMzNSAKNTI1IAo3MTUgCjMwNSAKNDk4IAo1MjUgCjQ3OSAKMjMwIAoyMzAgCjQyMyAKNTI1IAo1MjUgCjUyNSAKODk0IAo0MjMgCjQ3OSAKNTI1IAo0NTIgCjQ5OCAKNTA3IAo1MDcgCjUwNyAKNTA3IAo1MjUgCjgzNCAKNTY3IAo0OTggCl0gCmVuZG9iaiAKMTYgMCBvYmogCjw8IAovVHlwZSAvRm9udERlc2NyaXB0b3IgCi9Bc2NlbnQgOTUyIAovQ2FwSGVpZ2h0IDUwMCAKL0Rlc2NlbnQgLTI2OSAKL0ZsYWdzIDQgCi9Gb250QkJveCAxNyAwIFIgCi9Gb250TmFtZSAvQUFBQUFCK0NhbGlicmkgCi9JdGFsaWNBbmdsZSAwCi9TdGVtViAwIAovU3RlbUggMCAKL0F2Z1dpZHRoIDUyMSAKL0ZvbnRGaWxlMiAxOCAwIFIgCi9MZWFkaW5nIDAgCi9NYXhXaWR0aCAxNzQzIAovTWlzc2luZ1dpZHRoIDUyMSAKL1hIZWlnaHQgMCAKPj4gCmVuZG9iaiAKMTcgMCBvYmogClsgLTUwMyAtMzEzIDEyNDAgMTAyNiBdIAplbmRvYmogCjE5IDAgb2JqIApbIAo0NTkgCjQ5NCAKNDE4IAo1MzcgCjI0NiAKNTM3IAo1MDMgCjIyNiAKNDg4IAo1MzcgCjQ3NCAKNTM3IAozNTUgCjI0NiAKNjUzIAo0NzMgCjM5OSAKNTM4IAo1MzIgCjM0NyAKNjU5IAo4MTMgCjMxMiAKMzEyIAozMDYgCjI2NyAKNDM1IAo1MDcgCjUwNyAKNTA3IAo0MzAgCjI2NyAKNTA3IAo1MjkgCjUwNyAKNjc2IAo2MzAgCjQ1OSAKNDE4IAo0OTQgCjUzNyAKNDk0IAo4NzQgCjQ5NSAKNDIzIAoyNDYgCjUzNyAKNDk0IAo2MDYgCjY4NiAKNTkxIAo1NjMgCjMxNiAKNTAzIAo1MDcgCjUwNyAKNTA3IAo1MDcgCjI1OCAKMjc2IAo2MzEgCjUzNyAKNTM4IApdIAplbmRvYmogCjIxIDAgb2JqIAo8PCAKL1R5cGUgL0ZvbnREZXNjcmlwdG9yIAovQXNjZW50IDk1MiAKL0NhcEhlaWdodCA1MDAgCi9EZXNjZW50IC0yNjkgCi9GbGFncyA0IAovRm9udEJCb3ggMjIgMCBSIAovRm9udE5hbWUgL0FBQUFBRCtDYWxpYnJpLEJvbGQgCi9JdGFsaWNBbmdsZSAwCi9TdGVtViAwIAovU3RlbUggMCAKL0F2Z1dpZHRoIDUzNiAKL0ZvbnRGaWxlMiAyMyAwIFIgCi9MZWFkaW5nIDAgCi9NYXhXaWR0aCAxNzgxIAovTWlzc2luZ1dpZHRoIDUzNiAKL1hIZWlnaHQgMCAKPj4gCmVuZG9iaiAKMjIgMCBvYmogClsgLTUxOSAtMzQ5IDEyNjMgMTAzOSBdIAplbmRvYmogCjI0IDAgb2JqIAooUG93ZXJlZCBCeSBDcnlzdGFsKSAKZW5kb2JqIAoyNSAwIG9iaiAKKENyeXN0YWwgUmVwb3J0cykgCmVuZG9iaiAKMjYgMCBvYmogCjw8IAovUHJvZHVjZXIgKFBvd2VyZWQgQnkgQ3J5c3RhbCkgIAovQ3JlYXRvciAoQ3J5c3RhbCBSZXBvcnRzKSAgCj4+IAplbmRvYmogCnhyZWYgCjAgMjcgCjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDAxNyAwMDAwMCBuIAowMDAwMDQxMzc3IDAwMDAwIG4gCjAwMDAwNDE0NzYgMDAwMDAgbiAKMDAwMDA0MTUxMCAwMDAwMCBuIAowMDAwMDAwMTk0IDAwMDAwIG4gCjAwMDAwNDE1NDQgMDAwMDAgbiAKMDAwMDA0MTY1NCAwMDAwMCBuIAowMDAwMDQxNzEyIDAwMDAwIG4gCjAwMDAwNDE4MDQgMDAwMDAgbiAKMDAwMDA0MTk3OCAwMDAwMCBuIAowMDAwMDQ0NjcwIDAwMDAwIG4gCjAwMDAwNDQ3MjQgMDAwMDAgbiAKMDAwMDA0NTIyNCAwMDAwMCBuIAowMDAwMDQ1NDAzIDAwMDAwIG4gCjAwMDAwMDMwMzMgMDAwMDAgbiAKMDAwMDA0NTc3NyAwMDAwMCBuIAowMDAwMDQ2MDUzIDAwMDAwIG4gCjAwMDAwMDM3NzQgMDAwMDAgbiAKMDAwMDA0NjA5NiAwMDAwMCBuIAowMDAwMDI1MTU3IDAwMDAwIG4gCjAwMDAwNDY0MzUgMDAwMDAgbiAKMDAwMDA0NjcxNiAwMDAwMCBuIAowMDAwMDI1ODYyIDAwMDAwIG4gCjAwMDAwNDY3NTkgMDAwMDAgbiAKMDAwMDA0Njc5OSAwMDAwMCBuIAowMDAwMDQ2ODM2IDAwMDAwIG4gCnRyYWlsZXIgCjw8IAovU2l6ZSAyNyAKL1Jvb3QgMSAwIFIgCi9JbmZvIDI2IDAgUiAKPj4gCnN0YXJ0eHJlZiAKNDY5MjQgCiUlRU9GDQo=';

/* Anexo activo — começa com o embutido, actualiza do IDB assim que disponível */
var _anexoB64  = _ANEXO_DEFAULT_B64;
var _anexoNome = _ANEXO_DEFAULT_NOME;

/* ── IndexedDB helpers ── */
function _idbOpen(cb) {
  try {
    var req = indexedDB.open('iq_encomenda_gases', 1);
    req.onupgradeneeded = function(e) { e.target.result.createObjectStore('anexo'); };
    req.onsuccess = function(e) { cb(e.target.result); };
    req.onerror   = function()  { cb(null); };
  } catch(e) { cb(null); }
}
function _idbSave(b64, nome, onDone) {
  _idbOpen(function(db) {
    if (!db) { onDone(false); return; }
    var tx = db.transaction('anexo', 'readwrite');
    tx.objectStore('anexo').put({b64: b64, nome: nome}, 'current');
    tx.oncomplete = function() { onDone(true); };
    tx.onerror    = function() { onDone(false); };
  });
}
function _idbLoad(cb) {
  _idbOpen(function(db) {
    if (!db) { cb(null); return; }
    var tx  = db.transaction('anexo', 'readonly');
    var req = tx.objectStore('anexo').get('current');
    req.onsuccess = function(e) { cb(e.target.result || null); };
    req.onerror   = function()  { cb(null); };
  });
}
function _idbDelete(cb) {
  _idbOpen(function(db) {
    if (!db) { cb && cb(); return; }
    var tx = db.transaction('anexo', 'readwrite');
    tx.objectStore('anexo').delete('current');
    tx.oncomplete = function() { cb && cb(); };
    tx.onerror    = function() { cb && cb(); };
  });
}

function _refreshAnexoUI() {
  document.getElementById('anexo-nome').textContent = _anexoNome;
  var isDefault = (_anexoNome === _ANEXO_DEFAULT_NOME);
  document.getElementById('btn-repor').style.display = isDefault ? 'none' : '';
}

function onAnexoChange(input) {
  var file = input.files[0];
  if (!file) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    _anexoB64  = e.target.result.split(',')[1];
    _anexoNome = file.name;
    var nomeSemExt = file.name.replace(/\.pdf$/i, '');
    var notaEl = document.getElementById('nota');
    if (notaEl && !notaEl.value.trim()) { notaEl.value = nomeSemExt; }
    else if (notaEl) { notaEl.value = nomeSemExt; }
    updatePreview();
    _idbSave(_anexoB64, _anexoNome, function(ok) {
      _refreshAnexoUI();
      showToast(ok ? 'Anexo guardado: ' + file.name : 'Anexo seleccionado (não foi possível guardar permanentemente)');
    });
  };
  reader.readAsDataURL(file);
}


function editarInfoExtra() {
  var ta = document.getElementById('info_extra');
  var btn = document.getElementById('btn-edit-info');
  ta.readOnly = false;
  ta.style.background = '';
  ta.style.cursor = 'text';
  ta.style.resize = '';
  ta.focus();
  btn.innerHTML = '<i class="fas fa-lock fa-xs me-1"></i>Bloquear';
  btn.onclick = function() {
    ta.readOnly = true;
    ta.style.background = 'var(--iq-gray-50,#f9fafb)';
    ta.style.cursor = 'default';
    ta.style.resize = 'none';
    btn.innerHTML = '<i class="fas fa-pencil-alt fa-xs me-1"></i>Editar';
    btn.onclick = editarInfoExtra;
  };
}

function reporAnexoPadrao() {
  _idbDelete(function() {
    _anexoB64  = _ANEXO_DEFAULT_B64;
    _anexoNome = _ANEXO_DEFAULT_NOME;
    _refreshAnexoUI();
    showToast('Reposto: ' + _ANEXO_DEFAULT_NOME);
  });
}

/* ── Gas picker ── */
function showGasPicker(tipo, anchorEl) {
  var existing = document.getElementById('gas-picker');
  if (existing) { existing.remove(); return; }
  var picker = document.createElement('div');
  picker.id = 'gas-picker';
  picker.className = 'gas-picker';
  GASES.forEach(function(g) {
    var item = document.createElement('div');
    item.className = 'gas-picker-item';
    item.textContent = g;
    item.addEventListener('mousedown', function(e) {
      e.preventDefault();
      picker.remove();
      addGasRow(tipo, 1, g);
    });
    picker.appendChild(item);
  });
  var rect = anchorEl.getBoundingClientRect();
  picker.style.top   = (rect.bottom + 4) + 'px';
  picker.style.left  = rect.left + 'px';
  picker.style.width = Math.max(rect.width, 260) + 'px';
  document.body.appendChild(picker);
  function closePicker(e) {
    if (!picker.contains(e.target) && e.target !== anchorEl) {
      picker.remove();
      document.removeEventListener('click', closePicker);
    }
  }
  setTimeout(function() { document.addEventListener('click', closePicker); }, 0);
}

function makeGasSelect(selectedGas) {
  var sel = document.createElement('select');
  sel.className = 'form-select form-select-sm';
  GASES.forEach(function(g) {
    var opt = document.createElement('option');
    opt.value = g; opt.textContent = g;
    if (g === selectedGas) opt.selected = true;
    sel.appendChild(opt);
  });
  sel.addEventListener('change', updatePreview);
  return sel;
}

function addGasRow(tipo, qty, gas) {
  var container = document.getElementById('rows-' + tipo);
  var row = document.createElement('div');
  row.className = 'gas-row';
  var qtyInput = document.createElement('input');
  qtyInput.type = 'number'; qtyInput.min = '1'; qtyInput.max = '99';
  qtyInput.value = qty || 1;
  qtyInput.className = 'form-control form-control-sm';
  qtyInput.addEventListener('input', updatePreview);
  var sel = makeGasSelect(gas);
  var rmBtn = document.createElement('button');
  rmBtn.type = 'button'; rmBtn.className = 'btn-row-remove';
  rmBtn.innerHTML = '&times;'; rmBtn.title = 'Remover';
  rmBtn.addEventListener('click', function() { row.remove(); updatePreview(); });
  row.appendChild(qtyInput); row.appendChild(sel); row.appendChild(rmBtn);
  container.appendChild(row);
  updatePreview();
  qtyInput.focus(); qtyInput.select();
}

function getRows(tipo) {
  var rows = [];
  document.querySelectorAll('#rows-' + tipo + ' .gas-row').forEach(function(row) {
    rows.push({ qty: parseInt(row.querySelector('input').value, 10) || 1, gas: row.querySelector('select').value });
  });
  return rows;
}

var _tipo = 'garrafas'; // 'garrafas' | 'azoto'

function setTipo(t) {
  _tipo = t;
  document.getElementById('btn-tipo-garrafas').className = 'btn btn-sm ' + (t === 'garrafas' ? 'btn-primary' : 'btn-outline-secondary');
  document.getElementById('btn-tipo-azoto').className    = 'btn btn-sm ' + (t === 'azoto'    ? 'btn-primary' : 'btn-outline-secondary');
  document.getElementById('section-garrafas-cheias').style.display = t === 'garrafas' ? '' : 'none';
  document.getElementById('section-garrafas-vazias').style.display = t === 'garrafas' ? '' : 'none';
  document.getElementById('section-azoto').style.display           = t === 'azoto'    ? '' : 'none';
  updatePreview();
}

function fld(id) { return document.getElementById(id).value.trim(); }

function getData() {
  return {
    to: fld('to'), cc: fld('cc'),
    from_name: fld('from_name'), from_email: fld('from_email'),
    conta: fld('conta'), local: fld('local'), contacto: fld('contacto'),
    nota: fld('nota'), info_extra: fld('info_extra'),
    assunto: fld('assunto'),
    cheias: getRows('cheias'), vazias: getRows('vazias'),
    tipo: _tipo,
    az_volume: fld('az_volume'),
    az_lab: fld('az_lab'),
  };
}

function plural(n) { return n == 1 ? 'garrafa' : 'garrafas'; }

function gerarAssuntoAuto(d) {
  if (d.tipo === 'azoto') {
    return 'Encomenda de azoto líquido - ' + (d.local || '(local)') + ' (Conta: ' + (d.conta || '—') + ')';
  }
  return 'Encomenda de gases - ' + (d.local || '(local)') + ' (Conta: ' + (d.conta || '—') + ')';
}

function gerarCorpo(d) {
  var L = [];
  L.push('Bom dia,');
  L.push('');
  if (d.tipo === 'azoto') {
    var vol = d.az_volume || '?';
    var lab = d.az_lab ? ' do laboratório ' + d.az_lab : '';
    L.push('Solicitamos o enchimento de 1 recipiente de azoto líquido (' + vol + ' litros)' + lab + '.');
  } else {
    L.push('Solicitamos a entrega das seguintes garrafas cheias:');
    if (d.cheias.length === 0) { L.push('(nenhuma)'); }
    else { d.cheias.forEach(function(r) { L.push('- ' + r.qty + ' ' + plural(r.qty) + ' de ' + r.gas); }); }
    if (d.vazias.length > 0) {
      L.push('');
      L.push('com devolução de garrafas vazias de:');
      d.vazias.forEach(function(r) { L.push('- ' + r.qty + ' ' + plural(r.qty) + ' de ' + r.gas); });
    }
  }
  L.push('');
  L.push('Conta: ' + (d.conta || '—'));
  L.push('Local de entrega: ' + (d.local || '—'));
  L.push('Contacto: ' + (d.contacto || '—'));
  if (d.nota) L.push('N.º da nota de encomenda: ' + d.nota + ' (em anexo)');
  if (d.info_extra) { L.push(''); L.push(d.info_extra); }
  return L.join('\n');
}

function updatePreview() {
  var d = getData();
  var assunto = d.assunto || gerarAssuntoAuto(d);
  document.getElementById('pv-to').textContent      = d.to || '(não definido)';
  document.getElementById('pv-cc').textContent      = d.cc || '—';
  document.getElementById('pv-subject').textContent = assunto;
  document.getElementById('pv-body').textContent    = gerarCorpo(d);
  var nc = d.cheias.reduce(function(s,r){return s+r.qty;},0);
  var nv = d.vazias.reduce(function(s,r){return s+r.qty;},0);
  var parts = [];
  if (nc) parts.push(nc + ' cheias');
  if (nv) parts.push(nv + ' vazias');
  document.getElementById('pv-badge').textContent = parts.join(' · ') || '—';
}

function abrirMailto() {
  var d = getData();
  var assunto = d.assunto || gerarAssuntoAuto(d);
  var url = 'mailto:' + encodeURIComponent(d.to);
  var p = [];
  if (d.cc) p.push('cc=' + encodeURIComponent(d.cc));
  p.push('subject=' + encodeURIComponent(assunto));
  p.push('body=' + encodeURIComponent(gerarCorpo(d)));
  window.location.href = url + (p.length ? '?' + p.join('&') : '');
}

function mimeB64(str) {
  try { return '=?UTF-8?B?' + btoa(unescape(encodeURIComponent(str))) + '?='; } catch(e) { return str; }
}
function bodyB64(str) {
  try { return btoa(unescape(encodeURIComponent(str))).match(/.{1,76}/g).join('\r\n'); } catch(e) { return ''; }
}

function gerarEml(d) {
  var assunto = d.assunto || gerarAssuntoAuto(d);
  var from = d.from_name ? mimeB64(d.from_name) + ' <' + d.from_email + '>' : d.from_email;
  var base = ['MIME-Version: 1.0', 'X-Unsent: 1', 'Date: ' + new Date().toUTCString(), 'From: ' + from, 'To: ' + d.to];
  if (d.cc) base.push('CC: ' + d.cc);
  base.push('Subject: ' + mimeB64(assunto));
  var bodyPart = 'Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n' + bodyB64(gerarCorpo(d));
  if (_anexoB64 && _anexoNome) {
    var boundary = 'bnd_' + Date.now().toString(36);
    base.push('Content-Type: multipart/mixed; boundary="' + boundary + '"');
    base.push('');
    base.push('--' + boundary);
    base.push(bodyPart);
    base.push('');
    base.push('--' + boundary);
    base.push('Content-Type: application/pdf; name="' + _anexoNome + '"');
    base.push('Content-Transfer-Encoding: base64');
    base.push('Content-Disposition: attachment; filename="' + _anexoNome + '"');
    base.push('');
    base.push(_anexoB64.match(/.{1,76}/g).join('\r\n'));
    base.push('');
    base.push('--' + boundary + '--');
  } else {
    base.push(bodyPart);
  }
  return base.join('\r\n');
}

function downloadEml() {
  var d = getData();
  var blob = new Blob([gerarEml(d)], {type: 'message/rfc822'});
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url; a.download = 'encomenda-gases-conta' + (d.conta || 'X') + '.eml';
  document.body.appendChild(a); a.click();
  setTimeout(function() { URL.revokeObjectURL(url); document.body.removeChild(a); }, 1500);
  showToast('Ficheiro .eml gerado');
}

function copiarCorpo() {
  var text = gerarCorpo(getData());
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(function() { showToast('Corpo do email copiado'); }).catch(function() { fallbackCopy(text); });
  } else { fallbackCopy(text); }
}
function fallbackCopy(text) {
  var ta = document.createElement('textarea');
  ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); showToast('Corpo do email copiado'); } catch(e) { showToast('Não foi possível copiar automaticamente'); }
  document.body.removeChild(ta);
}

function showToast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(function() { t.classList.remove('show'); }, 2800);
}

var _assuntoManual = false;
document.addEventListener('DOMContentLoaded', function() {
  var aEl = document.getElementById('assunto');
  aEl.addEventListener('input', function() { updatePreview(); });
  ['conta', 'local'].forEach(function(id) {
    document.getElementById(id).addEventListener('input', function() {
      if (!_assuntoManual) aEl.value = gerarAssuntoAuto(getData());
      updatePreview();
    });
  });
  document.querySelectorAll('input:not(#assunto):not(#conta):not(#local), textarea').forEach(function(el) {
    el.addEventListener('input', updatePreview);
  });
  aEl.value = gerarAssuntoAuto(getData());
  updatePreview();

  /* Carrega anexo do IDB — actualiza UI quando disponível (tipicamente <20ms) */
  _idbLoad(function(stored) {
    if (stored && stored.b64 && stored.nome) {
      _anexoB64  = stored.b64;
      _anexoNome = stored.nome;
      var notaEl = document.getElementById('nota');
      if (notaEl && !notaEl.value.trim()) {
        notaEl.value = stored.nome.replace(/\.pdf$/i, '') + ' (em anexo)';
        updatePreview();
      }
    }
    _refreshAnexoUI();
  });
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
