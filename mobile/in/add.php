<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsMobile))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sth = $pdo->prepare('SELECT * FROM infodeqb_unidades_curriculares WHERE ano LIKE :ano AND curso LIKE :curso AND regime LIKE :regime ORDER BY regime, uc');

$flashMsg  = '';
$flashType = 'success';
if (isset($_SESSION['_mobile_flash'])) {
    list($flashMsg, $flashType) = $_SESSION['_mobile_flash'];
    unset($_SESSION['_mobile_flash']);
}

if (!empty($_POST)) {
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
        $sql = 'INSERT INTO infodeqb_registo_mobilidade
                    (nome, universidade, pais, programa, tipocontrato, anoletivo, duracao, inicio, fim, obs, dcoop, ucs)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $pdo->prepare($sql)->execute([$nome, $universidade, $pais, $programa, $tipocontrato,
                                      $anoletivo, $duracao, $inicio, $fim, $obs, $resposta, $ucs]);
        Database::disconnect();
        $_SESSION['_mobile_flash'] = ['Registo inserido com sucesso.', 'success'];
        header('Location: add.php');
        exit;
    } catch (PDOException $e) {
        $flashMsg  = 'Erro ao guardar: ' . $e->getMessage();
        $flashType = 'danger';
    }
}

$pageTitle = t('MOBILE_NEW_REQUEST');
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

