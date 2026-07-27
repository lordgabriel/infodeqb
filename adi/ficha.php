<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

// ── Modo de actualização (bloqueia acesso a não-admins) ───────────
$modo_atualizacao = false;
if ($modo_atualizacao && !$isAdmin) {
    $pageTitle = 'Em actualização';
    include ROOT_DIR . '/infodeqb/inc/header.php';
    echo '<div class="d-flex align-items-center justify-content-center" style="min-height:50vh">
          <div class="card text-center" style="max-width:480px;width:100%">
            <div class="card-body p-5">
              <div style="font-size:2.5rem;margin-bottom:1rem">🛠️</div>
              <h4 class="font-weight-bold mb-2">Em actualização</h4>
              <p class="text-muted">Estamos a actualizar a informação. O acesso ficará disponível assim que o processo estiver concluído.</p>
            </div>
          </div></div>';
    include ROOT_DIR . '/infodeqb/inc/footer.php';
    exit;
}

// ── Código numérico do utilizador (para comparações com DB) ───────
// $_SESSION['Code'] = up356946@up.pt  →  $currentNum = '356946'
$currentNum = preg_replace('/\D/', '', $_SESSION['Code'] ?? '');

// Admins locais ADI (além do admin global)
$_adiLocalAdmins  = array('356946', '246398');
$isAdiAdmin       = $isAdmin || in_array($currentNum, $_adiLocalAdmins);

// Acesso especial: par (utilizador → pode ver feup_id)
$special_access = array(
    '377662' => array('206237'),
    '448927' => array('206237'),
    '346678' => array('479807'),
    '211768' => array('479807'),
    '208925' => array('479807'),
);

// ── feup_id a visualizar ──────────────────────────────────────────
$feup_id = isset($_GET['feup_id']) ? (string)(int)$_GET['feup_id'] : null;

if (!$currentNum) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

// Verificar permissões de acesso
$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!$isAdiAdmin) {
    // Verificar se utilizador consta na tabela
    $chk = $pdo->prepare('SELECT COUNT(*) FROM infodeqb_espacos_elementos_deq WHERE feup_id = ?');
    $chk->execute(array($currentNum));
    if (!(int)$chk->fetchColumn()) {
        header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
        exit;
    }
}

// Se não passou feup_id, mostrar o próprio perfil
if (!$feup_id) {
    header('Location: ' . HTTP_DIR . '/infodeqb/adi/ficha.php?feup_id=' . $currentNum);
    exit;
}

// Verificar se pode ver este feup_id
$temAcessoEspecial = isset($special_access[$currentNum]) && in_array($feup_id, $special_access[$currentNum]);
if (!$isAdiAdmin && $currentNum !== $feup_id && !$temAcessoEspecial) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

