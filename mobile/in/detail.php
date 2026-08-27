<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsMobile))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
}

$id      = (int)($_GET['id'] ?? 0);
$dados   = [];
$checked = [];

if ($id) {
    $pdo = Database::connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sth_uc = $pdo->prepare(
        'SELECT * FROM infodeqb_unidades_curriculares
          WHERE ano LIKE :ano AND curso LIKE :curso AND regime LIKE :regime
          ORDER BY regime, uc'
    );
    $sth = $pdo->prepare(
        'SELECT nome, id, universidade, pais, programa, anoletivo, tipocontrato,
                duracao, inicio, fim, obs, ucs, dcoop
           FROM infodeqb_registo_mobilidade WHERE id = ?'
    );
    $sth->execute([$id]);
    $dados = $sth->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!empty($dados['ucs'])) {
        $sth1 = $pdo->prepare('SELECT codigo, uc FROM infodeqb_unidades_curriculares WHERE codigo = ?');
        foreach (explode(';', $dados['ucs']) as $codigo) {
            $codigo = trim($codigo);
            if ($codigo === '') continue;
            $sth1->execute([$codigo]);
            $row = $sth1->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $chave = $codigo . ' ' . $row['uc'];
                $checked[$chave] = true;
            }
        }
    }

    Database::disconnect();
}

