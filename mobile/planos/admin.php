<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once ROOT_DIR . '/infodeqb/inc/admins.php';

$isErasmusAdmin = $isAdmin || in_array($_iqCurrentUser, $_iqAdminsErasmus);
if (!$isErasmusAdmin) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php'); exit;
}

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$flashMsg  = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'add_pais') {
        $nome = trim($_POST['nome_pais'] ?? '');
        if ($nome) {
            $pdo->prepare('INSERT INTO infodeqb_erasmus_paises (nome_pais) VALUES (?)')->execute([$nome]);
            $flashMsg = "País '$nome' adicionado.";
        }
    }

    if ($acao === 'add_instituicao') {
        $id_pais = (int)($_POST['id_pais'] ?? 0);
        $nome    = trim($_POST['nome_instituicao'] ?? '');
        if ($nome && $id_pais) {
            $pdo->prepare('INSERT INTO infodeqb_erasmus_instituicoes (nome_instituicao, id_pais) VALUES (?,?)')
                ->execute([$nome, $id_pais]);
            $flashMsg = "Instituição '$nome' adicionada.";
        }
    }

    if ($acao === 'add_cadeira_feup') {
        $nome   = trim($_POST['nome_cadeira_feup'] ?? '');
        $codigo = trim($_POST['codigo'] ?? '');
        $ects   = (float)($_POST['ects_feup'] ?? 0);
        if ($nome && $codigo) {
            $pdo->prepare('INSERT INTO infodeqb_erasmus_cadeiras_feup (nome_cadeira_feup, codigo, ects_feup) VALUES (?,?,?)')
                ->execute([$nome, $codigo, $ects]);
            $flashMsg = "UC FEUP '$nome' adicionada.";
        }
    }

    if ($acao === 'add_cadeira_estrangeira') {
        $id_inst = (int)($_POST['id_instituicao_est'] ?? 0);
        $nome    = trim($_POST['nome_cadeira_estrangeira'] ?? '');
        $ects    = (float)($_POST['ects_estrangeira'] ?? 0);
        $link    = trim($_POST['link_url'] ?? '');
        if ($nome && $id_inst) {
            $pdo->prepare(
                'INSERT INTO infodeqb_erasmus_cadeiras_estrangeiras (id_instituicao, nome_cadeira_estrangeira, ects_estrangeira)
                 VALUES (?,?,?)'
            )->execute([$id_inst, $nome, $ects]);
            $id_ce = (int)$pdo->lastInsertId();
            if ($link && $id_ce) {
                $pdo->prepare('INSERT INTO infodeqb_erasmus_links_cadeiras (id_cadeira_estrangeira, link_url) VALUES (?,?)')
                    ->execute([$id_ce, $link]);
            }
            $flashMsg = "Cadeira estrangeira '$nome' adicionada.";
        }
    }

    if ($acao === 'salvar_plano_mestre') {
        $id_inst  = (int)($_POST['plano_id_instituicao'] ?? 0);
        $aluno    = (int)($_POST['plano_aluno'] ?? 0);
        $ano      = trim($_POST['plano_ano_letivo'] ?? '');
        $id_cf    = (int)($_POST['plano_id_cadeira_feup'] ?? 0);
        $cadeiras = isset($_POST['plano_cadeiras_estrangeiras']) ? (array)$_POST['plano_cadeiras_estrangeiras'] : [];

        if ($id_inst && $aluno && $ano && $id_cf && $cadeiras) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare(
                    'INSERT INTO infodeqb_erasmus_equivalencias (id_instituicao, id_cadeira_feup, numero_estudante_autor, ano_letivo)
                     VALUES (?,?,?,?)'
                )->execute([$id_inst, $id_cf, $aluno, $ano]);
                $id_eq = (int)$pdo->lastInsertId();
                $sRel  = $pdo->prepare(
                    'INSERT INTO infodeqb_erasmus_equivalencias_relacao (id_equivalencia, id_cadeira_estrangeira) VALUES (?,?)'
                );
                foreach ($cadeiras as $id_ce) {
                    $sRel->execute([$id_eq, (int)$id_ce]);
                }
                $pdo->commit();
                $flashMsg = 'Plano de estudos guardado com sucesso.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $flashMsg  = 'Erro ao guardar o plano: ' . $e->getMessage();
                $flashType = 'danger';
            }
        } else {
            $flashMsg  = 'Preencha todos os campos e seleccione pelo menos uma cadeira estrangeira.';
            $flashType = 'warning';
        }
    }
}

