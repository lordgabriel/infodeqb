<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

// ── Identificação do utilizador ───────────────────────────────────
$userCode  = $_SESSION['Code']  ?? '';   // up356946@up.pt
$userEmail = $_SESSION['user']  ?? '';   // eppn (produção: @fe.up.pt)
$userIdNum = (int)preg_replace('/\D/', '', $userCode);

$_meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
           'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$_dias  = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira',
           'Quinta-feira','Sexta-feira','Sábado'];
$dataHoje  = $_dias[date('w')] . ', ' . date('j') . ' de ' . $_meses[date('n')-1] . ' de ' . date('Y');
$firstName = explode(' ', trim($_SESSION['CommonName'] ?? $_SESSION['DisplayName'] ?? ''))[0];

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ═══════════════════════════════════════════════════════════════════
// DADOS ADMIN GLOBAL
// ═══════════════════════════════════════════════════════════════════
$hrNovo = $hrPendente = $hrAtivo = $hrNExpirar = 0;
$dsdAno = null;
$dsdDocentes = $dsdUcs = $dsdUcsSemServ = 0;
$adminExamsPorArquivar = 0;

if ($isAdmin) {
    $sth = $pdo->prepare('SELECT r.autoid FROM infodeqb_rds_colaborador c JOIN infodeqb_rds_registo r ON c.codigo=r.codigo WHERE c.deleted!=1 AND r.deleted!=1 AND r.status=?');
    $sth->execute(['Novo']);     $hrNovo     = $sth->rowCount();
    $sth->execute(['Pendente']); $hrPendente = $sth->rowCount();
    $sth->execute(['Ativo']);    $hrAtivo    = $sth->rowCount();

    $sthExp = $pdo->prepare('SELECT r.autoid FROM infodeqb_rds_colaborador c JOIN infodeqb_rds_registo r ON c.codigo=r.codigo WHERE c.deleted!=1 AND r.deleted!=1 AND r.status="Ativo" AND r.datafim<=?');
    $sthExp->execute([date('Y/m/d', strtotime('+30 days'))]);
    $hrNExpirar = $sthExp->rowCount();

    // Exames por arquivar: tickets onde NENHUMA linha tem nº caixa
    // (alinha com lógica da página: arquivado = pelo menos 1 linha com caixa)
    $adminExamsPorArquivar = (int)$pdo->query(
        "SELECT COUNT(*) FROM (
            SELECT request_id FROM infodeqb_exam_archive WHERE status=1
            GROUP BY request_id
            HAVING MIN(CASE WHEN num_caixa IS NOT NULL AND num_caixa != '' THEN 1 ELSE 0 END) = 0
         ) t"
    )->fetchColumn();

    try {
        // DSD agora está na mesma BD — usa $pdo directamente
        $dsdAno = $pdo->query("SELECT * FROM infodeqb_dsd_ano_letivo WHERE ativo=1 LIMIT 1")->fetch();
        if ($dsdAno) {
            $s=$pdo->prepare("SELECT COUNT(*) FROM infodeqb_dsd_docente d JOIN infodeqb_dsd_docente_ano da ON da.docente_id=d.id AND da.ano_letivo_id=? WHERE da.ativo=1"); $s->execute([$dsdAno['id']]); $dsdDocentes=(int)$s->fetchColumn();
            $s=$pdo->prepare("SELECT COUNT(*) FROM infodeqb_dsd_uc_ocorrencia WHERE ano_letivo_id=?"); $s->execute([$dsdAno['id']]); $dsdUcs=(int)$s->fetchColumn();
            $s=$pdo->prepare("SELECT COUNT(*) FROM infodeqb_dsd_uc_ocorrencia o WHERE o.ano_letivo_id=? AND NOT EXISTS(SELECT 1 FROM infodeqb_dsd_distribuicao d WHERE d.ocorrencia_id=o.id)"); $s->execute([$dsdAno['id']]); $dsdUcsSemServ=(int)$s->fetchColumn();
        }
    } catch (Exception $e) { $dsdAno = null; }
}

