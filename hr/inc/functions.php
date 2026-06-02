<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require ROOT_DIR . '/infodeqb/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
require ROOT_DIR . '/infodeqb/vendor/PHPmailer/src/Exception.php';
require ROOT_DIR . '/infodeqb/vendor/PHPmailer/src/PHPMailer.php';
require ROOT_DIR . '/infodeqb/vendor/PHPmailer/src/SMTP.php';

function startsWith ($haystack, $needle)
{
    $length = strlen($needle);
    return substr($haystack, 0, $length) === $needle;
}

function endsWith ($haystack, $needle)
{
    $length = strlen($needle);
    if (! $length) {
        return true;
    }
    return substr($haystack, - $length) === $needle;
}

// ── Helpers de acessos a laboratórios ────────────────────────────

/**
 * Retorna array de lab_ids associados a um registo.
 * Ex: ['E-101', 'E-102', 'E-147']
 */
function getRegistoAcessos(PDO $pdo, int $registoId): array {
    $s = $pdo->prepare(
        'SELECT lab_id FROM infodeqb_rds_registo_acessos
         WHERE registo_id = ? ORDER BY lab_id ASC'
    );
    $s->execute([$registoId]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Substitui todos os acessos de um registo (DELETE + INSERT).
 * Mantém compatibilidade: actualiza também acessosid/acessos no registo.
 */
function setRegistoAcessos(PDO $pdo, int $registoId, array $labIds, array $gabMap = []): void {
    $labIds = array_values(array_unique(array_filter(array_map('trim', $labIds))));

    // Nova tabela relacional
    $pdo->prepare('DELETE FROM infodeqb_rds_registo_acessos WHERE registo_id = ?')
        ->execute([$registoId]);
    if (!empty($labIds)) {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO infodeqb_rds_registo_acessos (registo_id, lab_id) VALUES (?,?)'
        );
        foreach ($labIds as $labId) {
            $stmt->execute([$registoId, $labId]);
        }
    }

    // Compatibilidade: manter acessosid + acessos no registo (legado)
    $acessosid = implode('; ', $labIds);
    $nomes = [];
    foreach ($labIds as $id) {
        $nomes[] = isset($gabMap[$id]) ? $gabMap[$id] : $id;
    }
    $acessos = implode('; ', $nomes);
    $pdo->prepare(
        'UPDATE infodeqb_rds_registo SET acessosid = ?, acessos = ? WHERE autoid = ?'
    )->execute([$acessosid, $acessos, $registoId]);
}

/**
 * Devolve mapa deqid → nomegab de infodeqb_rds_gabinetes.
 */
function getGabMap(PDO $pdo): array {
    $rows = $pdo->query('SELECT deqid, nomegab FROM infodeqb_rds_gabinetes WHERE visible != 0')
                ->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $r) $map[$r['deqid']] = $r['nomegab'];
    return $map;
}

// ── Helpers de telefone com indicativo ────────────────────────────

/** Indicativos disponíveis */
function phoneIndicativos(): array {
    return [
        '+351'=>'🇵🇹 +351','+34'=>'🇪🇸 +34','+33'=>'🇫🇷 +33',
        '+49'=>'🇩🇪 +49','+39'=>'🇮🇹 +39','+44'=>'🇬🇧 +44',
        '+31'=>'🇳🇱 +31','+32'=>'🇧🇪 +32','+41'=>'🇨🇭 +41',
        '+43'=>'🇦🇹 +43','+46'=>'🇸🇪 +46','+47'=>'🇳🇴 +47',
        '+45'=>'🇩🇰 +45','+48'=>'🇵🇱 +48','+420'=>'🇨🇿 +420',
        '+55'=>'🇧🇷 +55','+244'=>'🇦🇴 +244','+258'=>'🇲🇿 +258',
        '+238'=>'🇨🇻 +238','+1'=>'🇺🇸 +1',
    ];
}

/** Separa "+351 912345678" em [indicativo, numero] */
function parsePhone(string $stored): array {
    if (preg_match('/^(\+\d{1,4})\s+(.+)$/', trim($stored), $m)) {
        return [$m[1], $m[2]];
    }
    return ['+351', preg_replace('/\D/', '', $stored)];
}