$paises       = $pdo->query('SELECT * FROM infodeqb_erasmus_paises ORDER BY nome_pais ASC')->fetchAll(PDO::FETCH_ASSOC);
$instituicoes = $pdo->query('SELECT * FROM infodeqb_erasmus_instituicoes ORDER BY nome_instituicao ASC')->fetchAll(PDO::FETCH_ASSOC);
$cadeiras_feup = $pdo->query('SELECT * FROM infodeqb_erasmus_cadeiras_feup ORDER BY nome_cadeira_feup ASC')->fetchAll(PDO::FETCH_ASSOC);
$cadeiras_est  = $pdo->query(
    'SELECT CE.*, I.nome_instituicao
     FROM infodeqb_erasmus_cadeiras_estrangeiras CE
     JOIN infodeqb_erasmus_instituicoes I ON CE.id_instituicao = I.id_instituicao
     ORDER BY I.nome_instituicao ASC, CE.nome_cadeira_estrangeira ASC'
)->fetchAll(PDO::FETCH_ASSOC);

Database::disconnect();

$pageTitle = 'Gestão Erasmus';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<div class="iq-page-header d-flex align-items-center flex-wrap" style="gap:8px">
  <div class="mr-auto">
    <h1>Gestão de Equivalências Erasmus</h1>
  </div>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left mr-1"></i>Portal
  </a>
</div>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> alert-dismissible fade show mb-3" role="alert" style="font-size:.85rem">
  <?= htmlspecialchars($flashMsg) ?>
  <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<h5 class="mb-3">Parte 1 — Entidades base</h5>