// ═══════════════════════════════════════════════════════════════════
// DETECÇÃO DE ADMIN DE SECÇÃO
// ═══════════════════════════════════════════════════════════════════
// Listas centralizadas definidas em inc/admins.php
$isHrAdmin     = $isAdmin || in_array($userCode, $_iqAdminsHr);
$isWaterAdmin  = $isAdmin || in_array($userCode, $_iqAdminsWater);
$isExamAdmin   = $isAdmin || in_array($userCode, $_iqAdminsExam);
$isMobileAdmin = $isAdmin || in_array($userCode, $_iqAdminsMobile);

// Labs de equipamentos que gere
require_once ROOT_DIR . '/infodeqb/equipments/inc/permissions.php';
$labsEquip = !$isAdmin ? getLabsDoUtilizador($pdo, $userIdNum, false) : [];
$isEquipAdmin = $isAdmin || !empty($labsEquip);

$isSectionAdmin = !$isAdmin && ($isHrAdmin || $isWaterAdmin || $isExamAdmin || $isMobileAdmin || $isEquipAdmin);

// ── Flags de visibilidade de módulos ─────────────────────────────
$canSeeMobile = $isAdmin || $isMobileAdmin;

// ── Stats de secção (só se section admin, não global) ─────────────
$secHrNovo = $secHrPendente = $secHrNExpirar = 0;
$secExamsPorArquivar = 0;
$secMobileAno = null; $secMobileN = 0;
$secWaterLastDate = null;
$secWaterMes = 0;
$secEquipTotal = 0;

if ($isSectionAdmin) {
    if ($isHrAdmin) {
        $s=$pdo->prepare('SELECT r.autoid FROM infodeqb_rds_colaborador c JOIN infodeqb_rds_registo r ON c.codigo=r.codigo WHERE c.deleted!=1 AND r.deleted!=1 AND r.status=?');
        $s->execute(['Novo']);     $secHrNovo     = $s->rowCount();
        $s->execute(['Pendente']); $secHrPendente = $s->rowCount();
        $sExp=$pdo->prepare('SELECT r.autoid FROM infodeqb_rds_colaborador c JOIN infodeqb_rds_registo r ON c.codigo=r.codigo WHERE c.deleted!=1 AND r.deleted!=1 AND r.status="Ativo" AND r.datafim<=?');
        $sExp->execute([date('Y/m/d', strtotime('+30 days'))]); $secHrNExpirar=$sExp->rowCount();
    }
    if ($isExamAdmin) {
        $s=$pdo->query("SELECT COUNT(*) FROM (SELECT request_id FROM infodeqb_exam_archive WHERE status=1 GROUP BY request_id HAVING MAX(CASE WHEN num_caixa IS NOT NULL AND num_caixa!='' THEN 1 ELSE 0 END)=0) t"); $secExamsPorArquivar=(int)$s->fetchColumn();
    }
    if ($isMobileAdmin) {
        $s=$pdo->query('SELECT anoletivo, COUNT(*) AS n FROM infodeqb_registo_mobilidade GROUP BY anoletivo ORDER BY anoletivo DESC LIMIT 1'); $r=$s->fetch(PDO::FETCH_ASSOC); if($r){$secMobileAno=$r['anoletivo'];$secMobileN=$r['n'];}
    }
    if ($isWaterAdmin) {
        $s=$pdo->query('SELECT MAX(DATE_FORMAT(dia,"%d/%m/%Y")) FROM infodeqb_waterqc_ph_cond'); $secWaterLastDate=$s->fetchColumn();
        $s=$pdo->query('SELECT COALESCE(SUM(quantity),0) FROM infodeqb_water_ultrapure_record WHERE YEAR(data)='.date('Y').' AND MONTH(data)='.date('m')); $secWaterMes=round((float)$s->fetchColumn(),1);
    }
    if ($isEquipAdmin) {
        $ph = implode(',', array_fill(0, count($labsEquip), '?'));
        $s=$pdo->prepare("SELECT COUNT(*) FROM infodeqb_equipmentdeq WHERE Laboratorio IN ($ph)"); $s->execute($labsEquip); $secEquipTotal=(int)$s->fetchColumn();
    }
}

// ═══════════════════════════════════════════════════════════════════
// DADOS DO UTILIZADOR NORMAL
// ═══════════════════════════════════════════════════════════════════
$myHrRecord      = null;
$myServdoc       = null;   // null = não é docente
$myServdocSubm   = false;
$myAreas         = null;   // null = não é investigador
$myAreasSubm     = false;
$myAdiFeupId     = null;   // null = não tem ficha ADI

