-- Adiciona suporte para "registar em nome de" (proxy)
-- proxy_codigo: código UP de quem submeteu o registo
-- proxy_email:  email de quem submeteu (recebe notificações até registo ficar Activo)
ALTER TABLE infodeqb_rds_registo
    ADD COLUMN proxy_codigo VARCHAR(20)  NULL DEFAULT NULL AFTER extensao,
    ADD COLUMN proxy_email  VARCHAR(120) NULL DEFAULT NULL AFTER proxy_codigo;