/** Combina indicativo + numero limpo → "+351 912345678" */
function combinePhone(string $indicativo, string $numero): string {
    $n = preg_replace('/[^0-9]/', '', $numero);
    return $n ? trim($indicativo) . ' ' . $n : '';
}

/** Combina a partir de POST (telefone_indicativo + telefone_numero) */
function phoneFromPost(): string {
    $ind = trim($_POST['telefone_indicativo'] ?? '+351');
    $num = trim($_POST['telefone_numero'] ?? $_POST['telefone'] ?? '');
    return combinePhone($ind, $num);
}

/** Renderiza o input-group de telefone */
function renderPhoneInput(string $stored = '', string $size = ''): void {
    list($ind, $num) = parsePhone($stored);
    $sz  = $size ? ' input-group-' . $size : '';
    $szS = $size ? ' custom-select-' . $size : '';
    $szI = $size ? ' form-control-' . $size : '';
    echo '<div class="input-group' . $sz . '">';
    echo '<div class="input-group-prepend">';
    echo '<select name="telefone_indicativo" class="custom-select' . $szS . '" style="border-radius:.25rem 0 0 .25rem;min-width:95px">';
    foreach (phoneIndicativos() as $code => $lbl) {
        $sel = ($ind === $code) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($code) . '"' . $sel . '>' . $lbl . '</option>';
    }
    echo '</select></div>';
    echo '<input type="tel" name="telefone_numero" class="form-control' . $szI . '" placeholder="912 345 678" value="' . htmlspecialchars($num) . '">';
    echo '</div>';
}

function cleanData (&$str)
{
    $str = mb_convert_encoding($str, 'UTF-16', 'UTF-8');
}

function ExportFile ($records)
{
    $heading = false;
    if (! empty($records))
        foreach ($records as $row) {
            if (! $heading) {
                // display field/column names as a first row
                echo implode("\t", array_keys($row)) . "\n";
                $heading = true;
            }
            array_walk($row, __NAMESPACE__ . '\cleanData');
            echo implode("\t", array_values($row)) . "\n";
        }
    exit();
}

function formatDate ($format, $dateStr)
{
    if (trim($dateStr) == '' || substr($dateStr, 0, 10) == '0000-00-00') {
        return '';
    }
    $ts = strtotime($dateStr);
    if ($ts === false) {
        return '';
    }
    return date($format, $ts);
}

function format_email ($info, $format)
{
    // print_r ($info);
    // grab the template content
    $template = file_get_contents(ROOT_DIR . '/infodeqb/hr/inc/' . $format);
   
    // replace all the tags

    foreach ($info as $key => $value) {

        $substring = '>{valor' . $key . '}>';
        $template = preg_replace($substring, $info[$key], $template);
    }
    return $template;
}

