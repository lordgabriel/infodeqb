-- ============================================================
-- Tabela de validações de acesso por responsável de espaço
-- Executar uma vez em produção e desenvolvimento
-- ============================================================

CREATE TABLE IF NOT EXISTS `infodeqb_rds_validacao` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `pedido_id`     INT(11)       DEFAULT NULL  COMMENT 'FK infodeqb_rds_pedido.id — NULL para registos directos',
  `registo_id`    INT(11)       DEFAULT NULL  COMMENT 'FK infodeqb_rds_registo.autoid — para registos directos',
  `deq_id`        VARCHAR(20)   NOT NULL      COMMENT 'deqid do espaço (infodeqb_rds_gabinetes.deqid)',
  `gab_nome`      VARCHAR(100)  DEFAULT NULL  COMMENT 'Nome(s) do(s) espaço(s) — lista separada por vírgula se agrupado',
  `labs_json`     TEXT          DEFAULT NULL  COMMENT 'JSON [{deq_id,gab_nome},...] de todos os espaços agrupados neste pedido',
  `resp_codigo`   VARCHAR(20)   NOT NULL      COMMENT 'Código UP do responsável (email = up<codigo>@up.pt)',
  `resp_nome`     VARCHAR(100)  DEFAULT NULL  COMMENT 'Nome do responsável (desnormalizado)',
  `token`         VARCHAR(64)   NOT NULL      COMMENT 'Token seguro para o link de validação',
  `status`        ENUM('Pendente','Validado','Rejeitado') NOT NULL DEFAULT 'Pendente',
  `nota`          TEXT          DEFAULT NULL  COMMENT 'Nota do responsável (obrigatória em rejeição)',
  `criado_em`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `respondido_em` DATETIME      DEFAULT NULL,
  `expira_em`     DATETIME      DEFAULT NULL  COMMENT 'NULL = sem expiração; usado para invalidar links reenviados',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_pedido`   (`pedido_id`),
  KEY `idx_registo`  (`registo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Validações de acesso a espaços pelos respectivos responsáveis';
