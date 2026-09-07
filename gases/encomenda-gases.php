<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include    $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';
require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php'; // send_email()

// Qualquer utilizador autenticado pode usar esta página

$_iqNomeUtilizador  = $_SESSION['DisplayName'] ?? $_iqCurrentUser;
$_iqEmailUtilizador = $_iqCurrentUser; // ex: up356946@up.pt

/* ── helpers PHP ───────────────────────────────────────────────────── */
function buildCorpoGases($tipo, $conta, $local, $contacto, $cheias, $vazias, $az_volume, $az_lab, $nome = '', $utilizador = '') {
    $L = [];
    $L[] = 'Bom dia,';
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

        $to      = ['fmartins@fe.up.pt']; // TESTE — mudar para encomendagarrafas.pt@airliquide.com
        $ccList  = $cc ? array_values(array_filter(array_map('trim', explode(';', $cc)))) : [];
        $ccList[] = 'fmartins@fe.up.pt'; // TESTE — mudar para $_iqEmailUtilizador (auto-CC ao remetente)

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
                    az_volume, az_lab, cheias_json, vazias_json, assunto, enviado_em
             FROM infodeqb_encomenda_gases
             WHERE utilizador=? ORDER BY enviado_em DESC LIMIT 10"
        );
        $q->execute([$_iqEmailUtilizador]);
        Database::disconnect();
        echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));
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
    "SELECT id, tipo, conta, local_entrega, contacto, cc,
            az_volume, az_lab, cheias_json, vazias_json, assunto, enviado_em
     FROM infodeqb_encomenda_gases
     WHERE utilizador=? ORDER BY enviado_em DESC LIMIT 10"
);
$stmtHist->execute([$_iqEmailUtilizador]);
$historicoRows = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

$_jsonFile = __DIR__ . '/data/encomendas.json';
$_cfg      = file_exists($_jsonFile) ? (json_decode(file_get_contents($_jsonFile), true) ?: []) : [];
$_gasesG   = $_cfg['gases_garrafa']    ?? [];
$_gasesL   = $_cfg['gases_liquefeitos'] ?? [];

$pageTitle = 'Encomenda de Gases — Air Liquide';
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
      <p class="iq-page-sub">Air Liquide — envia email para <strong>encomendagarrafas.pt@airliquide.com</strong> <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">TESTE → fmartins@fe.up.pt</span></p>
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
        <button class="btn-add-gas btn-add-gas-vazias" type="button" onclick="showGasPicker('vazias', this)">
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
            <input type="text" class="form-control form-control-sm" id="conta" placeholder="ex: 12233224" oninput="onDetalhesInput()">
          </div>
          <div class="col-sm-8">
            <label class="form-label">Contacto</label>
            <input type="text" class="form-control form-control-sm" id="contacto" placeholder="Nome / telemóvel" oninput="updatePreview()">
          </div>
        </div>
        <div class="mb-0">
          <label class="form-label">Local de entrega</label>
          <input type="text" class="form-control form-control-sm" id="local" placeholder="ex: Central de gases da FEUP" oninput="onDetalhesInput()">
        </div>
      </div>

      <!-- Destinatários -->
      <div class="iq-card">
        <div class="iq-card-title"><i class="fas fa-envelope"></i> Destinatários</div>
        <div class="mb-2">
          <label class="form-label">CC <small class="text-muted">— separar com ;</small></label>
          <input type="text" class="form-control form-control-sm" id="cc" placeholder="email1@exemplo.pt; email2@exemplo.pt" oninput="updatePreview()">
        </div>
        <div>
          <label class="form-label">Assunto</label>
          <input type="text" class="form-control form-control-sm" id="assunto" readonly
            style="background:var(--iq-gray-50,#f9fafb);cursor:default">
        </div>
      </div>

      <!-- Acções -->
      <div class="d-flex flex-wrap gap-2 mb-2">
        <button class="btn btn-sm btn-primary" type="button" id="btn-enviar" onclick="enviarEncomenda()">
          <i class="fas fa-paper-plane me-1"></i>Submeter encomenda
        </button>
      </div>
      <div style="font-size:.78rem;color:var(--iq-muted)">
        <i class="fas fa-info-circle fa-xs me-1"></i>É enviada cópia automática para o teu email (<?= htmlspecialchars($_iqEmailUtilizador) ?>).
      </div>

    </div><!-- /coluna esquerda -->

    <!-- ── Coluna direita: pré-visualização + histórico ── -->
    <div class="preview-col">

      <!-- Link gases do contrato -->
      <?php if (!empty($_gasesG) || !empty($_gasesL)): ?>
      <div style="text-align:right;margin-bottom:.4rem">
        <a href="#" data-bs-toggle="modal" data-bs-target="#modalGasesContrato"
           style="font-size:.8rem;color:var(--iq-blue,#2563eb);text-decoration:none">
          <i class="fas fa-info-circle me-1"></i>Gases abrangidos pelo contrato
        </a>
      </div>
      <?php endif; ?>

      <!-- Histórico -->
      <div class="iq-card" style="margin-top:.65rem">
        <div class="iq-card-title"><i class="fas fa-history"></i> Histórico</div>
        <div id="historico-lista">
          <?php if (empty($historicoRows)): ?>
            <p class="hist-vazio mb-0">Sem encomendas anteriores.</p>
          <?php else: ?>
            <?php foreach ($historicoRows as $h): ?>
              <div class="hist-row">
                <div class="hist-info">
                  <div class="hist-assunto"><?= htmlspecialchars($h['assunto'] ?? '—') ?></div>
                  <div class="hist-meta">
                    <span class="hist-tipo-badge hist-tipo-<?= $h['tipo'] ?>">
                      <?= $h['tipo'] === 'azoto' ? '❄ azoto' : '🔵 garrafas' ?>
                    </span>
                    <?= htmlspecialchars(substr($h['enviado_em'] ?? '', 0, 10)) ?>
                  </div>
                </div>
                <button class="btn btn-xs btn-outline-primary flex-shrink-0"
                  style="font-size:.72rem;padding:.18rem .5rem"
                  onclick='repetirEncomenda(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)'>
                  Repetir
                </button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /coluna direita -->

  </div><!-- /main-layout -->

