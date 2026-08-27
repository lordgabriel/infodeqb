-- ============================================================
-- InfoDEQB / HR — Re-importação limpa de deqfeuppt → feupptdeqb
--
-- Objectivo: apagar os dados HR actuais do novo sistema e
--            reimportar a partir do sistema antigo (deqfeuppt).
--
-- Execução (local e produção):
--   mysql -u root feupptdeqb < migrate_hr_reimport.sql
-- ou correr em phpMyAdmin seleccionando a BD feupptdeqb.
--
-- ATENÇÃO: apaga todos os dados HR do novo sistema.
-- Confirmar com o utilizador antes de executar em produção.
-- ============================================================

USE feupptdeqb;
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── 1. Limpar tabelas HR do novo sistema ────────────────────────
TRUNCATE TABLE infodeqb_rds_registo_acessos;
TRUNCATE TABLE infodeqb_rds_validacao;
TRUNCATE TABLE infodeqb_rds_pedido;
TRUNCATE TABLE infodeqb_rds_registo;
TRUNCATE TABLE infodeqb_rds_colaborador;
TRUNCATE TABLE infodeqb_rds_gabinetes;
TRUNCATE TABLE infodeqb_rds_responsaveis;
TRUNCATE TABLE infodeqb_rds_grupo_categoria;
TRUNCATE TABLE infodeqb_rds_grupo;
TRUNCATE TABLE infodeqb_rds_categoria;

-- ── 2. Reimportar do sistema antigo ─────────────────────────────
INSERT INTO infodeqb_rds_categoria      SELECT * FROM deqfeuppt.rds_categoria;
INSERT INTO infodeqb_rds_grupo          SELECT * FROM deqfeuppt.rds_grupo;
INSERT INTO infodeqb_rds_colaborador    SELECT * FROM deqfeuppt.rds_colaborador;
INSERT INTO infodeqb_rds_responsaveis   SELECT * FROM deqfeuppt.rds_responsaveis;
INSERT INTO infodeqb_rds_gabinetes      SELECT * FROM deqfeuppt.rds_gabinetes;
-- infodeqb_rds_registo tem coluna extra 'substitui_registo' (não existe no antigo)
INSERT INTO infodeqb_rds_registo
  (autoid, codigo, datainicio, datafim, responsavel, outroresponsavel, unidade, acessodeq,
   grupo, categoria, local_trabalho, extensao, acessos, acessosid, curso, createdate,
   updatedate, dataregisto, datacica, deleted, status, dataativo, datainativo, datanotificacao)
SELECT autoid, codigo, datainicio, datafim, responsavel, outroresponsavel, unidade, acessodeq,
       grupo, categoria, local_trabalho, extensao, acessos, acessosid, curso, createdate,
       updatedate, dataregisto, datacica, deleted, status, dataativo, datainativo, datanotificacao
FROM deqfeuppt.rds_registo;
-- rds_pedido, rds_validacao e rds_grupo_categoria não existiam no sistema antigo
-- (criadas de raiz no novo sistema) — ficam vazias após migração, o que é o correcto.

SET FOREIGN_KEY_CHECKS = 1;

-- ── 3. Corrigir ENUM do pedido (pode já estar correcto localmente) ──
-- O sistema antigo pode ter rds_pedido.status sem 'Aguarda_SIGARRA',
-- 'Concluido' e 'Cancelado'. Garantir que o novo sistema tem o ENUM completo.
ALTER TABLE infodeqb_rds_pedido
  MODIFY COLUMN `status`
    ENUM('Pendente','Aprovado','Rejeitado','Aguarda_SIGARRA','Concluido','Cancelado')
    NOT NULL DEFAULT 'Pendente';

-- ── 4. Reaplicar mapeamento grupo → categoria revisto ───────────
-- (migrate_grupo_categorias.sql + v2 — só as linhas de dados)

-- Novas categorias adicionadas após a migração inicial
INSERT IGNORE INTO infodeqb_rds_categoria (categoriaid, categoria) VALUES
  (18, 'Investigador Auxiliar'),
  (19, 'Investigador Principal'),
  (20, 'Equiparado a Investigador Auxiliar'),
  (21, 'Equiparado a Investigador Coordenador'),
  (22, 'Equiparado a Investigador Principal'),
  (23, 'Técnico'),
  (24, 'Outro');

