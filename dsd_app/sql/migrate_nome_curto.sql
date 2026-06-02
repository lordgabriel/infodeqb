-- Adiciona nome_curto à tabela infodeqb_dsd_docente
ALTER TABLE `infodeqb_dsd_docente`
  ADD COLUMN `nome_curto` varchar(60) DEFAULT NULL AFTER `nome`;