<div class="row">

  <div class="col-md-6 mb-3">
    <div class="card shadow-sm">
      <div class="card-header py-2"><strong>Novo País</strong></div>
      <div class="card-body py-2">
        <form method="POST">
          <input type="hidden" name="acao" value="add_pais">
          <div class="form-group mb-2">
            <input type="text" name="nome_pais" class="form-control form-control-sm"
                   placeholder="Ex: Suécia" required>
          </div>
          <button type="submit" class="btn btn-sm btn-primary">+ Adicionar país</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6 mb-3">
    <div class="card shadow-sm">
      <div class="card-header py-2"><strong>Nova Instituição</strong></div>
      <div class="card-body py-2">
        <form method="POST">
          <input type="hidden" name="acao" value="add_instituicao">
          <div class="form-group mb-2">
            <select name="id_pais" class="form-control form-control-sm" required>
              <option value="">— País —</option>
              <?php foreach ($paises as $p): ?>
              <option value="<?= (int)$p['id_pais'] ?>"><?= htmlspecialchars($p['nome_pais']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-2">
            <input type="text" name="nome_instituicao" class="form-control form-control-sm"
                   placeholder="Ex: Universidade de Lund" required>
          </div>
          <button type="submit" class="btn btn-sm btn-primary">+ Adicionar instituição</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6 mb-3">
    <div class="card shadow-sm">
      <div class="card-header py-2"><strong>Nova UC FEUP</strong></div>
      <div class="card-body py-2">
        <form method="POST">
          <input type="hidden" name="acao" value="add_cadeira_feup">
          <div class="form-group mb-2">
            <input type="text" name="nome_cadeira_feup" class="form-control form-control-sm"
                   placeholder="Nome da UC" required>
          </div>
          <div class="form-row mb-2">
            <div class="col">
              <input type="text" name="codigo" class="form-control form-control-sm"
                     placeholder="Código (ex: M.EQ009)" required>
            </div>
            <div class="col">
              <input type="number" step="0.5" name="ects_feup" class="form-control form-control-sm"
                     placeholder="ECTS" required>
            </div>
          </div>
          <button type="submit" class="btn btn-sm btn-primary">+ Adicionar UC FEUP</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6 mb-3">
    <div class="card shadow-sm">
      <div class="card-header py-2"><strong>Nova Cadeira Estrangeira</strong></div>
      <div class="card-body py-2">
        <form method="POST">
          <input type="hidden" name="acao" value="add_cadeira_estrangeira">
          <div class="form-group mb-2">
            <select name="id_instituicao_est" class="form-control form-control-sm" required>
              <option value="">— Universidade —</option>
              <?php foreach ($instituicoes as $i): ?>
              <option value="<?= (int)$i['id_instituicao'] ?>"><?= htmlspecialchars($i['nome_instituicao']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-2">
            <input type="text" name="nome_cadeira_estrangeira" class="form-control form-control-sm"
                   placeholder="Nome da disciplina" required>
          </div>
          <div class="form-row mb-2">
            <div class="col">
              <input type="number" step="0.1" name="ects_estrangeira" class="form-control form-control-sm"
                     placeholder="ECTS" required>
            </div>
            <div class="col">
              <input type="url" name="link_url" class="form-control form-control-sm"
                     placeholder="URL programa (opcional)">
            </div>
          </div>
          <button type="submit" class="btn btn-sm btn-primary">+ Adicionar cadeira estrangeira</button>
        </form>
      </div>
    </div>
  </div>

</div>

<div class="card shadow-sm border-primary mb-4">
  <div class="card-header py-2 d-flex align-items-center" style="gap:8px">
    <i class="fas fa-graduation-cap text-primary"></i>
    <strong>Parte 2 — Novo Plano de Estudos</strong>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="acao" value="salvar_plano_mestre">
      <div class="form-row mb-3">
        <div class="col-md-6">
          <label class="small font-weight-bold">Universidade de Destino</label>
          <select name="plano_id_instituicao" class="form-control form-control-sm" required>
            <option value="">— Seleccione —</option>
            <?php foreach ($instituicoes as $i): ?>
            <option value="<?= (int)$i['id_instituicao'] ?>"><?= htmlspecialchars($i['nome_instituicao']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="small font-weight-bold">Ano Lectivo</label>
          <select name="plano_ano_letivo" class="form-control form-control-sm" required>
            <option value="2026/2027">2026/2027</option>
            <option value="2025/2026">2025/2026</option>
            <option value="2024/2025">2024/2025</option>
            <option value="2023/2024">2023/2024</option>
            <option value="Não especificado">Não especificado</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="small font-weight-bold">Nº Mecanográfico do Aluno</label>
          <input type="number" name="plano_aluno" class="form-control form-control-sm"
                 placeholder="Ex: 202306325" required>
        </div>
      </div>
      <div class="form-row mb-3">
        <div class="col-md-6">
          <label class="small font-weight-bold">UC da FEUP (equivalência concedida)</label>
          <select name="plano_id_cadeira_feup" class="form-control form-control-sm" required>
            <option value="">— Seleccione a UC —</option>
            <?php foreach ($cadeiras_feup as $cf): ?>
            <option value="<?= (int)$cf['id_cadeira_feup'] ?>">
              <?= htmlspecialchars($cf['nome_cadeira_feup']) ?>
              (<?= htmlspecialchars($cf['codigo'] ?? '') ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="small font-weight-bold">Cadeiras estrangeiras que compõem a equivalência</label>
          <div style="max-height:180px;overflow-y:auto;border:1px solid #ced4da;border-radius:.25rem;padding:6px;background:#fff;font-size:.82rem">
            <?php foreach ($cadeiras_est as $ce): ?>
            <div class="form-check py-1" style="border-bottom:1px solid #f0f0f0">
              <input class="form-check-input" type="checkbox"
                     name="plano_cadeiras_estrangeiras[]"
                     value="<?= (int)$ce['id_cadeira_estrangeira'] ?>"
                     id="ce_<?= (int)$ce['id_cadeira_estrangeira'] ?>">
              <label class="form-check-label" for="ce_<?= (int)$ce['id_cadeira_estrangeira'] ?>">
                <span class="text-muted">[<?= htmlspecialchars($ce['nome_instituicao']) ?>]</span>
                <strong><?= htmlspecialchars($ce['nome_cadeira_estrangeira']) ?></strong>
                (<?= htmlspecialchars((string)($ce['ects_estrangeira'] ?? '0')) ?> ECTS)
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i>Guardar plano de estudos
      </button>
    </form>
  </div>
</div>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
