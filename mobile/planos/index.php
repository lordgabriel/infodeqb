<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$isErasmusAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsErasmus);

$stmt = $pdo->prepare(
    'SELECT I.id_instituicao, I.nome_instituicao, P.nome_pais
     FROM infodeqb_erasmus_instituicoes I
     JOIN infodeqb_erasmus_paises P ON I.id_pais = P.id_pais
     ORDER BY P.nome_pais ASC, I.nome_instituicao ASC'
);
$stmt->execute();
$instituicoes_por_pais = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $instituicoes_por_pais[$row['nome_pais']][] = $row;
}

$id_selecionado      = isset($_GET['instituicao']) ? (int)$_GET['instituicao'] : 0;
$cadeiras_disponiveis = [];
$planos_estudos       = [];

if ($id_selecionado) {
    $s1 = $pdo->prepare(
        'SELECT DISTINCT CF.id_cadeira_feup, CF.nome_cadeira_feup, CF.codigo, CF.ects_feup
         FROM infodeqb_erasmus_equivalencias E
         JOIN infodeqb_erasmus_cadeiras_feup CF ON E.id_cadeira_feup = CF.id_cadeira_feup
         WHERE E.id_instituicao = ?
         ORDER BY CF.nome_cadeira_feup ASC'
    );
    $s1->execute([$id_selecionado]);
    $cadeiras_disponiveis = $s1->fetchAll(PDO::FETCH_ASSOC);

    $s2 = $pdo->prepare(
        'SELECT DISTINCT E.numero_estudante_autor, E.ano_letivo,
                CF.nome_cadeira_feup, CF.codigo AS codigo_feup,
                CE.nome_cadeira_estrangeira, CE.ects_estrangeira, L.link_url
         FROM infodeqb_erasmus_equivalencias E
         JOIN infodeqb_erasmus_cadeiras_feup CF ON E.id_cadeira_feup = CF.id_cadeira_feup
         JOIN infodeqb_erasmus_equivalencias_relacao ECR ON E.id_equivalencia = ECR.id_equivalencia
         JOIN infodeqb_erasmus_cadeiras_estrangeiras CE ON ECR.id_cadeira_estrangeira = CE.id_cadeira_estrangeira
         LEFT JOIN infodeqb_erasmus_links_cadeiras L ON CE.id_cadeira_estrangeira = L.id_cadeira_estrangeira
         WHERE E.id_instituicao = ?
         ORDER BY E.ano_letivo DESC, E.numero_estudante_autor ASC, CE.nome_cadeira_estrangeira ASC'
    );
    $s2->execute([$id_selecionado]);

    foreach ($s2->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $chave = $linha['numero_estudante_autor'] . '_' . $linha['ano_letivo'];
        if (!isset($planos_estudos[$chave])) {
            $planos_estudos[$chave] = [
                'ano_letivo'   => $linha['ano_letivo'] ?? 'Não especificado',
                'equivalencias' => [],
            ];
        }
        $sig = trim($linha['nome_cadeira_estrangeira']) . '|||' . trim($linha['nome_cadeira_feup']);
        if (!isset($planos_estudos[$chave]['equivalencias'][$sig])) {
            $ects = (!empty($linha['ects_estrangeira']) && floatval($linha['ects_estrangeira']) > 0)
                ? htmlspecialchars((string)floatval($linha['ects_estrangeira']))
                : '-';
            $planos_estudos[$chave]['equivalencias'][$sig] = [
                'cadeira_est'  => trim($linha['nome_cadeira_estrangeira']),
                'ects_est'     => $ects,
                'cadeira_feup' => trim($linha['nome_cadeira_feup']),
                'codigo_feup'  => $linha['codigo_feup'] ?? '-',
                'links'        => [],
            ];
        }
        $eq = &$planos_estudos[$chave]['equivalencias'][$sig];
        if ($linha['ects_estrangeira'] && $eq['ects_est'] === '-') {
            $eq['ects_est'] = htmlspecialchars((string)floatval($linha['ects_estrangeira']));
        }
        if (!empty($linha['link_url']) && !in_array($linha['link_url'], $eq['links'])) {
            $eq['links'][] = $linha['link_url'];
        }
        unset($eq);
    }
}

Database::disconnect();