// Investigador / Áreas Disciplinares — verificar para TODOS os utilizadores
// (admins vêem sempre; os outros só se estiverem na tabela)
if (!$isAdmin) {
    $s=$pdo->prepare('SELECT id FROM infodeqb_docentes_investigadores_perm WHERE codigo=?');
    $s->execute([$userIdNum]);
    if ($s->fetch()) {
        $myAreas = true;
        $s2=$pdo->prepare('SELECT COUNT(*) FROM infodeqb_respostas WHERE codigo_inquirido=?');
        $s2->execute([$userIdNum]); $myAreasSubm=(int)$s2->fetchColumn()>0;
    }
}
$canSeeAreas = $isAdmin || ($myAreas === true);

if (!$isAdmin && !$isSectionAdmin) {
    // Registo HR
    $s=$pdo->prepare('SELECT r.status, r.datafim FROM infodeqb_rds_registo r WHERE r.codigo=? AND r.deleted=0 ORDER BY CASE r.status WHEN "Ativo" THEN 1 WHEN "Pendente" THEN 2 WHEN "Novo" THEN 3 ELSE 4 END, r.datafim DESC LIMIT 1');
    $s->execute([$userIdNum]); $myHrRecord=$s->fetch(PDO::FETCH_ASSOC);

    // Docente / Serviço Docente
    $s=$pdo->prepare('SELECT Codigo FROM infodeqb_inv_deqb WHERE email=?');
    $s->execute([$userEmail]); $doc=$s->fetch(PDO::FETCH_ASSOC);
    if ($doc) {
        $myServdoc = true;
        $s2=$pdo->prepare('SELECT COUNT(*) FROM infodeqb_ucs_deqb_pref WHERE id_docente=?');
        $s2->execute([$doc['Codigo']]); $myServdocSubm=(int)$s2->fetchColumn()>0;
    }

    // Espaços de Investigação (ADI)
    $s=$pdo->prepare('SELECT feup_id FROM infodeqb_espacos_elementos_deq WHERE feup_id=?');
    $s->execute([$userIdNum]); if($r=$s->fetch(PDO::FETCH_ASSOC)) $myAdiFeupId=$r['feup_id'];
}

Database::disconnect();

$pageTitle = 'Início';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<!-- ── Saudação ─────────────────────────────────────────────────── -->
<div class="iq-dash-greeting">
  <div class="iq-dash-greeting-row">
    <div>
      <h1>Olá, <?= htmlspecialchars($firstName ?: 'bem-vindo') ?>!</h1>
      <div class="iq-dash-greeting-sub">
        <?= $dataHoje ?> &nbsp;·&nbsp; Departamento de Engenharia Química e Biológica
      </div>
    </div>
    <?php if ($isAdmin): ?>
    <span class="iq-admin-badge"><i class="fas fa-shield-alt fa-xs"></i> Administrador</span>
    <?php elseif ($isSectionAdmin): ?>
    <span class="iq-admin-badge" style="background:#f3e8fd;color:#6f42c1;border-color:#d8b4fe">
      <i class="fas fa-user-cog fa-xs"></i> Gestor
    </span>
    <?php endif; ?>
  </div>
</div>

<?php if ($isAdmin): ?>
<?php /* ═══════════ ADMIN GLOBAL ════════════════════════════════ */ ?>

