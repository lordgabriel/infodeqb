<?php
/**
 * Página pública de validação de acesso por responsável de espaço.
 * Acessível sem login através de um link com token único.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
require_once ROOT_DIR . '/infodeqb/hr/common.php';
require_once ROOT_DIR . '/infodeqb/hr/inc/functions.php';
date_default_timezone_set('Europe/Lisbon');

$pdo = Database::connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$token = trim($_GET['token'] ?? '');
$erro  = '';
$ok    = '';
$val   = null;

if (!$token) {
    $erro = 'Link inválido — token em falta.';
} else {
    $q = $pdo->prepare(
        "SELECT v.*,
                COALESCE(c1.nome, c2.nome)             AS colab_nome,
                COALESCE(r1.datainicio, r2.datainicio) AS datainicio,
                COALESCE(r1.datafim,    r2.datafim)    AS datafim
         FROM infodeqb_rds_validacao v
         LEFT JOIN infodeqb_rds_registo     r1 ON r1.autoid = v.registo_id
         LEFT JOIN infodeqb_rds_pedido      p  ON p.id       = v.pedido_id
         LEFT JOIN infodeqb_rds_registo     r2 ON r2.autoid  = p.registo_id
         LEFT JOIN infodeqb_rds_colaborador c1 ON c1.codigo  = r1.codigo
         LEFT JOIN infodeqb_rds_colaborador c2 ON c2.codigo  = p.codigo
         WHERE v.token = ?"
    );
    $q->execute([$token]);
    $val = $q->fetch(PDO::FETCH_ASSOC);

    if (!$val) {
        $erro = 'Link não encontrado ou inválido.';
    } elseif ($val['status'] !== 'Pendente') {
        $ok = ($val['status'] === 'Validado')
            ? 'Este acesso já foi <strong>validado</strong>. Obrigado pela sua resposta.'
            : 'Este pedido foi <strong>rejeitado</strong>. Obrigado pela sua resposta.';
    } elseif (!empty($val['expira_em']) && $val['expira_em'] < date('Y-m-d H:i:s')) {
        $erro = 'Este link expirou (validade de 30 dias). Por favor contacte o secretariado para um novo pedido de validação.';
    } elseif (!empty($_POST['resposta'])) {
        $resposta = $_POST['resposta'];
        if (!in_array($resposta, array('Validado', 'Rejeitado'))) {
            $erro = 'Resposta inválida.';
        } else {
            $nota = trim($_POST['nota'] ?? '');
            if ($resposta === 'Rejeitado' && $nota === '') {
                $erro = 'Por favor indique o motivo da rejeição.';
            } else {
                $pdo->prepare(
                    "UPDATE infodeqb_rds_validacao SET status=?, nota=?, respondido_em=NOW() WHERE id=?"
                )->execute([$resposta, $nota ?: null, $val['id']]);

                if ($resposta === 'Rejeitado') {
                    $val['nota'] = $nota;
                    _notificarRejeicaoValidacao($val, $val['colab_nome'] ?? '—');
                }

                $ok = ($resposta === 'Validado')
                    ? 'Acesso <strong>validado</strong> com sucesso. Obrigado!'
                    : 'Rejeição registada. O secretariado foi notificado. Obrigado pela sua resposta.';
            }
        }
    }
}

Database::disconnect();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Validação de Acesso — DEQ</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    body { background:#f4f6f9; }
    .val-card {
      max-width:580px; margin:60px auto; background:#fff;
      border-radius:6px; box-shadow:0 2px 12px rgba(0,0,0,.1); overflow:hidden;
    }
    .val-header {
      background:#2475ba; color:#fff; padding:24px 30px;
    }
    .val-header h1 { font-size:1.25rem; margin:0 0 4px 0; }
    .val-header p  { margin:0; font-size:.9rem; opacity:.85; }
    .val-body { padding:30px; }
    .val-footer {
      background:#18163a; color:#ccc; font-size:.8rem;
      padding:14px 30px; text-align:center;
    }
    .info-table td { padding:7px 12px; font-size:.95rem; }
    .info-table td:first-child { font-weight:600; color:#2475ba; width:40%; }
    .info-table tr:nth-child(odd) { background:#f3f8fe; }
    .btn-validar  { background:#27ae60; border-color:#27ae60; color:#fff; font-weight:bold; }
    .btn-validar:hover { background:#219a52; border-color:#219a52; color:#fff; }
    .btn-rejeitar { background:#e74c3c; border-color:#e74c3c; color:#fff; font-weight:bold; }
    .btn-rejeitar:hover { background:#c0392b; border-color:#c0392b; color:#fff; }
  </style>
</head>
<body>
<div class="val-card">
  <div class="val-header">
    <h1>Validação de Acesso a Espaço</h1>
    <p>Departamento de Engenharia Química e Biológica — FEUP</p>
  </div>
  <div class="val-body">

    <?php if ($erro): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= $erro ?>
      </div>

    <?php elseif ($ok): ?>
      <div class="alert alert-success text-center py-4">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#27ae60"
             viewBox="0 0 16 16" style="display:block;margin:0 auto 12px auto;">
          <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384
                   7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0
                   0-.01-1.05z"/>
        </svg>
        <?= $ok ?>
      </div>

    <?php elseif ($val): ?>
      <p class="mb-3" style="font-size:.95rem;">
        Foi submetido um pedido de acesso ao espaço sob a sua responsabilidade.
        Por favor reveja os detalhes e <strong>valide ou rejeite</strong> o pedido.
      </p>

      <table class="info-table table table-sm table-bordered mb-4">
        <tbody>
          <tr>
            <td>Colaborador</td>
            <td><?= htmlspecialchars($val['colab_nome'] ?? '—') ?></td>
          </tr>
          <tr>
            <td>Espaço(s) solicitado(s)</td>
            <td><?php
              $labsArr = !empty($val['labs_json']) ? json_decode($val['labs_json'], true) : null;
              if ($labsArr && count($labsArr) > 1):
            ?><ul class="mb-0 ps-3">
              <?php foreach ($labsArr as $lb): ?>
                <li><?= htmlspecialchars($lb['gab_nome']) ?></li>
              <?php endforeach; ?>
            </ul><?php
              else:
                echo htmlspecialchars($val['gab_nome'] ?? '—');
              endif;
            ?></td>
          </tr>
          <tr>
            <td>Período de acesso</td>
            <td>
              <?= htmlspecialchars($val['datainicio'] ?? '—') ?>
              &nbsp;—&nbsp;
              <?= htmlspecialchars($val['datafim'] ?? '—') ?>
            </td>
          </tr>
          <tr>
            <td>Link válido até</td>
            <td><?= $val['expira_em'] ? htmlspecialchars(substr($val['expira_em'],0,10)) : 'Sem expiração' ?></td>
          </tr>
        </tbody>
      </table>

      <?php if ($erro): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <!-- Formulário de resposta -->
      <form method="post" id="formValidacao">
        <input type="hidden" name="resposta" id="inputResposta" value="">

        <div class="form-group" id="grpNota" style="display:none;">
          <label for="nota"><strong>Motivo da rejeição</strong> <span class="text-danger">*</span></label>
          <textarea class="form-control" id="nota" name="nota" rows="3"
                    placeholder="Indique o motivo pelo qual não pode autorizar este acesso..."></textarea>
        </div>

        <div class="d-flex" style="gap:.5rem;flex-wrap:wrap;">
          <button type="button" class="btn btn-validar btn-lg flex-fill"
                  onclick="submeter('Validado')">
            ✔ Validar acesso
          </button>
          <button type="button" class="btn btn-rejeitar btn-lg flex-fill"
                  onclick="mostrarRejeicao()">
            ✖ Rejeitar acesso
          </button>
        </div>

        <div id="grpConfirmRejeicao" style="display:none;margin-top:12px;">
          <button type="button" class="btn btn-danger btn-block"
                  onclick="submeter('Rejeitado')">
            Confirmar rejeição
          </button>
          <button type="button" class="btn btn-link btn-block text-muted"
                  onclick="cancelarRejeicao()">Cancelar</button>
        </div>
      </form>

    <?php endif; ?>

  </div>
  <div class="val-footer">
    Secretariado da Direção &nbsp;|&nbsp; Departamento de Engenharia Química e Biológica &nbsp;|&nbsp;
    deqbdir@fe.up.pt &nbsp;|&nbsp; +351 225 084 520
  </div>
</div>

<script>
function mostrarRejeicao() {
  document.getElementById('grpNota').style.display = 'block';
  document.getElementById('grpConfirmRejeicao').style.display = 'block';
}
function cancelarRejeicao() {
  document.getElementById('grpNota').style.display = 'none';
  document.getElementById('grpConfirmRejeicao').style.display = 'none';
  document.getElementById('nota').value = '';
}
function submeter(resposta) {
  document.getElementById('inputResposta').value = resposta;
  document.getElementById('formValidacao').submit();
}
</script>
</body>
</html>
