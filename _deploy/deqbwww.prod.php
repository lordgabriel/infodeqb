<?php
/**
 * deqbwww.php — Configuração de PRODUÇÃO
 * Colocar em: /home/deqfeuppt/public_html/deqbwww.php
 * (um nível ACIMA de public_html/infodeqb/)
 *
 * !! NÃO commitar este ficheiro com credenciais reais !!
 */

class Database
{
    private static $dbName         = 'feupptdeqb';
    private static $dbHost         = 'localhost';
    private static $dbUsername     = 'PREENCHER';   // utilizador MySQL de produção
    private static $dbUserPassword = 'PREENCHER';   // password MySQL de produção
    private static $mailUserPassword = 'PREENCHER'; // password SMTP
    private static $mailUsername     = 'fmartins';

    private static $cont = null;

    public function __construct() {
        die('Init function is not allowed');
    }

    public static function connect()
    {
        if (null == self::$cont) {
            try {
                self::$cont = new PDO(
                    "mysql:host=" . self::$dbHost . ";dbname=" . self::$dbName,
                    self::$dbUsername,
                    self::$dbUserPassword,
                    [
                        PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    ]
                );
                self::$cont->exec("set names utf8");
            } catch (PDOException $e) {
                die($e->getMessage());
            }
        }
        return self::$cont;
    }

    public static function disconnect()
    {
        self::$cont = null;
    }
}

// ── Credenciais de email (SMTP FEUP) ─────────────────────────────────
define('mailUserPassword', 'PREENCHER');   // mesma que $mailUserPassword acima
define('mailUsername',     'up356946@up.pt');

// ── Caminhos ─────────────────────────────────────────────────────────
define('ROOT',     '/home/deqfeuppt/public_html');
define('ROOT_DIR', '/home/deqfeuppt/public_html');
define('HTTP_DIR', 'https://deq.fe.up.pt');

// ── Modo de teste de email ────────────────────────────────────────────
// true  = todos os emails vão para MAIL_TEST_RECIPIENTS (sem envio externo)
// false = envio normal para os destinatários reais
//
// Manter true enquanto estiver a testar o workflow em produção.
// Mudar para false apenas quando tudo estiver validado.
//
define('MAIL_TEST_MODE', true);
define('MAIL_TEST_RECIPIENTS', [
    'fmartins@fe.up.pt',
    'lfamartins@fe.up.pt',
]);
