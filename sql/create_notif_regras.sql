-- Regras de notificação por email: associa conjuntos de labs a destinatários CC/BCC
-- Usadas por hr/index.php ao criar novo registo
CREATE TABLE IF NOT EXISTS infodeqb_rds_notif_regras (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(200) NOT NULL,
    evento        VARCHAR(50)  NOT NULL DEFAULT 'novo_registo',
    tipo          ENUM('cc','bcc') NOT NULL DEFAULT 'bcc',
    labs_json     TEXT         NOT NULL COMMENT 'JSON array of deqid strings',
    emails        TEXT         NOT NULL COMMENT 'comma-separated email addresses',
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