<div class="iq-dash-label">Pessoal</div>
<div class="iq-stat-grid">
  <?php
  $hrBase = HTTP_DIR . '/infodeqb/hr/admin/index.php';
  ?>
  <a class="iq-stat <?= $hrNovo>0?'iq-stat-yellow':'iq-stat-green' ?>" href="<?= $hrBase ?>?tab=pills-new" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-file-alt"></i></div>
    <div><div class="iq-stat-value"><?= $hrNovo ?></div><div class="iq-stat-label"><?= t('DASH_NEW_RECORDS') ?></div></div>
  </a>
  <a class="iq-stat <?= $hrPendente>0?'iq-stat-yellow':'iq-stat-green' ?>" href="<?= $hrBase ?>?tab=pills-pendent" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-clock"></i></div>
    <div><div class="iq-stat-value"><?= $hrPendente ?></div><div class="iq-stat-label"><?= t('DASH_PENDING') ?></div></div>
  </a>
  <a class="iq-stat <?= $hrNExpirar>0?'iq-stat-red':'iq-stat-green' ?>" href="<?= $hrBase ?>?tab=pills-expire" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div><div class="iq-stat-value"><?= $hrNExpirar ?></div><div class="iq-stat-label"><?= t('DASH_EXPIRING') ?></div></div>
  </a>
  <a class="iq-stat iq-stat-blue" href="<?= $hrBase ?>?tab=pills-active" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-users"></i></div>
    <div><div class="iq-stat-value"><?= $hrAtivo ?></div><div class="iq-stat-label"><?= t('DASH_ACTIVE') ?></div></div>
  </a>
  <a class="iq-stat <?= $adminExamsPorArquivar>0?'iq-stat-yellow':'iq-stat-green' ?>"
     href="<?= HTTP_DIR ?>/infodeqb/exams/" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-archive"></i></div>
    <div><div class="iq-stat-value"><?= $adminExamsPorArquivar ?></div><div class="iq-stat-label"><?= t('EXAM_TO_ARCHIVE') ?></div></div>
  </a>
</div>

<?php if ($dsdAno): ?>
<div class="iq-dash-label">Serviço Docente &mdash; <?= htmlspecialchars($dsdAno['designacao']) ?></div>
<div class="iq-stat-grid">
  <a class="iq-stat iq-stat-blue" href="<?= HTTP_DIR ?>/infodeqb/dsd_app/" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
    <div><div class="iq-stat-value"><?= $dsdDocentes ?></div><div class="iq-stat-label">Docentes ativos</div></div>
  </a>
  <a class="iq-stat iq-stat-green" href="<?= HTTP_DIR ?>/infodeqb/dsd_app/pages/ocorrencias.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-book-open"></i></div>
    <div><div class="iq-stat-value"><?= $dsdUcs ?></div><div class="iq-stat-label">Ocorrências de UCs</div></div>
  </a>
  <a class="iq-stat <?= $dsdUcsSemServ>0?'iq-stat-red':'iq-stat-green' ?>" href="<?= HTTP_DIR ?>/infodeqb/dsd_app/pages/distribuicao.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-exclamation-circle"></i></div>
    <div><div class="iq-stat-value"><?= $dsdUcsSemServ ?></div><div class="iq-stat-label">UCs sem serviço</div></div>
  </a>
</div>
<?php endif; ?>

<div class="iq-dash-label">Módulos</div>
<div>

<?php elseif ($isSectionAdmin): ?>
<?php /* ═══════════ ADMIN DE SECÇÃO ═════════════════════════════ */ ?>

<?php if ($isHrAdmin): ?>
<div class="iq-dash-label">Pessoal</div>
<div class="iq-stat-grid">
  <a class="iq-stat <?= $secHrNovo>0?'iq-stat-yellow':'iq-stat-green' ?>" href="<?= HTTP_DIR ?>/infodeqb/hr/admin/index.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-file-alt"></i></div>
    <div><div class="iq-stat-value"><?= $secHrNovo ?></div><div class="iq-stat-label"><?= t('DASH_NEW_RECORDS') ?></div></div>
  </a>
  <a class="iq-stat <?= $secHrPendente>0?'iq-stat-yellow':'iq-stat-green' ?>" href="<?= HTTP_DIR ?>/infodeqb/hr/admin/index.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-clock"></i></div>
    <div><div class="iq-stat-value"><?= $secHrPendente ?></div><div class="iq-stat-label"><?= t('DASH_PENDING') ?></div></div>
  </a>
  <a class="iq-stat <?= $secHrNExpirar>0?'iq-stat-red':'iq-stat-green' ?>" href="<?= HTTP_DIR ?>/infodeqb/hr/admin/index.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div><div class="iq-stat-value"><?= $secHrNExpirar ?></div><div class="iq-stat-label"><?= t('DASH_EXPIRING') ?></div></div>
  </a>
</div>
<?php endif; ?>