$pageTitle = 'Equivalências Erasmus';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:8px">
  <div class="mr-auto">
    <h1>Equivalências Erasmus</h1>
    <small class="text-muted">Consulta de equivalências de cadeiras por universidade de destino</small>
  </div>
  <?php if ($isErasmusAdmin): ?>
  <a href="admin.php" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-cog mr-1"></i>Gestão
  </a>
  <?php endif; ?>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-body py-3">
    <form method="GET" action="index.php">
      <label class="font-weight-bold mb-1 d-block" for="instituicao">Universidade de Destino</label>
      <select name="instituicao" id="instituicao" class="form-control"
              onchange="this.form.submit()">
        <option value="">— Escolha uma instituição —</option>
        <?php foreach ($instituicoes_por_pais as $pais => $lista): ?>
        <optgroup label="<?= htmlspecialchars($pais) ?>">
          <?php foreach ($lista as $inst): ?>
          <option value="<?= (int)$inst['id_instituicao'] ?>"
            <?= $id_selecionado === (int)$inst['id_instituicao'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($inst['nome_instituicao']) ?>
          </option>
          <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<?php if ($id_selecionado): ?>

<h5 class="mb-3">Pesquisa individual por UC da FEUP</h5>
<?php if ($cadeiras_disponiveis): ?>
<div class="card shadow-sm mb-4">
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" style="font-size:.87rem">
      <thead>
        <tr>
          <th style="width:10%">Código</th>
          <th>UC da FEUP</th>
          <th style="width:8%">ECTS</th>
          <th style="width:12%"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cadeiras_disponiveis as $cad): ?>
        <tr>
          <td><strong><?= htmlspecialchars($cad['codigo'] ?? '-') ?></strong></td>
          <td><?= htmlspecialchars($cad['nome_cadeira_feup']) ?></td>
          <td><?= htmlspecialchars((string)($cad['ects_feup'] ?? '-')) ?></td>
          <td>
            <a class="btn btn-xs btn-outline-primary"
               href="detalhes.php?id_cadeira_feup=<?= (int)$cad['id_cadeira_feup'] ?>&id_inst=<?= $id_selecionado ?>">
              Ver equivalências
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<p class="text-muted mb-4"><em>Nenhuma UC registada para esta instituição.</em></p>
<?php endif; ?>

<h5 class="mb-1">Planos de estudo completos</h5>
<p class="text-muted mb-3" style="font-size:.85rem">
  Conjuntos de disciplinas realizadas em simultâneo por antigos estudantes nesta universidade.
</p>

<?php if ($planos_estudos):
  $num = 1;
  foreach ($planos_estudos as $dados): ?>
<div class="card shadow-sm mb-3">
  <div class="card-header py-2 d-flex align-items-center" style="gap:8px">
    <strong>Plano #<?= $num ?></strong>
    <span class="badge badge-secondary ml-1"><?= htmlspecialchars($dados['ano_letivo']) ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0" style="font-size:.85rem">
      <thead>
        <tr>
          <th style="width:35%">Cadeira no estrangeiro</th>
          <th style="width:8%">ECTS</th>
          <th>Equivalência FEUP</th>
          <th style="width:10%">Programa</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dados['equivalencias'] as $eq): ?>
        <tr>
          <td><strong><?= htmlspecialchars($eq['cadeira_est']) ?></strong></td>
          <td><?= $eq['ects_est'] !== '-' ? $eq['ects_est'] . ' ECTS' : '—' ?></td>
          <td>
            <span class="text-primary font-weight-bold"><?= htmlspecialchars($eq['cadeira_feup']) ?></span>
            <small class="text-muted ml-1">(<?= htmlspecialchars($eq['codigo_feup']) ?>)</small>
          </td>
          <td>
            <?php if ($eq['links']): ?>
              <?php foreach ($eq['links'] as $i => $url): ?>
              <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"
                 class="d-block" style="font-size:.8rem">
                Link<?= count($eq['links']) > 1 ? ' ' . ($i + 1) : '' ?> ↗
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
<?php $num++; endforeach; ?>
<?php else: ?>
<p class="text-muted"><em>Nenhum plano de estudos estruturado encontrado.</em></p>
<?php endif; ?>

<?php endif; ?>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
