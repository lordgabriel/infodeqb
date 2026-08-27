<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$pageTitle  = 'Administração';
$activePage = 'admin';
$db = getDB();
$al = getAnoLetivoAtivo();

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cfg = [
        'public_reports' => isset($_POST['public_reports']),
        'admin_only'     => isset($_POST['admin_only']),
    ];
    file_put_contents(__DIR__ . '/../config.json', json_encode($cfg, JSON_PRETTY_PRINT));
    flash('Configurações guardadas.');
    header('Location: admin.php'); exit;
}

$cfg = [
    'public_reports' => getConfig('public_reports', false),
    'admin_only'     => getConfig('admin_only', false),
];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title"><i class="fas fa-cog me-1"></i>Administração</div>
    <div class="page-sub">Gestão do sistema DSD</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">

  <!-- Docentes -->
  <div class="card">
    <div class="card-title"><i class="fas fa-chalkboard-teacher me-1"></i>Docentes</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <a href="<?= BASE_URL ?>/pages/docentes.php" class="btn btn-secondary">Lista de Docentes</a>
      <a href="<?= BASE_URL ?>/pages/docente-form.php" class="btn btn-secondary">+ Adicionar Docente</a>
      <hr style="margin:4px 0;border-color:var(--gray-200)">
      <a href="<?= BASE_URL ?>/pages/carreiras.php" class="btn btn-secondary"><i class="fas fa-graduation-cap me-1"></i>Carreiras</a>
      <a href="<?= BASE_URL ?>/pages/categorias.php" class="btn btn-secondary"><i class="fas fa-tags me-1"></i>Categorias</a>
      <a href="<?= BASE_URL ?>/pages/departamentos.php" class="btn btn-secondary"><i class="fas fa-building me-1"></i>Departamentos</a>
    </div>
  </div>

  <!-- UCs -->
  <div class="card">
    <div class="card-title"><i class="fas fa-book me-1"></i>Unidades Curriculares</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <a href="<?= BASE_URL ?>/pages/ucs.php" class="btn btn-secondary">Catálogo de UCs</a>
      <a href="<?= BASE_URL ?>/pages/uc-form.php" class="btn btn-secondary">+ Adicionar UC</a>
      <a href="<?= BASE_URL ?>/pages/ocorrencias.php" class="btn btn-secondary">Ocorrências (ano activo)</a>
      <a href="<?= BASE_URL ?>/pages/planos.php" class="btn btn-secondary">Planos de Estudo</a>
      <a href="<?= BASE_URL ?>/pages/areas.php" class="btn btn-secondary">Áreas Científicas</a>
    </div>
  </div>

  <!-- Anos letivos -->
  <div class="card">
    <div class="card-title"><i class="fas fa-calendar-alt me-1"></i>Anos Letivos</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <a href="<?= BASE_URL ?>/pages/anos-letivos.php" class="btn btn-secondary">Gerir Anos Letivos</a>
    </div>
  </div>

  <!-- Dados -->
  <div class="card">
    <div class="card-title"><i class="fas fa-save me-1"></i>Dados</div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <a href="<?= BASE_URL ?>/pages/import-csv.php" class="btn btn-secondary"><i class="fas fa-file-upload me-1"></i>Importar CSV</a>
      <a href="<?= BASE_URL ?>/pages/export.php"     class="btn btn-secondary"><i class="fas fa-file-export me-1"></i>Exportar / Backup</a>
    </div>
  </div>

  <!-- Relatórios Admin -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-title"><i class="fas fa-chart-line me-1"></i>Relatórios</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px">
      <a href="<?= BASE_URL ?>/reports/por-docente.php" class="btn btn-secondary"><i class="fas fa-chalkboard-teacher me-1"></i>Por Docente</a>
      <a href="<?= BASE_URL ?>/reports/por-ciclo.php" class="btn btn-secondary"><i class="fas fa-book me-1"></i>Por Ciclo de Estudos</a>
      <a href="<?= BASE_URL ?>/reports/resumo.php" class="btn btn-secondary"><i class="fas fa-chart-bar me-1"></i>Resumo de Horas</a>
      <a href="<?= BASE_URL ?>/reports/tabela1.php" class="btn btn-secondary"><i class="fas fa-clipboard-list me-1"></i>Tabela 1 – DSD</a>
      <a href="<?= BASE_URL ?>/reports/por-area.php" class="btn btn-secondary"><i class="fas fa-flask me-1"></i>Por Área Científica</a>
      <a href="<?= BASE_URL ?>/reports/horas-em-falta.php" class="btn btn-secondary"><i class="fas fa-exclamation-triangle me-1"></i>Horas em Falta</a>
      <a href="<?= BASE_URL ?>/reports/graficos.php" class="btn btn-secondary"><i class="fas fa-chart-line me-1"></i>Gráficos</a>
      <a href="<?= BASE_URL ?>/reports/por-carreira.php" class="btn btn-secondary"><i class="fas fa-chart-bar me-1"></i>Por Carreira e Categoria</a>
    </div>
  </div>

  <!-- Acesso -->
  <div class="card" style="grid-column:1/-1">
    <div class="card-title"><i class="fas fa-lock me-1"></i>Controlo de Acesso</div>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px">
      Autenticação completa será implementada numa versão futura.
      Por agora podes controlar o acesso aos relatórios e páginas de gestão.
    </p>
    <form method="post">
      <div style="display:flex;flex-direction:column;gap:12px;max-width:500px">
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px;border:1px solid var(--gray-200);border-radius:8px">
          <input type="checkbox" name="public_reports" <?= $cfg['public_reports'] ? 'checked' : '' ?>
                 style="margin-top:2px;flex-shrink:0">
          <div>
            <div style="font-weight:600">Relatórios públicos</div>
            <div style="font-size:12px;color:var(--gray-500)">
              Qualquer pessoa com acesso ao URL pode ver os relatórios (Por Docente, Por Ciclo, Resumo, etc.)
              sem necessidade de autenticação. Útil para partilhar com o departamento.
            </div>
          </div>
        </label>
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;padding:12px;border:1px solid var(--gray-200);border-radius:8px;opacity:.6" title="Em desenvolvimento">
          <input type="checkbox" name="admin_only" <?= $cfg['admin_only'] ? 'checked' : '' ?> disabled>
          <div>
            <div style="font-weight:600">Gestão restrita <span style="font-size:11px;color:var(--orange)">(requer autenticação — em desenvolvimento)</span></div>
            <div style="font-size:12px;color:var(--gray-500)">
              Páginas de gestão (docentes, UCs, distribuição) só acessíveis após login com credenciais de administrador.
            </div>
          </div>
        </label>
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar</button>
      </div>
    </form>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