function send_email ($to, $body, $subject, $cc_list = [], $bcc_list = [], $file1 = "", $file2 = "")
{
    // ── Modo de teste (localhost OU MAIL_TEST_MODE=true em produção) ─────────
    $isLocalhost  = defined('HTTP_DIR') && strpos(HTTP_DIR, 'localhost') !== false;
    $isTestMode   = $isLocalhost || (defined('MAIL_TEST_MODE') && MAIL_TEST_MODE === true);
    if ($isTestMode) {
        // Em produção com MAIL_TEST_MODE, os destinatários são os definidos em MAIL_TEST_RECIPIENTS
        // Em localhost usamos os endereços de dev habituais
        $testRecipients = (defined('MAIL_TEST_RECIPIENTS') && !empty(MAIL_TEST_RECIPIENTS))
            ? MAIL_TEST_RECIPIENTS
            : [];
    }
    if ($isTestMode) {
        // Endereços de teste: em produção usa MAIL_TEST_RECIPIENTS, em localhost usa valores fixos
        $DEV_ADMIN = !empty($testRecipients) ? $testRecipients[0] : 'up356946@up.pt';
        $DEV_USER  = !empty($testRecipients) ? ($testRecipients[1] ?? $testRecipients[0]) : 'lfamartins@gmail.com';

        // Endereços "de sistema" — redirecionados para o admin de teste
        $adminAddrs = array('sigarra@fe.up.pt','deqdir@fe.up.pt','deqbdir@fe.up.pt','fmartins@fe.up.pt');
        $isAdminDest = false;
        foreach ((array)$to as $addr) {
            if (in_array(strtolower(trim($addr)), $adminAddrs)) { $isAdminDest = true; break; }
        }

        // Guardar corpo do email em ficheiro HTML visualizável
        $devDir = ROOT_DIR . '/infodeqb/hr/email_dev';
        if (!is_dir($devDir)) { mkdir($devDir, 0755, true); }
        $ts       = date('Ymd_His');
        $slug     = preg_replace('/[^a-z0-9]+/', '_', strtolower(substr($subject, 0, 40)));
        $htmlFile = $devDir . '/' . $ts . '_' . $slug . '.html';
        $httpBase = (defined('HTTP_DIR') ? HTTP_DIR : '') . '/infodeqb/hr/email_dev/';
        $htmlUrl  = $httpBase . basename($htmlFile);

        // Banner informativo no email (mostra destinatários reais)
        $modeLabel = $isLocalhost ? 'DEV/LOCALHOST' : 'PRODUÇÃO — MODO TESTE';
        $banner  = "\n<div style='font-family:monospace;background:#fffbe6;border:2px solid #f90;padding:12px 16px;font-size:13px'>";
        $banner .= "<strong>⚠ $modeLabel — Email interceptado</strong><br>";
        $banner .= "Destinatário real: " . htmlspecialchars(implode(', ', (array)$to)) . "<br>";
        $banner .= "CC real: "   . htmlspecialchars(implode(', ', (array)$cc_list)) . "<br>";
        $banner .= "Assunto: " . htmlspecialchars($subject) . "<br>";
        $banner .= "Enviado para: " . htmlspecialchars($isAdminDest ? $DEV_ADMIN : $DEV_USER);
        $banner .= "</div>\n";
        $htmlOut = preg_replace('/(<body[^>]*>)/i', '$1' . $banner, $body, 1, $count);
        if (!$count) { $htmlOut = $banner . $body; }
        file_put_contents($htmlFile, $htmlOut);

        // Log
        $logFile = ROOT_DIR . '/infodeqb/hr/email_dev.log';
        $entry   = str_repeat('-', 60) . "\n";
        $entry  .= date('Y-m-d H:i:s') . " [$modeLabel]\n";
        $entry  .= 'Para:    ' . implode(', ', (array)$to)      . "\n";
        $entry  .= 'CC:      ' . implode(', ', (array)$cc_list)  . "\n";
        $entry  .= 'Assunto: ' . $subject . "\n";
        $entry  .= 'Enviado: ' . ($isAdminDest ? $DEV_ADMIN : $DEV_USER) . "\n";
        $entry  .= 'Preview: ' . $htmlUrl . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        // Redirecionar destinatários para endereços de teste
        $origTo  = implode(', ', (array)$to);
        $to      = $isAdminDest ? array($DEV_ADMIN) : array($DEV_USER);
        $cc_list = array($DEV_ADMIN); // admin recebe cópia de tudo
        $bcc_list = array();
        $subject = '[TESTE → ' . $origTo . '] ' . $subject;
        // tenta envio real mas não falha se SMTP não estiver acessível ↓
    }

    $mail = new PHPMailer(true);
    $mail->IsSMTP(); // set mailer to use SMTP
    $mail->Host = "mail.up.pt"; // specify main and backup server
    $mail->SMTPDebug = false;
    $mail->SMTPAuth = true; // turn on SMTP authentication
    $mail->Username = mailUsername; // SMTP username
    $mail->Password = mailUserPassword; // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom(
        'deqdir@fe.up.pt',
        'FEUP | Direção do DEQB'
        );

    // essencial para SPF / DMARC
    $mail->Sender = $mail->Username;


    foreach ($to as $addr) {
        $mail->addAddress($addr);
    }

    foreach ($cc_list as $cc) {
        $mail->addCC($cc);
    }

    foreach ($bcc_list as $bcc) {
        $mail->addBCC($bcc);
    }

    $mail->AddReplyTo("deqbdir@fe.up.pt", "");
    $mail->WordWrap = 50; // set word wrap to 50 characters
    $mail->IsHTML(true); // set email format to HTML
    $mail->Subject = $subject;
    $mail->Body = $body;

    // Em localhost, SMTP pode não estar acessível — não propagar excepção
    if ($isLocalhost) {
        try { $mail->Send(); } catch (Exception $e) { /* falha silenciosa em dev */ }
        return true;
    }

    $mail->Send();
    // $mail->AddEmbeddedImage('/usr/users1/web/deqwww/public_html/infodeqb/hr/inc/images/logo_deq_mail.png',
    // 'logomail');
    return true;
}

