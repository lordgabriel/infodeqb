-- Tabela de administradores por sala (múltiplos admins por sala)
-- O username é o número UP sem prefixo (ex: 356946)
CREATE TABLE IF NOT EXISTS %DB_TBL_PREFIX%room_admins (
  id          SERIAL PRIMARY KEY,
  room_id     INTEGER NOT NULL,
  username    VARCHAR(50) NOT NULL,
  UNIQUE (room_id, username)
);

UPDATE %DB_TBL_PREFIX%variables
   SET variable_content = '2'
 WHERE variable_name = 'local_db_version';
