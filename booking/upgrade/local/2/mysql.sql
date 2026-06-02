-- Tabela de administradores por sala (múltiplos admins por sala)
-- O username é o número UP sem prefixo (ex: 356946)
CREATE TABLE IF NOT EXISTS %DB_TBL_PREFIX%room_admins (
  id          INT NOT NULL AUTO_INCREMENT,
  room_id     INT NOT NULL,
  username    VARCHAR(50) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_room_admin (room_id, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO %DB_TBL_PREFIX%variables (variable_name, variable_content)
  VALUES ('local_db_version', '2')
  ON DUPLICATE KEY UPDATE variable_content = '2';
