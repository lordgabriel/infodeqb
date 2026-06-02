<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

date_default_timezone_set('Europe/Lisbon');

$language = (substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) === 'pt') ? 'pt' : 'en';
include ROOT_DIR . '/infodeqb/hr/lang/lang.' . $language . '.php';

require_once ROOT_DIR . '/infodeqb/inc/admins.php';
if (!($isAdmin || in_array($_iqCurrentUser, $_iqAdminsHr))) {
    header('Location: ' . HTTP_DIR . '/infodeqb/denied.php');
    exit;
}

$pdo = Database::connect();
$sth = $pdo->prepare(
    'SELECT c.codigo, c.nome, c.email, c.telefone,
            r.unidade, r.extensao, r.local_trabalho
     FROM infodeqb_rds_colaborador c
     INNER JOIN infodeqb_rds_registo r ON c.codigo = r.codigo
     INNER JOIN infodeqb_rds_grupo   g ON r.grupo  = g.grupoid
     WHERE c.deleted != 1 AND r.status = ?
     ORDER BY c.nome ASC'
);
$sth->execute(['Ativo']);
$rows = $sth->fetchAll(PDO::FETCH_ASSOC);
Database::disconnect();

// Letras com resultados (para desactivar as restantes)
$letrasComResultados = array();
foreach ($rows as $r) {
    $l = mb_strtoupper(mb_substr(trim($r['nome']), 0, 1));
    $letrasComResultados[$l] = true;
}

$pageTitle = 'Lista de Pessoal';
include ROOT_DIR . '/infodeqb/inc/header.php';
?>

<style>
/* ── Índice alfabético ─────────────────────────── */
.alpha-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    align-items: center;
    background: #f8f9fa;
    border: 1px solid #e3e6ea;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 1.2rem;
}
.alpha-bar .alpha-all {
    font-size: .75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 5px;
    border: 1px solid #adb5bd;
    color: #495057;
    background: #fff;
    cursor: pointer;
    text-decoration: none;
    margin-right: 6px;
    transition: background .12s, color .12s;
    white-space: nowrap;
}
.alpha-bar .alpha-letter {
    font-size: .78rem;
    font-weight: 600;
    width: 28px;
    height: 28px;
    line-height: 28px;
    text-align: center;
    border-radius: 5px;
    border: 1px solid #dee2e6;
    color: #495057;
    background: #fff;
    cursor: pointer;
    text-decoration: none;
    transition: background .12s, color .12s, border-color .12s;
}
.alpha-bar .alpha-letter.disabled {
    color: #ced4da;
    border-color: #f0f0f0;
    background: #fafafa;
    pointer-events: none;
    cursor: default;
}
.alpha-bar .alpha-letter.active,
.alpha-bar .alpha-all.active {
    background: #0d6efd;
    border-color: #0d6efd;
    color: #fff;
}
.alpha-bar .alpha-letter:not(.disabled):hover,
.alpha-bar .alpha-all:hover {
    background: #e9f0ff;
    border-color: #0d6efd;
    color: #0d6efd;
    text-decoration: none;
}
.alpha-bar .alpha-all.active:hover,
.alpha-bar .alpha-letter.active:hover {
    background: #0b5ed7;
    color: #fff;
}

/* ── Avatar iniciais ───────────────────────────── */
.iq-avatar-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e9ecef;
    color: #495057;
    font-size: .68rem;
    font-weight: 700;
    flex-shrink: 0;
    vertical-align: middle;
    margin-right: 7px;
}

/* ── Badge unidade ─────────────────────────────── */
.badge-unidade {
    font-size: .72rem;
    font-weight: 500;
    padding: 2px 7px;
    border-radius: 10px;
    background: #e8f0fe;
    color: #3b5bdb;
    white-space: nowrap;
}

/* ── Toolbar (pesquisa + contador) ─────────────── */
#tblLista_filter label { margin-bottom: 0; font-size: .85rem; }
#tblLista_filter input { font-size: .85rem; padding: 3px 8px; height: auto; }
#tblLista_info { font-size: .82rem; color: #6c757d; }
</style>