-- Substituir o mapeamento grupo → categoria pelo mapeamento revisto
TRUNCATE TABLE infodeqb_rds_grupo_categoria;

INSERT INTO infodeqb_rds_grupo_categoria (grupo_id, categoria_id) VALUES
-- Grupo 1: Pessoal Investigador das Universidades
(1,  4),(1,  9),(1, 10),(1, 18),(1, 19),(1, 20),(1, 21),(1, 22),
-- Grupo 5: Investigador ou colaborador interno
(5,  1),(5,  2),(5,  3),(5,  5),(5,  6),(5,  7),(5, 10),
-- Grupo 8: Docente
(8, 11),(8, 12),(8, 13),(8, 14),(8, 15),(8, 16),(8, 17),
-- Grupo 9: Investigador permanente
(9,  1),(9,  2),(9,  3),(9,  5),(9,  6),
-- Grupos 2, 3, 4, 6, 7 ficam sem categorias (migrate_grupo_categorias_v2)
-- Grupo 10 (caso exista): Pessoal não docente / técnico-administrativo
(10, 23),(10, 24);

-- ── 5. Reconstruir infodeqb_rds_registo_acessos ─────────────────
-- A tabela foi truncada no passo 1. Reconstruir a partir dos
-- valores acessosid em infodeqb_rds_registo.
-- NOTA: MySQL não tem suporte nativo a split de strings delimitadas.
-- Usar o script PHP: infodeqb/sql/migrate_rds_acessos_run.php
-- (ou correr migrate_rds_registo_acessos.sql + o PHP embebido)
--
-- Confirmar execução do PHP após este script.

-- ── 6. Substituir infodeqb_rds_gabinetes pela nova estrutura ────
-- Nova lista de espaços com responsáveis actualizados (reestruturação 2025/2026).
-- A tabela infodeqb_rds_responsaveis (antiga, Codigo/respespaco/Sigla) não é usada
-- pelo novo sistema — toda a info de responsável está em infodeqb_rds_gabinetes.responsavel.

TRUNCATE TABLE infodeqb_rds_gabinetes;

