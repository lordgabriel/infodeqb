<?php
require $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
$pdo = Database::connect();

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acesso restrito');
}
$username = $_SESSION['user'];

require ROOT_DIR . '/infodeqb/inc/admins.php'; // define $isAdmin

// DELETE CONTACT (apenas admin - regra atual)
if (isset($_GET['delete']) && $isAdmin) {
    $stmt = $pdo->prepare("DELETE FROM infodeqb_company_contacts WHERE id=?");
    $stmt->execute([ $_GET['delete'] ]);
    header('Location: index.php');
    exit();
}

// FILTROS
$selectedPais = $_GET['pais'] ?? '';
$selectedEmpresa = $_GET['empresa'] ?? '';

// Lista de países (tratando NULL como 'Sem País')
$statsPais = $pdo->query("
    SELECT
        COALESCE(pais,'Sem País') AS pais,
        COUNT(DISTINCT empresa) AS c
    FROM infodeqb_company_contacts
    GROUP BY pais
    ORDER BY COALESCE(pais,'Sem País')
")->fetchAll(PDO::FETCH_ASSOC);

// Lista de empresas quando há país selecionado
$empresas = [];
if ($selectedPais) {
    if ($selectedPais === 'null') {
        $stmt = $pdo->prepare("
            SELECT empresa, COUNT(*) AS c
            FROM infodeqb_company_contacts
            WHERE pais IS NULL
            GROUP BY empresa
            ORDER BY empresa
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT empresa, COUNT(*) AS c
            FROM infodeqb_company_contacts
            WHERE pais = ?
            GROUP BY empresa
            ORDER BY empresa
        ");
        $stmt->execute([ $selectedPais ]);
    }
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lista de contactos (empresa selecionada; com ou sem país)
$contacts = [];
if ($selectedEmpresa) {
    if ($selectedPais) {
        // Filtrado por país + empresa
        if ($selectedPais === 'null') {
            $stmt = $pdo->prepare("
                SELECT *
                FROM infodeqb_company_contacts
                WHERE pais IS NULL AND empresa = ?
                ORDER BY nome
            ");
            $stmt->execute([ $selectedEmpresa ]);
        } else {
            $stmt = $pdo->prepare("
                SELECT *
                FROM infodeqb_company_contacts
                WHERE pais = ? AND empresa = ?
                ORDER BY nome
            ");
            $stmt->execute([ $selectedPais, $selectedEmpresa ]);
        }
    } else {
        // Só empresa -> trazer todos os contactos da empresa em todos os países
        $stmt = $pdo->prepare("
            SELECT *
            FROM infodeqb_company_contacts
            WHERE empresa = ?
            ORDER BY COALESCE(pais, 'Sem País'), nome
        ");
        $stmt->execute([ $selectedEmpresa ]);
    }
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Estatísticas
$totalPaises = $pdo->query("
    SELECT COUNT(DISTINCT COALESCE(pais,'Sem País'))
    FROM infodeqb_company_contacts
")->fetchColumn();

$totalEmpresas = $pdo->query("
    SELECT COUNT(DISTINCT empresa)
    FROM infodeqb_company_contacts
")->fetchColumn();

// Gráfico: nº de empresas por país (só página inicial)
$chartPais = [];
if (!$selectedPais && !$selectedEmpresa) {
    $chartPais = $pdo->query("
        SELECT
            COALESCE(pais,'Sem País') AS pais,
            COUNT(DISTINCT empresa) AS c
        FROM infodeqb_company_contacts
        GROUP BY pais
        ORDER BY COALESCE(pais,'Sem País')
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Lista de todas as empresas (independente do país) para página inicial
$allEmpresas = [];
if (!$selectedPais && !$selectedEmpresa) {
    $stmt = $pdo->query("
        SELECT
            empresa,
            COUNT(*) AS c
        FROM infodeqb_company_contacts
        WHERE empresa IS NOT NULL AND TRIM(empresa) <> ''
        GROUP BY empresa
        ORDER BY empresa
    ");
    $allEmpresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Contactos DIE';
include ROOT_DIR.'/infodeqb/inc/header.php';
?>

                <!-- Breadcrumbs -->
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><h5 class="mb-0">Eng. Química - Mobilidade OUT</h5></li>
                </ol>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-table"></i> Contactos Empresariais
                        </span>

                        <span>
                            <!-- Botão Adicionar (apenas quando NÃO estamos dentro de uma empresa) -->
                            <?php if (!$selectedEmpresa): ?>
                                <a href="edit_contact.php" class="btn btn-success text-white">
                                    Adicionar Contacto
                                </a>
                            <?php endif; ?>

                            <!-- Botão Exportar (só admin; continua sempre visível, mesmo dentro da empresa) -->
                            <?php if ($isAdmin): ?>
                                <a href="export_contacts.php" class="btn btn-info text-white ml-2">
                                    Exportar Contactos
                                </a>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="card-body">

                        <!-- Gráfico só na página inicial -->
                        <?php if(!$selectedPais && !$selectedEmpresa && $chartPais): ?>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <canvas id="paisChart"></canvas>
                            </div>
                        </div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            new Chart(document.getElementById('paisChart'), {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode(array_column($chartPais, 'pais')) ?>,
                                    datasets: [{
                                        label: 'Número de Empresas',
                                        data: <?= json_encode(array_column($chartPais, 'c')) ?>,
                                        backgroundColor: 'rgba(54, 162, 235, 0.7)'
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: { legend: { display: false } },
                                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                                }
                            });
                        });
                        </script>
                        <?php endif; ?>

                        <!-- PÁGINA INICIAL: duas colunas lado a lado -->
                        <?php if(!$selectedPais && !$selectedEmpresa): ?>
  <div class="row">

    <!-- PRIMEIRO: Todas as Empresas -->
    <div class="col-md-6">
      <h5>Empresas
    <span class="badge badge-success p-2 ml-2" style="font-size:1rem;">
         <?=$totalEmpresas?>
    </span>
      </h5>
      <div class="row">
        <?php foreach($allEmpresas as $e): ?>
          <div class="col-12 col-sm-6 col-lg-4 company-tile">
            <a href="?empresa=<?= urlencode($e['empresa']) ?>"
               title="<?= htmlspecialchars($e['empresa']) ?>">
              <?= htmlspecialchars($e['empresa']) ?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- SEGUNDO: Lista de Países -->
    <div class="col-md-6">
      <h5>Países    <span class="badge badge-success p-2 ml-2" style="font-size:1rem;">
         <?=$totalPaises?>
    </span></h5>
<div class="row">
  <?php foreach($statsPais as $p): ?>
    <div class="col-12 col-sm-6 col-lg-4 company-tile">
      <a href="?pais=<?= ($p['pais']=='Sem País' ? 'null' : urlencode($p['pais'])) ?>"
         title="<?= htmlspecialchars($p['pais']) ?>">

         <?= htmlspecialchars($p['pais']) ?>

         <span class="badge badge-primary ml-2">
             <?= (int)$p['c'] ?> empresas
         </span>

      </a>
    </div>
  <?php endforeach; ?>
</div>
    </div>

  </div>
<?php endif; ?>

                        <!-- LISTA DE EMPRESAS (quando há país selecionado) -->
                        <?php if($selectedPais && !$selectedEmpresa): ?>
                          <h5>Empresas em <?= ($selectedPais=='null' ? 'Sem País' : htmlspecialchars($selectedPais)) ?>:</h5>
                          <ul class="list-group mb-3">
                            <?php foreach($empresas as $e): ?>
                              <li class="list-group-item">
                                <div class="d-flex align-items-center">
                                  <div><?= htmlspecialchars($e['empresa']) ?>                                        <span class="badge badge-primary badge-pill ml-2">
                                            <?= (int)$e['c'] ?>
                                        </span></div>
                                  <a
                                    href="?pais=<?= urlencode($selectedPais) ?>&empresa=<?= urlencode($e['empresa']) ?>"
                                    class="btn btn-sm btn-primary text-white ml-auto">
                                    Ver Contactos
                                  </a>
                                </div>
                              </li>
                            <?php endforeach; ?>
                          </ul>
                          <a href="index.php" class="btn btn-secondary text-white mb-3">← Limpar filtros</a>
                        <?php endif; ?>

                        <!-- TABELA DE CONTACTOS (empresa selecionada; com ou sem país) -->
                        <?php if($selectedEmpresa): ?>
                          <div class="d-flex justify-content-between mb-2">
                            <h5 class="mb-0">
                              Contactos da <?= htmlspecialchars($selectedEmpresa) ?>
                              <?php if($selectedPais): ?>
                                (<?= ($selectedPais=='null' ? 'Sem País' : htmlspecialchars($selectedPais)) ?>)
                              <?php else: ?>
                                (todos os países)
                              <?php endif; ?>:
                            </h5>

                            <a href="edit_contact.php<?= $selectedPais
                                  ? ('?pais=' . urlencode($selectedPais) . '&empresa=' . urlencode($selectedEmpresa))
                                  : ('?empresa=' . urlencode($selectedEmpresa)) ?>"
                               class="btn btn-success text-white">
                               Adicionar Contacto
                            </a>
                          </div>

                          <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle">
                              <thead class="table-dark">
                                <tr>
                                  <th>Nome</th>
                                  <th>Email</th>
                                  <th>País</th>
                                  <th>Contacto FEUP</th>
                                  <th>Ações</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach($contacts as $c): ?>
                                  <tr>
                                    <td><?= htmlspecialchars($c['nome']) ?></td>
                                    <td><?= htmlspecialchars($c['email']) ?></td>
                                    <td><?= htmlspecialchars($c['pais'] ?? 'Sem País') ?></td>
                                    <td><?= htmlspecialchars($c['contacto_feup']) ?></td>
                                    <td>
                                      <?php if($isAdmin || (isset($c['criado_por']) && $c['criado_por'] === $username)): ?>
                                        <a href="edit_contact.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary">✏️</a>
                                        <a href="?<?= $selectedPais
                                                ? ('pais=' . urlencode($selectedPais) . '&empresa=' . urlencode($selectedEmpresa))
                                                : ('empresa=' . urlencode($selectedEmpresa)) ?>&delete=<?= (int)$c['id'] ?>"
                                           onclick="return confirm('Apagar contacto?')"
                                           class="btn btn-sm btn-outline-danger">🗑️</a>
                                      <?php endif; ?>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>

                          <?php if($selectedPais): ?>
                            <a href="?pais=<?= urlencode($selectedPais) ?>" class="btn btn-secondary text-white mt-2">← Voltar às empresas</a>
                          <?php else: ?>
                            <a href="index.php" class="btn btn-secondary text-white mt-2">← Voltar à página inicial</a>
                          <?php endif; ?>
                        <?php endif; ?>

                    </div>
                </div>

<style>
  .company-tile {
    margin-bottom: .5rem;
  }
  .company-tile a {
    display: block;
    padding: .5rem .75rem;
    border: 1px solid rgba(0,0,0,.125);
    border-radius: .25rem;
    background: #fff;
    color: #212529;
    transition: background .15s ease-in-out;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .company-tile a:hover {
    text-decoration: none;
    background: #f8f9fa;
  }
</style>

<?php include ROOT_DIR.'/infodeqb/inc/footer.php'; ?>
