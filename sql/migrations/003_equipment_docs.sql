-- Documentos associados a equipamentos (manuais, fichas técnicas, etc.)
CREATE TABLE IF NOT EXISTS `infodeqb_equipment_docs` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `equipment_id` INT NOT NULL,
    `laboratorio`  VARCHAR(50) NOT NULL,
    `filename`     VARCHAR(200) NOT NULL,
    `descricao`    VARCHAR(300) DEFAULT NULL,
    `uploaded_by`  INT DEFAULT NULL,
    `uploaded_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_equipment` (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