// ── Queries ───────────────────────────────────────────────────────
try {
    $dados = $pdo->prepare(
        'SELECT t1.nome, t1.feup_id, t2.area_ocupada,
                t2.publicacoes_i, t2.formacao_i, t2.peso_docente,
                t2.transf_i, t2.Pc, t2.Cg, t2.Proj_i, t2.Pa_i, t2.A_atribuir
         FROM infodeqb_espacos_elementos_deq t1
         INNER JOIN infodeqb_espacos_pontuacoes t2 ON t1.feup_id = t2.feup_id
         WHERE t1.feup_id = ?'
    );
    $dados->execute(array($feup_id));
    $elem = $dados->fetch(PDO::FETCH_ASSOC);

    if (!$elem) {
        header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
        exit;
    }

    // PA total (ponderação sobre todos os elementos)
    $pa_total = (float)$pdo->query(
        'SELECT COALESCE(SUM(peso_docente * Pa_i), 0) FROM infodeqb_espacos_pontuacoes'
    )->fetchColumn();

    $formacao = $pdo->prepare(
        'SELECT *, YEAR(STR_TO_DATE(data_defesa,\'%d/%m/%Y\')) AS ano_defesa
         FROM infodeqb_espacos_formacao WHERE feup_id = ? ORDER BY ano_defesa DESC, nome_estudante ASC'
    );
    $formacao->execute(array($feup_id));
    $resultados_formacao = $formacao->fetchAll(PDO::FETCH_ASSOC);

    $trf = $pdo->prepare('SELECT * FROM infodeqb_espacos_trf WHERE feup_id = ? ORDER BY titulo');
    $trf->execute(array($feup_id));
    $resultados_trf = $trf->fetchAll(PDO::FETCH_ASSOC);

    $proj = $pdo->prepare('SELECT * FROM infodeqb_espacos_projetos WHERE feup_id = ? ORDER BY titulo');
    $proj->execute(array($feup_id));
    $resultados_proj = $proj->fetchAll(PDO::FETCH_ASSOC);

    $pub = $pdo->prepare(
        'SELECT e.nome AS elemento_nome, e.feup_id AS elemento_codigo,
                d.title, d.year, d.journal, d.quartile, d.doi, d.pub_id,
                d.pi, d.deq_authors, d.total_authors, ep.nome_ref,
                COALESCE(a.autores,\'\') AS autores
         FROM infodeqb_espacos_elementos_deq e
         JOIN infodeqb_espacos_pub_deq ep ON e.feup_id = ep.feup_id
         JOIN infodeqb_espacos_pub_details d ON ep.pub_id = d.pub_id
         LEFT JOIN (
             SELECT pub_id,
                    GROUP_CONCAT(author ORDER BY author_order SEPARATOR \', \') AS autores
             FROM infodeqb_espacos_pub_authors GROUP BY pub_id
         ) a ON d.pub_id = a.pub_id
         WHERE e.feup_id = ?
         ORDER BY d.year DESC, autores ASC'
    );
    $pub->execute(array($feup_id));
    $resultados_pub = $pub->fetchAll(PDO::FETCH_ASSOC);

    $ges = $pdo->prepare('SELECT * FROM infodeqb_espacos_gestao WHERE feup_id = ? ORDER BY funcao');
    $ges->execute(array($feup_id));
    $resultados_ges = $ges->fetchAll(PDO::FETCH_ASSOC);

    Database::disconnect();

} catch (Exception $e) {
    Database::disconnect();
    die('Erro ao carregar dados: ' . htmlspecialchars($e->getMessage()));
}

function formatar_data($data) {
    $d = DateTime::createFromFormat('d/m/Y', $data);
    return $d ? $d->format('d/m/Y') : htmlspecialchars((string)$data);
}

function fmt1($v) { return number_format((float)$v, 1, ',', '.'); }
function fmt2($v) { return number_format((float)$v, 2, ',', '.'); }

$pageTitle = htmlspecialchars($elem['nome']) . ' — Espaços de Investigação';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<style>
/* ── Nav pills lateral ─────────────────────────────────────── */
.adi-tabs .nav-link {
    font-size:.83rem; padding:.45rem .8rem;
    white-space:nowrap; border-radius:6px; color:#495057;
    transition:background .12s, color .12s;
}
.adi-tabs .nav-link:hover:not(.active) { background:#f3f4f6; }

/* Cores das tabs activas */
.adi-tabs .nav-link[href="#tab-pub"].active,
.adi-tabs .nav-link[href="#tab-form"].active,
.adi-tabs .nav-link[href="#tab-trf"].active,
.adi-tabs .nav-link[href="#tab-ges"].active,
.adi-tabs .nav-link[href="#tab-proj"].active { background:var(--iq-blue-light); color:var(--iq-blue); }

/* ── Cabeçalhos de tabela — mesma cor em todas as secções ──── */
#tab-pub  thead.table-dark th,
#tab-form thead.table-dark th,
#tab-trf  thead.table-dark th,
#tab-ges  thead.table-dark th,
#tab-proj thead.table-dark th { background:var(--iq-nav-bg)!important; color:#fff!important; }

/* ── Sub-headings de secção ────────────────────────────────── */
#tab-pub  h6.font-weight-bold { color:var(--iq-blue); }
#tab-form h6.font-weight-bold.text-primary { color:var(--iq-blue)!important; }
#tab-trf  h6.font-weight-bold.text-primary { color:var(--iq-blue)!important; }
#tab-ges  h6.font-weight-bold.text-primary { color:var(--iq-blue)!important; }
</style>

<div class="iq-page-header d-flex align-items-center">
  <div class="mr-auto">
    <h1><i class="fas fa-user-circle fa-sm me-2 text-muted"></i><?= htmlspecialchars($elem['nome']) ?></h1>
    <small class="text-muted">Espaços de Investigação · Produção Científica e Gestão 2018–2024</small>
  </div>
  <div class="d-flex align-items-center" style="gap:8px">
    <?php if ($isAdiAdmin): ?>
    <a href="<?= HTTP_DIR ?>/infodeqb/adi/" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-arrow-left me-1"></i><?= t('ADI_BACK_LIST') ?>
    </a>
    <?php endif; ?>
    <a href="<?= HTTP_DIR ?>/infodeqb/adi/Criterios_Espacos_Investigacao_DEQB.pdf"
       class="btn btn-outline-secondary btn-sm" target="_blank" download>
      <i class="fas fa-file-download me-1"></i>Critérios
    </a>
  </div>
</div>

<?php /* ── Painel de pontuações ────────────────────────────────── */ ?>
<div class="card shadow-sm mb-4">
  <div class="card-header py-2 iq-header-dark">
    <strong>Pontuação e Área</strong>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6 mb-3 mb-md-0">
        <div class="adi-score-card sc-blue h-100">
          <div class="mb-2 font-weight-bold" style="color:var(--iq-blue);font-size:.83rem">Produção Científica (P<sub>Ci</sub>)</div>
          <div class="d-flex align-items-baseline flex-wrap" style="gap:6px;font-size:.82rem">
            <span>P<sub>Pub</sub> = <strong><?= fmt1($elem['publicacoes_i']) ?></strong></span>
            <span class="text-muted">+</span>
            <span>P<sub>Form.</sub> = <strong><?= fmt1($elem['formacao_i']) ?></strong></span>
            <span class="text-muted">+</span>
            <span>P<sub>Transf.</sub> = <strong><?= fmt1($elem['transf_i']) ?></strong></span>
            <span class="text-muted">=</span>
            <span class="adi-score-val"><?= fmt1($elem['publicacoes_i'] + $elem['formacao_i'] + $elem['transf_i']) ?></span>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="row">
          <div class="col-6 col-md-3 mb-2">
            <div class="adi-score-card sc-purple text-center">
              <div class="small font-weight-bold" style="color:#6f42c1">Gestão (C<sub>g</sub>)</div>
              <div class="adi-score-val"><?= fmt1($elem['Cg']) ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3 mb-2">
            <div class="adi-score-card sc-teal text-center">
              <div class="small font-weight-bold" style="color:#0e7490">Projetos (P<sub>roj</sub>)</div>
              <div class="adi-score-val"><?= fmt1($elem['Proj_i']) ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3 mb-2">
            <div class="adi-score-card sc-green text-center">
              <div class="small font-weight-bold" style="color:#1e7e34">Área actual (m²)</div>
              <div class="adi-score-val"><?= $isAdiAdmin ? fmt1($elem['area_ocupada']) : '<span class="text-muted" style="font-size:.85rem">N.D.</span>' ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3 mb-2">
            <div class="adi-score-card sc-amber text-center">
              <div class="small font-weight-bold" style="color:#856404">Área prevista (m²)</div>
              <div class="adi-score-val"><?= $isAdiAdmin ? fmt1($elem['A_atribuir']) : '<span class="text-muted" style="font-size:.85rem">N.D.</span>' ?></div>
            </div>
          </div>
          <?php if ($isAdiAdmin): ?>
          <div class="col-6 col-md-3 mb-0">
            <div class="adi-score-card sc-slate text-center">
              <div class="small font-weight-bold" style="color:#374151">Pa individual</div>
              <div class="adi-score-val"><?= fmt1($elem['Pa_i']) ?></div>
            </div>
          </div>
          <div class="col-6 col-md-3 mb-0">
            <div class="adi-score-card sc-slate text-center">
              <div class="small font-weight-bold" style="color:#374151">Pa total dept.</div>
              <div class="adi-score-val"><?= fmt1($pa_total) ?></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php /* ── Tabs de detalhe ──────────────────────────────────────── */ ?>
<div class="d-flex align-items-start">

  <!-- Nav lateral -->
  <div class="nav flex-column nav-pills adi-tabs me-3" id="adiTab" role="tablist"
       style="min-width:130px;position:sticky;top:10px">
    <a class="nav-link active" data-bs-toggle="pill" href="#tab-pub"    role="tab">
      <i class="fas fa-newspaper fa-xs me-1"></i>Publicações
    </a>
    <a class="nav-link" data-bs-toggle="pill" href="#tab-form"   role="tab">
      <i class="fas fa-graduation-cap fa-xs me-1"></i>Formação
    </a>
    <a class="nav-link" data-bs-toggle="pill" href="#tab-trf"    role="tab">
      <i class="fas fa-lightbulb fa-xs me-1"></i>Transferência
    </a>
    <a class="nav-link" data-bs-toggle="pill" href="#tab-ges"    role="tab">
      <i class="fas fa-tasks fa-xs me-1"></i>Gestão
    </a>
    <a class="nav-link" data-bs-toggle="pill" href="#tab-proj"   role="tab">
      <i class="fas fa-project-diagram fa-xs me-1"></i>Projetos
    </a>
  </div>

  <!-- Conteúdo -->
  <div class="tab-content flex-fill" id="adiTabContent">

    <?php /* ── Publicações ─────────────────────────────────────── */ ?>
    <div class="tab-pane fade show active" id="tab-pub" role="tabpanel">
      <div class="alert alert-secondary py-2 mb-3 text-start" style="justify-content:flex-start;font-size:.8rem">
        <i class="fas fa-info-circle me-1"></i>
        Os docentes eméritos e aposentados não são considerados como 'Autores DEQB' para efeito da pontuação.
      </div>


      <?php
      $ordem_ref = array(
          'Autor de livro internacional',
          'Autor de livro nacional',
          'Autor de artigo em revista científica indexada Q1 ou Q2',
          'Autor de artigo em revista científica indexada Q3 ou Q4 ou ESCI',
          'Editor de livro',
      );
      if (empty($resultados_pub)) {
          echo '<p class="text-muted">' . t('NO_RECORDS') . '</p>';
      } else {
          $grupos = array();
          foreach ($resultados_pub as $linha) {
              $grupos[$linha['nome_ref'] ?? 'Outros'][] = $linha;
          }
          foreach ($ordem_ref as $nome_ref) {
              if (!isset($grupos[$nome_ref])) continue;
              $rows = $grupos[$nome_ref];
              $totais_ano = array();
              foreach ($rows as $r) {
                  $ano = (string)$r['year'];
                  $totais_ano[$ano] = ($totais_ano[$ano] ?? 0) + (float)$r['pi'];
              }
              usort($rows, function($a, $b) {
                  return $a['year'] == $b['year'] ? strcmp($a['autores'], $b['autores']) : ($a['year'] < $b['year'] ? 1 : -1);
              });
              echo '<h6 class="font-weight-bold mt-3 mb-2">' . htmlspecialchars($nome_ref) . '</h6>';
              echo '<div class="table-responsive"><small><table class="table table-sm  mb-3">';
              echo '<thead class="table-dark"><tr>';
              echo '<th >Publicação</th>';
              echo '<th class="text-center" style="width:6em">Aut. DEQB</th>';
              echo '<th class="text-center" style="width:5em">Total</th>';
              echo '<th class="text-end" style="width:4em">Pi</th>';
              echo '</tr></thead><tbody>';
              $ultimo_ano = null; $i = 0;
              foreach ($rows as $linha) {
                  $ano = (string)$linha['year'];
                  if ($ano !== $ultimo_ano) {
                      echo '<tr class="table-active font-weight-bold">';
                      echo '<td colspan="3">' . htmlspecialchars($ano) . '</td>';
                      echo '<td class="text-end">' . fmt2($totais_ano[$ano]) . '</td>';
                      echo '</tr>';
                      $ultimo_ano = $ano; $i = 1;
                  } else { $i++; }
                  $doi = trim($linha['doi'] ?? '');
                  $doi_html = $doi ? ' <a href="https://doi.org/' . htmlspecialchars($doi) . '" target="_blank" class="text-muted"><i class="fas fa-external-link-alt fa-xs"></i></a>' : '';
                  echo '<tr>';
                  echo '<td><b>' . $i . ')</b> ' . htmlspecialchars($linha['autores']) . ' (' . htmlspecialchars($linha['year']) . '), <em>' . htmlspecialchars($linha['title']) . '</em>. ' . htmlspecialchars($linha['journal']) . $doi_html . '</td>';
                  echo '<td class="text-center">' . (int)$linha['deq_authors'] . '</td>';
                  echo '<td class="text-center">' . htmlspecialchars($linha['total_authors']) . '</td>';
                  echo '<td class="text-end">' . fmt2($linha['pi']) . '</td>';
                  echo '</tr>';
              }
              echo '</tbody></table></small></div>';
          }
      }
      ?>
    </div>

    <?php /* ── Formação ─────────────────────────────────────────── */ ?>
    <div class="tab-pane fade" id="tab-form" role="tabpanel">
      <div class="alert alert-secondary py-2 mb-3" style="font-size:.8rem">
        <i class="fas fa-info-circle me-1"></i>
        As pontuações de doutoramentos concluídos são atribuídas ao orientador, ficando a cargo deste decidir o peso a dividir pelo(s) coorientador(es).
      </div>
      <?php
      if (empty($resultados_formacao)) {
          echo '<p class="text-muted">' . t('NO_RECORDS') . '</p>';
      } else {
          $form_ref = array();
          foreach ($resultados_formacao as $linha) $form_ref[$linha['nome_ref']][] = $linha;
          foreach ($form_ref as $nome_ref => $rows):
              $totais_ano = array();
              foreach ($rows as $r) {
                  $ano = $r['ano_defesa'];
                  $totais_ano[$ano] = ($totais_ano[$ano] ?? 0) + (float)str_replace(',', '.', $r['pi2']);
              }
              echo '<h6 class="font-weight-bold text-primary mt-3 mb-2">' . htmlspecialchars($nome_ref) . '</h6>';
              echo '<div class="table-responsive"><small><table class="table table-sm  mb-3">';
              echo '<thead class="table-dark"><tr>';
              echo '<th>Estudante</th><th>Papel</th><th class="text-center">Contrib.</th><th>Título</th><th class="text-center">Curso</th><th class="text-end" style="width:4em">Pi</th>';
              echo '</tr></thead><tbody>';
              $ultimo_ano = null;
              foreach ($rows as $linha) {
                  $ano = $linha['ano_defesa'];
                  if ($ano !== $ultimo_ano) {
                      echo '<tr class="table-active font-weight-bold">';
                      echo '<td colspan="5">' . htmlspecialchars($ano) . '</td>';
                      echo '<td class="text-end">' . fmt1($totais_ano[$ano]) . '</td>';
                      echo '</tr>';
                      $ultimo_ano = $ano;
                  }
                  echo '<tr>';
                  echo '<td>' . htmlspecialchars($linha['nome_estudante']) . '</td>';
                  echo '<td>' . htmlspecialchars($linha['papel']) . '</td>';
                  echo '<td class="text-center">' . htmlspecialchars($linha['peso_papel']) . '</td>';
                  echo '<td>' . htmlspecialchars($linha['titulo']) . '</td>';
                  echo '<td class="text-center">' . htmlspecialchars($linha['sigla_curso']) . '</td>';
                  echo '<td class="text-end">' . fmt1((float)str_replace(',', '.', $linha['pi2'])) . '</td>';
                  echo '</tr>';
              }
              echo '</tbody></table></small></div>';
          endforeach;
      }
      ?>
    </div>

    <?php /* ── Transferência de Tecnologia ──────────────────────── */ ?>
    <div class="tab-pane fade" id="tab-trf" role="tabpanel">
      <?php
      if (empty($resultados_trf)) {
          echo '<p class="text-muted">' . t('NO_RECORDS') . '</p>';
      } else {
          $trf_ref = array();
          foreach ($resultados_trf as $linha) $trf_ref[$linha['categoria']][] = $linha;
          foreach ($trf_ref as $cat => $rows):
              $is_spinoff = ($cat === 'Spin-Off');
              echo '<h6 class="font-weight-bold text-primary mt-3 mb-2">' . htmlspecialchars($cat) . '</h6>';
              echo '<div class="table-responsive"><small><table class="table table-sm  mb-3">';
              echo '<thead class="table-dark"><tr>';
              if ($is_spinoff) {
                  echo '<th>Empresa</th><th class="text-center">Ano</th><th class="text-center">Aut. DEQB</th><th class="text-center">Outros</th><th class="text-end">Pi</th>';
              } else {
                  echo '<th>Título</th><th>Aut. DEQB</th><th>Outros</th><th class="text-center">Reg. Nac.</th><th class="text-center">Reg. Int.</th><th class="text-center">Out. Nac.</th><th class="text-center">Out. Int.</th><th class="text-end">Pi</th>';
              }
              echo '</tr></thead><tbody>';
              $sub = 0;
              foreach ($rows as $linha):
                  echo '<tr>';
                  echo '<td>' . htmlspecialchars($linha['titulo']) . '</td>';
                  if ($is_spinoff) {
                      echo '<td class="text-center">' . (!empty($linha['data_registo_inpi']) ? date('Y', strtotime($linha['data_registo_inpi'])) : '') . '</td>';
                      echo '<td class="text-center">' . htmlspecialchars($linha['autores_deq']) . '</td>';
                      echo '<td class="text-center">' . htmlspecialchars($linha['outros_autores']) . '</td>';
                      echo '<td class="text-end">' . fmt1($linha['Pi']) . '</td>';
                      $sub += (float)$linha['Pi'];
                  } else {
                      $pi = round((float)$linha['Pi'], 1);
                      echo '<td class="text-center">' . htmlspecialchars($linha['autores_deq']) . '</td>';
                      echo '<td class="text-center">' . htmlspecialchars($linha['outros_autores']) . '</td>';
                      echo '<td class="text-center">' . htmlspecialchars($linha['data_registo_inpi']) . '</td>';
                      echo '<td class="text-center">' . htmlspecialchars($linha['data_registo_internacional']) . '</td>';
                      echo '<td class="text-center">' . htmlspecialchars($linha['data_outorgada_inpi']) . '</td>';
                      echo '<td class="text-center">' . htmlspecialchars($linha['data_outorgada_internacional']) . '</td>';
                      echo '<td class="text-end">' . fmt1($pi) . '</td>';
                      $sub += $pi;
                  }
                  echo '</tr>';
              endforeach;
              echo '<tr class="table-active font-weight-bold"><td class="text-end" colspan="' . ($is_spinoff ? 5 : 8) . '">Total: ' . fmt1($sub) . '</td></tr>';
              echo '</tbody></table></small></div>';
          endforeach;
      }
      ?>
    </div>

    <?php /* ── Gestão ───────────────────────────────────────────── */ ?>
    <div class="tab-pane fade" id="tab-ges" role="tabpanel">
      <?php
      if (empty($resultados_ges)) {
          echo '<p class="text-muted">' . t('NO_RECORDS') . '</p>';
      } else {
          $ges_grp = array();
          foreach ($resultados_ges as $linha) $ges_grp[$linha['grupo']][] = $linha;
          foreach ($ges_grp as $grupo => $rows):
              echo '<h6 class="font-weight-bold text-primary mt-3 mb-2">' . htmlspecialchars($grupo) . '</h6>';
              echo '<div class="table-responsive"><small><table class="table table-sm  mb-3">';
              echo '<thead class="table-dark"><tr>';
              echo '<th>Função</th><th style="width:8em">Início</th><th style="width:8em">Fim</th><th class="text-center" style="width:7em">Fracção (Anos)</th><th class="text-end" style="width:4em">Pi</th>';
              echo '</tr></thead><tbody>';
              $sub = 0;
              foreach ($rows as $linha) {
                  echo '<tr>';
                  echo '<td>' . htmlspecialchars($linha['funcao']) . '</td>';
                  echo '<td>' . formatar_data($linha['data_inicio']) . '</td>';
                  echo '<td>' . formatar_data($linha['data_fim']) . '</td>';
                  echo '<td class="text-end">' . htmlspecialchars($linha['Frac_meses_periodo']) . '</td>';
                  echo '<td class="text-end">' . fmt1($linha['Pi']) . '</td>';
                  echo '</tr>';
                  $sub += (float)$linha['Pi'];
              }
              echo '<tr class="table-active font-weight-bold"><td class="text-end" colspan="5">Total: ' . fmt1($sub) . '</td></tr>';
              echo '</tbody></table></small></div>';
          endforeach;
      }
      ?>
    </div>

    <?php /* ── Projetos ─────────────────────────────────────────── */ ?>
    <div class="tab-pane fade" id="tab-proj" role="tabpanel">
      <div class="alert alert-secondary py-2 mb-3" style="font-size:.8rem">
        <i class="fas fa-info-circle me-1"></i>
        Para os projetos foi utilizada a percentagem de trabalho constante na ficha do projeto no SIGARRA.
      </div>
      <?php
      if (empty($resultados_proj)) {
          echo '<p class="text-muted">' . t('NO_RECORDS') . '</p>';
      } else {
          echo '<div class="table-responsive"><small><table class="table table-sm ">';
          echo '<thead class="table-dark"><tr>';
          echo '<th>Título</th><th style="width:15em">Papel</th>';
          echo '<th class="text-end" style="width:5em">% Trab.</th>';
          echo '<th class="text-end" style="width:5em">Frac. Anos</th>';
          echo '<th class="text-end" style="width:4em">Ti</th><th class="text-end" style="width:4em">Ei</th>';
          echo '<th class="text-end" style="width:4em">Fi</th><th class="text-end" style="width:4em">Vi</th>';
          echo '<th class="text-end" style="width:4em">Pi</th>';
          echo '</tr></thead><tbody>';
          $sub = 0;
          foreach ($resultados_proj as $p) {
              echo '<tr>';
              echo '<td><a href="https://sigarra.up.pt/feup/pt/projectos_geral.mostra_projecto?P_ID=' . (int)$p['proj_id'] . '" target="_blank">' . htmlspecialchars($p['titulo']) . ' <i class="fas fa-external-link-alt fa-xs text-muted"></i></a></td>';
              echo '<td>' . htmlspecialchars($p['papel']) . '</td>';
              echo '<td class="text-end">' . fmt1($p['perc_trab']) . '</td>';
              echo '<td class="text-end">' . fmt1($p['frac_anos']) . '</td>';
              echo '<td class="text-end">' . fmt1($p['Ti']) . '</td>';
              echo '<td class="text-end">' . fmt1($p['Ei']) . '</td>';
              echo '<td class="text-end">' . fmt1($p['Fi']) . '</td>';
              echo '<td class="text-end">' . fmt1($p['Vi']) . '</td>';
              echo '<td class="text-end">' . fmt2($p['Pi']) . '</td>';
              echo '</tr>';
              $sub += (float)$p['Pi'];
          }
          echo '<tr class="table-active font-weight-bold"><td class="text-end" colspan="9">Total: ' . fmt2($sub) . '</td></tr>';
          echo '</tbody></table></small></div>';
      }
      ?>
    </div>

  </div><!-- /tab-content -->
</div><!-- /d-flex -->

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
