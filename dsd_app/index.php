<?php
session_start();
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Início';
$activePage = 'home';

$db = getDB();
$al = getAnoLetivoAtivo();

$nDocStmt = $db->prepare("SELECT COUNT(*) FROM infodeqb_dsd_docente d JOIN infodeqb_dsd_docente_ano da ON da.docente_id=d.id AND da.ano_letivo_id=? WHERE da.ativo=1");
$nDocStmt->execute([$al['id']]);
$nDocentes = $nDocStmt->fetchColumn();
$nUCs      = $db->query("SELECT COUNT(*) FROM infodeqb_dsd_uc WHERE ativo=1")->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM infodeqb_dsd_uc_ocorrencia WHERE ano_letivo_id=?");
$stmt->execute([$al['id']]);
$nOcs = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM infodeqb_dsd_distribuicao WHERE ano_letivo_id=?");
$stmt->execute([$al['id']]);
$nDist = $stmt->fetchColumn();

// Horas por carreira (em h/sem)
$stmt = $db->prepare("
    SELECT car.designacao,
           SUM(
               (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
                + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT)
               * d.semanas / 13
           ) AS h_total,
           SUM(
               (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L + d.turmas_Sem*d.horas_Sem)
               * d.semanas / 13 * o.f_slef
           ) AS h_slef_total,
           SUM(
               (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
                + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT)
               * d.semanas / 13 + d.h_tese
           ) AS h_equiv_total
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id = o.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
    WHERE d.ano_letivo_id = ? AND d.dsd_por_docente = 1
    GROUP BY car.id, car.designacao
    ORDER BY car.ordem
");
$stmt->execute([$al['id'], $al['id']]);
$porCarreira = $stmt->fetchAll();

// Top docentes (em h/sem)
$stmt = $db->prepare("
    SELECT doc.nome, car.designacao AS carreira,
           SUM((d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L + d.turmas_Sem*d.horas_Sem)
               * d.semanas / 13 * o.f_slef) AS h_total,
           da.h_slef AS h_slef
    FROM infodeqb_dsd_distribuicao d
    JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id = o.id
    JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
    LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id=doc.id AND da.ano_letivo_id=?
    JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
    WHERE d.ano_letivo_id = ? AND d.dsd_por_docente = 1
      AND car.designacao NOT LIKE '%Outras%'
    GROUP BY doc.id, doc.nome, car.designacao, da.h_slef
    ORDER BY h_total DESC
    LIMIT 10
");
$stmt->execute([$al['id'], $al['id']]);
$topDocentes = $stmt->fetchAll();

// Cobertura global — usa a mesma lógica do relatório horas-em-falta
// por UC: necessárias = n_turmas × h/sem; atribuídas = turmas × h × semanas/13
$stmt = $db->prepare("
    SELECT
        COALESCE(SUM(
            o.n_turmas_T*o.horas_T + o.n_turmas_TP*o.horas_TP + o.n_turmas_L*o.horas_L
            + o.n_turmas_Sem*o.horas_Sem + o.n_turmas_OT*o.horas_OT
        ), 0) AS h_necess,
        COALESCE(SUM(
            COALESCE((
                SELECT SUM((d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP
                    + d.turmas_L*d.horas_L + d.turmas_Sem*d.horas_Sem
                    + d.turmas_OT*d.horas_OT) * d.semanas / 13)
                FROM infodeqb_dsd_distribuicao d WHERE d.ocorrencia_id = o.id
            ), 0)
        ), 0) AS h_atrib
    FROM infodeqb_dsd_uc_ocorrencia o
    WHERE o.ano_letivo_id = ?
");
$stmt->execute([$al['id']]);
$cob = $stmt->fetch();
$hNecess = (float)($cob['h_necess'] ?? 0);
$hAtrib  = (float)($cob['h_atrib'] ?? 0);
$hFalta  = max(0, $hNecess - $hAtrib);
$pctCob  = $hNecess > 0 ? min(100, ($hAtrib / $hNecess) * 100) : 0;


// Ocorrências com mais horas em falta (top 5) — em h/sem
$stmt = $db->prepare("
    SELECT uc.designacao, pe.sigla AS plano, sub.id, sub.need_total, sub.done_total
    FROM (
        SELECT o.id,
               (o.n_turmas_T*o.horas_T + o.n_turmas_TP*o.horas_TP + o.n_turmas_L*o.horas_L
                + o.n_turmas_Sem*o.horas_Sem + o.n_turmas_OT*o.horas_OT) AS need_total,
               COALESCE((SELECT SUM(
                   (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
                    + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas / 13)
                 FROM infodeqb_dsd_distribuicao d WHERE d.ocorrencia_id = o.id), 0) AS done_total,
               o.uc_id, o.plano_id
        FROM infodeqb_dsd_uc_ocorrencia o
        WHERE o.ano_letivo_id = ?
    ) sub
    JOIN infodeqb_dsd_uc uc ON sub.uc_id = uc.id
    LEFT JOIN infodeqb_dsd_plano_estudo pe ON COALESCE(sub.plano_id, uc.plano_id) = pe.id
    WHERE sub.need_total - sub.done_total > 0.01
    ORDER BY (sub.need_total - sub.done_total) DESC
    LIMIT 5
");
$stmt->execute([$al['id']]);
$ocsFalta = $stmt->fetchAll();

// Contar UCs com horas em falta — filtrar em PHP (compatível com MariaDB)
$stmtFalta = $db->prepare("
    SELECT o.id,
        (o.n_turmas_T*o.horas_T + o.n_turmas_TP*o.horas_TP + o.n_turmas_L*o.horas_L
         + o.n_turmas_Sem*o.horas_Sem + o.n_turmas_OT*o.horas_OT) * o.semanas / 13
        - COALESCE((SELECT SUM((d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP
            + d.turmas_L*d.horas_L + d.turmas_Sem*d.horas_Sem
            + d.turmas_OT*d.horas_OT) * d.semanas / 13)
            FROM infodeqb_dsd_distribuicao d WHERE d.ocorrencia_id = o.id), 0) AS falta
    FROM infodeqb_dsd_uc_ocorrencia o WHERE o.ano_letivo_id = ?
");
$stmtFalta->execute([$al['id']]);
$nUcsFaltaTotal = 0;
foreach ($stmtFalta->fetchAll() as $_r) {
    if ((float)$_r['falta'] > 0.01) $nUcsFaltaTotal++;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-home me-1"></i>Painel Principal</div>
    <div class="page-sub">Ano letivo <?= esc($al['designacao']) ?></div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="pages/ocorrencias.php" class="btn btn-secondary"><i class="fas fa-calendar-alt me-1"></i>Ocorrências</a>
    <a href="pages/distribuicao.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nova Distribuição</a>
    <a href="reports/por-docente.php" class="btn btn-secondary"><i class="fas fa-chart-bar me-1"></i>Relatórios</a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
    <div><div class="stat-value"><?= $nDocentes ?></div><div class="stat-label">Docentes / Colaboradores</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-book"></i></div>
    <div><div class="stat-value"><?= $nUCs ?></div><div class="stat-label">UCs no Catálogo</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
    <div><div class="stat-value"><?= $nOcs ?></div><div class="stat-label">Ocorrências neste ano</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
    <div><div class="stat-value"><?= $nDist ?></div><div class="stat-label">Registos de Distribuição</div></div>
  </div>
</div>

<!-- Painel de cobertura (h/sem) -->
<div class="card" style="background:linear-gradient(135deg, var(--blue-light), #fff);border-left:4px solid var(--blue)">
  <div class="card-title"><i class="fas fa-chart-bar me-1"></i>Cobertura de Horas no Ano Letivo</div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;font-size:13px">
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:6px;padding:12px;text-align:center">
      <div style="color:var(--gray-600);font-size:11px;text-transform:uppercase;font-weight:600">Necessárias</div>
      <div style="font-size:22px;font-weight:700;color:var(--gray-900);margin:4px 0"><?= fmt($hNecess, 1) ?> h/sem</div>
      <div style="font-size:11px;color:var(--gray-500)">Soma das ocorrências (semestre de 13 semanas)</div>
    </div>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:6px;padding:12px;text-align:center">
      <div style="color:var(--gray-600);font-size:11px;text-transform:uppercase;font-weight:600">Atribuídas</div>
      <div style="font-size:22px;font-weight:700;color:var(--blue);margin:4px 0"><?= fmt($hAtrib, 1) ?> h/sem</div>
      <div class="progress-wrap" style="margin-top:6px">
        <div class="progress-bar <?= $pctCob >= 100 ? 'progress-ok' : ($pctCob >= 80 ? 'progress-ok' : 'progress-warn') ?>"
             style="width:<?= $pctCob ?>%"></div>
      </div>
      <div style="font-size:11px;color:var(--gray-500);margin-top:2px"><?= fmt($pctCob, 1) ?>% coberto</div>
    </div>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:6px;padding:12px;text-align:center">
      <div style="color:var(--gray-600);font-size:11px;text-transform:uppercase;font-weight:600">Em Falta</div>

      <div style="font-size:22px;font-weight:700;color:<?= $nUcsFaltaTotal > 0 ? 'var(--red)' : 'var(--green)' ?>;margin:4px 0">
        <?= $nUcsFaltaTotal > 0 ? $nUcsFaltaTotal . ' UC(s)' : '<i class="fas fa-check-circle me-1"></i>OK' ?>
      </div>
      <div style="font-size:11px;color:var(--gray-500)">
        <?= $nUcsFaltaTotal > 0 ? 'com horas por atribuir' : 'Tudo distribuído' ?>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<div class="card">
  <div class="card-title"><i class="fas fa-chart-bar me-1"></i>Horas por Carreira</div>
  <?php if (!$porCarreira): ?>
    <p style="color:var(--gray-400);font-size:13px">Sem dados de distribuição ainda.</p>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Carreira</th>
        <th style="text-align:right">H/s</th>
        <th style="text-align:right">H SLEf</th>
        <th style="text-align:right">H Equiv.</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $totHS = 0; $totSLEf = 0; $totEquiv = 0;
    foreach ($porCarreira as $r):
      $totHS    += (float)$r['h_total'];
      $totSLEf  += (float)$r['h_slef_total'];
      $totEquiv += (float)$r['h_equiv_total'];
    ?>
      <tr>
        <td><?= esc($r['designacao']) ?></td>
        <td class="num"><?= fmt((float)$r['h_total']) ?></td>
        <td class="num" style="color:var(--blue)"><?= fmt((float)$r['h_slef_total']) ?></td>
        <td class="num" style="color:var(--blue-dark);font-weight:600"><?= fmt((float)$r['h_equiv_total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:700;border-top:2px solid var(--gray-300)">
        <td>Total</td>
        <td class="num"><?= fmt($totHS) ?></td>
        <td class="num" style="color:var(--blue)"><?= fmt($totSLEf) ?></td>
        <td class="num" style="color:var(--blue-dark)"><?= fmt($totEquiv) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title"><i class="fas fa-trophy me-1"></i>Top 10 Docentes por Serviço</div>
  <?php if (!$topDocentes): ?>
    <p style="color:var(--gray-400);font-size:13px">Sem dados de distribuição ainda.</p>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr><th>Docente</th><th style="text-align:right">H SLEf</th><th style="text-align:right">Ref.</th></tr>
    </thead>
    <tbody>
    <?php foreach ($topDocentes as $r):
      $pct = $r['h_slef'] > 0 ? min(100, ($r['h_total'] / $r['h_slef']) * 100) : 0;
      $cls = $pct >= 100 ? 'progress-over' : ($pct >= 80 ? 'progress-ok' : 'progress-warn');
    ?>
      <tr>
        <td>
          <div style="font-size:12px"><?= esc($r['nome']) ?></div>
          <div class="progress-wrap" style="margin-top:4px">
            <div class="progress-bar <?= $cls ?>" style="width:<?= $pct ?>%"></div>
          </div>
        </td>
        <td class="num"><?= fmt((float)$r['h_total']) ?></td>
        <td class="num" style="color:var(--gray-400)"><?= fmt((float)$r['h_slef']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

</div>

<?php if ($ocsFalta): ?>
<div class="card" style="border-left:4px solid var(--red)">
  <div class="card-title" style="color:var(--red)"><i class="fas fa-exclamation-triangle me-1"></i>Ocorrências com mais horas em falta</div>
  <table class="data-table">
    <thead>
      <tr>
        <th>Plano</th><th>UC</th>
        <th style="text-align:right">Necessárias (h/sem)</th>
        <th style="text-align:right">Atribuídas (h/sem)</th>
        <th style="text-align:right">Falta (h/sem)</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($ocsFalta as $o):
      $falta = (float)$o['need_total'] - (float)$o['done_total'];
    ?>
      <tr>
        <td><span class="badge badge-blue"><?= esc($o['plano'] ?? '–') ?></span></td>
        <td><strong><?= esc($o['designacao']) ?></strong></td>
        <td class="num"><?= fmt((float)$o['need_total'], 1) ?></td>
        <td class="num"><?= fmt((float)$o['done_total'], 1) ?></td>
        <td class="num" style="color:var(--red);font-weight:700"><?= fmt($falta, 1) ?></td>
        <td style="text-align:right">
          <a href="pages/distribuicao.php?ocorrencia=<?= $o['id'] ?>" class="btn btn-primary btn-xs"><i class="fas fa-plus me-1"></i>Atribuir</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="margin-top:10px;text-align:right">
    <a href="reports/horas-em-falta.php" class="btn btn-secondary btn-sm">Ver relatório completo →</a>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title"><i class="fas fa-bolt me-1"></i>Acesso Rápido</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="pages/docentes.php" class="btn btn-secondary"><i class="fas fa-chalkboard-teacher me-1"></i>Docentes</a>
    <a href="pages/ucs.php" class="btn btn-secondary"><i class="fas fa-book me-1"></i>Catálogo UCs</a>
    <a href="pages/ocorrencias.php" class="btn btn-secondary"><i class="fas fa-calendar-alt me-1"></i>Ocorrências</a>
    <a href="pages/distribuicao.php" class="btn btn-secondary"><i class="fas fa-clipboard-list me-1"></i>Distribuição</a>
    <a href="pages/planos.php" class="btn btn-secondary"><i class="fas fa-graduation-cap me-1"></i>Planos</a>
    <a href="pages/areas.php" class="btn btn-secondary"><i class="fas fa-flask me-1"></i>Áreas</a>
    <a href="pages/import-csv.php" class="btn btn-secondary"><i class="fas fa-file-upload me-1"></i>Importar CSV</a>
    <a href="reports/por-docente.php" class="btn btn-secondary"><i class="fas fa-file-alt me-1"></i>Rel. Por Docente</a>
    <a href="reports/horas-em-falta.php" class="btn btn-secondary"><i class="fas fa-exclamation-triangle me-1"></i>Horas em Falta</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>