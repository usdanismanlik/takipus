-- HSE API Database Schema
-- Firma Bazlı Checklist Sistemi

-- Checklist'ler Tablosu
-- company_id: Auth App'ten gelen firma ID'si (örn: F12345)
CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL COMMENT 'Auth App firma ID (örn: F12345)',
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'draft', 'archived') DEFAULT 'draft',
    general_responsible_id INT COMMENT 'Checklist genel sorumlusu (Auth App user ID)',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_status (company_id, status),
    INDEX idx_status (status),
    INDEX idx_company_id (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist Soruları Tablosu
CREATE TABLE IF NOT EXISTS checklist_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    order_num INT NOT NULL DEFAULT 1,
    question_text TEXT NOT NULL,
    question_type ENUM('yes_no', 'score', 'text') NOT NULL,
    is_required TINYINT(1) DEFAULT 1,
    photo_required TINYINT(1) DEFAULT 0,
    help_text TEXT,
    min_score INT DEFAULT NULL,
    max_score INT DEFAULT NULL,
    responsible_user_ids JSON COMMENT 'Uygunsuzluk durumunda sorumlu kişiler [1,2,3]',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE,
    INDEX idx_checklist_order (checklist_id, order_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saha Turları Tablosu
CREATE TABLE IF NOT EXISTS field_tours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL COMMENT 'Firma ID',
    checklist_id INT NOT NULL,
    inspector_user_id INT NOT NULL COMMENT 'Turu başlatan kullanıcı ID (Auth App)',
    status ENUM('in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    location VARCHAR(255) COMMENT 'Tur yapılan lokasyon',
    notes TEXT COMMENT 'Genel notlar',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE RESTRICT,
    INDEX idx_company_status (company_id, status),
    INDEX idx_inspector (inspector_user_id),
    INDEX idx_checklist (checklist_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saha Turu Cevapları Tablosu
CREATE TABLE IF NOT EXISTS field_tour_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_tour_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_type ENUM('yes_no', 'score', 'text') NOT NULL,
    answer_value VARCHAR(255) COMMENT 'yes/no, skor değeri veya metin',
    is_compliant TINYINT(1) DEFAULT 1 COMMENT '1: Uygun, 0: Uygunsuz',
    notes TEXT COMMENT 'Açıklama',
    photos JSON COMMENT 'Fotoğraf URL dizisi',
    location VARCHAR(255) COMMENT 'Bölüm/Lokasyon',
    risk_score INT COMMENT 'Risk puanı (1-10)',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (field_tour_id) REFERENCES field_tours(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES checklist_questions(id) ON DELETE RESTRICT,
    INDEX idx_tour_question (field_tour_id, question_id),
    INDEX idx_compliant (is_compliant)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aksiyonlar Tablosu
CREATE TABLE IF NOT EXISTS actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL,
    field_tour_id INT NULL COMMENT 'Saha turu ID (manuel aksiyonlar için NULL)',
    response_id INT NULL COMMENT 'Hangi cevaptan oluştu (manuel aksiyonlar için NULL)',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),
    assigned_to_user_id INT COMMENT 'Atanan kişi (Auth App user ID)',
    assigned_to_department_id INT COMMENT 'Atanan departman ID',
    status ENUM('open', 'in_progress', 'pending_approval', 'completed', 'cancelled') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    risk_score INT,
    risk_probability INT COMMENT 'Olasılık (1-5)',
    risk_severity INT COMMENT 'Şiddet (1-5)',
    risk_level ENUM('very_low', 'low', 'medium', 'high', 'very_high') COMMENT 'Hesaplanan risk seviyesi',
    source_type ENUM('field_tour', 'periodic_inspection', 'third_party_audit', 'free_nonconformity', 'other') DEFAULT 'field_tour' COMMENT 'Aksiyon kaynağı',
    due_date DATE,
    due_date_reminder_days JSON COMMENT 'Termin öncesi uyarı günleri [7,3,1]',
    last_reminder_sent_at TIMESTAMP NULL COMMENT 'Son uyarı gönderim tarihi',
    is_overdue TINYINT(1) DEFAULT 0 COMMENT 'Termin aşımı durumu',
    overdue_notification_sent TINYINT(1) DEFAULT 0 COMMENT 'Termin aşım bildirimi gönderildi mi',
    completed_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (field_tour_id) REFERENCES field_tours(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (response_id) REFERENCES field_tour_responses(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_company_status (company_id, status),
    INDEX idx_assigned_user (assigned_to_user_id),
    INDEX idx_assigned_dept (assigned_to_department_id),
    INDEX idx_priority (priority),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_overdue (is_overdue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bildirimler Tablosu
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Bildirim gönderilecek kullanıcı (Auth App)',
    type ENUM('action_created', 'action_assigned', 'checklist_nonconformity', 'action_completed', 'action_overdue', 'action_due_reminder', 'action_status_changed') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_type ENUM('action', 'field_tour', 'response', 'free_nonconformity') COMMENT 'İlişkili kayıt tipi',
    related_id INT COMMENT 'İlişkili kayıt ID',
    notification_channel ENUM('database', 'email', 'push') DEFAULT 'database' COMMENT 'Bildirim kanalı',
    email_sent TINYINT(1) DEFAULT 0 COMMENT 'Email gönderildi mi',
    push_sent TINYINT(1) DEFAULT 0 COMMENT 'Push bildirim gönderildi mi',
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_type (type),
    INDEX idx_channel (notification_channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Serbest Uygunsuzluklar Tablosu
-- Checklist dışında manuel olarak eklenen uygunsuzluklar
CREATE TABLE IF NOT EXISTS free_nonconformities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),
    assigned_to_user_ids JSON COMMENT 'Sorumlu kişiler [1,2,3]',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    risk_score INT COMMENT 'Risk puanı (1-10)',
    photos JSON COMMENT 'Fotoğraf URL dizisi',
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    due_date DATE,
    created_by INT NOT NULL COMMENT 'Oluşturan kullanıcı ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_status (company_id, status),
    INDEX idx_created_by (created_by),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aksiyon Kapatma Talepleri Tablosu
CREATE TABLE IF NOT EXISTS action_closures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_id INT NOT NULL,
    requested_by INT NOT NULL COMMENT 'Kapatma talebini yapan kullanıcı (aksiyon sahibi)',
    closure_description TEXT NOT NULL COMMENT 'Yapılan düzeltici faaliyet açıklaması',
    evidence_files JSON COMMENT 'Kanıt dosyaları (fotoğraf/video/doküman URL dizisi)',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT COMMENT 'Onaylayan/Reddeden kullanıcı',
    review_notes TEXT COMMENT 'Onay/Red açıklaması',
    reviewed_at TIMESTAMP NULL,
    requires_upper_approval TINYINT(1) DEFAULT 0 COMMENT 'Üst amir onayı gerekli mi',
    upper_approved_by INT COMMENT 'Üst amir onayı',
    upper_review_notes TEXT,
    upper_reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (action_id) REFERENCES actions(id) ON DELETE CASCADE,
    INDEX idx_action_status (action_id, status),
    INDEX idx_requested_by (requested_by),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Log Tablosu
-- Tüm POST/PUT/DELETE işlemlerini loglar
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT COMMENT 'İşlemi yapan kullanıcı ID',
    action VARCHAR(50) NOT NULL COMMENT 'POST, PUT, DELETE',
    endpoint VARCHAR(255) NOT NULL COMMENT 'API endpoint',
    resource_type VARCHAR(50) COMMENT 'checklist, action, field_tour vb.',
    resource_id INT COMMENT 'İlgili kayıt ID',
    old_values JSON COMMENT 'Eski değerler (UPDATE için)',
    new_values JSON COMMENT 'Yeni değerler',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Periyodik Kontroller Tablosu
CREATE TABLE IF NOT EXISTS periodic_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL,
    equipment_name VARCHAR(255) NOT NULL COMMENT 'Ekipman adı',
    equipment_code VARCHAR(100) COMMENT 'Ekipman kodu',
    inspection_type VARCHAR(100) NOT NULL COMMENT 'Kontrol tipi',
    inspection_frequency INT NOT NULL COMMENT 'Kontrol sıklığı (gün)',
    last_inspection_date DATE,
    next_inspection_date DATE NOT NULL,
    responsible_user_id INT COMMENT 'Sorumlu kullanıcı',
    location VARCHAR(255),
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_status (company_id, status),
    INDEX idx_next_date (next_inspection_date),
    INDEX idx_responsible (responsible_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek Checklist Verisi
INSERT INTO checklists (company_id, name, description, status, general_responsible_id) VALUES 
('F12345', 'Genel İş Güvenliği Denetimi', 'Tüm alanlar için genel güvenlik kontrolü', 'active', 101),
('F12345', 'Yangın Güvenliği Kontrolü', 'Yangın söndürücü ve acil çıkış kontrolleri', 'draft', 101),
('F67890', 'Elektrik Güvenliği', 'Elektrik panoları ve kablo kontrolleri', 'active', 102);
