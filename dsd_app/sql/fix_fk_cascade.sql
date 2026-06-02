-- Corrige FK de infodeqb_dsd_distribuicao.ocorrencia_id para ON DELETE CASCADE
-- Executa no phpMyAdmin > separador SQL, ou na linha de comando MySQL
-- Necessário apenas uma vez para instalações existentes

SET FOREIGN_KEY_CHECKS=0;

-- Remove a FK existente (pode ter nomes diferentes conforme a instalação)
ALTER TABLE infodeqb_dsd_distribuicao
  DROP FOREIGN KEY IF EXISTS infodeqb_dsd_distribuicao_ibfk_2;

ALTER TABLE infodeqb_dsd_distribuicao
  DROP FOREIGN KEY IF EXISTS fk_dist_ocorrencia;

-- Recria com ON DELETE CASCADE
ALTER TABLE infodeqb_dsd_distribuicao
  ADD CONSTRAINT fk_dist_ocorrencia
  FOREIGN KEY (ocorrencia_id)
  REFERENCES infodeqb_dsd_uc_ocorrencia(id)
  ON DELETE CASCADE;

-- Limpa linhas órfãs existentes (onde ocorrencia_id = NULL por SET NULL anterior)
DELETE FROM infodeqb_dsd_distribuicao WHERE ocorrencia_id IS NULL;

SET FOREIGN_KEY_CHECKS=1;

SELECT 'FK corrigida com sucesso. ON DELETE CASCADE activo.' AS resultado;
