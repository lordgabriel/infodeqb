-- Popular infodeqb_rds_registo_acessos a partir de acessosid
-- Correr UMA vez em localhost e depois em produção

TRUNCATE TABLE infodeqb_rds_registo_acessos;

INSERT INTO infodeqb_rds_registo_acessos (registo_id, lab_id)
SELECT r.autoid, g.id
FROM infodeqb_rds_registo r
JOIN infodeqb_rds_gabinetes g
  ON CONCAT('; ', IFNULL(r.acessosid,''), '; ') LIKE CONCAT('%; ', g.deqid, '; %')
WHERE r.deleted = 0
  AND r.acessosid IS NOT NULL
  AND r.acessosid != '';

SELECT COUNT(*) AS linhas_inseridas FROM infodeqb_rds_registo_acessos;