<div class="iq-page-header d-flex align-items-center" style="gap:8px">
  <h1 class="mr-auto">
    <i class="fas fa-plus-circle fa-sm me-2 text-muted"></i>Novo registo — Mobilidade IN
  </h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i>Lista
  </a>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" style="font-size:.85rem">
  <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<form id="mobilidade" action="add.php" method="post">

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
            <option disabled selected value="">Seleccione…</option>
            <option value="2025/26">2025/26</option>
            <option value="2024/25">2024/25</option>
            <option value="2023/24">2023/24</option>
            <option value="2022/23">2022/23</option>
          </select>
        </div>
        <div class="col-md-4 form-group mb-2">
          <label class="mob-label">Nome <span class="text-danger">*</span></label>
          <input name="nome" type="text" placeholder="Nome completo" required class="form-control form-control-sm">
        </div>
        <div class="col-md-4 form-group mb-2">
          <label class="mob-label">Universidade</label>
          <input name="universidade" type="text" placeholder="Universidade de origem" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 form-group mb-2">
          <label class="mob-label">País</label>
          <select class="form-control form-control-sm" name="pais">
            <option disabled selected value="">País…</option>
            <option value="África do Sul">África do Sul</option><option value="Albânia">Albânia</option>
            <option value="Alemanha">Alemanha</option><option value="Andorra">Andorra</option>
            <option value="Angola">Angola</option><option value="Anguilla">Anguilla</option>
            <option value="Antigua">Antigua</option><option value="Arábia Saudita">Arábia Saudita</option>
            <option value="Argentina">Argentina</option><option value="Armênia">Armênia</option>
            <option value="Aruba">Aruba</option><option value="Austrália">Austrália</option>
            <option value="Áustria">Áustria</option><option value="Azerbaijão">Azerbaijão</option>
            <option value="Bahamas">Bahamas</option><option value="Bahrein">Bahrein</option>
            <option value="Bangladesh">Bangladesh</option><option value="Barbados">Barbados</option>
            <option value="Bélgica">Bélgica</option><option value="Benin">Benin</option>
            <option value="Bermudas">Bermudas</option><option value="Botsuana">Botsuana</option>
            <option value="Brasil">Brasil</option><option value="Brunei">Brunei</option>
            <option value="Bulgária">Bulgária</option><option value="Burkina Fasso">Burkina Fasso</option>
            <option value="Cabo Verde">Cabo Verde</option><option value="Camarões">Camarões</option>
            <option value="Camboja">Camboja</option><option value="Canadá">Canadá</option>
            <option value="Cazaquistão">Cazaquistão</option><option value="Chade">Chade</option>
            <option value="Chile">Chile</option><option value="China">China</option>
            <option value="Cidade do Vaticano">Cidade do Vaticano</option>
            <option value="Colômbia">Colômbia</option><option value="Congo">Congo</option>
            <option value="Coréia do Sul">Coréia do Sul</option>
            <option value="Costa do Marfim">Costa do Marfim</option>
            <option value="Costa Rica">Costa Rica</option><option value="Croácia">Croácia</option>
            <option value="Dinamarca">Dinamarca</option><option value="Djibuti">Djibuti</option>
            <option value="Dominica">Dominica</option><option value="EUA">EUA</option>
            <option value="Egito">Egito</option><option value="El Salvador">El Salvador</option>
            <option value="Emirados Árabes">Emirados Árabes</option>
            <option value="Equador">Equador</option><option value="Eritréia">Eritréia</option>
            <option value="Escócia">Escócia</option><option value="Eslováquia">Eslováquia</option>
            <option value="Eslovênia">Eslovênia</option><option value="Espanha">Espanha</option>
            <option value="Estônia">Estônia</option><option value="Etiópia">Etiópia</option>
            <option value="Fiji">Fiji</option><option value="Filipinas">Filipinas</option>
            <option value="Finlândia">Finlândia</option><option value="França">França</option>
            <option value="Gabão">Gabão</option><option value="Gâmbia">Gâmbia</option>
            <option value="Gana">Gana</option><option value="Geórgia">Geórgia</option>
            <option value="Gibraltar">Gibraltar</option><option value="Granada">Granada</option>
            <option value="Grécia">Grécia</option><option value="Guadalupe">Guadalupe</option>
            <option value="Guam">Guam</option><option value="Guatemala">Guatemala</option>
            <option value="Guiana">Guiana</option><option value="Guiana Francesa">Guiana Francesa</option>
            <option value="Guiné-bissau">Guiné-bissau</option><option value="Haiti">Haiti</option>
            <option value="Holanda">Holanda</option><option value="Honduras">Honduras</option>
            <option value="Hong Kong">Hong Kong</option><option value="Hungria">Hungria</option>
            <option value="Iêmen">Iêmen</option><option value="Ilhas Cayman">Ilhas Cayman</option>
            <option value="Ilhas Cook">Ilhas Cook</option><option value="Ilhas Curaçao">Ilhas Curaçao</option>
            <option value="Ilhas Marshall">Ilhas Marshall</option>
            <option value="Ilhas Turks & Caicos">Ilhas Turks & Caicos</option>
            <option value="Ilhas Virgens (brit.)">Ilhas Virgens (brit.)</option>
            <option value="Ilhas Virgens(amer.)">Ilhas Virgens(amer.)</option>
            <option value="Ilhas Wallis e Futuna">Ilhas Wallis e Futuna</option>
            <option value="Índia">Índia</option><option value="Indonésia">Indonésia</option>
            <option value="Inglaterra">Inglaterra</option><option value="Irlanda">Irlanda</option>
            <option value="Islândia">Islândia</option><option value="Israel">Israel</option>
            <option value="Itália">Itália</option><option value="Jamaica">Jamaica</option>
            <option value="Japão">Japão</option><option value="Jordânia">Jordânia</option>
            <option value="Kuwait">Kuwait</option><option value="Latvia">Latvia</option>
            <option value="Líbano">Líbano</option><option value="Liechtenstein">Liechtenstein</option>
            <option value="Lituânia">Lituânia</option><option value="Luxemburgo">Luxemburgo</option>
            <option value="Macau">Macau</option><option value="Macedônia">Macedônia</option>
            <option value="Madagascar">Madagascar</option><option value="Malásia">Malásia</option>
            <option value="Malaui">Malaui</option><option value="Mali">Mali</option>
            <option value="Malta">Malta</option><option value="Marrocos">Marrocos</option>
            <option value="Martinica">Martinica</option><option value="Mauritânia">Mauritânia</option>
            <option value="Mauritius">Mauritius</option><option value="México">México</option>
            <option value="Moldova">Moldova</option><option value="Mônaco">Mônaco</option>
            <option value="Montserrat">Montserrat</option><option value="Nepal">Nepal</option>
            <option value="Nicarágua">Nicarágua</option><option value="Niger">Niger</option>
            <option value="Nigéria">Nigéria</option><option value="Noruega">Noruega</option>
            <option value="Nova Caledônia">Nova Caledônia</option>
            <option value="Nova Zelândia">Nova Zelândia</option><option value="Omã">Omã</option>
            <option value="Palau">Palau</option><option value="Panamá">Panamá</option>
            <option value="Papua-nova Guiné">Papua-nova Guiné</option>
            <option value="Paquistão">Paquistão</option><option value="Peru">Peru</option>
            <option value="Polinésia Francesa">Polinésia Francesa</option>
            <option value="Polônia">Polônia</option><option value="Porto Rico">Porto Rico</option>
            <option value="Portugal">Portugal</option><option value="Qatar">Qatar</option>
            <option value="Quênia">Quênia</option>
            <option value="Rep. Dominicana">Rep. Dominicana</option>
            <option value="Rep. Tcheca">Rep. Tcheca</option><option value="Reunion">Reunion</option>
            <option value="Romênia">Romênia</option><option value="Ruanda">Ruanda</option>
            <option value="Rússia">Rússia</option><option value="Saipan">Saipan</option>
            <option value="Samoa Americana">Samoa Americana</option>
            <option value="Senegal">Senegal</option><option value="Serra Leone">Serra Leone</option>
            <option value="Seychelles">Seychelles</option><option value="Singapura">Singapura</option>
            <option value="Síria">Síria</option><option value="Sri Lanka">Sri Lanka</option>
            <option value="St. Kitts & Nevis">St. Kitts & Nevis</option>
            <option value="St. Lúcia">St. Lúcia</option><option value="St. Vincent">St. Vincent</option>
            <option value="Sudão">Sudão</option><option value="Suécia">Suécia</option>
            <option value="Suiça">Suiça</option><option value="Suriname">Suriname</option>
            <option value="Tailândia">Tailândia</option><option value="Taiwan">Taiwan</option>
            <option value="Tanzânia">Tanzânia</option><option value="Togo">Togo</option>
            <option value="Trinidad & Tobago">Trinidad & Tobago</option>
            <option value="Tunísia">Tunísia</option><option value="Turquia">Turquia</option>
            <option value="Ucrânia">Ucrânia</option><option value="Uganda">Uganda</option>
            <option value="Uruguai">Uruguai</option><option value="Venezuela">Venezuela</option>
            <option value="Vietnã">Vietnã</option><option value="Zaire">Zaire</option>
            <option value="Zâmbia">Zâmbia</option><option value="Zimbábue">Zimbábue</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="col-md-4 form-group mb-2">
          <label class="mob-label">Programa <span class="text-danger">*</span></label>
          <select class="form-control form-control-sm" required name="programa">
            <option disabled selected value="">Programa de mobilidade…</option>
            <option value="Erasmus Estudos - Europa">Erasmus Estudos — Europa</option>
            <option value="Erasmus Estágios (MI) e outros">Erasmus Estágios (MI) e outros</option>
            <option value="Estágios - Alunos de Doutoramento">Estágios — Alunos de Doutoramento</option>
            <option value="Estágios - Investigadores de pós-doc">Estágios — Investigadores de pós-doc</option>
            <option value="Programa Almeida Garrett">Programa Almeida Garrett</option>
            <option value="ERASMUS / BE MUNDUS / MUNDUS LINDO">ERASMUS / BE MUNDUS / MUNDUS LINDO — países que não Brasil</option>
            <option value="MOBILE + outros">MOBILE + outros — Brasil</option>
            <option value="1">Outro…</option>
          </select>
        </div>
        <div class="col-md-4 form-group mb-2 outroprograma" style="display:none">
          <label class="mob-label">Nome do programa</label>
          <input id="outroprograma" name="outroprograma" type="text" class="form-control form-control-sm" placeholder="Nome do programa">
        </div>
        <div class="col-md-4 form-group mb-2">
          <label class="mob-label">Tipo de contrato <span class="text-danger">*</span></label>
          <select class="form-control form-control-sm" required name="tipocontrato">
            <option disabled selected value="">Tipo de contrato…</option>
            <option value="UCs">Unidades Curriculares</option>
            <option value="Estágio">Estágio</option>
            <option value="UCs + Estágio">UCs + Estágio</option>
          </select>
        </div>
        <div class="col-md-2 form-group mb-2">
          <label class="mob-label">Duração <span class="text-danger">*</span></label>
          <select class="form-control form-control-sm" required name="duracao">
            <option disabled selected value="">Duração…</option>
            <option value="1º semestre">1º semestre</option>
            <option value="2º semestre">2º semestre</option>
            <option value="Anual">Anual</option>
            <option value="Outro">Outro…</option>
          </select>
        </div>
        <div class="col-md-2 form-group mb-2 outraduracao" style="display:none">
          <label class="mob-label">Data início</label>
          <input type="date" name="inicio" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 form-group mb-0 outraduracao" style="display:none">
          <label class="mob-label">Data fim</label>
          <input type="date" name="fim" class="form-control form-control-sm">
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

        <?php /* L.EQ */ ?>
        <div class="col-md-5">
          <div class="accordion mb-2" id="syllabusLEQ">
            <div class="card border">
              <div class="card-header py-2" style="background:var(--iq-accent)">
                <span class="text-white small font-weight-bold">L.EQ — UCs que se inscreve</span>
                <div class="float-right">
                  <button class="btn btn-xs btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#leq1">1º Ano</button>
                  <button class="btn btn-xs btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#leq2">2º Ano</button>
                  <button class="btn btn-xs btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#leq3">3º Ano</button>
                </div>
              </div>

              <div id="leq1" class="collapse show" data-bs-parent="#syllabusLEQ">
                <div class="card-body p-2">
                  <table class="table table-sm mb-0">
                    <tr class="uc-year-hd"><td colspan="6">1º Ano</td></tr>
                    <tr class="uc-sem-hd"><th>1º Semestre</th><th>2º Semestre</th></tr>
                    <tr>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>1,':curso'=>'L.EQ',':regime'=>'1S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>1,':curso'=>'L.EQ',':regime'=>'2S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>

              <div id="leq2" class="collapse" data-bs-parent="#syllabusLEQ">
                <div class="card-body p-2">
                  <table class="table table-sm mb-0">
                    <tr class="uc-year-hd"><td colspan="6">2º Ano</td></tr>
                    <tr class="uc-sem-hd"><th>1º Semestre</th><th>2º Semestre</th></tr>
                    <tr>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>2,':curso'=>'L.EQ',':regime'=>'1S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>2,':curso'=>'L.EQ',':regime'=>'2S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>

              <div id="leq3" class="collapse" data-bs-parent="#syllabusLEQ">
                <div class="card-body p-2">
                  <table class="table table-sm mb-0">
                    <tr class="uc-year-hd"><td colspan="6">3º Ano</td></tr>
                    <tr class="uc-sem-hd"><th>1º Semestre</th><th>2º Semestre</th></tr>
                    <tr>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>3,':curso'=>'L.EQ',':regime'=>'1S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>3,':curso'=>'L.EQ',':regime'=>'2S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>

            </div>
          </div>
        </div>

        <?php /* M.EQ */ ?>
        <div class="col-md-5">
          <div class="accordion mb-2" id="syllabusMEQ">
            <div class="card border">
              <div class="card-header py-2" style="background:var(--iq-accent)">
                <span class="text-white small font-weight-bold">M.EQ — UCs que se inscreve</span>
                <div class="float-right">
                  <button class="btn btn-xs btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#meq1">1º Ano</button>
                  <button class="btn btn-xs btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#meq2">2º Ano</button>
                </div>
              </div>

              <div id="meq1" class="collapse show" data-bs-parent="#syllabusMEQ">
                <div class="card-body p-2">
                  <table class="table table-sm mb-0">
                    <tr class="uc-year-hd"><td colspan="6">1º Ano</td></tr>
                    <tr class="uc-sem-hd"><th>1º Semestre</th><th>2º Semestre</th></tr>
                    <tr>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>1,':curso'=>'M.EQ',':regime'=>'1S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>1,':curso'=>'M.EQ',':regime'=>'2S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>

              <div id="meq2" class="collapse" data-bs-parent="#syllabusMEQ">
                <div class="card-body p-2">
                  <table class="table table-sm mb-0">
                    <tr class="uc-year-hd"><td colspan="6">2º Ano</td></tr>
                    <tr class="uc-sem-hd"><th>1º Semestre</th><th>2º Semestre</th></tr>
                    <tr>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>2,':curso'=>'M.EQ',':regime'=>'1S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                      <td class="align-top" style="width:50%">
                        <table class="table-sm w-100">
                          <?php $sth->execute([':ano'=>2,':curso'=>'M.EQ',':regime'=>'2S']);
                          while ($row = $sth->fetch(PDO::FETCH_ASSOC)): ?>
                          <tr class="uc-row">
                            <td><input type="checkbox" name="lista_uc[]" value="<?= $row['codigo'].' '.$row['uc'] ?>"></td>
                            <td class="text-muted"><?= $row['codigo'] ?></td>
                            <td><?= $row['uc'] ?></td>
                          </tr>
                          <?php endwhile; ?>
                        </table>
                      </td>
                    </tr>
                  </table>
                </div>
              </div>

            </div>
          </div>
        </div>

        <?php /* UCs seleccionadas */ ?>
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
          <textarea class="form-control form-control-sm" name="obs" rows="3" placeholder="Comentários…"></textarea>
        </div>
        <div class="col-md-6 form-group mb-0">
          <label class="mob-label">Resposta DCOOP</label>
          <textarea class="form-control form-control-sm" name="resposta" rows="3" placeholder="Resposta à DCOOP…"></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end mb-4" style="gap:8px">
    <a href="index.php" class="btn btn-outline-secondary"><?= t('CANCEL') ?></a>
    <button type="submit" class="btn btn-primary">
      <i class="fas fa-save me-1"></i><?= t('SUBMIT') ?>
    </button>
  </div>

</form>

<script>
$('input[type="checkbox"]').on('change', function() {
    var checked = $('input[type="checkbox"]:checked').map(function() {
        return this.value.substring(this.value.indexOf(' ') + 1);
    }).get();
    var unique = checked.filter(function(v, i, a) { return a.indexOf(v) === i; });
    $('#selectedUcs').html(unique.length ? unique.join('<br>') : '<span class="text-muted">—</span>');
});

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