// ══════════════════════════════════════════════════════════════════════
// VALIDAÇÕES DE ACESSO POR RESPONSÁVEL DE ESPAÇO
// ══════════════════════════════════════════════════════════════════════

/**
 * Cria registos de validação em infodeqb_rds_validacao para cada espaço da lista
 * e envia email ao respectivo responsável.
 * Espaços sem responsável definido são ignorados (não bloqueiam).
 * Retorna o número de emails enviados.
 */
function _criarValidacoes($pdo, $pedido_id, $registo_id, $deqids, $colab_nome, $datainicio, $datafim) {

    // 1. Resolver responsável de cada deqid e agrupar por resp_codigo
    // Eliminar duplicados antes de processar
    $deqids = array_unique(array_map('trim', array_map('strval', $deqids)));

    $byResp = array(); // resp_codigo => ['resp_nome'=>..., 'labs'=>[...]]
    foreach ($deqids as $deqid) {
        $deqid = trim((string)$deqid);
        if ($deqid === '') continue;

        $qGab = $pdo->prepare(
            "SELECT g.nomegab, r.Codigo AS resp_codigo, r.respespaco AS resp_nome
             FROM infodeqb_rds_gabinetes g
             LEFT JOIN infodeqb_rds_responsaveis r ON r.Codigo = g.responsavel
             WHERE g.deqid = ? LIMIT 1"
        );
        $qGab->execute([$deqid]);
        $gab = $qGab->fetch(PDO::FETCH_ASSOC);

        if (!$gab || empty($gab['resp_codigo'])) continue; // sem responsável: skip

        $rc = (string)$gab['resp_codigo'];
        if (!isset($byResp[$rc])) {
            $byResp[$rc] = array('resp_nome' => $gab['resp_nome'], 'labs' => array());
        }
        $byResp[$rc]['labs'][] = array('deq_id' => $deqid, 'gab_nome' => $gab['nomegab']);
    }

    // 2. Um registo + um email por responsável
    $enviados = 0;
    foreach ($byResp as $respCodigo => $respData) {

        // Não duplicar se já existe validação Pendente deste responsável
        if ($pedido_id !== null) {
            $chk = $pdo->prepare(
                "SELECT id FROM infodeqb_rds_validacao
                 WHERE pedido_id=? AND resp_codigo=? AND status='Pendente' LIMIT 1"
            );
            $chk->execute([$pedido_id, $respCodigo]);
        } else {
            $chk = $pdo->prepare(
                "SELECT id FROM infodeqb_rds_validacao
                 WHERE registo_id=? AND resp_codigo=? AND status='Pendente' LIMIT 1"
            );
            $chk->execute([$registo_id, $respCodigo]);
        }
        if ($chk->fetchColumn()) continue;

        $labs     = $respData['labs'];
        $firstId  = $labs[0]['deq_id'];
        $labNames = implode(', ', array_column($labs, 'gab_nome'));
        $labsJson = json_encode($labs, JSON_UNESCAPED_UNICODE);
        $token    = bin2hex(random_bytes(32));
        $expira   = date('Y-m-d H:i:s', strtotime('+30 days'));

        $pdo->prepare(
            "INSERT INTO infodeqb_rds_validacao
             (pedido_id, registo_id, deq_id, gab_nome, labs_json, resp_codigo, resp_nome, token, expira_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $pedido_id, $registo_id,
            $firstId, $labNames, $labsJson,
            $respCodigo, $respData['resp_nome'],
            $token, $expira,
        ]);

        _enviarEmailValidacao(
            array('nomegab' => $labNames, 'resp_codigo' => $respCodigo, 'resp_nome' => $respData['resp_nome']),
            $token, $colab_nome, $datainicio, $datafim
        );
        $enviados++;
    }
    return $enviados;
}

