-- InfoDEQB / Equipamentos — acessos específicos por equipamento
-- Executar em deq.fe.up.pt (localhost já aplicado)

CREATE TABLE IF NOT EXISTS infodeqb_equipmentdeq_access (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL COMMENT 'Código numérico UP do utilizador',
    equipment_id INT NOT NULL,
    granted_by   INT NOT NULL COMMENT 'Código do admin que atribuiu',
    granted_at   DATETIME NOT NULL DEFAULT NOW(),
    UNIQUE KEY uq_user_equip (user_id, equipment_id),
    FOREIGN KEY (equipment_id) REFERENCES infodeqb_equipmentdeq(equipment_id) ON DELETE CASCADE
);
