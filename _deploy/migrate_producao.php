<?php
/**
 * InfoDEQB — Migração de produção (duas ligações independentes)
 * ============================================================
 * Usa duas ligações MySQL separadas:
 *   $pdoSrc → deqfeuppt  (dados actuais)
 *   $pdoTgt → feupptdeqb (destino)
 *
 * Fases:
 *   1. Verificar ligações
 *   2. Copiar dados deqfeuppt → feupptdeqb (infodeqb_*)
 *   3. Criar tabelas novas em feupptdeqb
 *   4. Adicionar colunas novas a tabelas existentes
 *   5. Migrar acessosid → infodeqb_rds_registo_acessos
 *
 * APAGAR ESTE FICHEIRO APÓS USAR.
 * ============================================================
 */

define('MIGRATION_KEY', 'mig2026deqb');
if (($_GET['key'] ?? '') !== MIGRATION_KEY) {
    http_response_code(403);
    exit('Acesso negado. Use ?key=CHAVE');
}

// ── Credenciais BD fonte (deqfeuppt) ─────────────────────────
$SRC_HOST = 'webdb.fe.up.pt';
$SRC_USER = 'deqfeuppt';
$SRC_PASS = 'kiuQuooth6eiWeichaiqui9uicho3U';
$SRC_DB   = 'deqfeuppt';

// ── Credenciais BD destino (feupptdeqb) ──────────────────────
$TGT_HOST = 'webdb.fe.up.pt';
$TGT_USER = 'feupptdeqb';   // ← utilizador MySQL do feupptdeqb
$TGT_PASS = 'HQYbFNxqcYPf7eFTazfkJzT6bJDxYb';   // ← password MySQL do feupptdeqb
$TGT_DB   = 'feupptdeqb';

// ─────────────────────────────────────────────────────────────
set_time_limit(600);
ini_set('memory_limit', '512M');

header('Content-Type: text/plain; charset=utf-8');
echo "InfoDEQB — Migração de produção\n";
echo "================================\n\n";

function log_ok($msg)   { echo "  [OK] $msg\n";   flush(); ob_flush(); }
function log_err($msg)  { echo "  [ERRO] $msg\n"; flush(); ob_flush(); }
function log_skip($msg) { echo "  [--] $msg\n";   flush(); ob_flush(); }
function section($t)    { echo "\n=== $t ===\n";   flush(); ob_flush(); }

// ════════════════════════════════════════════════════════════
// FASE 1 — Verificar ligações
// ════════════════════════════════════════════════════════════
section("Fase 1 — Verificar ligações");

