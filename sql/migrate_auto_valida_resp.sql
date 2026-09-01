-- Migração: responsável com auto-validação
-- Executar uma vez em cada ambiente (local e produção)

-- 1. Adicionar coluna à tabela de responsáveis
ALTER TABLE infodeqb_rds_responsaveis
  ADD COLUMN auto_valida TINYINT NOT NULL DEFAULT 0
  AFTER Sigla;

-- 2. Criar responsável virtual para espaços sem necessidade de validação
--    Código 1 é claramente sintético (códigos reais UP têm 6+ dígitos)
INSERT INTO infodeqb_rds_responsaveis (Codigo, respespaco, Sigla, auto_valida)
VALUES (1, 'Diretor DEQB (auto-validado)', 'DIR', 1);

-- 3. Reatribuir espaços isentos ao novo responsável
--    (antes atribuídos a up246398, que tem outros espaços que requerem validação real)
UPDATE infodeqb_rds_gabinetes
SET responsavel = 1
WHERE deqid IN (
  'E-177B', 'E-177D', 'E-177B|E-177D',
  'E-102',  'E107',   'E108', 'E109', 'E111', 'E113',
  'E-140',  'E-172',  'E220', 'E221', 'E224', 'E275',
  'E307',   'E319',   'E320', 'E321', 'E322', 'E324',
  'E375',   'E406',   'E416', 'E417', 'E418', 'E419', 'E421',
  'INESC ENTRADA'
);
