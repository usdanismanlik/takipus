-- Action Timeline Tablosu
-- Her aksiyonun detaylı zaman çizelgesi için

CREATE TABLE IF NOT EXISTS action_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_id INT NOT NULL,
    event_type ENUM(
        'created',              -- Aksiyon oluşturuldu
        'assigned',             -- Atama yapıldı
        'closure_requested',    -- Kapatma talebi gönderildi
        'closure_approved',     -- Kapatma talebi onaylandı
        'closure_rejected',     -- Kapatma talebi reddedildi
        'status_changed',       -- Durum değişti
        'note_added',           -- Not eklendi
        'photo_added'           -- Fotoğraf eklendi
    ) NOT NULL,
    user_id INT NOT NULL COMMENT 'İşlemi yapan kullanıcı',
    title VARCHAR(255) NOT NULL COMMENT 'Olay başlığı',
    description TEXT COMMENT 'Detaylı açıklama',
    metadata JSON COMMENT 'Ek bilgiler: notlar, fotoğraflar, eski/yeni değerler',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (action_id) REFERENCES actions(id) ON DELETE CASCADE,
    INDEX idx_action_id (action_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek kayıtlar (test için)
-- INSERT INTO action_timeline (action_id, event_type, user_id, title, description, metadata) VALUES
-- (1, 'created', 2430, 'Aksiyon Oluşturuldu', 'Yeni aksiyon oluşturuldu', '{"assigned_to": "Kullanıcı #2399", "due_date": "2026-01-01"}');