<?php if ($isEquipAdmin && !empty($labsEquip)): ?>
<div class="iq-dash-label">Equipamentos</div>
<div class="iq-stat-grid">
  <a class="iq-stat iq-stat-blue" href="<?= HTTP_DIR ?>/infodeqb/equipments/" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-flask"></i></div>
    <div><div class="iq-stat-value"><?= $secEquipTotal ?></div><div class="iq-stat-label">Equipamentos nos meus labs</div></div>
  </a>
  <a class="iq-stat iq-stat-green" href="<?= HTTP_DIR ?>/infodeqb/equipments/edit_equipment.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-plus-circle"></i></div>
    <div><div class="iq-stat-value" style="font-size:1.2rem"><i class="fas fa-plus fa-xs"></i></div><div class="iq-stat-label"><?= t('EQUIP_ADD') ?></div></div>
  </a>
</div>
<?php endif; ?>

<?php if ($isExamAdmin): ?>
<div class="iq-dash-label">Arquivo de Exames</div>
<div class="iq-stat-grid">
  <a class="iq-stat <?= $secExamsPorArquivar>0?'iq-stat-yellow':'iq-stat-green' ?>" href="<?= HTTP_DIR ?>/infodeqb/exams/" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-archive"></i></div>
    <div><div class="iq-stat-value"><?= $secExamsPorArquivar ?></div><div class="iq-stat-label">Autos por arquivar</div></div>
  </a>
</div>
<?php endif; ?>

<?php if ($isWaterAdmin): ?>
<div class="iq-dash-label">Água</div>
<div class="iq-stat-grid">
  <a class="iq-stat iq-stat-blue" href="<?= HTTP_DIR ?>/infodeqb/water/waterqc.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-tint"></i></div>
    <div><div class="iq-stat-value" style="font-size:.95rem"><?= htmlspecialchars($secWaterLastDate ?: '—') ?></div><div class="iq-stat-label">Última leitura</div></div>
  </a>
  <a class="iq-stat iq-stat-green" href="<?= HTTP_DIR ?>/infodeqb/water/index.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-water"></i></div>
    <div><div class="iq-stat-value"><?= $secWaterMes ?> L</div><div class="iq-stat-label">Consumo este mês</div></div>
  </a>
</div>
<?php endif; ?>

<?php if ($isMobileAdmin && $secMobileAno): ?>
<div class="iq-dash-label">Mobilidade</div>
<div class="iq-stat-grid">
  <a class="iq-stat iq-stat-blue" href="<?= HTTP_DIR ?>/infodeqb/mobile/in/" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-plane-arrival"></i></div>
    <div><div class="iq-stat-value"><?= $secMobileN ?></div><div class="iq-stat-label"><?= htmlspecialchars($secMobileAno) ?></div></div>
  </a>
</div>
<?php endif; ?>

<div class="iq-dash-label">Todos os módulos</div>
<div style="margin-top:.5rem">

<?php else: ?>
<?php /* ═══════════ UTILIZADOR NORMAL ════════════════════════════ */ ?>

<?php /* ── O meu registo HR ──────────────────────────────────────── */ ?>
<div class="iq-dash-label">O meu registo de colaborador</div>
<?php
$hrStatusBadge = ['Ativo'=>['iq-stat-green','check-circle'], 'Pendente'=>['iq-stat-yellow','clock'], 'Novo'=>['iq-stat-blue','file-alt'], 'Inativo'=>['iq-stat-red','user-slash']];
$hrStat = $myHrRecord ? ($hrStatusBadge[$myHrRecord['status']] ?? ['iq-stat-slate','question-circle']) : null;
?>
<div class="iq-stat-grid" style="margin-bottom:1.2rem">
  <?php if ($myHrRecord): ?>
  <a class="iq-stat <?= $hrStat[0] ?>" href="<?= HTTP_DIR ?>/infodeqb/hr/meu-registo.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-<?= $hrStat[1] ?>"></i></div>
    <div>
      <div class="iq-stat-value" style="font-size:1rem"><?= htmlspecialchars($myHrRecord['status']) ?></div>
      <?php if ($myHrRecord['status']==='Ativo' && $myHrRecord['datafim']): ?>
      <div class="iq-stat-label">Válido até <?= htmlspecialchars($myHrRecord['datafim']) ?></div>
      <?php else: ?>
      <div class="iq-stat-label">Ver / gerir registo</div>
      <?php endif; ?>
    </div>
  </a>
  <?php else: ?>
  <a class="iq-stat iq-stat-slate" href="<?= HTTP_DIR ?>/infodeqb/hr/index.php" style="text-decoration:none">
    <div class="iq-stat-icon"><i class="fas fa-plus-circle"></i></div>
    <div><div class="iq-stat-value" style="font-size:1rem">Sem registo</div><div class="iq-stat-label">Criar registo de colaborador</div></div>
  </a>
  <?php endif; ?>
