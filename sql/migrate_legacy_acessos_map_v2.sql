-- ============================================================
-- Adenda ao mapa de acessos — v2
-- Adiciona self-maps (deqids já em formato novo usados como antigos)
-- e correcções de formato.
-- Importar após migrate_legacy_acessos_setup.sql.
-- ============================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `infodeqb_rds_gabinetes_map` (`deqid_old`, `nomegab_old`, `deqid_new`) VALUES
-- Typo: traço em vez de underscore
('E102B-JBC',        'E102B (João Campos) [traço]',  'E102B_JBC'),

-- gabid antigo sem sufixo → deqid actual
('E302B',            'E302 (Helena Soares)',          'E302_HMVMS'),
('E101A',            'E101A (Adélio Mendes)',          'E101A/B'),

-- Self-maps: deqid já em formato novo, usado directamente no acessosid
('E301_AMTS',        'E301_AMTS',                    'E301_AMTS'),
('E301_MFP',         'E301_MFP',                     'E301_MFP'),
('E302_AMTS',        'E302_AMTS',                    'E302_AMTS'),
('E302_MFP',         'E302_MFP',                     'E302_MFP'),
('E302_JDF',         'E302_JDF',                     'E302_JDF'),
('E303_MFP',         'E303_MFP',                     'E303_MFP'),
('E007_MVS',         'E007_MVS',                     'E007_MVS'),
('E008A_FJM',        'E008A_FJM',                    'E008A_FJM'),
('E008A_MVS',        'E008A_MVS',                    'E008A_MVS'),
('E403_JDF',         'E403_JDF',                     'E403_JDF'),
('E403_FDM',         'E403_FDM',                     'E403_FDM'),
('E305',             'E305',                          'E305'),
('E205',             'E205',                          'E205'),
('E143_FDM',         'E143_FDM',                     'E143_FDM'),
('E143_MMB',         'E143_MMB',                     'E143_MMB'),
('E304A_DGB',        'E304A_DGB',                    'E304A_DGB'),
('E102B_MMA',        'E102B_MMA',                    'E102B_MMA'),
('E404_CMB',         'E404_CMB',                     'E404_CMB'),
('E404_RJNS',        'E404_RJNS',                    'E404_RJNS'),
('E405_MEM',         'E405_MEM',                     'E405_MEM'),
('R001_DEQB',        'R001_DEQB',                    'R001_DEQB'),
('INESC 4.0C_LEPABE','INESC 4.0C_LEPABE',            'INESC 4.0C_LEPABE'),
('INESC3.0A_LEPABE', 'INESC3.0A_LEPABE',             'INESC3.0A_LEPABE'),
('INESC 4.0C_CEFT',  'INESC 4.0C_CEFT',             'INESC 4.0C_CEFT'),
('INESC3.0A_LSRE-LCM','INESC3.0A_LSRE-LCM',         'INESC3.0A_LSRE-LCM');
