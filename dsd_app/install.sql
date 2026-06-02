-- ============================================================
-- DSD – Distribuição de Serviço Docente (DEQB / FEUP)
-- Script de instalação completo
-- Versão: pós-migração Opção B (infodeqb_dsd_docente_ano)
-- ============================================================

CREATE DATABASE IF NOT EXISTS dsd_deqb
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dsd_deqb;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_ano_letivo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `designacao` varchar(20) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_designacao` (`designacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `infodeqb_dsd_ano_letivo` (`designacao`, `ativo`) VALUES ('2026/2027', 1);

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_carreira` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `designacao` varchar(100) NOT NULL,
  `ordem` int(11) DEFAULT 99,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_designacao` (`designacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `infodeqb_dsd_carreira` (`designacao`, `ordem`) VALUES
  ('Docente DEQB',1),('Investigadores Permanentes',2),('Investigadores',3),
  ('Convidados',4),('Docente Parceiro',5),('Mobilidade Interna',6),
  ('Mobilidade Externa',7),('Outros Departamentos',8);

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_categoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `designacao` varchar(100) NOT NULL,
  `carreira_id` int(11) DEFAULT NULL,
  `ordem` int(11) DEFAULT 99,
  `slef_ref` decimal(5,2) DEFAULT 0.00,
  `ref_ecdu` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`carreira_id`) REFERENCES `infodeqb_dsd_carreira`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `infodeqb_dsd_categoria` (`designacao`, `carreira_id`, `ordem`, `slef_ref`, `ref_ecdu`) VALUES
  ('Catedrático',1,1,12,12),('Associado',1,2,14,12),('Auxiliar',1,3,16,12),
  ('Investigador Principal',2,1,0,0),('Investigador Auxiliar',2,2,0,0),
  ('Doutorado Equiparado a Investigador Principal',3,2,0,0),
  ('Doutorado Equiparado a Investigador Auxiliar',3,3,0,0),
  ('Doutorado de Nível Inicial',3,4,0,0),('Assistente de Investigação',3,5,0,0);

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_departamento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sigla` varchar(30) NOT NULL,
  `designacao` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sigla` (`sigla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `infodeqb_dsd_departamento` (`sigla`) VALUES
  ('DEQB'),('LSRE-LCM'),('LEPABE'),('CEFT'),('CONSTRUCT'),
  ('DECG'),('DEEC'),('DEGI'),('DEI'),('DEMec'),('FCUP'),('UA');

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_docente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(200) NOT NULL,
  `nome_curto` varchar(60) DEFAULT NULL,
  `departamento_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`departamento_id`) REFERENCES `infodeqb_dsd_departamento`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_docente_ano` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `docente_id` int(11) NOT NULL,
  `ano_letivo_id` int(11) NOT NULL,
  `carreira_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `deti` decimal(4,2) DEFAULT 1.00,
  `h_slef` decimal(6,2) DEFAULT 0.00,
  `ref_ecdu` decimal(6,2) DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_docente_ano` (`docente_id`,`ano_letivo_id`),
  FOREIGN KEY (`docente_id`) REFERENCES `infodeqb_dsd_docente`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ano_letivo_id`) REFERENCES `infodeqb_dsd_ano_letivo`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`carreira_id`) REFERENCES `infodeqb_dsd_carreira`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`categoria_id`) REFERENCES `infodeqb_dsd_categoria`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_plano_estudo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sigla` varchar(30) NOT NULL,
  `designacao` varchar(200) DEFAULT NULL,
  `tipo_curso` enum('Lic','M','D','') DEFAULT '',
  `ordem` int(11) DEFAULT 99,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sigla` (`sigla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `infodeqb_dsd_plano_estudo` (`sigla`, `designacao`, `tipo_curso`, `ordem`) VALUES
  ('L.EQ', NULL, '', 10),
  ('L.BIO', NULL, '', 30),
  ('L.EA', NULL, '', 50),
  ('L.EC', NULL, '', 90),
  ('L:EF', NULL, '', 70),
  ('L.EIC', NULL, '', 110),
  ('L.EMAT', NULL, '', 120),
  ('L.EMG', NULL, '', 130),
  ('L.EEC', NULL, '', 100),
  ('M.EQ', NULL, '', 20),
  ('M.BIO', NULL, '', 40),
  ('M.EA', NULL, '', 60),
  ('M:EF', NULL, '', 80),
  ('M.EMAT', NULL, '', 140),
  ('MEB', NULL, '', 150),
  ('MESHO', NULL, '', 160),
  ('MMC', NULL, '', 170),
  ('PDEQB', NULL, '', 180),
  ('PDEA', NULL, '', 190),
  ('PDQUI', NULL, '', 210),
  ('CT', NULL, '', 220),
  ('Form. Cont.', NULL, '', 240),
  ('PRODEF', NULL, '', 200),
  ('BCBD', NULL, '', 99),
  ('DEQB', NULL, '', 99),
  ('Sabática', NULL, '', 99);

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_area_cientifica` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sigla` varchar(10) NOT NULL,
  `designacao` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sigla` (`sigla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `infodeqb_dsd_area_cientifica` (`sigla`) VALUES
  ('TQM'),('FTR'),('ERP'),('BIO'),('AMB'),('QUI'),
  ('FIS'),('MMN'),('ENE'),('ESP'),('OUTR');

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_uc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(200) DEFAULT NULL,
  `plano_resp_id` int(11) DEFAULT NULL,
  `codigo` varchar(30) DEFAULT NULL,
  `designacao` varchar(300) NOT NULL,
  `ano` varchar(20) DEFAULT NULL,
  `semestre` enum('1S','2S','A','') DEFAULT '',
  `especializacao` varchar(100) DEFAULT NULL,
  `ac_deq` varchar(10) DEFAULT NULL,
  `outros_planos` varchar(200) DEFAULT NULL,
  `tipo` enum('OB','OPT','') DEFAULT 'OB',
  `tipo_curso` enum('Lic','M','D','') DEFAULT '',
  `ativo` tinyint(1) DEFAULT 1,
  `h_T`   decimal(5,2) DEFAULT 0.00,
  `h_TP`  decimal(5,2) DEFAULT 0.00,
  `h_L`   decimal(5,2) DEFAULT 0.00,
  `h_Sem` decimal(5,2) DEFAULT 0.00,
  `h_OT`  decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`plano_resp_id`) REFERENCES `infodeqb_dsd_plano_estudo`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_uc_area` (
  `uc_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `principal` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`uc_id`,`area_id`),
  FOREIGN KEY (`uc_id`) REFERENCES `infodeqb_dsd_uc`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`area_id`) REFERENCES `infodeqb_dsd_area_cientifica`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_uc_ocorrencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uc_id` int(11) NOT NULL,
  `plano_id` int(11) DEFAULT NULL,
  `ano_letivo_id` int(11) NOT NULL,
  `estudantes` int(11) DEFAULT 0,
  `f_slef` decimal(4,2) DEFAULT 1.00,
  `outros_planos` varchar(200) DEFAULT NULL,
  `semanas` decimal(6,2) DEFAULT 13.00,
  `n_turmas_T` decimal(6,4) DEFAULT 0.0000,
  `n_turmas_TP` decimal(6,4) DEFAULT 0.0000,
  `n_turmas_L` decimal(6,4) DEFAULT 0.0000,
  `n_turmas_Sem` decimal(6,4) DEFAULT 0.0000,
  `n_turmas_OT` decimal(6,4) DEFAULT 0.0000,
  `horas_T` decimal(6,3) DEFAULT 0.000,
  `horas_TP` decimal(6,3) DEFAULT 0.000,
  `horas_L` decimal(6,3) DEFAULT 0.000,
  `horas_Sem` decimal(6,3) DEFAULT 0.000,
  `horas_OT` decimal(6,3) DEFAULT 0.000,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ocorrencia` (`uc_id`,`ano_letivo_id`,`plano_id`),
  FOREIGN KEY (`uc_id`) REFERENCES `infodeqb_dsd_uc`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`plano_id`) REFERENCES `infodeqb_dsd_plano_estudo`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`ano_letivo_id`) REFERENCES `infodeqb_dsd_ano_letivo`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `infodeqb_dsd_distribuicao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ano_letivo_id` int(11) NOT NULL,
  `ocorrencia_id` int(11) DEFAULT NULL,
  `uc_id` int(11) DEFAULT NULL,
  `docente_id` int(11) NOT NULL,
  `rotulo` varchar(50) DEFAULT NULL,
  `dsd_por_docente` tinyint(1) DEFAULT 1,
  `regente` tinyint(1) DEFAULT 0,
  `semanas` decimal(6,2) DEFAULT 13.00,
  `turmas_T` decimal(6,4) DEFAULT 0.0000,
  `horas_T` decimal(6,3) DEFAULT 0.000,
  `turmas_TP` decimal(6,4) DEFAULT 0.0000,
  `horas_TP` decimal(6,3) DEFAULT 0.000,
  `turmas_L` decimal(6,4) DEFAULT 0.0000,
  `horas_L` decimal(6,3) DEFAULT 0.000,
  `turmas_Sem` decimal(6,4) DEFAULT 0.0000,
  `horas_Sem` decimal(6,3) DEFAULT 0.000,
  `turmas_OT` decimal(6,4) DEFAULT 0.0000,
  `horas_OT` decimal(6,3) DEFAULT 0.000,
  `h_tese` decimal(6,2) DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`ano_letivo_id`) REFERENCES `infodeqb_dsd_ano_letivo`(`id`),
  FOREIGN KEY (`ocorrencia_id`) REFERENCES `infodeqb_dsd_uc_ocorrencia`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uc_id`) REFERENCES `infodeqb_dsd_uc`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`docente_id`) REFERENCES `infodeqb_dsd_docente`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── View principal ───────────────────────────────────────────
CREATE OR REPLACE VIEW `dsd_v_distribuicao` AS
SELECT
    d.id AS dist_id,
    al.id AS ano_letivo_id, al.designacao AS ano_letivo,
    pe.sigla AS plano,
    uc.id AS uc_id, uc.designacao AS uc_nome,
    uc.semestre, uc.ano, uc.ac_deq, uc.tipo,
    o.id AS ocorrencia_id, o.estudantes, o.f_slef,
    o.semanas AS oc_semanas,
    o.n_turmas_T, o.horas_T AS oc_hT,
    o.n_turmas_TP, o.horas_TP AS oc_hTP,
    o.n_turmas_L, o.horas_L AS oc_hL,
    o.n_turmas_Sem, o.horas_Sem AS oc_hSem,
    o.n_turmas_OT, o.horas_OT AS oc_hOT,
    (o.n_turmas_T*o.horas_T + o.n_turmas_TP*o.horas_TP + o.n_turmas_L*o.horas_L
     + o.n_turmas_Sem*o.horas_Sem + o.n_turmas_OT*o.horas_OT) * o.semanas / 13 AS oc_hs_total,
    doc.id AS docente_id, doc.nome AS docente_nome,
    da.h_slef AS doc_ref_slef, da.ref_ecdu,
    car.designacao AS carreira, car.ordem AS ordem_carreira,
    cat.designacao AS categoria,
    dep.sigla AS depto,
    d.dsd_por_docente, d.regente, d.rotulo,
    d.semanas, d.turmas_T, d.horas_T, d.turmas_TP, d.horas_TP,
    d.turmas_L, d.horas_L, d.turmas_Sem, d.horas_Sem,
    d.turmas_OT, d.horas_OT, d.h_tese, d.observacoes,
    (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
     + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas / 13 AS hs,
    (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
     + d.turmas_Sem*d.horas_Sem + d.turmas_OT*d.horas_OT) * d.semanas AS ht,
    CASE WHEN d.dsd_por_docente = 1 THEN
        (d.turmas_T*d.horas_T + d.turmas_TP*d.horas_TP + d.turmas_L*d.horas_L
         + d.turmas_Sem*d.horas_Sem) * d.semanas / 13 * o.f_slef
    ELSE 0 END AS h_slef_uc,
    d.turmas_OT * d.horas_OT * d.semanas / 13 AS h_ot_norm
FROM infodeqb_dsd_distribuicao d
JOIN infodeqb_dsd_ano_letivo al ON d.ano_letivo_id = al.id
LEFT JOIN infodeqb_dsd_uc_ocorrencia o ON d.ocorrencia_id = o.id
LEFT JOIN infodeqb_dsd_uc uc ON o.uc_id = uc.id
LEFT JOIN infodeqb_dsd_plano_estudo pe ON COALESCE(o.plano_id, uc.plano_id) = pe.id
JOIN infodeqb_dsd_docente doc ON d.docente_id = doc.id
LEFT JOIN infodeqb_dsd_docente_ano da ON da.docente_id = doc.id AND da.ano_letivo_id = al.id
LEFT JOIN infodeqb_dsd_carreira car ON da.carreira_id = car.id
LEFT JOIN infodeqb_dsd_categoria cat ON da.categoria_id = cat.id
LEFT JOIN infodeqb_dsd_departamento dep ON doc.departamento_id = dep.id;

SET FOREIGN_KEY_CHECKS = 1;
-- FIM
