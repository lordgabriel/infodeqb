<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/deqbwww.php';
include $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/common.php';
include ROOT_DIR . '/infodeqb/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/infodeqb/hr/inc/functions.php';

require_once __DIR__ . '/auth.php'; // define $isAdmin, redireciona se nao autorizado

$pedido = null;
$notifica = null;
$subject = null;
$body = null;
$id = null;
$autoid = null;
$email = 'fmartins@fe.up.pt';
$msg = null;
date_default_timezone_set('Europe/Lisbon');

if (empty($_SESSION['ids'])) {
    header("Location: ../index.php");
} else {

    $pdo = Database::connect();
    foreach ($_SESSION['ids'] as $key => $value) {
        $sql = 'SELECT * FROM infodeqb_rds_colaborador INNER JOIN infodeqb_rds_registo ON infodeqb_rds_colaborador.codigo=infodeqb_rds_registo.codigo left join infodeqb_rds_responsaveis on infodeqb_rds_registo.responsavel=infodeqb_rds_responsaveis.codigo where infodeqb_rds_colaborador.codigo =' .
                $value . ' and infodeqb_rds_registo.deleted = 0 and infodeqb_rds_registo.autoid = ' .
                $key . ' order by infodeqb_rds_registo.datafim DESC';
        // Mapear deqid -> nomegab para mostrar nomes de labs nos emails
        $sql_gab = 'SELECT nomegab, deqid FROM infodeqb_rds_gabinetes';
        $statement = $pdo->query($sql_gab);
        $gabs = $statement->fetchAll(PDO::FETCH_ASSOC);
        $column_values = array_column($gabs, 'nomegab', 'deqid');

        foreach ($pdo->query($sql) as $row) {
            // Usar tabela relacional para obter deqids do registo
            $regDeqids = getRegistoAcessos($pdo, (int)$row['autoid']);
            $arrayacessosid = array_flip($regDeqids);
            $labNames = array_intersect_key($column_values, $arrayacessosid);
            $labNames = array_unique($labNames);
            $result = implode('; ', $labNames);

            $info = array(
                    'codigo'      => $row['codigo'],
                    'nome'        => $row['nome'],
                    'mail'        => $row['email'],
                    'fim'         => $row['datafim'],
                    'responsavel' => ($row['responsavel'] == 0 ? $row['outroresponsavel'] : $row['respespaco']),
                    'acessos'     => ($row['acessodeq'] == 1 ? 'Porta Norte; ' : '') . $result,
            );
        }

        $key = $_SESSION['action'];
        // echo $key;
        // var_dump($info);
        switch ($key) {
            case 'Pendente':
                $pedido = isset($_SESSION['action']) ? $_SESSION['action'] : null;
                $body = format_email($info, 'mail_pedido.html');
                $subject = 'Acessos DEQB: Solicitação de novos acessos';
                $to = array(
                        'sigarra@fe.up.pt',
                );
                $list = array(
                        'deqdir@fe.up.pt',
                        'fmartins@fe.up.pt',
                        'fpereira@fe.up.pt'
                );
                break;
            case 'notify':
                $notifica = isset($_SESSION['action']) ? $_SESSION['action'] : null;
                $body = format_email($info, 'mail_expira.html');
                $subject = 'Acessos DEQB: Acessos a caducar';
                $to = array(
                        $info['mail']
                );
                $list = array(
                        'fmartins@fe.up.pt',
                        'deqdir@fe.up.pt'
                );
                break;
            case 'Inativo':
                # $desativar = isset($_SESSION['action']) ? $_SESSION['action']
                # : null;
                # $body = format_email($info,'mail_desativa.html');
                # $subject= 'Acessos DEQB: Cancelamento de acessos';
                # $to=array ('deqdir@fe.up.pt','catc@fe.up.pt');
                # $list=array('fmartins@fe.up.pt');
                break;
            case 'Ativo':
                $ativar = isset($_SESSION['action']) ? $_SESSION['action'] : null;
                $body = format_email($info, 'mail_ativa.html');
                $subject = 'Acessos DEQB: Acessos concedidos';
                $to = array(
                        $info['mail']
                );
                $list = array(
                        'fmartins@fe.up.pt',
                        'deqdir@fe.up.pt'
                );
                break;
            default:
                break;
        }

        if ($key != 'Inativo') {
            $emailOk = false;
            try {
                $emailOk = send_email($to, $body, $subject, $list);
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                error_log('HR email.php falhou para autoid=' . $row['autoid'] . ': ' . $e->getMessage());
                $_SESSION['email_error'] = 'Erro ao enviar email (autoid ' . (int)$row['autoid'] . '): ' . $e->getMessage();
            } catch (Exception $e) {
                error_log('HR email.php falhou para autoid=' . $row['autoid'] . ': ' . $e->getMessage());
                $_SESSION['email_error'] = 'Erro ao enviar email (autoid ' . (int)$row['autoid'] . '): ' . $e->getMessage();
            }

            if ($emailOk) {
                $timestamp = date('Y-m-d H:i:s');
                $autoid = $row['autoid'];
                if (! is_null($pedido)) {
                    $q3 = $pdo->prepare(
                        'UPDATE infodeqb_rds_registo SET status = "Pendente", datacica = ? WHERE autoid = ?'
                    );
                    $q3->execute([$timestamp, $row['autoid']]);
                } elseif ($notifica == 'true') {
                    $q3 = $pdo->prepare(
                        'UPDATE infodeqb_rds_registo SET datanotificacao = ? WHERE autoid = ?'
                    );
                    $q3->execute([$timestamp, $row['autoid']]);
                }
            }
        }
    }
}

Database::disconnect();
header("Location: ./index.php");
?>