</div>

<!-- ── Modal: Gases abrangidos pelo contrato ── -->
<?php if (!empty($_gasesG) || !empty($_gasesL)): ?>
<div class="modal fade" id="modalGasesContrato" tabindex="-1" aria-labelledby="modalGasesContratoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalGasesContratoLabel">
          <i class="fas fa-list me-2"></i>Gases abrangidos pelo contrato
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body p-0">

        <?php if (!empty($_gasesG)): ?>
        <div class="px-3 pt-3 pb-1">
          <h6 class="text-muted mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">
            <i class="fas fa-wind me-1"></i>Gases em garrafa &nbsp;<span class="fw-normal text-secondary">· Preços sem IVA · Concurso 2023</span>
          </h6>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Gás</th><th>Pureza</th><th>Designação comercial</th><th>Garrafa</th>
                <th class="text-end">Prazo (dias)</th><th class="text-end">Preço/garrafa (s/IVA)</th>
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
            <i class="fas fa-tint me-1"></i>Gases liquefeitos
          </h6>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Produto</th><th>Pureza</th><th>Recipiente</th>
                <th class="text-end">Prazo (dias)</th><th class="text-end">Preço unitário (s/IVA)</th>
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
            Prazos assinalados com ⚠ são ≥ 30 dias úteis — planear as encomendas com antecedência.
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

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
  if (temDados && !confirm('Só é possível submeter um tipo de encomenda de cada vez.\nAo mudar de tipo, os dados já preenchidos serão apagados. Continuar?')) return;

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

  if (!d.conta)  { showToast('Preenche a conta'); return; }
  if (!d.local)  { showToast('Preenche o local de entrega'); return; }
  if (d.tipo === 'azoto' && !d.az_volume) { showToast('Indica o volume de azoto'); return; }
  if (d.tipo === 'garrafas' && d.cheias.length === 0 && d.vazias.length === 0) {
    showToast('Adiciona pelo menos um gás'); return;
  }

  var btn = document.getElementById('btn-enviar');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>A enviar…';

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
        showToast('Encomenda enviada!');
        recarregarHistorico();
      } else {
        showToast('Erro: ' + (res.erro || 'falha no envio'));
      }
    })
    .catch(function() { showToast('Erro de rede — tenta novamente'); })
    .finally(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submeter encomenda';
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

function renderHistorico(rows) {
  var el = document.getElementById('historico-lista');
  if (!rows || !rows.length) {
    el.innerHTML = '<p class="hist-vazio mb-0">Sem encomendas anteriores.</p>';
    return;
  }
  var html = '';
  rows.forEach(function(h) {
    var badge = h.tipo === 'azoto'
      ? '<span class="hist-tipo-badge hist-tipo-azoto">❄ azoto</span>'
      : '<span class="hist-tipo-badge hist-tipo-garrafas">🔵 garrafas</span>';
    var data = (h.enviado_em || '').substr(0, 10);
    html += '<div class="hist-row">';
    html += '<div class="hist-info">';
    html += '<div class="hist-assunto">' + esc(h.assunto || '—') + '</div>';
    html += '<div class="hist-meta">' + badge + data + '</div>';
    html += '</div>';
    html += '<button class="btn btn-xs btn-outline-primary flex-shrink-0" style="font-size:.72rem;padding:.18rem .5rem" '
          + 'onclick=\'repetirEncomenda(' + esc(JSON.stringify(h)) + ')\'>Repetir</button>';
    html += '</div>';
  });
  el.innerHTML = html;
}

function repetirEncomenda(h) {
  if (typeof h === 'string') h = JSON.parse(h);
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
  showToast('Formulário preenchido com encomenda anterior');
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function updatePreview() {} // stub — preview removido

/* ── Toast ───────────────────────────────────────────────────────────── */
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
