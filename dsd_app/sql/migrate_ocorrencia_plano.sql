-- Migração: adiciona plano_id a infodeqb_dsd_uc_ocorrencia
-- Executa no phpMyAdmin > separador SQL da base de dados dsd_deqb

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Remover FKs existentes
ALTER TABLE infodeqb_dsd_uc_ocorrencia 
  DROP FOREIGN KEY infodeqb_dsd_uc_ocorrencia_ibfk_1,
  DROP FOREIGN KEY infodeqb_dsd_uc_ocorrencia_ibfk_2;

-- 2. Remover UNIQUE KEY antigo
ALTER TABLE infodeqb_dsd_uc_ocorrencia 
  DROP INDEX uk_ocorrencia;

-- 3. Adicionar coluna plano_id
ALTER TABLE infodeqb_dsd_uc_ocorrencia 
  ADD COLUMN plano_id INT(11) DEFAULT NULL AFTER uc_id;

-- 4. Popular plano_id com o plano da UC (para dados existentes)
UPDATE infodeqb_dsd_uc_ocorrencia o
JOIN infodeqb_dsd_uc uc ON o.uc_id = uc.id
SET o.plano_id = uc.plano_id
WHERE o.plano_id IS NULL;

-- 5. Novo UNIQUE KEY incluindo plano_id
ALTER TABLE infodeqb_dsd_uc_ocorrencia
  ADD UNIQUE KEY uk_ocorrencia (uc_id, ano_letivo_id, plano_id);

-- 6. Recriar FKs + nova FK para plano_id
ALTER TABLE infodeqb_dsd_uc_ocorrencia
  ADD CONSTRAINT infodeqb_dsd_uc_ocorrencia_ibfk_1 
    FOREIGN KEY (uc_id) REFERENCES infodeqb_dsd_uc(id) ON DELETE CASCADE,
  ADD CONSTRAINT infodeqb_dsd_uc_ocorrencia_ibfk_2 
    FOREIGN KEY (ano_letivo_id) REFERENCES infodeqb_dsd_ano_letivo(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_ocor_plano 
    FOREIGN KEY (plano_id) REFERENCES infodeqb_dsd_plano_estudo(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Migracao concluida com sucesso.' AS resultado;
