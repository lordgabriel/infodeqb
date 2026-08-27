<?php
require $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'].'/infodeqb/session.php';

// === MODO DE ATUALIZAÇÃO ===
$modo_atualizacao = false;

$filtro_users_ativo = true;
$users_com_filtro = [
    '356946','246398','249542','320005','369837',
    '246608','211326','403670','209838','211847',
    '334829','246389','427186',
];
$unidade_filtro = 'EF';

$admin_users = ['356946','246398'];

$current_user = $_SESSION['Code'] ?? null;
// Código numérico para comparações com arrays de códigos numéricos
$currentNum   = preg_replace('/\D/', '', $current_user ?? '');

require_once ROOT_DIR . '/infodeqb/inc/admins.php';

// Modo manutenção (página isolada — não usa header comum)
if ($modo_atualizacao && !$isAdmin && !in_array($currentNum, $admin_users)) {
    require $_SERVER['DOCUMENT_ROOT'].'/deqbwww.php';
    $base = HTTP_DIR . '/infodeqb';
    ?><!DOCTYPE html>
<html lang="pt"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Em atualização — InfoDEQB</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo $base; ?>/css/infodeq.css" rel="stylesheet">
</head><body style="background:var(--iq-bg);display:flex;align-items:center;justify-content:center;min-height:100vh;">
<div class="card text-center" style="max-width:500px;width:100%;">
  <div class="card-body p-5">
    <div style="font-size:2.5rem;margin-bottom:1rem;">🛠️</div>
    <h4 class="font-weight-700 mb-2">Em atualização</h4>
    <p class="text-muted">Estamos a atualizar a informação. O acesso ficará disponível assim que o processo estiver concluído.</p>
    <p class="text-muted">Obrigado pela compreensão.</p>
  </div>
</div>
</body></html><?php
    exit;
}

if (!$currentNum) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

// Admin local ou global → acesso total ao index
$isAdiAdmin = $isAdmin || in_array($currentNum, $admin_users);
// Utilizadores com filtro → vêem o index mas só a unidade filtrada
$temFiltro  = $filtro_users_ativo && in_array($currentNum, $users_com_filtro);

if (!$isAdiAdmin && !$temFiltro) {
    header('Location: ' . HTTP_DIR . '/infodeqb/adi/ficha.php?feup_id=' . urlencode($currentNum));
    exit;
}

// Admin global vê tudo; admin local e utilizadores com filtro vêem só a unidade filtrada
$aplicar_filtro = (!$isAdiAdmin && $temFiltro);

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
    SELECT t1.nome, t1.feup_id, t1.unidade, t1.grupo, t1.subgrupo,
           t2.area_ocupada, t2.Pa_i, t2.A_atribuir, t2.area_EF
    FROM infodeqb_espacos_elementos_deq t1
    INNER JOIN infodeqb_espacos_pontuacoes t2 ON t1.feup_id = t2.feup_id
";
if ($aplicar_filtro) {
    $sql .= " WHERE t1.unidade = :unidade ";
}
$sql .= " ORDER BY t1.unidade, t1.grupo, t1.subgrupo DESC, t1.nome";

$stmt = $pdo->prepare($sql);
if ($aplicar_filtro) {
    $stmt->bindParam(':unidade', $unidade_filtro, PDO::PARAM_STR);
}
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totais
$unidadeTotals = []; $grupoTotals = []; $subgrupoTotals = [];
foreach ($result as $row) {
    $u = $row['unidade'] ?? '';
    $g = $row['grupo']   ?? '';
    $s = $row['subgrupo'] ?? '';
    $area   = (float)($row['area_ocupada'] ?? 0);
    $atr    = (float)($row['A_atribuir']   ?? 0);
    $areaEF = (float)($row['area_EF']      ?? 0);

    if (!isset($unidadeTotals[$u])) $unidadeTotals[$u] = ['area_ocupada'=>0,'A_atribuir'=>0,'area_EF'=>0];
    $unidadeTotals[$u]['area_ocupada'] += $area;
    $unidadeTotals[$u]['A_atribuir']   += $atr;
    $unidadeTotals[$u]['area_EF']      += $areaEF;

    if (!isset($grupoTotals[$u][$g])) $grupoTotals[$u][$g] = ['area_ocupada'=>0,'A_atribuir'=>0,'area_EF'=>0];
    $grupoTotals[$u][$g]['area_ocupada'] += $area;
    $grupoTotals[$u][$g]['A_atribuir']   += $atr;
    $grupoTotals[$u][$g]['area_EF']      += $areaEF;

    if (!isset($subgrupoTotals[$u][$g][$s])) $subgrupoTotals[$u][$g][$s] = ['area_ocupada'=>0,'A_atribuir'=>0,'area_EF'=>0];
    $subgrupoTotals[$u][$g][$s]['area_ocupada'] += $area;
    $subgrupoTotals[$u][$g][$s]['A_atribuir']   += $atr;
    $subgrupoTotals[$u][$g][$s]['area_EF']      += $areaEF;
}

$pageTitle = t('ADI_PAGE_TITLE');
$mainClass  = 'iq-hr-page';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

<style>
#adiTable                              { font-size:.875rem; }
#adiTable thead th                     { background:var(--iq-accent)!important; color:#fff!important; font-size:.875rem; }
/* indentação dos sub-níveis — sobrepõe o padding do iq-tbl-group-* */
#adiTable .row-grupo    > td:first-child { padding-left:1.25rem!important; }
#adiTable .row-subgrupo > td:first-child { padding-left:2.5rem!important;  }
/* valores numéricos: mesmo tamanho e alinhamento em todas as linhas */
#adiTable tr > td:not(:first-child) { font-size:.875rem!important; text-align:right!important; }
</style>