</div>

<?php /* ── Formulários que me dizem respeito ──────────────────────── */ ?>
<?php if ($myServdoc !== null || $myAreas !== null || $myAdiFeupId !== null): ?>
<div class="iq-dash-label">Os meus formulários</div>
<div class="iq-modules" style="margin-bottom:1.2rem">

  <?php if ($myServdoc !== null): ?>
  <a class="iq-module <?= $myServdocSubm ? 'iq-mod-emerald' : 'iq-mod-amber' ?>" href="<?= HTTP_DIR ?>/infodeqb/servdoc/">
    <span class="iq-module-tag">MOD · SRV</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="iq-module-body">
        <div class="iq-module-title">Preferência Serviço Docente</div>
        <div class="iq-module-sub">
          <?php if ($myServdocSubm): ?>
            <i class="fas fa-check-circle me-1" style="color:#0e9f6e"></i>Preferências submetidas
          <?php else: ?>
            <i class="fas fa-exclamation-circle me-1" style="color:#d97706"></i>Por preencher
          <?php endif; ?>
        </div>
      </div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php endif; ?>

  <?php if ($myAreas !== null): ?>
  <a class="iq-module <?= $myAreasSubm ? 'iq-mod-emerald' : 'iq-mod-amber' ?>" href="<?= HTTP_DIR ?>/infodeqb/areas/">
    <span class="iq-module-tag">MOD · ARE</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-sitemap"></i></div>
      <div class="iq-module-body">
        <div class="iq-module-title">Áreas Disciplinares</div>
        <div class="iq-module-sub">
          <?php if ($myAreasSubm): ?>
            <i class="fas fa-check-circle me-1" style="color:#0e9f6e"></i>Resposta submetida
          <?php else: ?>
            <i class="fas fa-exclamation-circle me-1" style="color:#d97706"></i>Por preencher
          <?php endif; ?>
        </div>
      </div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php endif; ?>

  <?php if ($myAdiFeupId !== null): ?>
  <a class="iq-module iq-mod-indigo" href="<?= HTTP_DIR ?>/infodeqb/adi/ficha.php?feup_id=<?= urlencode($myAdiFeupId) ?>">
    <span class="iq-module-tag">MOD · ADI</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-building"></i></div>
      <div class="iq-module-body">
        <div class="iq-module-title">Espaços de Investigação</div>
        <div class="iq-module-sub">Ver a minha ficha ADI</div>
      </div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php endif; ?>

</div>
<?php endif; ?>

<div class="iq-dash-label">Acesso rápido</div>
<div style="margin-top:.5rem">

<?php endif; /* fim dos 3 casos */ ?>

