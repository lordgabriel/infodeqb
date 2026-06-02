-- ============================================================
-- Migração: adicionar tabela infodeqb_dsd_docente_ano (snapshot por ano)
-- Executar no phpMyAdmin sobre a BD dsd_deqb
-- ============================================================
USE dsd_deqb;

-- 1. Criar tabela
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

-- 2. Popular com snapshot do ano letivo activo (copia dados actuais dos docentes)
INSERT INTO `infodeqb_dsd_docente_ano`
    (docente_id, ano_letivo_id, carreira_id, categoria_id, deti, h_slef, ref_ecdu, observacoes, ativo)
SELECT
    d.id,
    (SELECT id FROM infodeqb_dsd_ano_letivo WHERE ativo=1 ORDER BY id DESC LIMIT 1),
    d.carreira_id,
    d.categoria_id,
    d.deti,
    d.h_slef,
    d.ref_ecdu,
    d.observacoes,
    d.ativo
FROM infodeqb_dsd_docente d
ON DUPLICATE KEY UPDATE
    carreira_id  = VALUES(carreira_id),
    categoria_id = VALUES(categoria_id),
    deti         = VALUES(deti),
    h_slef       = VALUES(h_slef),
    ref_ecdu     = VALUES(ref_ecdu),
    observacoes  = VALUES(observacoes),
    ativo        = VALUES(ativo);

-- 3. Confirmar
SELECT 'Snapshot criado:' AS info, COUNT(*) AS registos FROM infodeqb_dsd_docente_ano;
