-- ============================================================
-- Migração Opção B: mover campos variáveis de infodeqb_dsd_docente
-- para infodeqb_dsd_docente_ano
-- Executar no phpMyAdmin sobre a BD dsd_deqb
-- ============================================================
USE dsd_deqb;

-- 1. Criar tabela infodeqb_dsd_docente_ano se não existir
CREATE TABLE IF NOT EXISTS `infodeqb_dsd_docente_ano` (
  `id`            int(11) NOT NULL AUTO_INCREMENT,
  `docente_id`    int(11) NOT NULL,
  `ano_letivo_id` int(11) NOT NULL,
  `carreira_id`   int(11) DEFAULT NULL,
  `categoria_id`  int(11) DEFAULT NULL,
  `deti`          decimal(4,2) DEFAULT 1.00,
  `h_slef`        decimal(6,2) DEFAULT 0.00,
  `ref_ecdu`      decimal(6,2) DEFAULT 0.00,
  `observacoes`   text DEFAULT NULL,
  `ativo`         tinyint(1) DEFAULT 1,
  `criado_em`     timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_docente_ano` (`docente_id`, `ano_letivo_id`),
  FOREIGN KEY (`docente_id`)    REFERENCES `infodeqb_dsd_docente`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`ano_letivo_id`) REFERENCES `infodeqb_dsd_ano_letivo`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`carreira_id`)   REFERENCES `infodeqb_dsd_carreira`(`id`)   ON DELETE SET NULL,
  FOREIGN KEY (`categoria_id`)  REFERENCES `infodeqb_dsd_categoria`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Popular infodeqb_dsd_docente_ano com os dados actuais de infodeqb_dsd_docente
--    para TODOS os anos letivos existentes
INSERT INTO `infodeqb_dsd_docente_ano`
    (docente_id, ano_letivo_id, carreira_id, categoria_id, deti, h_slef, ref_ecdu, observacoes, ativo)
SELECT
    d.id,
    al.id,
    d.carreira_id,
    d.categoria_id,
    d.deti,
    d.h_slef,
    d.ref_ecdu,
    d.observacoes,
    d.ativo
FROM infodeqb_dsd_docente d, infodeqb_dsd_ano_letivo al
ON DUPLICATE KEY UPDATE
    carreira_id  = VALUES(carreira_id),
    categoria_id = VALUES(categoria_id),
    deti         = VALUES(deti),
    h_slef       = VALUES(h_slef),
    ref_ecdu     = VALUES(ref_ecdu),
    observacoes  = VALUES(observacoes),
    ativo        = VALUES(ativo);

-- 3. Confirmar dados migrados
SELECT 'Snapshots criados:' AS info, COUNT(*) AS total FROM infodeqb_dsd_docente_ano;

-- 4. Remover campos variáveis de infodeqb_dsd_docente
--    (só depois de confirmar que o passo anterior correu bem)

-- 4a. Remover foreign keys primeiro
ALTER TABLE `infodeqb_dsd_docente`
    DROP FOREIGN KEY `infodeqb_dsd_docente_ibfk_1`,
    DROP FOREIGN KEY `infodeqb_dsd_docente_ibfk_2`;

-- 4b. Remover os campos
ALTER TABLE `infodeqb_dsd_docente`
    DROP COLUMN `carreira_id`,
    DROP COLUMN `categoria_id`,
    DROP COLUMN `deti`,
    DROP COLUMN `h_slef`,
    DROP COLUMN `ref_ecdu`,
    DROP COLUMN `observacoes`,
    DROP COLUMN `ativo`;

-- 5. Confirmar estrutura final
DESCRIBE infodeqb_dsd_docente;
DESCRIBE infodeqb_dsd_docente_ano;

-- ============================================================
-- Após correr este script, infodeqb_dsd_docente fica apenas com:
--   id, nome, departamento_id
-- E infodeqb_dsd_docente_ano tem todos os campos variáveis por ano.
-- ============================================================