// ── Flash ──────────────────────────────────────────────────────────
$flashMsg  = '';
$flashType = 'success';
if (isset($_SESSION['_mobile_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_mobile_flash'];
    unset($_SESSION['_mobile_flash']);
}

// ── POST ───────────────────────────────────────────────────────────
if (!empty($_POST) && $id) {
    $nome          = trim($_POST['nome']         ?? '');
    $duracao       = $_POST['duracao']            ?? '';
    $tipocontrato  = $_POST['tipocontrato']       ?? '';
    $universidade  = trim($_POST['universidade']  ?? '');
    $pais          = $_POST['pais']               ?? '';
    $programa      = $_POST['programa']           ?? '';
    $anoletivo     = $_POST['anoletivo']          ?? '';
    $obs           = trim($_POST['obs']           ?? '');
    $outroprograma = trim($_POST['outroprograma'] ?? '');
    $resposta      = trim($_POST['resposta']      ?? '');
    $inicio        = !empty($_POST['inicio']) ? date('Y-m-d', strtotime($_POST['inicio'])) : null;
    $fim           = !empty($_POST['fim'])    ? date('Y-m-d', strtotime($_POST['fim']))    : null;

    if ($programa === '1') {
        $programa = $outroprograma;
    }

    if (empty($_POST['lista_uc'])) {
        $ucs = '';
    } else {
        $listaucs = array_map(function($element) {
            return substr($element, 0, strpos($element, ' '));
        }, $_POST['lista_uc']);
        $ucs = implode(';', array_unique($listaucs));
    }

    try {
        $pdo = Database::connect();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->prepare(
            'UPDATE infodeqb_registo_mobilidade
                SET nome=?, universidade=?, pais=?, programa=?, tipocontrato=?,
                    anoletivo=?, duracao=?, inicio=?, fim=?, obs=?, dcoop=?, ucs=?
              WHERE id=?'
        )->execute([$nome, $universidade, $pais, $programa, $tipocontrato,
                    $anoletivo, $duracao, $inicio, $fim, $obs, $resposta, $ucs, $id]);
        Database::disconnect();
        $_SESSION['_mobile_flash'] = ['Registo actualizado com sucesso.', 'success'];
        header('Location: detail.php?id=' . $id);
        exit;
    } catch (PDOException $e) {
        $flashMsg  = 'Erro ao guardar: ' . $e->getMessage();
        $flashType = 'danger';
    }
}

// ── Helper para selected ───────────────────────────────────────────
function sel($val, $current) { return $val === ($current ?? '') ? ' selected' : ''; }

// Se sth_uc não foi criado ainda (POST sem $id não devia acontecer, mas por segurança)
if ($id && !isset($sth_uc)) {
    $pdo   = Database::connect();
    $sth_uc = $pdo->prepare(
        'SELECT * FROM infodeqb_unidades_curriculares
          WHERE ano LIKE :ano AND curso LIKE :curso AND regime LIKE :regime
          ORDER BY regime, uc'
    );
}

$mostrarDatas = ($dados['duracao'] ?? '') === 'Outro';

$pageTitle = t('MOBILE_DETAIL');
$mainClass  = 'iq-hr-page';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

<style>
.mob-label { font-size:.8rem; font-weight:600; display:block; margin-bottom:.15rem; }
.uc-year-hd > td { background:var(--iq-accent) !important; color:#fff !important; font-weight:600; font-size:.78rem; padding:3px 6px; }
.uc-sem-hd  > th { background:var(--iq-blue-light) !important; color:var(--iq-blue-dark) !important; font-size:.76rem; padding:3px 6px; }
.uc-row td { font-size:.76rem; padding:1px 3px; vertical-align:top; }
#selectedUcs { font-size:.78rem; line-height:1.8; }
</style>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:8px">
  <h1 class="mr-auto">
    <i class="fas fa-edit fa-sm me-2 text-muted"></i>
    Editar registo — <?= htmlspecialchars($dados['nome'] ?? 'Mobilidade IN') ?>
  </h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i>Lista
  </a>
  <a href="add.php" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-plus me-1"></i>Novo
  </a>
  <?php if ($id): ?>
  <a href="delete.php?id=<?= $id ?>" class="btn btn-outline-danger btn-sm"
     onclick="return confirm('Apagar este registo?')">
    <i class="fas fa-trash me-1"></i>Apagar
  </a>
  <?php endif; ?>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" style="font-size:.85rem">
  <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<?php if (!$id): ?>
<div class="alert alert-warning">Nenhum registo seleccionado.</div>
<?php else: ?>

<form id="mobilidade" action="detail.php?id=<?= $id ?>" method="post">

  <?php /* ── Dados do estudante ──────────────────────────────────── */ ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header py-2">
      <i class="fas fa-user-graduate fa-xs me-1 text-muted"></i>
      <strong>Dados do estudante</strong>
    </div>
    <div class="card-body">

      <div class="form-row">
        <div class="col-md-2 form-group mb-2">
          <label class="mob-label">Ano letivo <span class="text-danger">*</span></label>
          <select class="form-control form-control-sm" required name="anoletivo">
            <option value="2025/26"<?= sel('2025/26', $dados['anoletivo'] ?? '') ?>>2025/26</option>
            <option value="2024/25"<?= sel('2024/25', $dados['anoletivo'] ?? '') ?>>2024/25</option>
            <option value="2023/24"<?= sel('2023/24', $dados['anoletivo'] ?? '') ?>>2023/24</option>
            <option value="2022/23"<?= sel('2022/23', $dados['anoletivo'] ?? '') ?>>2022/23</option>
            <option value="2021/22"<?= sel('2021/22', $dados['anoletivo'] ?? '') ?>>2021/22</option>
          </select>
        </div>
        <div class="col-md-4 form-group mb-2">
          <label class="mob-label">Nome <span class="text-danger">*</span></label>
          <input name="nome" type="text" required class="form-control form-control-sm"
                 value="<?= htmlspecialchars($dados['nome'] ?? '') ?>">
        </div>
        <div class="col-md-4 form-group mb-2">
          <label class="mob-label">Universidade</label>
          <input name="universidade" type="text" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($dados['universidade'] ?? '') ?>">
        </div>
        <div class="col-md-2 form-group mb-2">
          <label class="mob-label">País</label>
          <?php $paisAtual = $dados['pais'] ?? ''; ?>
          <select class="form-control form-control-sm" name="pais">
            <?php if ($paisAtual && !in_array($paisAtual, ['África do Sul','Albânia','Alemanha','Andorra'])): ?>
            <option value="<?= htmlspecialchars($paisAtual) ?>" selected><?= htmlspecialchars($paisAtual) ?></option>
            <?php endif; ?>
            <?php $paises = ['África do Sul','Albânia','Alemanha','Andorra','Angola','Anguilla','Antigua',
              'Arábia Saudita','Argentina','Armênia','Aruba','Austrália','Áustria','Azerbaijão','Bahamas',
              'Bahrein','Bangladesh','Barbados','Bélgica','Benin','Bermudas','Botsuana','Brasil','Brunei',
              'Bulgária','Burkina Fasso','Cabo Verde','Camarões','Camboja','Canadá','Cazaquistão','Chade',
              'Chile','China','Cidade do Vaticano','Colômbia','Congo','Coréia do Sul','Costa do Marfim',
              'Costa Rica','Croácia','Dinamarca','Djibuti','Dominica','EUA','Egito','El Salvador',
              'Emirados Árabes','Equador','Eritréia','Escócia','Eslováquia','Eslovênia','Espanha',
              'Estônia','Etiópia','Fiji','Filipinas','Finlândia','França','Gabão','Gâmbia','Gana',
              'Geórgia','Gibraltar','Granada','Grécia','Guadalupe','Guam','Guatemala','Guiana',
              'Guiana Francesa','Guiné-bissau','Haiti','Holanda','Honduras','Hong Kong','Hungria',
              'Iêmen','Ilhas Cayman','Ilhas Cook','Ilhas Curaçao','Ilhas Marshall',
              'Ilhas Turks & Caicos','Ilhas Virgens (brit.)','Ilhas Virgens(amer.)',
              'Ilhas Wallis e Futuna','Índia','Indonésia','Inglaterra','Irlanda','Islândia',
              'Israel','Itália','Jamaica','Japão','Jordânia','Kuwait','Latvia','Líbano',
              'Liechtenstein','Lituânia','Luxemburgo','Macau','Macedônia','Madagascar','Malásia',
              'Malaui','Mali','Malta','Marrocos','Martinica','Mauritânia','Mauritius','México',
              'Moldova','Mônaco','Montserrat','Nepal','Nicarágua','Niger','Nigéria','Noruega',
              'Nova Caledônia','Nova Zelândia','Omã','Palau','Panamá','Papua-nova Guiné',
              'Paquistão','Peru','Polinésia Francesa','Polônia','Porto Rico','Portugal','Qatar',
              'Quênia','Rep. Dominicana','Rep. Tcheca','Reunion','Romênia','Ruanda','Rússia',
              'Saipan','Samoa Americana','Senegal','Serra Leone','Seychelles','Singapura','Síria',
              'Sri Lanka','St. Kitts & Nevis','St. Lúcia','St. Vincent','Sudão','Suécia','Suiça',
              'Suriname','Tailândia','Taiwan','Tanzânia','Togo','Trinidad & Tobago','Tunísia',
              'Turquia','Ucrânia','Uganda','Uruguai','Venezuela','Vietnã','Zaire','Zâmbia','Zimbábue'];
            foreach ($paises as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>"<?= sel($p, $paisAtual) ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="col-md-4 form-group mb-2">
          <label class="mob-label">Programa</label>
          <select class="form-control form-control-sm" name="programa">
            <?php $progAtual = $dados['programa'] ?? '';
            $progs = [
              'Erasmus Estudos - Europa'               => 'Erasmus Estudos — Europa',
              'Erasmus Estágios (MI) e outros'         => 'Erasmus Estágios (MI) e outros',
              'Estágios - Alunos de Doutoramento'      => 'Estágios — Alunos de Doutoramento',
              'Estágios - Investigadores de pós-doc'   => 'Estágios — Investigadores de pós-doc',
              'Programa Almeida Garrett'               => 'Programa Almeida Garrett',
              'ERASMUS / BE MUNDUS / MUNDUS LINDO'     => 'ERASMUS / BE MUNDUS / MUNDUS LINDO — países que não Brasil',
              'MOBILE + outros'                        => 'MOBILE + outros — Brasil',
            ];
            foreach ($progs as $v => $label): ?>
            <option value="<?= htmlspecialchars($v) ?>"<?= sel($v, $progAtual) ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
            <option value="1"<?= (!array_key_exists($progAtual, $progs) && $progAtual !== '') ? ' selected' : '' ?>>Outro…</option>
          </select>
        </div>
        <div class="col-md-4 form-group mb-2 outroprograma"
             style="display:<?= (!array_key_exists($dados['programa'] ?? '', $progs ?? []) && ($dados['programa'] ?? '') !== '') ? 'block' : 'none' ?>">
          <label class="mob-label">Nome do programa</label>
          <input id="outroprograma" name="outroprograma" type="text" class="form-control form-control-sm"
                 value="<?= htmlspecialchars(!array_key_exists($progAtual, $progs ?? []) ? $progAtual : '') ?>">
        </div>
        <div class="col-md-4 form-group mb-2">
          <label class="mob-label">Tipo de contrato <span class="text-danger">*</span></label>
          <select class="form-control form-control-sm" required name="tipocontrato">
            <option value="UCs"<?= sel('UCs', $dados['tipocontrato'] ?? '') ?>>Unidades Curriculares</option>
            <option value="Estágio"<?= sel('Estágio', $dados['tipocontrato'] ?? '') ?>>Estágio</option>
            <option value="UCs + Estágio"<?= sel('UCs + Estágio', $dados['tipocontrato'] ?? '') ?>>UCs + Estágio</option>
          </select>
        </div>
        <div class="col-md-2 form-group mb-2">
          <label class="mob-label">Duração <span class="text-danger">*</span></label>
          <select class="form-control form-control-sm" required name="duracao">
            <option value="1º semestre"<?= sel('1º semestre', $dados['duracao'] ?? '') ?>>1º semestre</option>
            <option value="2º semestre"<?= sel('2º semestre', $dados['duracao'] ?? '') ?>>2º semestre</option>
            <option value="Anual"<?= sel('Anual',      $dados['duracao'] ?? '') ?>>Anual</option>
            <option value="Outro"<?= sel('Outro',      $dados['duracao'] ?? '') ?>>Outro…</option>
          </select>
        </div>
        <div class="col-md-2 form-group mb-2 outraduracao" style="display:<?= $mostrarDatas ? 'block' : 'none' ?>">
          <label class="mob-label">Data início</label>
          <input type="date" name="inicio" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($dados['inicio'] ?? '') ?>">
        </div>
        <div class="col-md-2 form-group mb-0 outraduracao" style="display:<?= $mostrarDatas ? 'block' : 'none' ?>">
          <label class="mob-label">Data fim</label>
          <input type="date" name="fim" class="form-control form-control-sm"
                 value="<?= htmlspecialchars($dados['fim'] ?? '') ?>">
        </div>
      </div>

    </div>
  </div>

  <?php /* ── Unidades Curriculares ─────────────────────────────────── */ ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header py-2">
      <i class="fas fa-book fa-xs me-1 text-muted"></i>
      <strong>Unidades Curriculares</strong>
    </div>
    <div class="card-body">
      <div class="row">

        <?php /* L.EQ */
        $leqPanels = [
          'leq1' => [1, '1º Ano'],
          'leq2' => [2, '2º Ano'],
          'leq3' => [3, '3º Ano'],
        ]; ?>
        <div class="col-md-5">
          <div class="accordion mb-2" id="syllabusLEQ">
            <div class="card border">
              <div class="card-header py-2" style="background:var(--iq-accent)">
                <span class="text-white small font-weight-bold">L.EQ — UCs que se inscreve</span>
                <div class="float-right">
                  <?php foreach ($leqPanels as $panelId => $info): ?>
                  <button class="btn btn-xs btn-outline-light" type="button"
                          data-bs-toggle="collapse" data-bs-target="#<?= $panelId ?>"><?= $info[1] ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php foreach ($leqPanels as $panelId => [$ano, $titulo]): ?>
              <div id="<?= $panelId ?>" class="collapse<?= $panelId === 'leq1' ? ' show' : '' ?>" data-bs-parent="#syllabusLEQ">
                <div class="card-body p-2">
                  <table class="table table-sm mb-0">
                    <tr class="uc-year-hd"><td colspan="3"><?= $titulo ?></td></tr>
                    <tr class="uc-sem-hd"><th>1º Semestre</th><th>2º Semestre</th></tr>
                    <tr>
                      <?php foreach (['1S', '2S'] as $regime): ?>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth_uc->execute([':ano' => $ano, ':curso' => 'L.EQ', ':regime' => $regime]);
                          while ($row = $sth_uc->fetch(PDO::FETCH_ASSOC)):
                            $chave = $row['codigo'] . ' ' . $row['uc'];
                            $ck = isset($checked[$chave]) ? ' checked' : ''; ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]"<?= $ck ?> value="<?= htmlspecialchars($chave) ?>"></td>
                            <td class="text-muted"><?= htmlspecialchars($row['codigo']) ?></td>
                            <td><?= htmlspecialchars($row['uc']) ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                      <?php endforeach; ?>
                    </tr>
                  </table>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <?php /* M.EQ */
        $meqPanels = [
          'meq1' => [1, '1º Ano'],
          'meq2' => [2, '2º Ano'],
        ]; ?>
        <div class="col-md-5">
          <div class="accordion mb-2" id="syllabusMEQ">
            <div class="card border">
              <div class="card-header py-2" style="background:var(--iq-accent)">
                <span class="text-white small font-weight-bold">M.EQ — UCs que se inscreve</span>
                <div class="float-right">
                  <?php foreach ($meqPanels as $panelId => $info): ?>
                  <button class="btn btn-xs btn-outline-light" type="button"
                          data-bs-toggle="collapse" data-bs-target="#<?= $panelId ?>"><?= $info[1] ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php foreach ($meqPanels as $panelId => [$ano, $titulo]): ?>
              <div id="<?= $panelId ?>" class="collapse<?= $panelId === 'meq1' ? ' show' : '' ?>" data-bs-parent="#syllabusMEQ">
                <div class="card-body p-2">
                  <table class="table table-sm mb-0">
                    <tr class="uc-year-hd"><td colspan="3"><?= $titulo ?></td></tr>
                    <tr class="uc-sem-hd"><th>1º Semestre</th><th>2º Semestre</th></tr>
                    <tr>
                      <?php foreach (['1S', '2S'] as $regime): ?>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth_uc->execute([':ano' => $ano, ':curso' => 'M.EQ', ':regime' => $regime]);
                          while ($row = $sth_uc->fetch(PDO::FETCH_ASSOC)):
                            $chave = $row['codigo'] . ' ' . $row['uc'];
                            $ck = isset($checked[$chave]) ? ' checked' : ''; ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]"<?= $ck ?> value="<?= htmlspecialchars($chave) ?>"></td>
                            <td class="text-muted"><?= htmlspecialchars($row['codigo']) ?></td>
                            <td><?= htmlspecialchars($row['uc']) ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                      <?php endforeach; ?>
                    </tr>
                  </table>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="col-md-2">
          <label class="mob-label">UCs seleccionadas</label>
          <div id="selectedUcs" class="text-muted">—</div>
        </div>

      </div>
    </div>
  </div>

  <?php /* ── Observações ───────────────────────────────────────────── */ ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header py-2">
      <i class="fas fa-comment-alt fa-xs me-1 text-muted"></i>
      <strong>Observações</strong>
    </div>
    <div class="card-body">
      <div class="form-row">
        <div class="col-md-6 form-group mb-0">
          <label class="mob-label">Comentários</label>
          <textarea class="form-control form-control-sm" name="obs" rows="3"
                    placeholder="Comentários…"><?= htmlspecialchars($dados['obs'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6 form-group mb-0">
          <label class="mob-label">Resposta DCOOP</label>
          <textarea class="form-control form-control-sm" name="resposta" rows="3"
                    placeholder="Resposta à DCOOP…"><?= htmlspecialchars($dados['dcoop'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end mb-4" style="gap:8px">
    <a href="index.php" class="btn btn-outline-secondary"><?= t('CANCEL') ?></a>
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save me-1"></i><?= t('SAVE_CHANGES') ?>
    </button>
  </div>

</form>
<?php endif; ?>

<script>
// Inicializar lista de UCs seleccionadas ao carregar
function refreshSelected() {
    var checked = $('input[type="checkbox"]:checked').map(function() {
        return this.value.substring(this.value.indexOf(' ') + 1);
    }).get();
    var unique = checked.filter(function(v, i, a) { return a.indexOf(v) === i; });
    $('#selectedUcs').html(unique.length ? unique.join('<br>') : '<span class="text-muted">—</span>');
}

$('input[type="checkbox"]').on('change', refreshSelected);
refreshSelected();

$('select[name=programa]').on('change', function() {
    if ($(this).val() === '1') {
        $('.outroprograma').show();
        document.getElementById('outroprograma').required = true;
    } else {
        $('.outroprograma').hide();
        document.getElementById('outroprograma').required = false;
    }
});

$('select[name=duracao]').on('change', function() {
    if ($(this).val() === 'Outro') {
        $('.outraduracao').show();
    } else {
        $('.outraduracao').hide();
    }
});
</script>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