INSERT INTO infodeqb_rds_gabinetes (gabid, deqid, edificio, piso, nomegab, nomegab_en, responsavel, visible) VALUES
-- ── Edifício B ──────────────────────────────────────────────────
('B233','B233_CDP','B','Edifício B','B233 (Carlos Pintassilgo)','B233 (Carlos Pintassilgo)',246608,1),
('B233','B233_PJVG','B','Edifício B','B233 (Paulo Garcia)','B233 (Paulo Garcia)',249542,1),
('B234','B234_CDP','B','Edifício B','B234 (Carlos Pintassilgo)','B234 (Carlos Pintassilgo)',246608,1),
('B234','B234_PJVG','B','Edifício B','B234 (Paulo Garcia)','B234 (Paulo Garcia)',249542,1),
('B235','B235_CDP','B','Edifício B','B235 (Carlos Pintassilgo)','B235 (Carlos Pintassilgo)',246608,1),
('B235','B235_PJVG','B','Edifício B','B235 (Paulo Garcia)','B235 (Paulo Garcia)',249542,1),
('B236','B236_CDP','B','Edifício B','B236 (Carlos Pintassilgo)','B236 (Carlos Pintassilgo)',246608,1),
('B236','B236_PJVG','B','Edifício B','B236 (Paulo Garcia)','B236 (Paulo Garcia)',249542,1),
-- ── Edifício E — Piso 0 ─────────────────────────────────────────
('E001','E001','E','Piso 0','E001','E001',241300,1),
('E002','E002','E','Piso 0','E002','E002',246398,1),
('E003','E003','E','Piso 0','E003','E003',241311,1),
('E004','E004','E','Piso 0','E004','E004',232673,1),
('E005','E005','E','Piso 0','E005','E005',211360,1),
('E006','E006','E','Piso 0','E006','E006',230268,1),
('E007','E007_APB','E','Piso 0','E007 (Anabela Borges)','E007 (Anabela Borges)',483269,1),
('E007','E007_MVS','E','Piso 0','E007 (Manuel Simões)','E007 (Manuel Simões)',448910,1),
('E008','E008A_AASP','E','Piso 0','E008A (Ana Pereira)','E008A (Ana Pereira)',356607,1),
('E008','E008A_APB','E','Piso 0','E008A (Anabela Borges)','E008A (Anabela Borges)',483269,1),
('E008','E008A_FJM','E','Piso 0','E008A (Filipe Mergulhão)','E008A (Filipe Mergulhão)',403865,1),
('E008','E008A_LCFG','E','Piso 0','E008A (Luciana Gomes)','E008A (Luciana Gomes)',464327,1),
('E008','E008A_MVS','E','Piso 0','E008A (Manuel Simões)','E008A (Manuel Simões)',448910,1),
('E008','E008A_NFA','E','Piso 0','E008A (Nuno Azevedo)','E008A (Nuno Azevedo)',465511,1),
('E008','E008B_AASP','E','Piso 0','E008B (Ana Pereira)','E008B (Ana Pereira)',356607,1),
('E008','E008B_FJM','E','Piso 0','E008B (Filipe Mergulhão)','E008B (Filipe Mergulhão)',403865,1),
('E008','E008B_LCFG','E','Piso 0','E008B (Luciana Gomes)','E008B (Luciana Gomes)',464327,1),
('E008','E008B_NFA','E','Piso 0','E008B (Nuno Azevedo)','E008B (Nuno Azevedo)',465511,1),
('E008','E009A_AASP','E','Piso 0','E009A (Ana Pereira)','E009A (Ana Pereira)',356607,1),
('E008','E009A_FJM','E','Piso 0','E009A (Filipe Mergulhão)','E009A (Filipe Mergulhão)',403865,1),
('E008','E009A_LCFG','E','Piso 0','E009A (Luciana Gomes)','E009A (Luciana Gomes)',464327,1),
('E008','E009B_AASP','E','Piso 0','E009B (Ana Pereira)','E009B (Ana Pereira)',356607,1),
('E008','E009B_FJM','E','Piso 0','E009B (Filipe Mergulhão)','E009B (Filipe Mergulhão)',403865,1),
('E008','E009B_LCFG','E','Piso 0','E009B (Luciana Gomes)','E009B (Luciana Gomes)',464327,1),
-- ── Edifício E — Piso 1 ─────────────────────────────────────────
('E101A','E101A/B','E','Piso 1','E101A/B (Adélio Mendes)','E101A/B (Adélio Mendes)',230268,1),
('E101B','E101C','E','Piso 1','E101C (Alexandre Ferreira)','E101C (Alexandre Ferreira)',448927,1),
('E101B','E101C','E','Piso 1','E101C (Ana Mafalda Ribeiro)','E101C (Ana Mafalda Ribeiro)',377662,1),
('E101C','E102A','E','Piso 1','E102A (Adélio Mendes)','E102A (Adélio Mendes)',230268,1),
('E101C','E102A','E','Piso 1','E102A (Tânia Lopes)','E102A (Tânia Lopes)',462390,1),
('E101C','E102A','E','Piso 1','E102A (Paula Dias)','E102A (Paula Dias)',488741,1),
('E102 (Arquivo)','E102B (Laser)','E','Piso 1','E102B (Laser)','E102B (Laser)',232673,1),
('E102B','E102B-JBC','E','Piso 1','E102B (João Campos)','E102B (João Campos)',209016,1),
('E102B','E102B_DSCF','E','Piso 1','E102B (Daniela Falcão)','E102B (Daniela Falcão)',418260,1),
('E102B','E102B_JDPA','E','Piso 1','E102B (José Daniel Araújo)','E102B (José Daniel Araújo)',458815,1),
('E102B','E102B_MMA','E','Piso 1','E102B (Manuel Alves)','E102B (Manuel Alves)',232673,1),
('E102B','E102B_VSBO','E','Piso 1','E102B (Vânia Oliveira)','E102B (Vânia Oliveira)',415078,1),
('E103','E103','E','Piso 1','E103','E103',209016,1),
('E104','E104','E','Piso 1','E104','E104',211784,1),
('E105','E105','E','Piso 1','E105','E105',467313,0),
('E105','E106','E','Piso 1','E106','E106',467313,1),
('E107','E107','E','Piso 1','E107','E107',246398,0),
('E108','E108','E','Piso 1','E108','E108',246398,0),
('E109','E109','E','Piso 1','E109','E109',246398,0),
('E111','E111','E','Piso 1','E111','E111',246398,0),
('E113','E113','E','Piso 1','E113','E113',246398,1),
('E143','E143_ADMP','E','Piso 1','E143 (Artur Pinto)','E143 (Artur Pinto)',489094,1),
('E143','E143_FDM','E','Piso 1','E143 (Fernão Magalhães)','E143 (Fernão Magalhães)',211360,1),
('E143','E143_MMB','E','Piso 1','E143 (Margarida Bastos)','E143 (Margarida Bastos)',210741,1),
-- ── Edifício E — Piso -1 ────────────────────────────────────────
('E-101','E-101','E','Piso -1','E-101','E-101',465511,1),
('E-102','E-102','E','Piso -1','E-102','E-102',465511,1),
('E-103','E-103','E','Piso -1','E-103','E-103',448910,1),
('E-104','E-104','E','Piso -1','E-104','E-104',464327,1),
('E-105','E-105','E','Piso -1','E-105','E-105',240013,1),
('E-140','E-140','E','Piso -1','E-140','E-140',246398,1),
('E-144','E-144','E','Piso -1','E-144','E-144',481720,1),
('E-146','E-146_LMM','E','Piso -1','E-146 (Miguel Madeira)','E-146 (Miguel Madeira)',241311,1),
('E-146','E-146_MAS','E','Piso -1','E-146 (Miguel Soria)','E-146 (Miguel Soria)',516425,1),
('E-172','E-172','E','Piso -1','Armário de Segurança','Security Cabinet',246398,0),
('E-177B|E-177D','E-177B|E-177D','E','Piso -1','Água p/ fins laboratoriais e gelo','Ice and Water for laboratory purposes',246398,1),
-- ── Edifício E — Piso 2 ─────────────────────────────────────────
('E201','E201_LSS','E','Piso 2','E201 (Lúcia Santos)','E201 (Lúcia Santos)',211784,1),
('E201','E201_NMRN','E','Piso 2','E201 (Nuno Ratola)','E201 (Nuno Ratola)',350296,1),
('E201','E201_SIVS','E','Piso 2','E201 (Sofia Sousa)','E201 (Sofia Sousa)',373448,1),
('E201','E201_VH','E','Piso 2','E201 (Vera Homem)','E201 (Vera Homem)',467313,1),
('E202','E202','E','Piso 2','E202','E202',230268,1),
('E203A','E203_JCMP','E','Piso 2','E203 (José Carlos Pires)','E203 (José Carlos Pires)',400639,1),
('E203A','E203_LMM','E','Piso 2','E203 (Miguel Madeira)','E203 (Miguel Madeira)',241311,1),
('E203A','E203_OPN','E','Piso 2','E203 (Olga Nunes)','E203 (Olga Nunes)',240013,1),
('E204A','E204A','E','Piso 2','E204A','E204A',241300,1),
('E205','E205','E','Piso 2','E205','E205',241300,1),
('E206','E206_ARP','E','Piso 2','E206 (Alexandra Pinto)','E206 (Alexandra Pinto)',209636,1),
('E206','E206_VSBO','E','Piso 2','E206 (Vânia Oliveira)','E206 (Vânia Oliveira)',415078,1),
('E218','E218','E','Piso 2','E218','E218',241311,0),
('E219','E219_FGM','E','Piso 2','E219 (Fernando Martins)','E219 (Fernando Martins)',241384,1),
('E219','E219_JCMP','E','Piso 2','E219 (José Carlos Pires)','E219 (José Carlos Pires)',400639,1),
('E220','E220','E','Piso 2','E220','E220',246398,0),
('E221','E221','E','Piso 2','E221','E221',246398,1),
('E224','E224','E','Piso 2','E224','E224',246398,1),
('E275','E275','E','Piso 2','E275','E275',246398,0),
-- ── Edifício E — Piso 3 ─────────────────────────────────────────
('E301','E301_AMTS','E','Piso 3','E301 (Adrián Silva)','E301 (Adrián Silva)',423841,1),
('E301','E301_MFP','E','Piso 3','E301 (Fernando Pereira)','E301 (Fernando Pereira)',246398,1),
('E302','E301_CGS','E','Piso 4','E301 (Cláudia Gomes Silva)','E301 (Cláudia Gomes Silva)',346678,1),
('E302','E301_OSGPS','E','Piso 4','E301 (Salomé Soares)','E301 (Salomé Soares)',444781,1),
('E303','E301_JDF','E','Piso 5','E301 (Joaquim Faria)','E301 (Joaquim Faria)',211768,1),
('E304','E301_OSGPS','E','Piso 6','E301 (Salomé Soares)','E301 (Salomé Soares)',444781,1),
('E302','E302_AMTS','E','Piso 3','E302 (Adrián Silva)','E302 (Adrián Silva)',423841,1),
('E302','E302_CGS','E','Piso 3','E302 (Cláudia Gomes Silva)','E302 (Cláudia Gomes Silva)',346678,1),
('E302','E302_JDF','E','Piso 3','E302 (Joaquim Faria)','E302 (Joaquim Faria)',211768,1),
('E302','E302_MFP','E','Piso 3','E302 (Fernando Pereira)','E302 (Fernando Pereira)',246398,1),
('E302B','E302_HMVMS','E','Piso 3','E302 (Helena Soares)','E302 (Helena Soares)',210637,1),
('E303','E303_LR','E','Piso 3','E303 (Lucília Ribeiro)','E303 (Lucília Ribeiro)',464644,0),
('E303','E303_MFP','E','Piso 3','E303 (Fernando Pereira)','E303 (Fernando Pereira)',246398,1),
('E303','E303_OSGPS','E','Piso 3','E303 (Salomé Soares)','E303 (Salomé Soares)',444781,0),
('E304','E304A_AMAF','E','Piso 3','E304A (António Ferreira)','E304A (António Ferreira)',345961,0),
('E304','E304A_DGB','E','Piso 3','E304A (Domingos Barbosa)','E304A (Domingos Barbosa)',209023,1),
('E304B','E304B_AMTS','E','Piso 3','E304B (Adrián Silva)','E304B (Adrián Silva)',423841,1),
('E304B','E304B_OSGPS','E','Piso 3','E304B (Salomé Soares)','E304B (Salomé Soares)',444781,1),
('E305','E305','E','Piso 3','E305','E305',347493,1),
('E306','E306','E','Piso 3','E306','E306',246398,1),
('E307','E307','E','Piso 3','E307','E307',246398,1),
('E318','E318','E','Piso 3','E318','E318',241311,1),
('E319','E319','E','Piso 3','E319','E319',246398,1),
('E320','E320','E','Piso 3','E320','E320',246398,1),
('E321','E321_JCMP','E','Piso 3','E321 (José Carlos Pires)','E321 (José Carlos Pires)',400639,1),
('E321','E321_SIVS','E','Piso 3','E321 (Sofia Sousa)','E321 (Sofia Sousa)',373448,1),
('E322','E322','E','Piso 3','E322','E322',246398,1),
('E324','E324','E','Piso 3','E324','E324',246398,1),
('E375','E375','E','Piso 3','E375','E375',246398,1),
-- ── Edifício E — Piso 4 ─────────────────────────────────────────
('E401','E401_AFPF','E','Piso 4','E401 (Alexandre Ferreira)','E401 (Alexandre Ferreira)',448927,1),
('E401','E401_AMR','E','Piso 4','E401 (Ana Mafalda Ribeiro)','E401 (Ana Mafalda Ribeiro)',377662,1),
('E401','E401_DFMR','E','Piso 4','E401 (Diogo Rodrigues)','E401 (Diogo Rodrigues)',700674,1),
('E402','E402_CGS','E','Piso 4','E402 (Cláudia Silva)','E402 (Cláudia Silva)',346678,1),
('E402','E402_VJPV','E','Piso 4','E402 (Vítor Vilar)','E402 (Vítor Vilar)',420880,1),
('E402','E402_YJAM','E','Piso 4','E402 (Yaidelin Manrique)','E402 (Yaidelin Manrique)',487539,1),
('E403N','E403_JDF','E','Piso 4','E403 (Joaquim Faria)','E403 (Joaquim Faria)',211768,1),
('E403S','E403_FDM','E','Piso 4','E403 (Fernão Magalhães)','E403 (Fernão Magalhães)',211360,1),
('E403S','E403_LMMA','E','Piso 4','E403 (Luísa Andrade)','E403 (Luísa Andrade)',487476,1),
('E404A','E404_CMB','E','Piso 4','E404 (Cidália Botelho)','E404 (Cidália Botelho)',211120,1),
('E404A','E404_VJPV','E','Piso 4','E404 (Vítor Vilar)','E404 (Vítor Vilar)',420880,1),
('E404B','E404_RJNS','E','Piso 4','E404 (Ricardo Santos)','E404 (Ricardo Santos)',347493,1),
('E405','E405_HISP','E','Piso 4','E405 (Helena Passos)','E405 (Helena Passos)',705613,1),
('E405','E405_MEM','E','Piso 4','E405 (Eugénia Macedo)','E405 (Eugénia Macedo)',705613,1),
('E405','E405JDF','E','Piso 4','E405 (Joaquim Faria)','E405 (Joaquim Faria)',211768,1),
('E406','E406','E','Piso 4','E406','E406',246398,1),
('E416','E416','E','Piso 4','E416','E416',246398,1),
('E418','E418','E','Piso 4','E418','E418',246398,1),
('E419','E419','E','Piso 4','E419','E419',246398,1),
('E421','E421','E','Piso 4','E421','E421',246398,1),
-- ── Edifício F ──────────────────────────────────────────────────
('F103A','F103A','F','Edifício F','F103A','F103A',548412,1),
('F301','F301_FXDDAM','F','Edifício F','F301','F301',519585,1),
('F301','F301_TSGT','F','Edifício F','F301','F301',462390,1),
-- ── INESC ───────────────────────────────────────────────────────
('INESC 4.0C','INESC 4.0C_CEFT','S','Piso 4','INESC Piso 4 (CEFT)','INESC Piso 4 (CEFT)',209016,1),
('INESC 4.0C','INESC 4.0C_LEPABE','S','Piso 4','INESC Piso 4 (LEPABE)','INESC Piso 4 (LEPABE)',241311,1),
('INESC 4.2','INESC 4.2','S','Piso 4','INESC Piso 4','INESC Piso 4',246398,1),
('INESC ENTRADA','INESC ENTRADA','S','Piso 0','INESC Entrada','INESC Entry',246398,0),
('INESC3.0A','INESC3.0A_LEPABE','S','Piso 3','INESC Piso 3 (LEPABE)','INESC Piso 3 (LEPABE)',241311,1),
('INESC3.0A','INESC3.0A_LSRE-LCM','S','Piso 3','INESC Piso 3 (LSRE-LCM)','INESC Piso 3 (LSRE-LCM)',211768,1),
-- ── ETAR ────────────────────────────────────────────────────────
('R001(ETAR)','R001_DEQB','R','ETAR','ETAR (DEQB)','ETAR (DEQB)',246398,1),
('R001(ETAR)','R001_JCMP','R','ETAR','ETAR (José Carlos Pires)','ETAR (José Carlos Pires)',400639,1),
('R001(ETAR)','R001_VJPV','R','ETAR','ETAR (Vítor Vilar)','ETAR (Vítor Vilar)',420880,1);