<?php /* ════════ GRELHA DE MÓDULOS (todos os casos) ══════════════ */ ?>
<div class="iq-modules">

  <?php if (!$isSectionAdmin && !$isAdmin): /* só para utilizador normal */ ?>
  <a class="iq-module iq-mod-indigo" href="<?= HTTP_DIR ?>/infodeqb/hr/meu-registo.php">
    <span class="iq-module-tag">MOD · HR</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-id-card"></i></div>
      <div class="iq-module-body"><div class="iq-module-title">O meu registo</div><div class="iq-module-sub">Colaboradores DEQB</div></div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php else: ?>
  <a class="iq-module iq-mod-indigo" href="<?= HTTP_DIR ?>/infodeqb/hr<?= ($isAdmin||$isHrAdmin)?'/admin/index.php':'' ?>">
    <span class="iq-module-tag">MOD · HR</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-users"></i></div>
      <div class="iq-module-body">
        <div class="iq-module-title">Colaboradores</div>
        <div class="iq-module-sub"><?= $isAdmin ? $hrAtivo . ' ativos'.($hrNExpirar>0?' &nbsp;·&nbsp; <span style="color:#991b1b;font-weight:600">'.$hrNExpirar.' a expirar</span>':'') : 'Registo e gestão de pessoal' ?></div>
      </div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php endif; ?>

  <a class="iq-module iq-mod-sky" href="<?= HTTP_DIR ?>/infodeqb/equipments/">
    <span class="iq-module-tag">MOD · EQP</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-flask"></i></div>
      <div class="iq-module-body"><div class="iq-module-title">Equipamentos</div><div class="iq-module-sub">Catálogo de equipamentos</div></div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>

  <a class="iq-module iq-mod-emerald" href="<?= HTTP_DIR ?>/infodeqb/booking" target="_blank">
    <span class="iq-module-tag">MOD · RES</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-calendar-alt"></i></div>
      <div class="iq-module-body"><div class="iq-module-title">Reserva de recursos</div><div class="iq-module-sub">Salas e equipamentos partilhados <i class="fa fa-external-link fa-xs"></i></div></div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>

  <a class="iq-module iq-mod-amber" href="<?= HTTP_DIR ?>/infodeqb/dsd_app/">
    <span class="iq-module-tag">MOD · DSD</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="iq-module-body">
        <div class="iq-module-title">Serviço Docente</div>
        <div class="iq-module-sub"><?= ($isAdmin && $dsdAno) ? $dsdUcs.' UCs &nbsp;·&nbsp; '.htmlspecialchars($dsdAno['designacao']) : 'Distribuição e relatórios' ?></div>
      </div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>

  <?php if ($isAdmin || in_array($userCode, $_iqAdminsHr)): ?>
  <a class="iq-module iq-mod-indigo" href="<?= HTTP_DIR ?>/infodeqb/servdoc/">
    <span class="iq-module-tag">MOD · SRV</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-list-ol"></i></div>
      <div class="iq-module-body">
        <div class="iq-module-title">Pref. Serviço Docente</div>
        <div class="iq-module-sub">Gestão de preferências</div>
      </div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php endif; ?>

  <?php if ($isAdmin || in_array($userCode, $_iqAdminsWater)): ?>
  <a class="iq-module iq-mod-sky" href="<?= HTTP_DIR ?>/infodeqb/water/index.php">
    <span class="iq-module-tag">MOD · H₂O</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-water"></i></div>
      <div class="iq-module-body">
        <div class="iq-module-title">Consumos de água</div>
        <div class="iq-module-sub">Água ultrapura — registos</div>
      </div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php endif; ?>

  <a class="iq-module iq-mod-violet" href="<?= HTTP_DIR ?>/infodeqb/exams">
    <span class="iq-module-tag">MOD · EXM</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-archive"></i></div>
      <div class="iq-module-body"><div class="iq-module-title">Arquivo de exames</div><div class="iq-module-sub">Autos de incorporação</div></div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>

  <?php if ($canSeeMobile): ?>
  <a class="iq-module iq-mod-sky" href="<?= HTTP_DIR ?>/infodeqb/mobile">
    <span class="iq-module-tag">MOD · MOB</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-globe"></i></div>
      <div class="iq-module-body"><div class="iq-module-title">Mobilidade</div><div class="iq-module-sub">Mobilidade EQ e contactos DIE</div></div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php endif; ?>

  <a class="iq-module iq-mod-slate" href="<?= HTTP_DIR ?>/infodeqb/water/waterqc.php">
    <span class="iq-module-tag">MOD · H₂O</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-tint"></i></div>
      <div class="iq-module-body"><div class="iq-module-title">Qualidade da água</div><div class="iq-module-sub">Monitorização e registos</div></div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>

  <a class="iq-module iq-mod-emerald" href="<?= HTTP_DIR ?>/infodeqb/reagentes/">
    <span class="iq-module-tag">MOD · RGT</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-vial"></i></div>
      <div class="iq-module-body"><div class="iq-module-title">Reagentes</div><div class="iq-module-sub">Inventário de reagentes</div></div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>

  <?php if ($canSeeAreas): ?>
  <a class="iq-module iq-mod-slate" href="<?= HTTP_DIR ?>/infodeqb/areas/">
    <span class="iq-module-tag">MOD · ARE</span>
    <div class="iq-module-row">
      <div class="iq-module-icon"><i class="fas fa-sitemap"></i></div>
      <div class="iq-module-body"><div class="iq-module-title">Áreas disciplinares</div><div class="iq-module-sub">Estrutura do departamento</div></div>
      <i class="fas fa-chevron-right iq-module-arrow"></i>
    </div>
  </a>
  <?php endif; ?>

</div><!-- /.iq-modules -->