/**
 * Envia email de validação ao responsável do espaço.
 * $gab deve ter: nomegab (ou gab_nome), resp_codigo, resp_nome
 */
function _enviarEmailValidacao($gab, $token, $colab_nome, $datainicio, $datafim) {
    $baseUrl = (defined('HTTP_DIR') ? HTTP_DIR : '') . '/infodeqb/hr/validar-acesso.php';
    $link    = $baseUrl . '?token=' . $token;
    $info = array(
        'nome_resp'  => $gab['resp_nome']  ?? ($gab['resp_nome'] ?? '—'),
        'nome_colab' => $colab_nome,
        'espaco'     => $gab['nomegab']    ?? ($gab['gab_nome'] ?? '—'),
        'datainicio' => $datainicio,
        'datafim'    => $datafim,
        'link'       => $link,
    );
    $body      = format_email($info, 'mail_validacao_espaco.html');
    $respEmail = 'up' . $gab['resp_codigo'] . '@up.pt';
    try {
        send_email(
            array($respEmail),
            $body,
            'Acessos DEQ: Pedido de validação de acesso — ' . $colab_nome,
            array('deqdir@fe.up.pt')
        );
    } catch (Exception $e) {
        error_log('HR validação email falhou token=' . $token . ': ' . $e->getMessage());
    }
}

/**
 * Reabre uma validação: gera novo token, redefine status para Pendente
 * e reenvia o email ao responsável.
 */
function _reabrirValidacao($pdo, $valId) {
    $q = $pdo->prepare(
        "SELECT v.*,
                COALESCE(c1.nome, c2.nome)         AS colab_nome,
                COALESCE(r1.datainicio, r2.datainicio) AS datainicio,
                COALESCE(r1.datafim,    r2.datafim)    AS datafim
         FROM infodeqb_rds_validacao v
         LEFT JOIN infodeqb_rds_registo     r1 ON r1.autoid = v.registo_id
         LEFT JOIN infodeqb_rds_pedido      p  ON p.id       = v.pedido_id
         LEFT JOIN infodeqb_rds_registo     r2 ON r2.autoid  = p.registo_id
         LEFT JOIN infodeqb_rds_colaborador c1 ON c1.codigo  = r1.codigo
         LEFT JOIN infodeqb_rds_colaborador c2 ON c2.codigo  = p.codigo
         WHERE v.id = ?"
    );
    $q->execute([$valId]);
    $val = $q->fetch(PDO::FETCH_ASSOC);
    if (!$val) return;

    $newToken = bin2hex(random_bytes(32));
    $expira   = date('Y-m-d H:i:s', strtotime('+30 days'));
    $pdo->prepare(
        "UPDATE infodeqb_rds_validacao
         SET token=?, status='Pendente', nota=NULL, respondido_em=NULL, expira_em=?, criado_em=NOW()
         WHERE id=?"
    )->execute([$newToken, $expira, $valId]);

    _enviarEmailValidacao(
        array(
            'nomegab'    => $val['gab_nome'],
            'resp_codigo'=> $val['resp_codigo'],
            'resp_nome'  => $val['resp_nome'],
        ),
        $newToken,
        $val['colab_nome'] ?? '—',
        $val['datainicio'] ?? '',
        $val['datafim']    ?? ''
    );
}

/**
 * Notifica o secretariado quando um responsável rejeita uma validação.
 */
function _notificarRejeicaoValidacao($val, $colab_nome) {
    $info = array(
        'nome_colab' => $colab_nome,
        'espaco'     => $val['gab_nome']   ?? '—',
        'nome_resp'  => $val['resp_nome']  ?? '—',
        'nota'       => $val['nota']       ?: 'Sem nota adicional.',
    );
    $body = format_email($info, 'mail_validacao_rejeitada_sec.html');
    try {
        send_email(
            array('deqdir@fe.up.pt'),
            $body,
            'Acessos DEQ: Validação rejeitada — ' . $colab_nome,
            array('fmartins@fe.up.pt')
        );
    } catch (Exception $e) {
        error_log('HR notif rejeição validação: ' . $e->getMessage());
    }
}

?>