<div class="iq-page-header d-flex align-items-center">
  <div class="mr-auto">
    <h1><i class="fas fa-users fa-sm me-2 text-muted"></i>Pessoal ativo DEQB</h1>
    <small class="text-muted" style="font-size:.85rem">
      <?= count($rows) ?> colaboradores / investigadores
    </small>
  </div>
</div>

<!-- Índice alfabético -->
<div class="alpha-bar" id="alpha-index">
  <a href="#" class="alpha-all active" data-letter=""><?= t('ALL') ?></a>
  <?php foreach (range('A','Z') as $l):
    $temResultados = isset($letrasComResultados[$l]);
  ?>
  <a href="#" class="alpha-letter <?= $temResultados ? '' : 'disabled' ?>"
     data-letter="<?= $l ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<!-- Tabela -->
<div class="card mb-4 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-sm table-hover mb-0" id="tblLista">
      <thead class="">
        <tr>
          <th style="width:0"><?= t('CODE') ?></th>
          <th><?= t('NAME') ?></th>
          <th style="width:9em">Telefone</th>
          <th>Email</th>
          <th style="width:7em">Extensão</th>
          <th>Local de trabalho</th>
          <th style="width:9em">Unidade I&amp;D</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row):
        $link = (strlen($row['codigo']) > 6)
            ? 'https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico='
            : 'https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=';
        // Iniciais para avatar
        $parts    = preg_split('/\s+/', trim($row['nome']));
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
      ?>
        <tr>
          <td><?= htmlspecialchars($row['codigo']) ?></td>
          <td class="align-middle">
            <span class="iq-avatar-sm"><?= htmlspecialchars($initials) ?></span>
            <?= htmlspecialchars($row['nome']) ?>
            <a href="<?= $link . htmlspecialchars($row['codigo']) ?>"
               target="_blank" title="Ver no SIGARRA" class="ml-1 text-muted">
              <i class="fas fa-external-link-alt fa-xs"></i>
            </a>
          </td>
          <td class="align-middle"><?= htmlspecialchars($row['telefone'] ?? '') ?></td>
          <td class="align-middle">
            <?php if ($row['email']): ?>
            <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="text-body">
              <?= htmlspecialchars($row['email']) ?>
            </a>
            <?php endif; ?>
          </td>
          <td class="align-middle text-center"><?= htmlspecialchars($row['extensao'] ?? '') ?></td>
          <td class="align-middle"><?= htmlspecialchars($row['local_trabalho'] ?? '') ?></td>
          <td class="align-middle">
            <?php if (!empty($row['unidade'])): ?>
            <span class="badge-unidade"><?= htmlspecialchars($row['unidade']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
$(document).ready(function () {
    var table = $('#tblLista').DataTable({
        paging: false,
        order: [[1, 'asc']],
        language: {
            search: '',
            searchPlaceholder: 'Pesquisar nome, email…',
            info: '_TOTAL_ registos',
            infoFiltered: '(de _MAX_)',
            infoEmpty: 'Sem registos',
            zeroRecords: 'Nenhum resultado',
        },
        dom: '<"d-flex align-items-center justify-content-between px-3 pt-3 pb-2"fi>t',
        columnDefs: [
            { targets: [0], visible: false, searchable: false }
        ]
    });

    // Índice alfabético — filtro customizado
    $.fn.dataTable.ext.search.push(function (settings, data) {
        if (settings.nTable.id !== 'tblLista') return true;
        var letter = $('#alpha-index').data('active') || '';
        if (!letter) return true;
        return mb_first(data[1]) === letter;
    });

    function mb_first(str) {
        // Extrai primeiro caracter visível (ignora tags HTML)
        var plain = str.replace(/<[^>]+>/g, '').trim();
        return plain.charAt(0).toUpperCase();
    }

    $(document).on('click', '.alpha-all, .alpha-letter:not(.disabled)', function (e) {
        e.preventDefault();
        var letter = $(this).data('letter');
        $('#alpha-index').data('active', letter);
        $('.alpha-all, .alpha-letter').removeClass('active');
        $(this).addClass('active');
        table.draw();
    });
});
</script>

<?php include ROOT_DIR . '/infodeqb/inc/footer.php'; ?>