<?php if ($isAdmin): ?>
</div><!-- /modules wrap -->

<div class="iq-dash-label"><?= t('DASH_ADMIN_SEC') ?></div>
<div class="iq-tool-card" style="margin-bottom:.75rem">
  <div class="iq-tool-icon"><i class="fas fa-user-cog"></i></div>
  <div class="iq-tool-body">
    <div class="iq-tool-title"><?= t('DASH_SECTION_ADMINS') ?></div>
    <div class="iq-tool-desc"><?= t('DASH_SECTION_ADMINS_DESC') ?></div>
  </div>
  <div class="iq-tool-actions">
    <a href="<?= HTTP_DIR ?>/infodeqb/admin-sections.php" class="iq-tool-btn iq-tool-btn-primary">
      <i class="fas fa-cog fa-xs"></i> <?= t('DASH_MANAGE') ?>
    </a>
  </div>
</div>

<div class="iq-dash-label"><?= t('DASH_TOOLS') ?></div>

<!-- Backup BD -->
<div class="iq-tool-card" style="margin-bottom:.6rem">
  <div class="iq-tool-icon"><i class="fas fa-database"></i></div>
  <div class="iq-tool-body">
    <div class="iq-tool-title">Base de dados</div>
    <div class="iq-tool-desc">Exportar estrutura e dados (.sql)</div>
  </div>
  <div class="iq-tool-actions">
    <a href="<?= HTTP_DIR ?>/infodeqb/backup.php?db=hr"  class="iq-tool-btn" title="feupptdeqb — HR, DIE, água, exames…">
      <i class="fas fa-download fa-xs"></i> HR
    </a>
    <a href="<?= HTTP_DIR ?>/infodeqb/backup.php?db=dsd" class="iq-tool-btn" title="feupptdeqb — Serviço Docente (DSD)">
      <i class="fas fa-download fa-xs"></i> DSD
    </a>
    <a href="<?= HTTP_DIR ?>/infodeqb/backup.php?db=all" class="iq-tool-btn iq-tool-btn-primary">
      <i class="fas fa-download fa-xs"></i> Ambas
    </a>
  </div>
</div>

<!-- Backup Ficheiros -->
<div class="iq-tool-card" style="margin-bottom:.6rem">
  <div class="iq-tool-icon"><i class="fas fa-file-archive"></i></div>
  <div class="iq-tool-body">
    <div class="iq-tool-title">Ficheiros / Páginas</div>
    <div class="iq-tool-desc">Exportar código e uploads (.zip)</div>
  </div>
  <div class="iq-tool-actions">
    <a href="<?= HTTP_DIR ?>/infodeqb/backup-files.php?mode=pages" class="iq-tool-btn"
       title="PHP, CSS, JS, imagens, uploads — sem vendor/ (~rápido)">
      <i class="fas fa-download fa-xs"></i> Páginas
    </a>
    <a href="<?= HTTP_DIR ?>/infodeqb/backup-files.php?mode=full" class="iq-tool-btn iq-tool-btn-primary"
       title="Tudo incluindo vendor/ (~completo, mais lento)">
      <i class="fas fa-download fa-xs"></i> Completo
    </a>
  </div>
</div>

<!-- Backup Sistema completo (BD + Páginas) -->
<div class="iq-tool-card">
  <div class="iq-tool-icon"><i class="fas fa-server"></i></div>
  <div class="iq-tool-body">
    <div class="iq-tool-title">Sistema completo</div>
    <div class="iq-tool-desc">BD (ambas) + Páginas — dois downloads em sequência</div>
  </div>
  <div class="iq-tool-actions">
    <button type="button" class="iq-tool-btn iq-tool-btn-primary" id="btnBackupAll">
      <i class="fas fa-download fa-xs"></i> Iniciar
    </button>
  </div>
</div>

<script>
document.getElementById('btnBackupAll').addEventListener('click', function () {
    var base = '<?= HTTP_DIR ?>/infodeqb/';
    // Inicia os dois downloads em sequência (com pequeno atraso para não bloquear)
    window.location.href = base + 'backup.php?db=all';
    setTimeout(function () {
        window.location.href = base + 'backup-files.php?mode=pages';
    }, 1500);
});
</script>

<?php else: ?>
</div><!-- /modules wrap -->
<?php endif; ?>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