<div class="iq-page-header">
  <h1><i class="fas fa-building fa-sm me-2 text-muted"></i><?= t('ADI_TITLE') ?></h1>
  <div class="iq-page-header-actions">
    <a href="<?php echo HTTP_DIR; ?>/infodeqb/adi/Criterios_Espacos_Investigacao_DEQB.pdf"
       class="btn btn-secondary btn-sm" target="_blank" download>
      <i class="fas fa-file-download me-1"></i> <?= t('ADI_CRITERIA') ?>
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="fas fa-table"></i> <?= t('ADI_SUBTITLE') ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 iq-no-stripe" id="adiTable">
        <thead>
          <tr>
            <th rowspan="2">Nome</th>
            <th rowspan="2" class="text-end"><?= t('ADI_AREA_CURRENT') ?></th>
            <th colspan="2" class="text-center"><?= t('ADI_AREA_FORECAST') ?></th>
          </tr>
          <tr>
            <th class="text-end"><?= t('ADI_AREA_DEQ_DEF') ?></th>
            <th class="text-end"><?= t('ADI_AREA_DEF_ONLY') ?></th>
          </tr>
        </thead>
        <tbody>
<?php
$currentUnidade = $currentGrupo = $currentSubgrupo = null;
// $isAdiAdmin já foi definido no topo via admins.php; não sobrescrever

if ($result) {
    foreach ($result as $row) {
        $u = $row['unidade'] ?? '';
        $g = $row['grupo']   ?? '';
        $s = $row['subgrupo'] ?? '';
        $area   = (float)($row['area_ocupada'] ?? 0);
        $atr    = (float)($row['A_atribuir']   ?? 0);
        $areaEF = (float)($row['area_EF']      ?? 0);
        $nd     = '<span class="text-muted">N.D.</span>';
        $isEF   = ($u === 'EF');

        if ($currentUnidade !== $u) {
            $currentUnidade = $u; $currentGrupo = $currentSubgrupo = null;
            $ut = $unidadeTotals[$u];
            echo "<tr class='row-unidade iq-tbl-group-1'>";
            echo "<td>" . htmlspecialchars($u) . "</td>";
            if ($isAdiAdmin || $temFiltro) {
                echo "<td class='text-end'>" . number_format($ut['area_ocupada'],1,',','.') . "</td>";
                echo "<td class='text-end'>" . number_format($ut['A_atribuir'],1,',','.') . "</td>";
                echo "<td class='text-end'>" . ($isEF ? number_format($ut['area_EF'],1,',','.') : '—') . "</td>";
            } else {
                echo "<td class='text-end'>$nd</td><td class='text-end'>$nd</td><td class='text-end'>$nd</td>";
            }
            echo "</tr>";
        }

        if ($currentGrupo !== $g) {
            $currentGrupo = $g; $currentSubgrupo = null;
            $gt = $grupoTotals[$u][$g] ?? ['area_ocupada'=>0,'A_atribuir'=>0,'area_EF'=>0];
            echo "<tr class='row-grupo iq-tbl-group-2'>";
            echo "<td>" . htmlspecialchars($g) . "</td>";
            if ($isAdiAdmin || $temFiltro) {
                echo "<td class='text-end'>" . number_format($gt['area_ocupada'],1,',','.') . "</td>";
                echo "<td class='text-end'>" . number_format($gt['A_atribuir'],1,',','.') . "</td>";
                echo "<td class='text-end'>" . ($isEF ? number_format($gt['area_EF'],1,',','.') : '—') . "</td>";
            } else {
                echo "<td class='text-end'>$nd</td><td class='text-end'>$nd</td><td class='text-end'>$nd</td>";
            }
            echo "</tr>";
        }

        if ($currentSubgrupo !== $s) {
            $currentSubgrupo = $s;
            $st = $subgrupoTotals[$u][$g][$s] ?? ['area_ocupada'=>0,'A_atribuir'=>0,'area_EF'=>0];
            echo "<tr class='row-subgrupo iq-tbl-group-3'>";
            echo "<td>" . htmlspecialchars($s) . "</td>";
            if ($isAdiAdmin || $temFiltro) {
                echo "<td class='text-end'>" . number_format($st['area_ocupada'],1,',','.') . "</td>";
                echo "<td class='text-end'>" . number_format($st['A_atribuir'],1,',','.') . "</td>";
                echo "<td class='text-end'>" . ($isEF ? number_format($st['area_EF'],1,',','.') : '—') . "</td>";
            } else {
                echo "<td class='text-end'>$nd</td><td class='text-end'>$nd</td><td class='text-end'>$nd</td>";
            }
            echo "</tr>";
        }

        // Linha do elemento
        echo "<tr>";
        echo "<td style='padding-left:3.75rem'>";
        echo "<a href='" . HTTP_DIR . "/infodeqb/adi/ficha.php?feup_id=" . urlencode($row['feup_id']) . "'>" . htmlspecialchars($row['nome']) . "</a>";
        echo "</td>";
        if ($isAdiAdmin || $temFiltro) {
            echo "<td class='text-end'>" . number_format($area,1,',','.') . "</td>";
            echo "<td class='text-end'>" . number_format($atr,1,',','.') . "</td>";
            echo "<td class='text-end'>" . ($isEF ? number_format($areaEF,1,',','.') : '—') . "</td>";
        } else {
            echo "<td class='text-end'>$nd</td><td class='text-end'>$nd</td><td class='text-end'>$nd</td>";
        }
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='4' class='text-center text-muted py-3'>" . t('NO_RECORDS') . "</td></tr>";
}
?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
