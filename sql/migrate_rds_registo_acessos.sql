-- ============================================================
-- Migração: acessos a labs do HR de string para tabela relacional
-- BD: feupptdeqb (produção: base de dados do deq.fe.up.pt)
--
-- Idempotente: pode ser corrida múltiplas vezes sem erros.
-- Passos:
--   1. Criar tabela infodeqb_rds_registo_acessos (se não existir)
--   2. Popular a tabela a partir de acessosid (INSERT IGNORE)
-- ============================================================

-- 1. Criar tabela relacional (idempotente)
CREATE TABLE IF NOT EXISTS infodeqb_rds_registo_acessos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    registo_id INT NOT NULL,
    lab_id     VARCHAR(50) NOT NULL,
    criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_registo_lab (registo_id, lab_id),
    INDEX idx_registo (registo_id),
    INDEX idx_lab     (lab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Popular a partir das strings acessosid existentes
--    (só processa registos onde acessosid não é nulo/vazio)
--
-- NOTA: MySQL não tem um equivalente directo de JSON_TABLE para strings
-- delimitadas por ';'. O script PHP abaixo faz a migração correctamente.
-- Se preferir SQL puro, use o bloco comentado no final.
--
-- Execute o seguinte script PHP no servidor de produção:
-- (guarde como migrate_rds_acessos.php e corra via HTTP ou CLI)
--
/*
<?php
require_once '/home/deqfeuppt/public_html/deqbwww.php';
$pdo = Database::connect();
$rows = $pdo->query(
    "SELECT autoid, acessosid FROM infodeqb_rds_registo
     WHERE acessosid IS NOT NULL AND TRIM(acessosid) != ''"
)->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare(
    'INSERT IGNORE INTO infodeqb_rds_registo_acessos (registo_id, lab_id) VALUES (?,?)'
);
$total = 0;
foreach ($rows as $row) {
    $ids = array_values(array_unique(array_filter(
        array_map('trim', explode(';', $row['acessosid']))
    )));
    foreach ($ids as $labId) {
        if ($labId !== '') { $stmt->execute([$row['autoid'], $labId]); $total++; }
    }
}
echo "Migrated $total lab access rows from " . count($rows) . " registos\n";
Database::disconnect();
*/

-- Alternativa SQL (requer MySQL 8.0+ com JSON_TABLE, ou usa WHILE loop em stored proc):
-- Para MySQL 5.7/8.0 em produção, recomenda-se o script PHP acima.

-- Verificação pós-migração:
-- SELECT COUNT(*) FROM infodeqb_rds_registo_acessos;
-- SELECT r.autoid, r.acessosid, COUNT(a.id) AS n_labs_relacional
--   FROM infodeqb_rds_registo r
--   LEFT JOIN infodeqb_rds_registo_acessos a ON a.registo_id = r.autoid
--   WHERE r.acessosid IS NOT NULL AND TRIM(r.acessosid) != ''
--   GROUP BY r.autoid
--   HAVING n_labs_relacional = 0
--   LIMIT 10;
-- (deve devolver 0 linhas após migração bem sucedida)