try {
    $pdoSrc = new PDO("mysql:host=$SRC_HOST;dbname=$SRC_DB;charset=utf8mb4", $SRC_USER, $SRC_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    log_ok("Ligado a `$SRC_DB` (fonte)");
} catch (PDOException $e) {
    log_err("Falha a ligar a `$SRC_DB`: " . $e->getMessage());
    exit(1);
}

try {
    $pdoTgt = new PDO("mysql:host=$TGT_HOST;dbname=$TGT_DB;charset=utf8mb4", $TGT_USER, $TGT_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    log_ok("Ligado a `$TGT_DB` (destino)");
} catch (PDOException $e) {
    log_err("Falha a ligar a `$TGT_DB`: " . $e->getMessage());
    exit(1);
}

$pdoSrc->exec("SET NAMES utf8mb4");
$pdoTgt->exec("SET NAMES utf8mb4");
$pdoTgt->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdoTgt->exec("SET sql_mode = ''");

// Helper: colunas de uma tabela
function getCols($pdo, $db, $tbl) {
    return $pdo->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$tbl'
         ORDER BY ORDINAL_POSITION"
    )->fetchAll(PDO::FETCH_COLUMN);
}

// ════════════════════════════════════════════════════════════
// FASE 2 — Copiar tabelas deqfeuppt → feupptdeqb
// Lê os dados em PHP e insere no destino (2 conexões separadas)
// ════════════════════════════════════════════════════════════
section("Fase 2 — Copiar tabelas `$SRC_DB` → `$TGT_DB`");

$srcTables = $pdoSrc->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$tgtTables = $pdoTgt->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
log_ok("Tabelas na fonte: " . count($srcTables));
log_ok("Tabelas no destino (já existentes): " . count($tgtTables));

$nCopied = 0; $nErrors = 0;

foreach ($srcTables as $tbl) {
    $dstName = "infodeqb_$tbl";

    try {
        // 1. Obter CREATE TABLE da fonte e adaptar para o destino
        $createRow = $pdoSrc->query("SHOW CREATE TABLE `$tbl`")->fetch(PDO::FETCH_ASSOC);
        $createSql = end($createRow);
        // Substituir nome da tabela
        $createSql = preg_replace(
            '/CREATE TABLE `' . preg_quote($tbl, '/') . '`/',
            "CREATE TABLE IF NOT EXISTS `$dstName`",
            $createSql
        );
        $pdoTgt->exec($createSql);

        // 2. Intersecção de colunas (lida com colunas novas no destino)
        $srcCols = getCols($pdoSrc, $SRC_DB, $tbl);
        $dstCols = getCols($pdoTgt, $TGT_DB, $dstName);
        $common  = array_values(array_intersect($srcCols, $dstCols));

        if (empty($common)) {
            log_skip("$tbl → $dstName (sem colunas comuns)");
            continue;
        }

        // 3. Verificar se já tem dados (idempotência)
        $existing = $pdoTgt->query("SELECT COUNT(*) FROM `$dstName`")->fetchColumn();
        if ($existing > 0) {
            log_skip("$tbl → $dstName (já tem $existing linhas — a saltar)");
            $nCopied++;
            continue;
        }

        // 4. Ler da fonte e inserir no destino em lotes
        $colsSql  = implode(', ', array_map(function($c) { return "`$c`"; }, $common));
        $pholds   = implode(', ', array_fill(0, count($common), '?'));
        $rows     = $pdoSrc->query("SELECT $colsSql FROM `$tbl`")->fetchAll(PDO::FETCH_NUM);

        $inserted = 0;
        if (!empty($rows)) {
            $stmt = $pdoTgt->prepare(
                "INSERT IGNORE INTO `$dstName` ($colsSql) VALUES ($pholds)"
            );
            $pdoTgt->beginTransaction();
            foreach ($rows as $row) {
                $stmt->execute($row);
                $inserted++;
            }
            $pdoTgt->commit();
        }

        $newCols = array_values(array_diff($dstCols, $srcCols));
        $note = !empty($newCols) ? ' [novas c/ default: ' . implode(', ', $newCols) . ']' : '';
        log_ok("$tbl → $dstName  ($inserted linhas)$note");
        $nCopied++;

    } catch (PDOException $e) {
        if ($pdoTgt->inTransaction()) $pdoTgt->rollBack();
        log_err("$tbl: " . $e->getMessage());
        $nErrors++;
    }
}

echo "\nCopiadas: $nCopied | Erros: $nErrors\n";

// Actualizar lista de tabelas no destino
$tgtTables = $pdoTgt->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

// ════════════════════════════════════════════════════════════
// FASE 3 — Criar tabelas NOVAS em feupptdeqb
// ════════════════════════════════════════════════════════════
section("Fase 3 — Criar tabelas novas em `$TGT_DB`");

$ddls = [];

$ddls['infodeqb_rds_pedido'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_rds_pedido` (
  `id`               int(11)  NOT NULL AUTO_INCREMENT,
  `tipo`             enum('novo','alteracao','novo_registo','alteracao_labs','alteracao_datafim','alteracao_sigarra') NOT NULL,
  `origem`           enum('utilizador','secretariado') NOT NULL DEFAULT 'utilizador',
  `codigo`           varchar(9)  NOT NULL,
  `registo_id`       int(11)     DEFAULT NULL,
  `dados_json`       text        NOT NULL,
  `dados_anteriores` text        DEFAULT NULL,
  `campos_alterados` text        DEFAULT NULL,
  `observacoes`      text        DEFAULT NULL,
  `status`           enum('Pendente','Aprovado','Rejeitado','Aguarda_SIGARRA','Concluido','Cancelado') NOT NULL DEFAULT 'Pendente',
  `criado_em`        datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processado_em`    datetime    DEFAULT NULL,
  `processado_por`   varchar(30) DEFAULT NULL,
  `notas_admin`      text        DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_codigo` (`codigo`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_rds_validacao'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_rds_validacao` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `pedido_id`     int(11)      DEFAULT NULL,
  `registo_id`    int(11)      DEFAULT NULL,
  `deq_id`        varchar(20)  NOT NULL,
  `gab_nome`      varchar(100) DEFAULT NULL,
  `labs_json`     text         DEFAULT NULL,
  `resp_codigo`   varchar(20)  NOT NULL,
  `resp_nome`     varchar(100) DEFAULT NULL,
  `token`         varchar(64)  NOT NULL,
  `status`        enum('Pendente','Validado','Rejeitado') NOT NULL DEFAULT 'Pendente',
  `nota`          text         DEFAULT NULL,
  `criado_em`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `respondido_em` datetime     DEFAULT NULL,
  `expira_em`     datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_pedido`  (`pedido_id`),
  KEY `idx_registo` (`registo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_section_admins'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_section_admins` (
  `id`        int(11)      NOT NULL AUTO_INCREMENT,
  `module`    varchar(50)  NOT NULL,
  `user_code` varchar(50)  NOT NULL,
  `user_name` varchar(200) DEFAULT NULL,
  `added_by`  varchar(50)  NOT NULL,
  `added_at`  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mod_user` (`module`,`user_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_rds_grupo_categoria'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_rds_grupo_categoria` (
  `grupo_id`     int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  PRIMARY KEY (`grupo_id`,`categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_servdoc_edit_request'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_servdoc_edit_request` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `id_docente`  int(11)      NOT NULL,
  `pedido_em`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `motivo`      varchar(500) DEFAULT NULL,
  `aprovado`    tinyint(4)   NOT NULL DEFAULT 0,
  `aprovado_em` datetime     DEFAULT NULL,
  `editado_em`  datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_docente` (`id_docente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_labs_ensino'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_labs_ensino` (
  `lab_id`     varchar(512) NOT NULL,
  `designacao` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`lab_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_equipmentdeq_access'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_equipmentdeq_access` (
  `id`           int(11)  NOT NULL AUTO_INCREMENT,
  `user_id`      int(11)  NOT NULL,
  `equipment_id` int(11)  NOT NULL,
  `granted_by`   int(11)  NOT NULL,
  `granted_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_equip` (`user_id`,`equipment_id`),
  KEY `equipment_id` (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_indisponibilidade_users'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_indisponibilidade_users` (
  `id`    int(11)      NOT NULL AUTO_INCREMENT,
  `nome`  varchar(100) NOT NULL,
  `sigla` text         NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_availability'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_availability` (
  `id`          int(11)    NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)    NOT NULL,
  `slot_date`   date       NOT NULL,
  `slot_code`   enum('M1','M2','T1','T2') NOT NULL,
  `unavailable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at`  timestamp  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_slot` (`user_id`,`slot_date`,`slot_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$ddls['infodeqb_lab_responsibles'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_lab_responsibles` (
  `id`      int(11)     NOT NULL AUTO_INCREMENT,
  `user_id` int(11)     NOT NULL,
  `lab_id`  varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// SEMPRE A ÚLTIMA — depende de infodeqb_rds_registo
$ddls['infodeqb_rds_registo_acessos'] = "
CREATE TABLE IF NOT EXISTS `infodeqb_rds_registo_acessos` (
  `id`         int(11)     NOT NULL AUTO_INCREMENT,
  `registo_id` int(11)     NOT NULL,
  `lab_id`     varchar(50) NOT NULL,
  `criado_em`  datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_registo_lab` (`registo_id`,`lab_id`),
  KEY `idx_registo` (`registo_id`),
  KEY `idx_lab`     (`lab_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

foreach ($ddls as $tblName => $ddl) {
    if (in_array($tblName, $tgtTables)) {
        log_skip("$tblName — já existe");
        continue;
    }
    try {
        $pdoTgt->exec($ddl);
        log_ok("$tblName — criada");
    } catch (PDOException $e) {
        log_err("$tblName: " . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════
// FASE 4 — Adicionar colunas novas a tabelas existentes
// ════════════════════════════════════════════════════════════
section("Fase 4 — Adicionar colunas novas");

$alterations = [
    'infodeqb_rds_registo' => [
        ['substitui_registo', "ALTER TABLE `infodeqb_rds_registo` ADD COLUMN `substitui_registo` int(11) DEFAULT NULL"],
    ],
    'infodeqb_rds_colaborador' => [
        ['emergency_contact_name',   "ALTER TABLE `infodeqb_rds_colaborador` ADD COLUMN `emergency_contact_name`   varchar(250) DEFAULT NULL"],
        ['emergency_contact_number', "ALTER TABLE `infodeqb_rds_colaborador` ADD COLUMN `emergency_contact_number` varchar(50)  DEFAULT NULL"],
    ],
];

$tgtTables = $pdoTgt->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($alterations as $tbl => $cols) {
    if (!in_array($tbl, $tgtTables)) {
        log_skip("$tbl não existe — a saltar");
        continue;
    }
    $existingCols = getCols($pdoTgt, $TGT_DB, $tbl);
    foreach ($cols as $colDef) {
        list($colName, $sql) = $colDef;
        if (in_array($colName, $existingCols)) {
            log_skip("$tbl.`$colName` já existe");
            continue;
        }
        try {
            $pdoTgt->exec($sql);
            log_ok("$tbl.`$colName` adicionada");
        } catch (PDOException $e) {
            log_err("$tbl.`$colName`: " . $e->getMessage());
        }
    }
}

// ════════════════════════════════════════════════════════════
// FASE 5 — Migrar acessosid → infodeqb_rds_registo_acessos
// ════════════════════════════════════════════════════════════
section("Fase 5 — Migrar acessos a labs (acessosid → relacional)");

$nExisting = $pdoTgt->query("SELECT COUNT(*) FROM `infodeqb_rds_registo_acessos`")->fetchColumn();
if ($nExisting > 0) {
    log_skip("Tabela já tem $nExisting linhas — migração já feita");
} else {
    $rows = $pdoTgt->query(
        "SELECT autoid, acessosid FROM `infodeqb_rds_registo`
         WHERE acessosid IS NOT NULL AND TRIM(acessosid) != ''"
    )->fetchAll(PDO::FETCH_ASSOC);

    $stmt  = $pdoTgt->prepare("INSERT IGNORE INTO `infodeqb_rds_registo_acessos` (registo_id, lab_id) VALUES (?,?)");
    $total = 0;
    foreach ($rows as $row) {
        $ids = array_values(array_unique(array_filter(array_map('trim', explode(';', $row['acessosid'])))));
        foreach ($ids as $labId) {
            if ($labId !== '') { $stmt->execute([(int)$row['autoid'], $labId]); $total++; }
        }
    }
    log_ok("$total pares registo/lab inseridos a partir de " . count($rows) . " registos");
}

// ════════════════════════════════════════════════════════════
// RESUMO
// ════════════════════════════════════════════════════════════
$pdoTgt->exec("SET FOREIGN_KEY_CHECKS = 1");

section("Resumo final");
$final    = $pdoTgt->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$nAcessos = $pdoTgt->query("SELECT COUNT(*) FROM `infodeqb_rds_registo_acessos`")->fetchColumn();
$nRegs    = $pdoTgt->query("SELECT COUNT(*) FROM `infodeqb_rds_registo`")->fetchColumn();

echo "  BD destino      : $TGT_DB\n";
echo "  Total tabelas   : " . count($final) . "\n";
echo "  HR registos     : $nRegs\n";
echo "  HR acessos/labs : $nAcessos\n";
echo "\n!! CONCLUÍDO — APAGUE ESTE FICHEIRO DO SERVIDOR !!\n";
