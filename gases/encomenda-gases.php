<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php'; // send_email()

// Qualquer utilizador autenticado pode usar esta página

$_iqNomeUtilizador  = $_SESSION['DisplayName'] ?? $_iqCurrentUser;
$_iqEmailUtilizador = $_iqCurrentUser; // ex: up356946@up.pt
$isGasAdmin         = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsGases);

/* ── helpers PHP ───────────────────────────────────────────────────── */
function buildCorpoGases($tipo, $conta, $local, $contacto, $cheias, $vazias, $az_volume, $az_lab, $nome = '', $utilizador = '') {
    $L = [];
    $L[] = 'Exmos. Srs.,';
    $L[] = '';
    if ($tipo === 'azoto') {
        $vol = $az_volume ?: '?';
        $lab = $az_lab ? ' do laboratório ' . $az_lab : '';
        $L[] = 'Solicitamos o enchimento de 1 recipiente de azoto líquido (' . $vol . ' litros)' . $lab . '.';
    } else {
        $L[] = 'Solicitamos a entrega das seguintes garrafas cheias:';
        if (empty($cheias)) {
            $L[] = '(nenhuma)';
        } else {
            foreach ($cheias as $r) {
                $n = intval($r['qty'] ?? 1);
                $L[] = '- ' . $n . ' ' . ($n == 1 ? 'garrafa' : 'garrafas') . ' de ' . ($r['gas'] ?? '?');
            }
        }
        if (!empty($vazias)) {
            $L[] = '';
            $L[] = 'com devolução de garrafas vazias de:';
            foreach ($vazias as $r) {
                $n = intval($r['qty'] ?? 1);
                $L[] = '- ' . $n . ' ' . ($n == 1 ? 'garrafa' : 'garrafas') . ' de ' . ($r['gas'] ?? '?');
            }
        }
    }
    $L[] = '';
    $L[] = 'Conta: ' . ($conta ?: '—');
    $L[] = 'Local de entrega: ' . ($local ?: '—');
    $L[] = 'Contacto: ' . ($contacto ?: '—');
    $L[] = '';
    $L[] = 'Com os melhores cumprimentos,';
    if ($nome || $utilizador) {
        $assinatura = $nome ?: '';
        if ($utilizador) $assinatura .= ($assinatura ? ' (' . $utilizador . ')' : $utilizador);
        $L[] = $assinatura;
    }
    return implode("\n", $L);
}

function buildAssuntoGases($tipo, $local, $conta) {
    if ($tipo === 'azoto') {
        return 'Encomenda de azoto líquido - ' . ($local ?: '(local)') . ' (Conta: ' . ($conta ?: '—') . ')';
    }
    return 'Encomenda de gases - ' . ($local ?: '(local)') . ' (Conta: ' . ($conta ?: '—') . ')';
}

