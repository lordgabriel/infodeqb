-- ============================================================
-- InfoDEQB / HR — Migração: tabelas para pedidos de alteração
-- Executar uma vez em deqfeuppt
-- ============================================================

-- Tabela de pedidos de alteração/novo registo submetidos por utilizadores
CREATE TABLE IF NOT EXISTS `infodeqb_rds_pedido` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `tipo`            ENUM('novo','alteracao') NOT NULL DEFAULT 'alteracao'
                    COMMENT 'novo = primeiro registo; alteracao = pedido de update a registo existente',
  `codigo`          VARCHAR(9)   NOT NULL               COMMENT 'Código FEUP de quem submete',
  `registo_id`      INT(11)      DEFAULT NULL            COMMENT 'autoid do infodeqb_rds_registo a alterar (NULL para novos)',
  `dados_json`      TEXT         NOT NULL                COMMENT 'Dados propostos em JSON',
  `campos_alterados` TEXT        DEFAULT NULL            COMMENT 'Lista de campos modificados (JSON array)',
  `observacoes`     TEXT         DEFAULT NULL            COMMENT 'Nota livre do utilizador',
  `status`          ENUM('Pendente','Aprovado','Rejeitado') NOT NULL DEFAULT 'Pendente',
  `criado_em`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processado_em`   DATETIME     DEFAULT NULL,
  `processado_por`  VARCHAR(30)  DEFAULT NULL,
  `notas_admin`     TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_codigo`  (`codigo`),
  KEY `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Pedidos de registo ou alteração submetidos por utilizadores';


-- Tabela de mapeamento grupo profissional ↔ categorias
-- (usada para filtrar as categorias disponíveis no formulário)
CREATE TABLE IF NOT EXISTS `infodeqb_rds_grupo_categoria` (
  `grupo_id`     INT(11) NOT NULL,
  `categoria_id` INT(11) NOT NULL,
  PRIMARY KEY (`grupo_id`, `categoria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mapeamento proposto — ajustar conforme necessário
INSERT IGNORE INTO `infodeqb_rds_grupo_categoria` (`grupo_id`, `categoria_id`) VALUES
-- Grupo 1: Pessoal Investigador das Universidades
(1,1),(1,2),(1,3),(1,5),(1,6),(1,7),
-- Grupo 2: Estudante de doutoramento
(2,4),(2,5),
-- Grupo 3: Estudante de Licenciatura ou Mestrado
(3,9),(3,80),
-- Grupo 4: Bolseiro de investigação científica
(4,3),(4,6),(4,9),(4,10),
-- Grupo 5: Investigador ou colaborador interno
(5,1),(5,2),(5,3),(5,5),(5,6),(5,7),(5,10),
-- Grupo 6: Investigador ou colaborador externo
(6,6),(6,9),(6,10),(6,80),
-- Grupo 7: Estudante em Estudos Avançados
(7,4),(7,9),
-- Grupo 8: Docente
(8,11),(8,12),(8,13),(8,14),(8,15),(8,16),(8,17),
-- Grupo 9: Investigador permanente
(9,1),(9,2),(9,3),(9,5),(9,6);