-- ── 6b. Actualizar acessosid nos registos antigos ────────────────
-- Para mudanças de formato puro (mesmo espaço, mesma pessoa, só o deqid mudou).
-- Registos com espaços extintos ou divididos ficam com o deqid antigo (stale, aceite).
-- ⚠ Executar DEPOIS de reimportar infodeqb_rds_registo (passo 2).

UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'B233CP','B233_CDP')    WHERE acessosid LIKE '%B233CP%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'B233PG','B233_PJVG')   WHERE acessosid LIKE '%B233PG%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'B234CP','B234_CDP')    WHERE acessosid LIKE '%B234CP%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'B234PG','B234_PJVG')   WHERE acessosid LIKE '%B234PG%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'B235CP','B235_CDP')    WHERE acessosid LIKE '%B235CP%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'B235PG','B235_PJVG')   WHERE acessosid LIKE '%B235PG%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'B236CP','B236_CDP')    WHERE acessosid LIKE '%B236CP%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'B236PG','B236_PJVG')   WHERE acessosid LIKE '%B236PG%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E007MVS','E007_MVS')   WHERE acessosid LIKE '%E007MVS%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E008AFJM','E008A_FJM') WHERE acessosid LIKE '%E008AFJM%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E008AMVS','E008A_MVS') WHERE acessosid LIKE '%E008AMVS%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E102BJBC','E102B-JBC') WHERE acessosid LIKE '%E102BJBC%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E102BMMA','E102B_MMA') WHERE acessosid LIKE '%E102BMMA%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E143FDM','E143_FDM')   WHERE acessosid LIKE '%E143FDM%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E143MMB','E143_MMB')   WHERE acessosid LIKE '%E143MMB%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E205MCP','E205')        WHERE acessosid LIKE '%E205MCP%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E301AMTS','E301_AMTS') WHERE acessosid LIKE '%E301AMTS%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E301MFP','E301_MFP')   WHERE acessosid LIKE '%E301MFP%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E302AMTS','E302_AMTS') WHERE acessosid LIKE '%E302AMTS%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E302JDF','E302_JDF')   WHERE acessosid LIKE '%E302JDF%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E302MFP','E302_MFP')   WHERE acessosid LIKE '%E302MFP%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E303MFP','E303_MFP')   WHERE acessosid LIKE '%E303MFP%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E304ADGB','E304A_DGB') WHERE acessosid LIKE '%E304ADGB%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E305MMD','E305')        WHERE acessosid LIKE '%E305MMD%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E403N','E403_JDF')     WHERE acessosid LIKE '%E403N%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E403S','E403_FDM')     WHERE acessosid LIKE '%E403S%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E404A','E404_CMB')     WHERE acessosid LIKE '%E404A%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E404BMRC','E404_RJNS') WHERE acessosid LIKE '%E404BMRC%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'E405MEM','E405_MEM')   WHERE acessosid LIKE '%E405MEM%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'INESCOS3LEPABE','INESC3.0A_LEPABE')      WHERE acessosid LIKE '%INESCOS3LEPABE%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'INESCOS3LSRELCM','INESC3.0A_LSRE-LCM')  WHERE acessosid LIKE '%INESCOS3LSRELCM%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'INESCOS4CEFT','INESC 4.0C_CEFT')         WHERE acessosid LIKE '%INESCOS4CEFT%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'INESCOS4LEPABE','INESC 4.0C_LEPABE')     WHERE acessosid LIKE '%INESCOS4LEPABE%';
UPDATE infodeqb_rds_registo SET acessosid = REPLACE(acessosid,'R001(ETAR)','R001_DEQB')               WHERE acessosid LIKE '%R001(ETAR)%';
-- Bare 'E302' (Helena Soares) — usar CONCAT para não corromper E302AMTS etc. já actualizados acima
UPDATE infodeqb_rds_registo
  SET acessosid = TRIM(';' FROM REPLACE(CONCAT(';', acessosid, ';'), ';E302;', ';E302_HMVMS;'))
  WHERE CONCAT(';', acessosid, ';') LIKE '%;E302;%';
-- Nota: deqids extintos sem mapeamento claro (E007LM, E008B, E009A, E009BMVS, E009BNFA,
-- E201, E203, E205MANC, E206, E219, E302JMO, E304BJML, E304BMFP, E306JIM, E306MFP,
-- E401, E402, E-146, F301, E405_HISP) ficam stale nos registos antigos.

-- ── Fim ─────────────────────────────────────────────────────────
SELECT 'Migração HR concluída.' AS status;
SELECT 'Executar migrate_rds_acessos_run.php para reconstruir registo_acessos.' AS proximo_passo;
