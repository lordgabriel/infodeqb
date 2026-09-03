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
        'SELECT g.deqid FROM infodeqb_rds_gabinetes g
         JOIN infodeqb_rds_registo_acessos ra ON ra.lab_id = g.id
         WHERE ra.registo_id = ?'
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

    // Actualizar tabela relacional (fonte primária)
    $pdo->prepare('DELETE FROM infodeqb_rds_registo_acessos WHERE registo_id = ?')->execute([$registoId]);
    if (!empty($labIds)) {
        $ph   = implode(',', array_fill(0, count($labIds), '?'));
        $gabs = $pdo->prepare("SELECT id FROM infodeqb_rds_gabinetes WHERE deqid IN ($ph)");
        $gabs->execute($labIds);
        $ins  = $pdo->prepare('INSERT INTO infodeqb_rds_registo_acessos (registo_id, lab_id) VALUES (?, ?)');
        foreach ($gabs->fetchAll(PDO::FETCH_COLUMN) as $labId) {
            $ins->execute([$registoId, $labId]);
        }
    }

    // Actualizar cache de texto
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

/** Renderiza o campo de telefone moderno (indicativo + número num só campo visual) */
function renderPhoneInput(string $stored = '', string $size = '', bool $required = false, bool $disabled = false): void {
    list($ind, $num) = parsePhone($stored);
    $h = $size === 'sm' ? '31px' : '38px';
    $fs = $size === 'sm' ? '13px' : '14px';
    $dis = $disabled ? 'disabled ' : '';
    echo '<div style="display:flex;align-items:stretch;border:1px solid #ced4da;border-radius:.375rem;overflow:hidden;background:#fff;transition:border-color .15s ease-in-out,box-shadow .15s ease-in-out;" '
        . 'onfocusin="this.style.borderColor=\'#80bdff\';this.style.boxShadow=\'0 0 0 .2rem rgba(0,123,255,.25)\'" '
        . 'onfocusout="this.style.borderColor=\'#ced4da\';this.style.boxShadow=\'none\'">';
    echo '<select name="telefone_indicativo" ' . $dis
        . 'style="border:none;outline:none;background:transparent;padding:0 4px 0 8px;font-size:' . $fs . ';height:' . $h . ';line-height:' . $h . ';cursor:pointer;flex-shrink:0;min-width:90px;appearance:none;-webkit-appearance:none;" '
        . 'title="Indicativo de país">';
    foreach (phoneIndicativos() as $code => $lbl) {
        $sel = ($ind === $code) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($code) . '"' . $sel . '>' . $lbl . '</option>';
    }
    echo '</select>';
    echo '<span style="width:1px;background:#ced4da;flex-shrink:0;margin:6px 0;"></span>';
    echo '<input type="tel" name="telefone_numero" placeholder="912 345 678" ' . ($required ? 'required ' : '') . $dis
        . 'value="' . htmlspecialchars($num) . '" '
        . 'style="border:none;outline:none;flex:1;padding:0 10px;font-size:' . $fs . ';height:' . $h . ';background:transparent;min-width:0;">';
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
    $template = file_get_contents(ROOT_DIR . '/infodeqb/hr/inc/' . $format);

    // ── Injectar parciais de cabeçalho/rodapé ─────────────────────────────────
    // Os templates que usam {{HEADER}} e {{FOOTER}} declaram metadados em comentários:
    //   <!-- EMAIL_SUBTITLE: Título do email -->
    //   <!-- EMAIL_HEADER_COLOR: #c0392b -->      (opcional, default #2475ba)
    //   <!-- EMAIL_SUBTITLE_COLOR: #fde -->        (opcional, default #cce0f5)
    if (strpos($template, '{{HEADER}}') !== false) {
        $hcor    = '#2475ba';
        $hcorSub = '#cce0f5';
        $subtitle = 'Acessos DEQB';

        if (preg_match('/<!--\s*EMAIL_SUBTITLE:\s*(.+?)\s*-->/', $template, $m)) {
            $subtitle = trim($m[1]);
        }
        if (preg_match('/<!--\s*EMAIL_HEADER_COLOR:\s*(.+?)\s*-->/', $template, $m)) {
            $hcor = trim($m[1]);
        }
        if (preg_match('/<!--\s*EMAIL_SUBTITLE_COLOR:\s*(.+?)\s*-->/', $template, $m)) {
            $hcorSub = trim($m[1]);
        }

        $header = file_get_contents(ROOT_DIR . '/infodeqb/hr/inc/mail__header.html');
        $header = str_replace('__HCOR__',     $hcor,    $header);
        $header = str_replace('__HCOR_SUB__', $hcorSub, $header);
        $header = str_replace('__SUBTITLE__', htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'), $header);
        $template = str_replace('{{HEADER}}', $header, $template);
    }

    if (strpos($template, '{{FOOTER}}') !== false) {
        $footer = file_get_contents(ROOT_DIR . '/infodeqb/hr/inc/mail__footer.html');
        $template = str_replace('{{FOOTER}}', $footer, $template);
    }

    // ── Substituir placeholders {valorKEY} ────────────────────────────────────
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

        // Redirecionar destinatários ANTES de gerar o banner
        $origTo   = implode(', ', (array)$to);
        $to       = !empty($testRecipients) ? $testRecipients : array($DEV_ADMIN);
        $cc_list  = array();
        $bcc_list = array();

        // Banner informativo (gerado com os destinatários reais já actualizados)
        $modeLabel = $isLocalhost ? 'DEV/LOCALHOST' : 'PRODUÇÃO — MODO TESTE';
        $banner  = "\n<div style='font-family:monospace;background:#fffbe6;border:2px solid #f90;padding:12px 16px;font-size:13px'>";
        $banner .= "<strong>⚠ $modeLabel — Email interceptado</strong><br>";
        $banner .= "Destinatário real: " . htmlspecialchars($origTo) . "<br>";
        $banner .= "CC real: "   . htmlspecialchars(implode(', ', array('deqdir@fe.up.pt','fmartins@fe.up.pt'))) . "<br>";
        $banner .= "Assunto: " . htmlspecialchars($subject) . "<br>";
        $banner .= "Enviado para: " . htmlspecialchars(implode(', ', $to));
        $banner .= "</div>\n";
        $htmlOut = preg_replace('/(<body[^>]*>)/i', '$1' . $banner, $body, 1, $count);
        if (!$count) { $htmlOut = $banner . $body; }
        file_put_contents($htmlFile, $htmlOut);

        // Log
        $logFile = ROOT_DIR . '/infodeqb/hr/email_dev.log';
        $entry   = str_repeat('-', 60) . "\n";
        $entry  .= date('Y-m-d H:i:s') . " [$modeLabel]\n";
        $entry  .= 'Para orig: ' . $origTo . "\n";
        $entry  .= 'Enviado:   ' . implode(', ', $to) . "\n";
        $entry  .= 'Assunto:   ' . $subject . "\n";
        $entry  .= 'FROM:      ' . mailUsername . "\n";
        $entry  .= 'Preview:   ' . $htmlUrl . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

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

    // FROM: usa MAIL_FROM se definido em deqbwww.php, senão o utilizador autenticado
    // Sender (Return-Path / envelope) = utilizador autenticado → SPF passa
    $fromAddr = defined('MAIL_FROM') && MAIL_FROM ? MAIL_FROM : mailUsername;
    $fromName = defined('MAIL_FROM_NAME') && MAIL_FROM_NAME ? MAIL_FROM_NAME : 'InfoDEQB — DEQB/FEUP';
    $mail->setFrom($fromAddr, $fromName);
    if ($fromAddr !== mailUsername) {
        // Reply-To = FROM quando é endereço funcional
        $mail->addReplyTo($fromAddr, $fromName);
    }
    // Sender (envelope) = utilizador SMTP autenticado (garante SPF)
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

/**
 * Indica se um registo já tem TODAS as validações concluídas ("Validado")
 * e não tem nenhum acesso com responsável definido sem pedido de validação.
 * Usado para bloquear novos pedidos de validação redundantes (mass action
 * em admin/index.php e botão "Solicitar validações" em detail.php/admin).
 */
function _registoTotalmenteValidado($pdo, $registo_id) {
    $qChk = $pdo->prepare(
        "SELECT id, deq_id, labs_json, status FROM infodeqb_rds_validacao WHERE registo_id=?"
    );
    $qChk->execute([$registo_id]);
    $vals = $qChk->fetchAll(PDO::FETCH_ASSOC);

    if (!$vals) return false;

    // Todas as validações deste registo têm de estar 'Validado'
    foreach ($vals as $_v) {
        if ($_v['status'] !== 'Validado') return false;
    }

    // Conjunto de deqids já cobertos por algum pedido de validação
    // (cada validação pode cobrir vários espaços via labs_json)
    $deqidsComPedido = array();
    foreach ($vals as $_v) {
        if (!empty($_v['labs_json'])) {
            $_labs = json_decode($_v['labs_json'], true);
            if (is_array($_labs)) {
                foreach ($_labs as $_l) {
                    if (!empty($_l['deq_id'])) $deqidsComPedido[] = trim((string)$_l['deq_id']);
                }
                continue;
            }
        }
        if (!empty($_v['deq_id'])) $deqidsComPedido[] = trim((string)$_v['deq_id']);
    }
    $deqidsComPedido = array_unique($deqidsComPedido);

    // Acessos actuais do registo com responsável definido e ainda sem pedido
    $deqids = getRegistoAcessos($pdo, $registo_id);
    if (!empty($deqids)) {
        $ph = implode(',', array_fill(0, count($deqids), '?'));
        $qG = $pdo->prepare(
            "SELECT g.deqid FROM infodeqb_rds_gabinetes g
             JOIN infodeqb_rds_responsaveis r ON r.Codigo = g.responsavel
             WHERE g.deqid IN ($ph)
               AND g.responsavel IS NOT NULL AND g.responsavel != 0
               AND COALESCE(r.auto_valida, 0) = 0"
        );
        $qG->execute($deqids);
        foreach ($qG->fetchAll(PDO::FETCH_COLUMN) as $_deqid) {
            if (!in_array(trim((string)$_deqid), $deqidsComPedido)) return false;
        }
    }

    return true;
}

function _criarValidacoes($pdo, $pedido_id, $registo_id, $deqids, $colab_nome, $datainicio, $datafim, $colab_codigo = '', $resp_trabalho = '') {

    $deqids = array_unique(array_map('trim', array_map('strval', $deqids)));

    // Resolver responsável (com flag auto_valida) de cada deqid e agrupar por resp_codigo
    $byResp = array(); // resp_codigo => ['resp_nome'=>..., 'auto_valida'=>..., 'labs'=>[...]]
    foreach ($deqids as $deqid) {
        $deqid = trim((string)$deqid);
        if ($deqid === '') continue;

        $qGab = $pdo->prepare(
            "SELECT g.nomegab, r.Codigo AS resp_codigo, r.respespaco AS resp_nome,
                    COALESCE(r.auto_valida, 0) AS auto_valida
             FROM infodeqb_rds_gabinetes g
             LEFT JOIN infodeqb_rds_responsaveis r ON r.Codigo = g.responsavel
             WHERE g.deqid = ?"
        );
        $qGab->execute([$deqid]);
        foreach ($qGab->fetchAll(PDO::FETCH_ASSOC) as $gab) {
            if (empty($gab['resp_codigo'])) continue;
            $rc = (string)$gab['resp_codigo'];
            if (!isset($byResp[$rc])) {
                $byResp[$rc] = array(
                    'resp_nome'   => $gab['resp_nome'],
                    'auto_valida' => (int)$gab['auto_valida'],
                    'labs'        => array(),
                );
            }
            $byResp[$rc]['labs'][] = array('deq_id' => $deqid, 'gab_nome' => $gab['nomegab']);
        }
    }

    $enviados = 0;
    foreach ($byResp as $respCodigo => $respData) {
        $labs     = $respData['labs'];
        $firstId  = $labs[0]['deq_id'];
        $labNames = substr(implode(', ', array_column($labs, 'gab_nome')), 0, 490);
        $labsJson = json_encode($labs, JSON_UNESCAPED_UNICODE);

        if ($respData['auto_valida']) {
            // Auto-aprovar sem email — verificar se já existe
            if ($pedido_id !== null) {
                $chkI = $pdo->prepare("SELECT id FROM infodeqb_rds_validacao WHERE pedido_id=? AND resp_codigo=? LIMIT 1");
                $chkI->execute([$pedido_id, $respCodigo]);
            } else {
                $chkI = $pdo->prepare("SELECT id FROM infodeqb_rds_validacao WHERE registo_id=? AND resp_codigo=? LIMIT 1");
                $chkI->execute([$registo_id, $respCodigo]);
            }
            if ($chkI->fetchColumn()) continue;

            $pdo->prepare(
                "INSERT INTO infodeqb_rds_validacao
                 (pedido_id, registo_id, deq_id, gab_nome, labs_json,
                  resp_codigo, resp_nome, token, status, respondido_em, nota)
                 VALUES (?,?,?,?,?,?,?,?,'Validado',NOW(),'Auto-validado')"
            )->execute([
                $pedido_id, $registo_id,
                $firstId, $labNames, $labsJson,
                $respCodigo, $respData['resp_nome'],
                bin2hex(random_bytes(16)),
            ]);
            continue;
        }

        // Não duplicar se já existe qualquer validação (Pendente ou Validado) deste responsável
        if ($pedido_id !== null) {
            $chk = $pdo->prepare(
                "SELECT id FROM infodeqb_rds_validacao
                 WHERE pedido_id=? AND resp_codigo=? AND status IN ('Pendente','Validado') LIMIT 1"
            );
            $chk->execute([$pedido_id, $respCodigo]);
        } else {
            $chk = $pdo->prepare(
                "SELECT id FROM infodeqb_rds_validacao
                 WHERE registo_id=? AND resp_codigo=? AND status IN ('Pendente','Validado') LIMIT 1"
            );
            $chk->execute([$registo_id, $respCodigo]);
        }
        if ($chk->fetchColumn()) continue;

        $token  = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+30 days'));

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
            $token, $colab_nome, $datainicio, $datafim, $colab_codigo, $resp_trabalho
        );
        $enviados++;
    }
    return $enviados;
}

/**
 * Devolve a forma curta do nome para saudações de email.
 * Maria/Mário: 1º + 2º + último nome. Outros: 1º nome.
 */
function _nomeEmailCurto($nomeCompleto) {
    $partes = preg_split('/\s+/', trim($nomeCompleto));
    if (count($partes) <= 1) return $nomeCompleto;
    $primeiro = mb_strtolower($partes[0], 'UTF-8');
    if (in_array($primeiro, array('maria', 'mário', 'mario'))) {
        $preps = array('do', 'da', 'de', 'dos', 'das', 'e');
        if (count($partes) >= 3) {
            // Se partes[1] é preposição, incluir também partes[2]
            if (in_array(mb_strtolower($partes[1], 'UTF-8'), $preps) && isset($partes[2])) {
                $meio = $partes[1] . ' ' . $partes[2];
            } else {
                $meio = $partes[1];
            }
            return $partes[0] . ' ' . $meio . ' ' . end($partes);
        }
        return $partes[0] . ' ' . $partes[1];
    }
    return $partes[0] . ' ' . end($partes);
}

/**
 * Envia email de validação ao responsável do espaço.
 * $gab deve ter: nomegab (ou gab_nome), resp_codigo, resp_nome
 */
function _enviarEmailValidacao($gab, $token, $colab_nome, $datainicio, $datafim, $colab_codigo = '', $resp_trabalho = '') {
    $baseUrl = (defined('HTTP_DIR') ? HTTP_DIR : '') . '/infodeqb/hr/validar-acesso.php';
    $link    = $baseUrl . '?token=' . $token;

    // Construir link SIGARRA para o colaborador
    $codigo = preg_replace('/\D/', '', (string)$colab_codigo);
    if (strlen($codigo) === 9) {
        $sigarraUrl = 'https://sigarra.up.pt/feup/pt/fest_geral.cursos_list?pv_num_unico=' . $codigo;
    } elseif (strlen($codigo) === 6) {
        $sigarraUrl = 'https://sigarra.up.pt/feup/pt/func_geral.formview?p_codigo=' . $codigo;
    } else {
        $sigarraUrl = '';
    }
    $linkColab = $sigarraUrl
        ? '<a href="' . $sigarraUrl . '" style="color:#2475ba;text-decoration:none;" target="_blank">'
          . htmlspecialchars($colab_nome)
          . ' <span style="font-size:.7em;vertical-align:super;opacity:.75;">&#8599;</span></a>'
        : htmlspecialchars($colab_nome);

    $info = array(
        'nome_resp'      => isset($gab['resp_nome']) ? $gab['resp_nome'] : '—',
        'nome_resp_curto'=> isset($gab['resp_nome']) ? _nomeEmailCurto($gab['resp_nome']) : '—',
        'nome_colab'     => $colab_nome,
        'link_colab'     => $linkColab,
        'espaco'         => isset($gab['nomegab']) ? $gab['nomegab'] : (isset($gab['gab_nome']) ? $gab['gab_nome'] : '—'),
        'datainicio'     => $datainicio,
        'datafim'        => $datafim,
        'resp_trabalho'  => $resp_trabalho ? htmlspecialchars($resp_trabalho) : '—',
        'link'           => $link,
    );
    $body      = format_email($info, 'mail_validacao_espaco.html');
    $respEmail = 'up' . $gab['resp_codigo'] . '@up.pt';
    try {
        send_email(
            array($respEmail),
            $body,
            'Acessos DEQB: Pedido de validação de acesso — ' . $colab_nome,
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
                COALESCE(c1.nome, c2.nome)               AS colab_nome,
                COALESCE(c1.codigo, c2.codigo)           AS colab_codigo,
                COALESCE(r1.datainicio, r2.datainicio)   AS datainicio,
                COALESCE(r1.datafim,    r2.datafim)      AS datafim,
                COALESCE(IF(r1.responsavel=0,r1.outroresponsavel,rsp1.respespaco),
                         IF(r2.responsavel=0,r2.outroresponsavel,rsp2.respespaco)) AS resp_trabalho
         FROM infodeqb_rds_validacao v
         LEFT JOIN infodeqb_rds_registo        r1   ON r1.autoid  = v.registo_id
         LEFT JOIN infodeqb_rds_pedido         p    ON p.id        = v.pedido_id
         LEFT JOIN infodeqb_rds_registo        r2   ON r2.autoid   = p.registo_id
         LEFT JOIN infodeqb_rds_colaborador    c1   ON c1.codigo   = r1.codigo
         LEFT JOIN infodeqb_rds_colaborador    c2   ON c2.codigo   = p.codigo
         LEFT JOIN infodeqb_rds_responsaveis   rsp1 ON rsp1.Codigo = r1.responsavel
         LEFT JOIN infodeqb_rds_responsaveis   rsp2 ON rsp2.Codigo = r2.responsavel
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
        isset($val['colab_nome'])   ? $val['colab_nome']   : '—',
        isset($val['datainicio'])   ? $val['datainicio']   : '',
        isset($val['datafim'])      ? $val['datafim']      : '',
        isset($val['colab_codigo']) ? $val['colab_codigo'] : '',
        isset($val['resp_trabalho'])? $val['resp_trabalho']: ''
    );
}

/**
 * Notifica o próprio e admins quando um novo pedido de registo é submetido
 * (via meu-registo.php — utilizador já autenticado).
 */
/**
 * Carrega o mapeamento grupo_id → [categoria_id, ...] da BD.
 * Usado pelos formulários para filtragem dinâmica de categorias.
 * Retorna array associativo: ['1' => [4,9,10,...], '5' => [6,23,24], ...]
 */
function getGrupoCategoriasMap(PDO $pdo): array {
    $rows = $pdo->query(
        'SELECT grupo_id, categoria_id FROM infodeqb_rds_grupo_categoria ORDER BY grupo_id, categoria_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $map = array();
    foreach ($rows as $r) {
        $map[(int)$r['grupo_id']][] = (int)$r['categoria_id'];
    }
    return $map;
}

function _emailNovoRegisto($nome, $email, $codigo, $datainicio, $datafim, $tipo = 'Novo registo') {
    $L = (substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'pt', 0, 2) === 'en') ? 'en' : 'pt';
    $e = function($pt, $en) use ($L) { return ($L === 'en') ? $en : $pt; };
    $info = array(
        'nome'          => $nome,
        'codigo'        => $codigo,
        'inicio'        => $datainicio ?: '-',
        'fim'           => $datafim ?: '-',
        'tipo'          => $tipo ?: '-',
        'subtitle'      => $e('Pedido de Registo Submetido', 'Registration Request Submitted'),
        'msg_intro'     => $e(
            'O seu pedido de registo foi submetido com sucesso e aguarda aprovação pelo secretariado.',
            'Your registration request was successfully submitted and is pending approval by the secretariat.'
        ),
        'label_section' => $e('Dados do Pedido', 'Request Details'),
        'label_nome'    => $e('Nome', 'Name'),
        'label_codigo'  => $e('Código FEUP', 'FEUP Code'),
        'label_inicio'  => $e('Data início', 'Start date'),
        'label_fim'     => $e('Data fim', 'End date'),
        'label_tipo'    => $e('Tipo', 'Type'),
        'msg_footer'    => $e(
            'Será notificado por email quando o pedido for processado.',
            'You will be notified by email when the request is processed.'
        ),
    );
    $body = format_email($info, 'mail_novo_registo.html');
    try {
        send_email(
            array($email),
            $body,
            'Acessos DEQB: Pedido de registo submetido',
            array('deqdir@fe.up.pt', 'fmartins@fe.up.pt')
        );
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('HR _emailNovoRegisto falhou para ' . $codigo . ': ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('HR _emailNovoRegisto falhou para ' . $codigo . ': ' . $e->getMessage());
    }
}

/**
 * Notifica o próprio e admins quando um pedido de alteração é submetido.
 */
function _emailPedidoAlteracao($nome, $email, $codigo, $detalhe) {
    $info = array(
        'nome'    => $nome,
        'codigo'  => $codigo,
        'detalhe' => $detalhe,
    );
    $body = format_email($info, 'mail_pedido_alteracao.html');
    try {
        send_email(
            array($email),
            $body,
            'Acessos DEQB: Pedido de alteração submetido',
            array('deqdir@fe.up.pt', 'fmartins@fe.up.pt')
        );
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('HR _emailPedidoAlteracao falhou para ' . $codigo . ': ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('HR _emailPedidoAlteracao falhou para ' . $codigo . ': ' . $e->getMessage());
    }
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
            'Acessos DEQB: Validação rejeitada — ' . $colab_nome,
            array('fmartins@fe.up.pt')
        );
    } catch (Exception $e) {
        error_log('HR notif rejeição validação: ' . $e->getMessage());
    }
}

// ── Enviar email de alteração ao SIGARRA (usado em index.php e validacao-action.php) ──
function _emailSigarra($pdo, $ped, $d) {
    $regRow = $pdo->prepare(
        'SELECT r.*, c.nome, c.email, c.emailalt,
                IF(r.responsavel=0, r.outroresponsavel, rsp.respespaco) AS resp_nome
         FROM infodeqb_rds_registo r
         JOIN infodeqb_rds_colaborador c ON c.codigo = r.codigo
         LEFT JOIN infodeqb_rds_responsaveis rsp ON rsp.Codigo = r.responsavel
         WHERE r.autoid = ?'
    );
    $regRow->execute([$ped['registo_id']]);
    $reg = $regRow->fetch(PDO::FETCH_ASSOC);
    if (!$reg) return;

    // Determinar datafim a mostrar (nova se mudou, actual caso contrário)
    $datafim = isset($d['datafim_novo']) ? $d['datafim_novo'] : $reg['datafim'];

    // Construir bloco HTML dinâmico de alterações
    $tdStyle = 'padding:8px;font-family:Arial,sans-serif;font-size:14px;';
    $thStyle = 'padding:8px;font-family:Arial,sans-serif;font-size:14px;background:#2475ba;color:#fff;text-align:left;';
    $alteracoes = '';

    // ── Alteração de data de fim ──────────────────────────────────
    if (isset($d['datafim_novo'])) {
        $alteracoes .= '<h3 style="font-family:Arial,sans-serif;font-size:15px;margin:0 0 8px 0;color:#153643;">Alteração de Data de Fim</h3>';
        $alteracoes .= '<table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:20px;">';
        $alteracoes .= '<tr><th style="' . $thStyle . 'width:160px;">Data anterior</th><th style="' . $thStyle . '">Nova data</th></tr>';
        $alteracoes .= '<tr>';
        $alteracoes .= '<td style="' . $tdStyle . 'color:#999;text-decoration:line-through;">' . htmlspecialchars(isset($d['datafim_antigo']) ? $d['datafim_antigo'] : '—') . '</td>';
        $alteracoes .= '<td style="' . $tdStyle . 'font-weight:bold;">'                        . htmlspecialchars(isset($d['datafim_novo'])   ? $d['datafim_novo']   : '—') . '</td>';
        $alteracoes .= '</tr></table>';
    }

    // ── Alteração de acessos a labs + Acesso DEQB (Porta Norte) ─────
    $addNomes = array_values(array_filter((array)(isset($d['labs_adicionados_nomes']) ? $d['labs_adicionados_nomes'] : (isset($d['labs_adicionados']) ? $d['labs_adicionados'] : array()))));
    $remNomes = array_values(array_filter((array)(isset($d['labs_removidos_nomes'])   ? $d['labs_removidos_nomes']   : (isset($d['labs_removidos'])   ? $d['labs_removidos']   : array()))));
    $dadosAnt = json_decode(isset($ped['dados_anteriores']) ? $ped['dados_anteriores'] : 'null', true);
    if (!is_array($dadosAnt)) $dadosAnt = array();
    $oldDeq = isset($dadosAnt['acessodeq']) ? (int)$dadosAnt['acessodeq'] : null;
    $newDeq = isset($d['acessodeq'])        ? (int)$d['acessodeq']        : null;

    // Tratar "Acesso DEQB" (Porta Norte) como gabid regular no diff
    if ($oldDeq !== null && $newDeq !== null && $oldDeq !== $newDeq) {
        if ($newDeq === 1 && !in_array('Porta Norte', $addNomes, true)) {
            array_unshift($addNomes, 'Porta Norte');
        } elseif ($newDeq === 0 && !in_array('Porta Norte', $remNomes, true)) {
            array_unshift($remNomes, 'Porta Norte');
        }
    }

    // Lista completa: include "Porta Norte" se acessodeq activo
    $acessosNomesLista = isset($d['acessos_nomes']) ? (array)$d['acessos_nomes'] : array();
    $acessodeqFinal = ($newDeq !== null) ? $newDeq : (int)$reg['acessodeq'];
    if ($acessodeqFinal === 1 && !in_array('Porta Norte', $acessosNomesLista, true)) {
        array_unshift($acessosNomesLista, 'Porta Norte');
    }
    if (empty($acessosNomesLista) && !empty($reg['acessos'])) {
        $acessosNomesLista = array($reg['acessos']);
    }

    if (!empty($addNomes) || !empty($remNomes)) {
        $alteracoes .= '<h3 style="font-family:Arial,sans-serif;font-size:15px;margin:0 0 8px 0;color:#153643;">Alteração de Acessos</h3>';
        $alteracoes .= '<table border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;margin-bottom:20px;">';
        $alteracoes .= '<tr><th style="' . $thStyle . 'width:50%;">Adicionar</th><th style="' . $thStyle . '">Remover</th></tr>';
        $addCell = !empty($addNomes)
            ? implode('', array_map(function($n) {
                return '<span style="display:inline-block;background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:3px;padding:2px 8px;margin:2px;font-size:13px;">+ ' . htmlspecialchars($n) . '</span>';
              }, $addNomes))
            : '<span style="color:#999;">—</span>';
        $remCell = !empty($remNomes)
            ? implode('', array_map(function($n) {
                return '<span style="display:inline-block;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:3px;padding:2px 8px;margin:2px;font-size:13px;">- ' . htmlspecialchars($n) . '</span>';
              }, $remNomes))
            : '<span style="color:#999;">—</span>';
        $alteracoes .= '<tr><td style="' . $tdStyle . '">' . $addCell . '</td><td style="' . $tdStyle . '">' . $remCell . '</td></tr>';
        if (!empty($acessosNomesLista)) {
            $alteracoes .= '<tr><td colspan="2" style="' . $tdStyle . 'color:#555;font-size:13px;">'
                         . '<strong>Lista completa após atualização:</strong> ' . htmlspecialchars(implode('; ', $acessosNomesLista)) . '</td></tr>';
        }
        $alteracoes .= '</table>';
    } elseif (!empty($acessosNomesLista) && isset($d['datafim_novo'])) {
        // Só datas mudaram — mostrar lista de acessos actuais para contexto
        $alteracoes .= '<p style="font-family:Arial,sans-serif;font-size:13px;color:#555;margin:4px 0 16px 0;">'
                     . '<strong>Acessos actuais:</strong> ' . htmlspecialchars(implode('; ', $acessosNomesLista)) . '</p>';
    }

    $info = array(
        'codigo'      => $reg['codigo'],
        'nome'        => $reg['nome'],
        'fim'         => $datafim,
        'responsavel' => isset($reg['resp_nome']) ? $reg['resp_nome'] : '—',
        'alteracoes'  => $alteracoes ?: '<p style="color:#555;font-family:Arial,sans-serif;">Sem alterações de detalhe disponíveis.</p>',
    );
    $body    = format_email($info, 'mail_alteracao_sigarra.html');
    $subject = 'Acessos DEQB: Atualização de acessos — ' . $reg['nome'];
    try {
        send_email(
            array('sigarra@fe.up.pt'),
            $body, $subject,
            array('deqdir@fe.up.pt','fmartins@fe.up.pt')
        );
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('HR email SIGARRA falhou registo_id=' . $ped['registo_id'] . ': ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('HR email SIGARRA falhou registo_id=' . $ped['registo_id'] . ': ' . $e->getMessage());
    }
}

// ══════════════════════════════════════════════════════════════════════
// ACCORDION DE LABORATÓRIOS (reutilizável)
// ══════════════════════════════════════════════════════════════════════

/**
 * Constrói o HTML do accordion de checkboxes de laboratórios.
 * $selectedDeqids : array de deqid já seleccionados
 * $inputName      : nome do campo HTML (ex: 'acessos[]' ou 'notif_labs[]')
 * $idPrefix       : prefixo único para os IDs dos colapsos (ex: 'lab', 'notif')
 */
function buildGabAccordion(PDO $pdo, array $selectedDeqids, $inputName = 'acessos[]', $idPrefix = 'lab') {
    $gabRows = $pdo->query(
        'SELECT * FROM infodeqb_rds_gabinetes WHERE visible != 0 ORDER BY edificio, piso, nomegab'
    )->fetchAll(PDO::FETCH_ASSOC);

    $gabByEdificio = array();
    foreach ($gabRows as $r) { $gabByEdificio[$r['edificio']][] = $r; }

    $html = '';
    foreach ($gabByEdificio as $edificio => $edRows) {
        $edId    = $idPrefix . '-ed-' . preg_replace('/[^a-z0-9]/i', '', $edificio);
        $edSel   = 0;
        foreach ($edRows as $r) { if (in_array($r['deqid'], $selectedDeqids)) $edSel++; }
        $open    = $edSel > 0 ? ' show' : '';
        $expd    = $edSel > 0 ? 'true' : 'false';
        $badge   = '<span class="iq-lab-sel-count"' . ($edSel > 0 ? '' : ' style="display:none"') . '>'
                 . ($edSel > 0 ? $edSel : '') . '</span>';
        $byPiso  = array();
        foreach ($edRows as $r) { $byPiso[$r['piso']][] = $r; }

        $html .= '<div class="iq-lab-building">'
              . '<button type="button" class="iq-lab-building-header" data-bs-toggle="collapse" data-bs-target="#' . $edId . '" aria-expanded="' . $expd . '">'
              . '<span>Edifício ' . htmlspecialchars($edificio) . '</span>' . $badge
              . '<i class="fas fa-chevron-down ms-auto"></i></button>'
              . '<div class="collapse' . $open . '" id="' . $edId . '">';
        $pisoIdx = 0;
        foreach ($byPiso as $piso => $pisoRows) {
            $pisoId  = $edId . '-p' . $pisoIdx++;
            $pisoSel = 0;
            foreach ($pisoRows as $r) { if (in_array($r['deqid'], $selectedDeqids)) $pisoSel++; }
            $pisoOpen = $pisoSel > 0 ? ' show' : '';
            $pisoExp  = $pisoSel > 0 ? 'true' : 'false';
            $html .= '<div class="iq-checkgroup">'
                  . '<button type="button" class="iq-piso-header" data-bs-toggle="collapse" data-bs-target="#' . $pisoId . '" aria-expanded="' . $pisoExp . '">'
                  . '<i class="fas fa-layer-group fa-xs me-1"></i>' . htmlspecialchars($piso)
                  . '<i class="fas fa-chevron-down ms-auto iq-piso-chevron"></i></button>'
                  . '<div class="collapse iq-piso-body' . $pisoOpen . '" id="' . $pisoId . '">';
            foreach ($pisoRows as $rowgab) {
                $checked = in_array($rowgab['deqid'], $selectedDeqids) ? ' checked' : '';
                $html .= '<label><input type="checkbox" name="' . htmlspecialchars($inputName) . '" value="'
                       . htmlspecialchars($rowgab['deqid']) . '"' . $checked . '> '
                       . htmlspecialchars($rowgab['nomegab']) . '</label>';
            }
            $html .= '</div></div>';
        }
        $html .= '</div></div>';
    }
    return $html;
}

/**
 * Retorna array de deqids de laboratórios cujo responsável tem auto_valida=1.
 * Estes labs são auto-aprovados e não entram no fluxo de validação normal.
 */
function _labsIsentos() {
    global $pdo;
    $q = $pdo->query(
        "SELECT g.deqid FROM infodeqb_rds_gabinetes g
         JOIN infodeqb_rds_responsaveis r ON r.Codigo = g.responsavel
         WHERE COALESCE(r.auto_valida, 0) = 1"
    );
    return $q ? $q->fetchAll(PDO::FETCH_COLUMN) : array();
}

?>
