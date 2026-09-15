<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$id_cadeira_feup = isset($_GET['id_cadeira_feup']) ? (int)$_GET['id_cadeira_feup'] : 0;
$id_inst         = isset($_GET['id_inst'])         ? (int)$_GET['id_inst']         : 0;

if (!$id_cadeira_feup || !$id_inst) {
    header('Location: index.php'); exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sCad = $pdo->prepare(
    'SELECT nome_cadeira_feup FROM infodeqb_erasmus_cadeiras_feup WHERE id_cadeira_feup = ?'
);
$sCad->execute([$id_cadeira_feup]);
$infoCad = $sCad->fetch(PDO::FETCH_ASSOC);
if (!$infoCad) { header('Location: index.php'); exit; }

$sInst = $pdo->prepare(
    'SELECT nome_instituicao FROM infodeqb_erasmus_instituicoes WHERE id_instituicao = ?'
);
$sInst->execute([$id_inst]);
$infoInst = $sInst->fetch(PDO::FETCH_ASSOC);

$sRows = $pdo->prepare(
    'SELECT E.id_equivalencia,
            CE.id_cadeira_estrangeira, CE.nome_cadeira_estrangeira, CE.ects_estrangeira,
            L.link_url
     FROM infodeqb_erasmus_equivalencias E
     JOIN infodeqb_erasmus_equivalencias_relacao ECR ON E.id_equivalencia = ECR.id_equivalencia
     JOIN infodeqb_erasmus_cadeiras_estrangeiras CE ON ECR.id_cadeira_estrangeira = CE.id_cadeira_estrangeira
     LEFT JOIN infodeqb_erasmus_links_cadeiras L ON L.id_cadeira_estrangeira = CE.id_cadeira_estrangeira
     WHERE E.id_cadeira_feup = ? AND E.id_instituicao = ?
     ORDER BY E.id_equivalencia DESC, CE.nome_cadeira_estrangeira ASC'
);
$sRows->execute([$id_cadeira_feup, $id_inst]);

$tmp = [];
foreach ($sRows->fetchAll(PDO::FETCH_ASSOC) as $linha) {
    $eq_id = $linha['id_equivalencia'];
    $ce_id = $linha['id_cadeira_estrangeira'];
    if (!isset($tmp[$eq_id][$ce_id])) {
        $tmp[$eq_id][$ce_id] = [
            'nome'  => $linha['nome_cadeira_estrangeira'],
            'ects'  => $linha['ects_estrangeira'],
            'links' => [],
        ];
    }
    if (!empty($linha['link_url']) && !in_array($linha['link_url'], $tmp[$eq_id][$ce_id]['links'])) {
        $tmp[$eq_id][$ce_id]['links'][] = $linha['link_url'];
    }
}

$agrupadas = [];
$sigs      = [];
foreach ($tmp as $eq_id => $cadeiras) {
    $sig = implode('_', array_keys($cadeiras));
    if (!in_array($sig, $sigs)) {
        $sigs[]            = $sig;
        $agrupadas[$eq_id] = $cadeiras;
    }
}

Database::disconnect();

$pageTitle = 'Equivalências — ' . htmlspecialchars($infoCad['nome_cadeira_feup']);
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:8px">
  <div class="mr-auto">
    <h1>Composição da Equivalência</h1>
    <?php if ($infoInst): ?>
    <small class="text-muted"><?= htmlspecialchars($infoInst['nome_instituicao']) ?></small>
    <?php endif; ?>
  </div>
  <a href="index.php?instituicao=<?= $id_inst ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left mr-1"></i>Voltar
  </a>
</div>

<div class="card shadow-sm mb-4" style="border-left:4px solid var(--iq-primary)">
  <div class="card-body py-2" style="font-size:.87rem">
    <div class="text-muted" style="font-size:.75rem">UC da FEUP</div>
    <strong><?= htmlspecialchars($infoCad['nome_cadeira_feup']) ?></strong>
  </div>
</div>

<p class="text-muted mb-3" style="font-size:.87rem">
  <?= count($agrupadas) ?> conjunto<?= count($agrupadas) !== 1 ? 's' : '' ?> alternativo<?= count($agrupadas) !== 1 ? 's' : '' ?> encontrado<?= count($agrupadas) !== 1 ? 's' : '' ?>.
  Pode cumprir <u>qualquer um</u> dos seguintes:
</p>

<?php $n = 1; foreach ($agrupadas as $eq_id => $cadeiras): ?>
<?php if ($n > 1): ?>
<div class="text-center my-3">
  <span class="badge badge-secondary px-3 py-1" style="font-size:.82rem">OU</span>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-3">
  <div class="card-header py-2 d-flex align-items-center" style="gap:8px">
    <strong>Opção <?= $n ?></strong>
    <small class="text-muted ml-auto">ref. #<?= (int)$eq_id ?></small>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0" style="font-size:.85rem">
      <thead>
        <tr>
          <th>Cadeira no estrangeiro</th>
          <th style="width:8%">ECTS</th>
          <th style="width:12%">Programa</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cadeiras as $dados): ?>
        <tr>
          <td><strong><?= htmlspecialchars($dados['nome']) ?></strong></td>
          <td><?= $dados['ects'] ? htmlspecialchars((string)$dados['ects']) . ' ECTS' : '—' ?></td>
          <td>
            <?php if ($dados['links']): ?>
              <?php foreach ($dados['links'] as $i => $url): ?>
              <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"
                 style="font-size:.8rem" class="d-block">
                Link<?= count($dados['links']) > 1 ? ' ' . ($i + 1) : '' ?> ↗
              </a>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $n++; endforeach; ?>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