/* ── AJAX POST handler ─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_POST['action'] === 'enviar') {
        $tipo      = in_array($_POST['tipo'] ?? '', ['garrafas','azoto']) ? $_POST['tipo'] : 'garrafas';
        $conta     = trim($_POST['conta'] ?? '');
        $local     = trim($_POST['local'] ?? '');
        $contacto  = trim($_POST['contacto'] ?? '');
        $cc        = trim($_POST['cc'] ?? '');
        $az_volume = intval($_POST['az_volume'] ?? 0) ?: null;
        $az_lab    = trim($_POST['az_lab'] ?? '') ?: null;
        $cheias    = json_decode($_POST['cheias_json'] ?? '[]', true) ?: [];
        $vazias    = json_decode($_POST['vazias_json'] ?? '[]', true) ?: [];

        $assunto = buildAssuntoGases($tipo, $local, $conta);
        $corpo   = buildCorpoGases($tipo, $conta, $local, $contacto, $cheias, $vazias, $az_volume, $az_lab, $_iqNomeUtilizador, $_iqEmailUtilizador);

        $to      = [$_iqEmailUtilizador];
        $ccList  = $cc ? array_values(array_filter(array_map('trim', explode(';', $cc)))) : [];
        $ccList[] = 'gases@fe.up.pt'; 

        $htmlCorpo = '<div style="font-family:monospace;font-size:14px;line-height:1.8">'
                   . nl2br(htmlspecialchars($corpo))
                   . '</div>';
        try {
            send_email($to, $htmlCorpo, $assunto, $ccList);

            $pdo->prepare(
                "INSERT INTO infodeqb_encomenda_gases
                 (utilizador, nome, tipo, conta, local_entrega, contacto, cc,
                  az_volume, az_lab, cheias_json, vazias_json, assunto, corpo, enviado_em)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
            )->execute([
                $_iqEmailUtilizador, $_iqNomeUtilizador, $tipo, $conta, $local, $contacto, $cc,
                $az_volume, $az_lab,
                $_POST['cheias_json'] ?? '[]', $_POST['vazias_json'] ?? '[]',
                $assunto, $corpo,
            ]);

            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
        }
        Database::disconnect();
        exit;
    }

    if ($_POST['action'] === 'historico') {
        $q = $pdo->prepare(
            "SELECT id, tipo, conta, local_entrega, contacto, cc,
                    az_volume, az_lab, cheias_json, vazias_json, assunto, corpo, enviado_em
             FROM infodeqb_encomenda_gases
             WHERE utilizador=? AND YEAR(enviado_em) = YEAR(NOW())
             ORDER BY enviado_em DESC LIMIT 60"
        );
        $q->execute([$_iqEmailUtilizador]);
        Database::disconnect();
        echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_POST['action'] === 'apagar_hist') {
        if (!$isGasAdmin) {
            echo json_encode(['ok' => false, 'erro' => 'Sem permissão.']);
            exit;
        }
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        if (empty($ids)) {
            echo json_encode(['ok' => false, 'erro' => 'Nenhum ID fornecido.']);
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM infodeqb_encomenda_gases WHERE id IN ($placeholders)")
            ->execute(array_values($ids));
        Database::disconnect();
        echo json_encode(['ok' => true, 'apagados' => count($ids)]);
        exit;
    }

    Database::disconnect();
    echo json_encode(['ok' => false, 'erro' => 'Acção desconhecida']);
    exit;
}

/* ── Carrega histórico (página normal) ─────────────────────────────── */
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `infodeqb_encomenda_gases` (
        `id`           int(11)                      NOT NULL AUTO_INCREMENT,
        `utilizador`   varchar(50)                  NOT NULL,
        `nome`         varchar(200)                 DEFAULT NULL,
        `tipo`         enum('garrafas','azoto')     NOT NULL DEFAULT 'garrafas',
        `conta`        varchar(50)                  DEFAULT NULL,
        `local_entrega` varchar(200)                DEFAULT NULL,
        `contacto`     varchar(200)                 DEFAULT NULL,
        `cc`           varchar(300)                 DEFAULT NULL,
        `az_volume`    int(11)                      DEFAULT NULL,
        `az_lab`       varchar(50)                  DEFAULT NULL,
        `cheias_json`  text                         DEFAULT NULL,
        `vazias_json`  text                         DEFAULT NULL,
        `assunto`      varchar(300)                 DEFAULT NULL,
        `corpo`        text                         DEFAULT NULL,
        `enviado_em`   datetime                     DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_utilizador` (`utilizador`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) { /* tabela já existe */ }

$stmtHist = $pdo->prepare(
    "SELECT id, utilizador, nome, tipo, conta, local_entrega, contacto, cc,
            az_volume, az_lab, cheias_json, vazias_json, assunto, corpo, enviado_em
     FROM infodeqb_encomenda_gases
     WHERE utilizador=? AND YEAR(enviado_em) = YEAR(NOW())
     ORDER BY enviado_em DESC LIMIT 60"
);
$stmtHist->execute([$_iqEmailUtilizador]);
$historicoRows = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

$_jsonFile = __DIR__ . '/data/encomendas.json';
$_cfg      = file_exists($_jsonFile) ? (json_decode(file_get_contents($_jsonFile), true) ?: []) : [];
$_gasesG   = $_cfg['gases_garrafa']    ?? [];
$_gasesL   = $_cfg['gases_liquefeitos'] ?? [];

$pageTitle = t('GENC_TITLE');
$mainClass = 'iq-main';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>
<style>
.main-layout, .main-layout input, .main-layout textarea, .main-layout select, .main-layout button {
  font-size: 1rem;
}
.form-label { font-size: .95rem; }

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
  background: var(--iq-blue-light, #dbeafe);
  border: 1px dashed #93c5fd;
  border-radius: 6px;
  color: var(--iq-blue, #2563eb);
  font-family: inherit;
  font-size: .8rem;
  padding: .28rem .7rem;
  text-align: left;
  cursor: pointer;
  transition: border-color .15s, color .15s, background .15s;
}
.btn-add-gas:hover {
  border-color: var(--iq-blue, #2563eb);
  background: #bfdbfe;
}
.btn-add-gas-vazias {
  background: #fef3c7;
  border-color: #fcd34d;
  color: #92400e;
}
.btn-add-gas-vazias:hover {
  border-color: #d97706;
  background: #fde68a;
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

.preview-col { position: sticky; top: 1rem; }

/* ── Cores cheias/vazias ── */
#section-garrafas-cheias { border-left: 3px solid #16a34a; }
#section-garrafas-vazias { border-left: 3px solid #d97706; }

/* ── Histórico ── */
.hist-vazio { font-size: .83rem; color: var(--iq-muted); padding: .4rem 0; }
.hist-row {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .45rem 0;
  border-bottom: 1px solid var(--iq-border);
  font-size: .82rem;
}
.hist-row:last-child { border-bottom: none; }
.hist-info { flex: 1; min-width: 0; }
.hist-assunto {
  font-weight: 500;
  color: var(--iq-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: .8rem;
}
.hist-meta { color: var(--iq-muted); font-size: .73rem; margin-top: 1px; }
.hist-tipo-badge {
  display: inline-block;
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  border-radius: 3px;
  padding: 1px 5px;
  margin-right: 4px;
}
.hist-tipo-garrafas { background: #dbeafe; color: #1d4ed8; }
.hist-tipo-azoto    { background: #e0f2fe; color: #0369a1; }

/* ── Agrupamento por mês ── */
.hist-month-header {
  display: flex; align-items: center; gap: .35rem;
  padding: .3rem 0 .25rem;
  font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
  color: var(--iq-muted);
  border-bottom: 1px solid var(--iq-border);
  margin-top: .4rem;
  cursor: pointer; user-select: none;
}
.hist-month-header:hover { color: var(--iq-text); }
.hist-month-toggle { transition: transform .18s; flex-shrink: 0; }
.hist-month-group.collapsed .hist-month-toggle { transform: rotate(-90deg); }
.hist-month-group.collapsed .hist-row { display: none; }
.hist-month-count {
  margin-left: auto;
  font-size: .65rem; font-weight: 700;
  background: var(--iq-gray-100,#f3f4f6); color: var(--iq-muted);
  border-radius: 8px; padding: 0 6px;
}
.hist-month-group:first-child .hist-month-header { margin-top: 0; }

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
        <i class="fas fa-flask me-2" style="color:var(--iq-blue)"></i><?= t('GENC_TITLE') ?>
      </h1>
      <p class="iq-page-sub">Air Liquide — envia email para <strong>encomendagarrafas.pt@airliquide.com</strong> <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">TESTE → fmartins@fe.up.pt</span></p>
    </div>
  </div>

  <div class="main-layout" lang="pt">

    <!-- ── Coluna esquerda: formulário ── -->
    <div>

      <!-- Tipo de encomenda -->
      <div class="iq-card">
        <div class="iq-card-title"><i class="fas fa-tag"></i> <?= t('GENC_TIPO') ?></div>
        <div class="d-flex gap-2">
          <button type="button" id="btn-tipo-garrafas" class="btn btn-sm btn-primary" onclick="setTipo('garrafas')">
            <i class="fas fa-flask me-1"></i><?= t('GENC_GARRAFAS') ?>
          </button>
          <button type="button" id="btn-tipo-azoto" class="btn btn-sm btn-outline-secondary" onclick="setTipo('azoto')">
            <i class="fas fa-snowflake me-1"></i><?= t('GENC_AZOTO_LIQ') ?>
          </button>
        </div>
      </div>

      <!-- Garrafas cheias -->
      <div class="iq-card" id="section-garrafas-cheias">
        <div class="iq-card-title">
          <i class="fas fa-check-circle" style="color:#16a34a"></i> <?= t('GENC_CHEIAS') ?>
          <span class="badge rounded-pill ms-1" style="background:#dcfce7;color:#15803d;font-weight:500;font-size:.63rem"><?= t('GENC_CHEIAS_BADGE') ?></span>
        </div>
        <div id="rows-cheias" class="gas-rows"></div>
        <button class="btn-add-gas" type="button" onclick="showGasPicker('cheias', this)">
          <i class="fas fa-plus fa-xs me-1"></i><?= t('GENC_ADD_GAS') ?>
        </button>
      </div>

      <!-- Garrafas vazias -->
      <div class="iq-card" id="section-garrafas-vazias">
        <div class="iq-card-title">
          <i class="fas fa-circle" style="color:#b45309"></i> <?= t('GENC_VAZIAS') ?>
          <span class="badge rounded-pill ms-1" style="background:#fef3c7;color:#b45309;font-weight:500;font-size:.63rem"><?= t('GENC_VAZIAS_BADGE') ?></span>
        </div>
        <div id="rows-vazias" class="gas-rows"></div>
        <button class="btn-add-gas btn-add-gas-vazias" type="button" onclick="showGasPicker('vazias', this)">
          <i class="fas fa-plus fa-xs me-1"></i><?= t('GENC_ADD_GAS') ?>
        </button>
      </div>

      <!-- Azoto Líquido -->
      <div class="iq-card" id="section-azoto" style="display:none">
        <div class="iq-card-title"><i class="fas fa-snowflake" style="color:#0ea5e9"></i> <?= t('GENC_AZOTO_LIQ') ?></div>
        <div class="row g-2 mb-2">
          <div class="col-sm-4">
            <label class="form-label"><?= t('GENC_VOLUME_L') ?></label>
            <input type="number" class="form-control form-control-sm" id="az_volume" min="1" placeholder="ex: 25" oninput="updatePreview()">
          </div>
          <div class="col-sm-8">
            <label class="form-label"><?= t('GENC_LAB') ?></label>
            <input type="text" class="form-control form-control-sm" id="az_lab" placeholder="ex: E301" oninput="updatePreview()" lang="pt" spellcheck="false">
          </div>
        </div>
      </div>

      <!-- Detalhes -->
      <div class="iq-card">
        <div class="iq-card-title"><i class="fas fa-file-invoice"></i> <?= t('GENC_DETALHES') ?></div>
        <div class="row g-2 mb-2">
          <div class="col-sm-4">
            <label class="form-label"><?= t('GENC_CONTA') ?></label>
            <input type="text" class="form-control form-control-sm" id="conta" placeholder="ex: 12233224" oninput="onDetalhesInput()" lang="pt" spellcheck="false">
          </div>
          <div class="col-sm-8">
            <label class="form-label"><?= t('GENC_CONTACTO') ?></label>
            <input type="text" class="form-control form-control-sm" id="contacto" placeholder="Nome / telemóvel" oninput="updatePreview()" lang="pt" spellcheck="true">
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label"><?= t('GENC_LOCAL') ?></label>
          <input type="text" class="form-control form-control-sm" id="local" placeholder="ex: Central de gases da FEUP" oninput="onDetalhesInput()" lang="pt" spellcheck="true">
        </div>
      </div>

      <!-- Destinatários -->
      <div class="iq-card">
        <div class="iq-card-title"><i class="fas fa-envelope"></i> <?= t('GENC_DEST') ?></div>
        <div class="mb-2">
          <label class="form-label"><?= t('GENC_CC') ?></label>
          <input type="text" class="form-control form-control-sm" id="cc" placeholder="email1@fe.up.pt; email2@up.pt" oninput="updatePreview()" lang="pt" spellcheck="false">
          <div class="form-text" style="font-size:.75rem"><i class="fas fa-info-circle me-1"></i><?= t('GENC_CC_HINT') ?></div>
        </div>
        <div>
          <label class="form-label"><?= t('GENC_ASSUNTO') ?></label>
          <input type="text" class="form-control form-control-sm" id="assunto" readonly
            style="background:var(--iq-gray-50,#f9fafb);cursor:default">
        </div>
      </div>

      <!-- Acções -->
      <div class="d-flex flex-wrap gap-2 mb-2">
        <button class="btn btn-sm btn-primary" type="button" id="btn-enviar" onclick="enviarEncomenda()">
          <i class="fas fa-paper-plane me-1"></i><?= t('GENC_SUBMETER') ?>
        </button>
        <button class="btn btn-sm btn-outline-secondary" type="button" onclick="limparFormulario()">
          <i class="fas fa-eraser me-1"></i><?= t('GENC_LIMPAR') ?>
        </button>
      </div>
      <div style="font-size:.78rem;color:var(--iq-muted)">
        <i class="fas fa-info-circle fa-xs me-1"></i><?= t('GENC_COPIA_AUTO', htmlspecialchars($_iqEmailUtilizador)) ?>
      </div>

    </div><!-- /coluna esquerda -->

    <!-- ── Coluna direita: pré-visualização + histórico ── -->
    <div class="preview-col">

      <!-- Link gases do contrato -->
      <?php if (!empty($_gasesG) || !empty($_gasesL)): ?>
      <div style="text-align:right;margin-bottom:.4rem">
        <a href="#" data-bs-toggle="modal" data-bs-target="#modalGasesContrato"
           style="font-size:.8rem;color:var(--iq-blue,#2563eb);text-decoration:none">
          <i class="fas fa-info-circle me-1"></i><?= t('GENC_CONTRATO') ?>
        </a>
      </div>
      <?php endif; ?>

      <!-- Histórico -->
      <div class="iq-card" style="margin-top:.65rem">
        <div class="iq-card-title">
          <i class="fas fa-history"></i> <?= t('GENC_HISTORICO') ?>
        </div>
        <div id="historico-lista">
          <?php if (empty($historicoRows)): ?>
            <p class="hist-vazio mb-0"><?= t('GENC_SEM_ENC') ?></p>
          <?php else:
            $_meses  = $GLOBALS['_lang']['GENC_MESES'] ?? ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
            $mesesPt = array_combine(['01','02','03','04','05','06','07','08','09','10','11','12'], array_values($_meses));
            $byMonth = [];
            foreach ($historicoRows as $h) {
                $ym = substr($h['enviado_em'] ?? '', 0, 7);
                $byMonth[$ym][] = $h;
            }
            $gIdx = 0;
            foreach ($byMonth as $ym => $mRows):
                list($ano, $mes) = explode('-', $ym);
                $label = ($mesesPt[$mes] ?? $mes) . ' ' . $ano;
                $collapsed = $gIdx > 0 ? ' collapsed' : '';
                $gIdx++;
          ?>
            <div class="hist-month-group<?= $collapsed ?>">
              <div class="hist-month-header"
                onclick="this.closest('.hist-month-group').classList.toggle('collapsed')">
                <span class="hist-month-toggle"><i class="fas fa-chevron-down fa-xs"></i></span>
                <?= htmlspecialchars($label) ?>
                <span class="hist-month-count"><?= count($mRows) ?></span>
              </div>
              <?php foreach ($mRows as $h): ?>
              <div class="hist-row" data-id="<?= (int)$h['id'] ?>">
                <div class="hist-info">
                  <div class="hist-assunto"><?= htmlspecialchars($h['assunto'] ?? '—') ?></div>
                  <div class="hist-meta">
                    <span class="hist-tipo-badge hist-tipo-<?= $h['tipo'] ?>">
                      <?= $h['tipo'] === 'azoto' ? t('GENC_BADGE_AZOTO') : t('GENC_BADGE_GARR') ?>
                    </span>
                    <?= htmlspecialchars(substr($h['enviado_em'] ?? '', 0, 10)) ?>
                  </div>
                </div>
                <div class="d-flex gap-1 flex-shrink-0">
                  <button class="btn btn-xs btn-outline-secondary"
                    style="font-size:.72rem;padding:.18rem .45rem"
                    onclick="showDetalhe(<?= (int)$h['id'] ?>)">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button class="btn btn-xs btn-outline-primary"
                    style="font-size:.72rem;padding:.18rem .5rem"
                    onclick="repetirEncomenda(<?= (int)$h['id'] ?>)">
                    <?= t('GENC_REPETIR') ?>
                  </button>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </div><!-- /coluna direita -->

  </div><!-- /main-layout -->

</div>

<!-- ── Modal: Detalhe de encomenda ── -->
<div class="modal fade" id="modalDetalheEncomenda" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-envelope-open-text me-2"></i><?= t('GENC_DETALHE') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-2" style="font-size:.78rem" id="detalhe-meta"></p>
        <div id="detalhe-corpo"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('GENC_FECHAR') ?></button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal: Gases abrangidos pelo contrato ── -->
<?php if (!empty($_gasesG) || !empty($_gasesL)): ?>
<div class="modal fade" id="modalGasesContrato" tabindex="-1" aria-labelledby="modalGasesContratoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalGasesContratoLabel">
          <i class="fas fa-list me-2"></i><?= t('GENC_CONTRATO') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('GENC_FECHAR') ?>"></button>
      </div>
      <div class="modal-body p-0">

        <?php if (!empty($_gasesG)): ?>
        <div class="px-3 pt-3 pb-1">
          <h6 class="text-muted mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">
            <i class="fas fa-wind me-1"></i><?= t('GENC_GASES_GARRAFA') ?> &nbsp;<span class="fw-normal text-secondary">· Preços sem IVA · Concurso 2023</span>
          </h6>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th><?= t('GENC_COL_GAS') ?></th><th><?= t('GENC_COL_PUREZA') ?></th><th><?= t('GENC_COL_DESIG') ?></th><th><?= t('GENC_COL_GARRAFA') ?></th>
                <th class="text-end"><?= t('GENC_COL_PRAZO') ?></th><th class="text-end"><?= t('GENC_COL_PRECO_G') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($_gasesG as $g): ?>
              <?php $longo = (int)($g['prazo'] ?? 0) >= 30; ?>
              <tr>
                <td><?= htmlspecialchars($g['gas'] ?? '') ?></td>
                <td><?= htmlspecialchars($g['pureza'] ?? '') ?></td>
                <td><?= htmlspecialchars($g['designacao'] ?? '') ?></td>
                <td><?= htmlspecialchars($g['garrafa'] ?? '') ?></td>
                <td class="text-end <?= $longo ? 'text-danger' : '' ?>">
                  <?= (int)($g['prazo'] ?? 0) ?><?= $longo ? ' ⚠' : '' ?>
                </td>
                <td class="text-end"><?= htmlspecialchars($g['preco'] ?? '') ?>&nbsp;€</td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($_gasesL)): ?>
        <div class="px-3 pt-3 pb-1 mt-2">
          <h6 class="text-muted mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">
            <i class="fas fa-tint me-1"></i><?= t('GENC_GASES_LIQ') ?>
          </h6>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th><?= t('GENC_COL_PRODUTO') ?></th><th><?= t('GENC_COL_PUREZA') ?></th><th><?= t('GENC_COL_RECIP') ?></th>
                <th class="text-end"><?= t('GENC_COL_PRAZO') ?></th><th class="text-end"><?= t('GENC_COL_PRECO_U') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($_gasesL as $g): ?>
              <tr>
                <td><?= htmlspecialchars($g['gas'] ?? '') ?></td>
                <td><?= htmlspecialchars($g['pureza'] ?? '') ?></td>
                <td><?= htmlspecialchars($g['recipiente'] ?? '') ?></td>
                <td class="text-end"><?= (int)($g['prazo'] ?? 0) ?></td>
                <td class="text-end"><?= htmlspecialchars($g['preco'] ?? '') ?>&nbsp;€/<?= htmlspecialchars($g['unidade'] ?? 'un') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <div class="px-3 py-2">
          <small class="text-muted">
            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
            <?= t('GENC_AVISO_PRAZO') ?>
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('GENC_FECHAR') ?></button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="iq-toast" id="toast"></div>

<script>
var _T = <?= json_encode([
  'tipo_confirm'  => t('GENC_JS_TIPO_CONFIRM'),
  'conta'         => t('GENC_JS_CONTA'),
  'local'         => t('GENC_JS_LOCAL'),
  'volume'        => t('GENC_JS_VOLUME'),
  'add_gas'       => t('GENC_JS_ADD_GAS'),
  'a_enviar'      => t('GENC_JS_A_ENVIAR'),
  'enviado'       => t('GENC_JS_ENVIADO'),
  'erro_rede'     => t('GENC_JS_ERRO_REDE'),
  'remover'       => t('GENC_JS_REMOVER'),
  'repetido'      => t('GENC_JS_REPETIDO'),
  'sem_gas'       => t('GENC_JS_SEM_GAS'),
  'limpar_cf'     => t('GENC_JS_LIMPAR_CF'),
  'submeter'      => t('GENC_SUBMETER'),
  'badge_azoto'   => t('GENC_BADGE_AZOTO'),
  'badge_garr'    => t('GENC_BADGE_GARR'),
  'sem_enc'       => t('GENC_SEM_ENC'),
  'repetir'       => t('GENC_REPETIR'),
  'conta_th'      => t('GENC_CONTA'),
  'local_th'      => t('GENC_LOCAL'),
  'contacto_th'   => t('GENC_CONTACTO'),
  'col_gas'       => t('GENC_COL_GAS'),
  'cheias_th'     => t('GENC_CHEIAS_PEDIDAS'),
  'vazias_th'     => t('GENC_VAZIAS_LEVANTAR'),
  'meses'         => array_values($GLOBALS['_lang']['GENC_MESES'] ?? ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro']),
]) ?>;

var _histRows = <?= json_encode(array_column($historicoRows, null, 'id')) ?>;

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

/* ── Gas picker ─────────────────────────────────────────────────────── */
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
  rmBtn.innerHTML = '&times;'; rmBtn.title = _T.remover;
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

/* ── Tipo toggle ─────────────────────────────────────────────────────── */
var _tipo = 'garrafas';

function setTipo(t) {
  if (t === _tipo) return;

  // Verificar se há dados no tipo actual antes de limpar
  var temDados = false;
  if (_tipo === 'garrafas') {
    temDados = document.querySelectorAll('#rows-cheias .gas-row, #rows-vazias .gas-row').length > 0;
  } else {
    temDados = !!(document.getElementById('az_volume').value || document.getElementById('az_lab').value);
  }
  if (temDados && !confirm(_T.tipo_confirm)) return;

  // Limpar secção inactiva
  if (_tipo === 'garrafas') {
    document.getElementById('rows-cheias').innerHTML = '';
    document.getElementById('rows-vazias').innerHTML = '';
  } else {
    document.getElementById('az_volume').value = '';
    document.getElementById('az_lab').value    = '';
  }

  _tipo = t;
  document.getElementById('btn-tipo-garrafas').className = 'btn btn-sm ' + (t === 'garrafas' ? 'btn-primary' : 'btn-outline-secondary');
  document.getElementById('btn-tipo-azoto').className    = 'btn btn-sm ' + (t === 'azoto'    ? 'btn-primary' : 'btn-outline-secondary');
  document.getElementById('section-garrafas-cheias').style.display = t === 'garrafas' ? '' : 'none';
  document.getElementById('section-garrafas-vazias').style.display = t === 'garrafas' ? '' : 'none';
  document.getElementById('section-azoto').style.display           = t === 'azoto'    ? '' : 'none';
  updatePreview();
}

/* ── Dados do formulário ─────────────────────────────────────────────── */
function fld(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }

function getData() {
  return {
    cc: fld('cc'),
    conta: fld('conta'), local: fld('local'), contacto: fld('contacto'),
    assunto: fld('assunto'),
    cheias: getRows('cheias'), vazias: getRows('vazias'),
    tipo: _tipo,
    az_volume: fld('az_volume'),
    az_lab: fld('az_lab'),
  };
}

function gerarAssuntoAuto(d) {
  if (d.tipo === 'azoto') {
    return 'Encomenda de azoto líquido - ' + (d.local || '(local)') + ' (Conta: ' + (d.conta || '—') + ')';
  }
  return 'Encomenda de gases - ' + (d.local || '(local)') + ' (Conta: ' + (d.conta || '—') + ')';
}

var _assuntoManual = false;

function onDetalhesInput() {
  var aEl = document.getElementById('assunto');
  if (!_assuntoManual) aEl.value = gerarAssuntoAuto(getData());
}

/* ── Envio ───────────────────────────────────────────────────────────── */
function enviarEncomenda() {
  var d = getData();

  if (!d.conta)  { showToast(_T.conta); return; }
  if (!d.local)  { showToast(_T.local); return; }
  if (d.tipo === 'azoto' && !d.az_volume) { showToast(_T.volume); return; }
  if (d.tipo === 'garrafas' && d.cheias.length === 0 && d.vazias.length === 0) {
    showToast(_T.add_gas); return;
  }

  var btn = document.getElementById('btn-enviar');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + _T.a_enviar;

  var fd = new FormData();
  fd.append('action', 'enviar');
  fd.append('tipo', d.tipo);
  fd.append('cc', d.cc);
  fd.append('conta', d.conta);
  fd.append('local', d.local);
  fd.append('contacto', d.contacto);
  fd.append('assunto', d.assunto || gerarAssuntoAuto(d));
  fd.append('az_volume', d.az_volume);
  fd.append('az_lab', d.az_lab);
  fd.append('cheias_json', JSON.stringify(d.cheias));
  fd.append('vazias_json', JSON.stringify(d.vazias));

  fetch(window.location.pathname, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.ok) {
        showToast(_T.enviado);
        recarregarHistorico();
      } else {
        showToast('Erro: ' + (res.erro || 'falha no envio'));
      }
    })
    .catch(function() { showToast(_T.erro_rede); })
    .finally(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>' + _T.submeter;
    });
}

/* ── Histórico ───────────────────────────────────────────────────────── */
function recarregarHistorico() {
  var fd = new FormData();
  fd.append('action', 'historico');
  fetch(window.location.pathname, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(rows) { renderHistorico(rows); })
    .catch(function() {});
}

function esc(s) {
  return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

var MESES_PT = _T.meses;

function renderHistorico(rows) {
  var el = document.getElementById('historico-lista');
  if (!rows || !rows.length) {
    el.innerHTML = '<p class="hist-vazio mb-0">' + esc(_T.sem_enc) + '</p>';
    return;
  }
  _histRows = {};
  var byMonth = {}, order = [];
  rows.forEach(function(h) {
    _histRows[h.id] = h;
    var ym = (h.enviado_em || '').substr(0, 7);
    if (!byMonth[ym]) { byMonth[ym] = []; order.push(ym); }
    byMonth[ym].push(h);
  });
  var html = '';
  order.forEach(function(ym, gIdx) {
    var parts = ym.split('-');
    var label = MESES_PT[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
    var collapsed = gIdx > 0 ? ' collapsed' : '';
    html += '<div class="hist-month-group' + collapsed + '">';
    html += '<div class="hist-month-header" onclick="this.closest(\'.hist-month-group\').classList.toggle(\'collapsed\')">';
    html += '<span class="hist-month-toggle"><i class="fas fa-chevron-down fa-xs"></i></span>';
    html += esc(label);
    html += '<span class="hist-month-count">' + byMonth[ym].length + '</span>';
    html += '</div>';
    byMonth[ym].forEach(function(h) {
      var badge = h.tipo === 'azoto'
        ? '<span class="hist-tipo-badge hist-tipo-azoto">' + esc(_T.badge_azoto) + '</span>'
        : '<span class="hist-tipo-badge hist-tipo-garrafas">' + esc(_T.badge_garr) + '</span>';
      var data = (h.enviado_em || '').substr(0, 10);
      html += '<div class="hist-row" data-id="' + h.id + '">';
      html += '<div class="hist-info">';
      html += '<div class="hist-assunto">' + esc(h.assunto || '—') + '</div>';
      html += '<div class="hist-meta">' + badge + data + '</div>';
      html += '</div>';
      html += '<div class="d-flex gap-1 flex-shrink-0">';
      html += '<button class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.18rem .45rem" '
            + 'onclick="showDetalhe(' + h.id + ')"><i class="fas fa-eye"></i></button>';
      html += '<button class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:.18rem .5rem" '
            + 'onclick="repetirEncomenda(' + h.id + ')">' + esc(_T.repetir) + '</button>';
      html += '</div>';
      html += '</div>';
    });
    html += '</div>';
  });
  el.innerHTML = html;
}

function repetirEncomenda(id) {
  var h = _histRows[id];
  if (!h) return;
  setTipo(h.tipo || 'garrafas');
  document.getElementById('conta').value    = h.conta || '';
  document.getElementById('local').value    = h.local_entrega || '';
  document.getElementById('contacto').value = h.contacto || '';
  document.getElementById('cc').value       = h.cc || '';

  document.getElementById('rows-cheias').innerHTML = '';
  document.getElementById('rows-vazias').innerHTML = '';

  if (h.tipo === 'azoto') {
    document.getElementById('az_volume').value = h.az_volume || '';
    document.getElementById('az_lab').value    = h.az_lab || '';
  } else {
    var cheias = [];
    var vazias = [];
    try { cheias = JSON.parse(h.cheias_json || '[]'); } catch(e) {}
    try { vazias = JSON.parse(h.vazias_json || '[]'); } catch(e) {}
    cheias.forEach(function(g) { addGasRow('cheias', g.qty, g.gas); });
    vazias.forEach(function(g) { addGasRow('vazias', g.qty, g.gas); });
  }

  _assuntoManual = false;
  document.getElementById('assunto').value = gerarAssuntoAuto(getData());
  updatePreview();
  showToast(_T.repetido);
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function updatePreview() {} // stub — preview removido

/* ── Detalhe modal ────────────────────────────────────────────────────── */
function renderDetalheGases(h) {
  var html = '';
  html += '<table class="table table-sm mb-3" style="font-size:.82rem">';
  html += '<tr><th style="width:38%">' + esc(_T.conta_th) + '</th><td>' + esc(h.conta || '—') + '</td></tr>';
  html += '<tr><th>' + esc(_T.local_th) + '</th><td>' + esc(h.local_entrega || '—') + '</td></tr>';
  if (h.contacto) html += '<tr><th>' + esc(_T.contacto_th) + '</th><td>' + esc(h.contacto) + '</td></tr>';
  html += '</table>';
  if (h.tipo === 'azoto') {
    html += '<p style="margin:0"><strong>' + esc(_T.badge_azoto) + ':</strong> ' + esc(h.az_volume || '?') + ' L';
    if (h.az_lab) html += ' — Lab.&nbsp;' + esc(h.az_lab);
    html += '</p>';
  } else {
    var cheias = [], vazias = [], gases = {}, order = [];
    try { cheias = JSON.parse(h.cheias_json || '[]'); } catch(e) {}
    try { vazias = JSON.parse(h.vazias_json || '[]'); } catch(e) {}
    cheias.forEach(function(r) {
      if (!gases[r.gas]) { gases[r.gas] = {c: 0, v: 0}; order.push(r.gas); }
      gases[r.gas].c += (parseInt(r.qty, 10) || 0);
    });
    vazias.forEach(function(r) {
      if (!gases[r.gas]) { gases[r.gas] = {c: 0, v: 0}; order.push(r.gas); }
      gases[r.gas].v += (parseInt(r.qty, 10) || 0);
    });
    if (order.length) {
      html += '<table class="table table-sm table-bordered" style="font-size:.82rem">';
      html += '<thead class="table-light"><tr>';
      html += '<th>' + esc(_T.col_gas) + '</th>';
      html += '<th class="text-center" style="white-space:nowrap">' + esc(_T.cheias_th) + '</th>';
      html += '<th class="text-center" style="white-space:nowrap">' + esc(_T.vazias_th) + '</th>';
      html += '</tr></thead><tbody>';
      order.forEach(function(g) {
        var c = gases[g].c, v = gases[g].v;
        html += '<tr><td>' + esc(g) + '</td>';
        html += '<td class="text-center">' + (c ? c : '<span style="color:#9ca3af">—</span>') + '</td>';
        html += '<td class="text-center">' + (v ? v : '<span style="color:#9ca3af">—</span>') + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
    } else {
      html += '<p class="text-muted">' + esc(_T.sem_gas) + '</p>';
    }
  }
  return html;
}

function showDetalhe(id) {
  var h = _histRows[id];
  if (!h) return;
  var data = (h.enviado_em || '').substr(0, 16).replace('T', ' ');
  document.getElementById('detalhe-meta').textContent = (h.assunto || '') + ' · ' + data;
  document.getElementById('detalhe-corpo').innerHTML = renderDetalheGases(h);
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalheEncomenda')).show();
}

function limparFormulario() {
  if (!confirm(_T.limpar_cf)) return;
  document.getElementById('conta').value    = '';
  document.getElementById('local').value    = '';
  document.getElementById('contacto').value = '';
  document.getElementById('cc').value       = '';
  document.getElementById('rows-cheias').innerHTML = '';
  document.getElementById('rows-vazias').innerHTML = '';
  document.getElementById('az_volume').value = '';
  document.getElementById('az_lab').value    = '';
  _assuntoManual = false;
  onDetalhesInput();
}

function showToast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(function() { t.classList.remove('show'); }, 2800);
}

/* ── Init ────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  var aEl = document.getElementById('assunto');
  aEl.addEventListener('input', function() { _assuntoManual = true; });
  aEl.value = gerarAssuntoAuto(getData());
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
