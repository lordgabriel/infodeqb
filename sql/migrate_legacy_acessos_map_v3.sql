-- ============================================================
-- Adenda ao mapa de acessos — v3
-- Tokens em falta no campo acessos (nomegab_old)
-- Importar após v1 e v2.
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `infodeqb_rds_gabinetes_map` (`deqid_old`, `nomegab_old`, `deqid_new`) VALUES
('E007MVS2',           'E007 (Manuel Simões)',    'E007_MVS'),
('E101A/B_nom',        'E101A/B',                 'E101A/B'),
('E102JBC_nom',        'E102 (João Campos)',       'E102B_JBC'),
('E102A_nom',          'E102A',                   'E102A_AMM'),
('E302B_nom',          'E302B',                   'E302_HMVMS'),
('E404A_nom',          'E404A',                   'E404_CMB'),
('E404BMD_nom',        'E404B (Madalena Dias)',    'E404_RJNS'),
('INESC_ENTRADA_nom',  'Entrada INESC',            'INESC ENTRADA');
