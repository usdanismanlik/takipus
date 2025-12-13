-- ============================================
-- HSE İŞ GÜVENLİĞİ AKSİYON YÖNETİM SİSTEMİ
-- Full Database Script (Schema + Test Data)
-- Version: 1.0.0
-- Date: 14.12.2024
-- ============================================

-- Database oluştur
CREATE DATABASE IF NOT EXISTS hse_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hse_db;

-- ============================================
-- SCHEMA (Tablo Yapıları)
-- ============================================

-- Checklist Tablosu
CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    general_responsible_id INT COMMENT 'Genel sorumlu (Auth App user ID)',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_status (company_id, status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist Soruları Tablosu
CREATE TABLE IF NOT EXISTS checklist_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    order_num INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('yes_no', 'score', 'text') NOT NULL,
    is_required TINYINT(1) DEFAULT 1,
    photo_required TINYINT(1) DEFAULT 0,
    help_text TEXT,
    responsible_user_ids JSON COMMENT 'Sorumlu kullanıcı ID dizisi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE,
    INDEX idx_checklist_order (checklist_id, order_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saha Turları Tablosu
CREATE TABLE IF NOT EXISTS field_tours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL,
    checklist_id INT NOT NULL,
    inspector_user_id INT NOT NULL COMMENT 'Kontrolör (Auth App user ID)',
    status ENUM('in_progress', 'completed', 'cancelled') DEFAULT 'in_progress',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    location VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE RESTRICT,
    INDEX idx_company_status (company_id, status),
    INDEX idx_inspector (inspector_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saha Turu Cevapları Tablosu
CREATE TABLE IF NOT EXISTS field_tour_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_tour_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_type ENUM('yes_no', 'score', 'text') NOT NULL,
    answer_value TEXT NOT NULL,
    is_compliant TINYINT(1) DEFAULT 1,
    notes TEXT,
    photos JSON COMMENT 'Fotoğraf URL dizisi',
    location VARCHAR(255),
    risk_score INT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
    user_id INT NOT NULL COMMENT 'Bildirim alacak kullanıcı',
    type ENUM('action_created', 'action_assigned', 'checklist_nonconformity', 'action_completed', 'action_overdue', 'action_due_reminder', 'action_status_changed') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_type ENUM('action', 'field_tour', 'response', 'free_nonconformity') COMMENT 'İlişkili kayıt tipi',
    related_id INT COMMENT 'İlişkili kayıt ID',
    is_read TINYINT(1) DEFAULT 0,
    notification_channel ENUM('database', 'email', 'push') DEFAULT 'database' COMMENT 'Bildirim kanalı',
    email_sent TINYINT(1) DEFAULT 0 COMMENT 'Email gönderildi mi',
    push_sent TINYINT(1) DEFAULT 0 COMMENT 'Push bildirim gönderildi mi',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Serbest Uygunsuzluklar Tablosu
CREATE TABLE IF NOT EXISTS free_nonconformities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),
    assigned_to_user_ids JSON COMMENT 'Sorumlu kullanıcı ID dizisi',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    risk_score INT,
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

-- ============================================
-- TEST VERİLERİ
-- ============================================

-- Mevcut verileri temizle
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE action_closures;
TRUNCATE TABLE notifications;
TRUNCATE TABLE actions;
TRUNCATE TABLE field_tour_responses;
TRUNCATE TABLE field_tours;
TRUNCATE TABLE checklist_questions;
TRUNCATE TABLE checklists;
TRUNCATE TABLE free_nonconformities;
TRUNCATE TABLE periodic_inspections;
SET FOREIGN_KEY_CHECKS = 1;

-- Checklist 1: Genel İş Güvenliği Denetimi
INSERT INTO checklists (id, company_id, name, description, status, general_responsible_id, created_by) VALUES 
(1, 'F12345', 'Genel İş Güvenliği Denetimi', 'Tüm alanlar için genel güvenlik kontrolü', 'active', 101, 101);

INSERT INTO checklist_questions (checklist_id, order_num, question_text, question_type, is_required, photo_required, help_text, responsible_user_ids) VALUES
(1, 1, 'Acil çıkış yolları açık ve işaretli mi?', 'yes_no', 1, 1, 'Tüm acil çıkış kapıları kontrol edilmeli', '[201,202]'),
(1, 2, 'Yangın söndürücüler yerinde ve dolgun mu?', 'yes_no', 1, 1, 'Son kontrol tarihini kontrol edin', '[201]'),
(1, 3, 'Elektrik panoları kapalı ve etiketli mi?', 'yes_no', 1, 1, 'Pano kapakları kontrol edilmeli', '[203]'),
(1, 4, 'Çalışma alanı temizliği (1-10)', 'score', 1, 0, '1: Çok kötü, 10: Mükemmel', '[202]'),
(1, 5, 'Genel gözlemler ve notlar', 'text', 0, 0, 'Ek gözlemlerinizi yazın', NULL);

-- Checklist 2: Yangın Güvenliği Kontrolü
INSERT INTO checklists (id, company_id, name, description, status, general_responsible_id, created_by) VALUES 
(2, 'F12345', 'Yangın Güvenliği Kontrolü', 'Aylık yangın güvenliği denetimi', 'active', 101, 101);

INSERT INTO checklist_questions (checklist_id, order_num, question_text, question_type, is_required, photo_required, responsible_user_ids) VALUES
(2, 1, 'Yangın alarm sistemi çalışıyor mu?', 'yes_no', 1, 0, '[201]'),
(2, 2, 'Sprinkler sistemi aktif mi?', 'yes_no', 1, 0, '[201]'),
(2, 3, 'Yangın merdivenleri erişilebilir mi?', 'yes_no', 1, 1, '[201,202]'),
(2, 4, 'Yangın dolabı ekipmanları tam mı?', 'yes_no', 1, 1, '[201]');

-- Saha Turu 1: Tamamlanmış
INSERT INTO field_tours (id, company_id, checklist_id, inspector_user_id, status, started_at, completed_at, location, notes) VALUES
(1, 'F12345', 1, 301, 'completed', '2024-12-10 09:00:00', '2024-12-10 11:30:00', 'Üretim Alanı - A Blok', 'Genel durum iyi, birkaç uygunsuzluk tespit edildi');

INSERT INTO field_tour_responses (field_tour_id, question_id, answer_type, answer_value, is_compliant, notes, photos, location, risk_score, priority) VALUES
(1, 1, 'yes_no', 'yes', 1, 'Tüm acil çıkışlar açık ve işaretli', '["https://files.misafirus.com/hse/tour1-q1.jpg"]', 'A Blok', NULL, 'low'),
(1, 2, 'yes_no', 'no', 0, 'A Blok girişindeki yangın söndürücü eksik', '["https://files.misafirus.com/hse/tour1-q2-1.jpg","https://files.misafirus.com/hse/tour1-q2-2.jpg"]', 'A Blok Giriş', 8, 'high'),
(1, 3, 'yes_no', 'no', 0, 'Elektrik panosu kapağı açık, uyarı levhası yok', '["https://files.misafirus.com/hse/tour1-q3.jpg"]', 'A Blok Elektrik Odası', 9, 'high'),
(1, 4, 'score', '7', 1, 'Genel temizlik iyi seviyede', NULL, 'A Blok', NULL, 'medium'),
(1, 5, 'text', 'Genel olarak güvenlik bilinci yüksek. Personel KKE kullanımına dikkat ediyor.', 1, NULL, NULL, 'A Blok', NULL, 'low');

-- Aksiyonlar
INSERT INTO actions (id, company_id, field_tour_id, response_id, title, description, location, assigned_to_user_id, assigned_to_department_id, status, priority, risk_score, risk_probability, risk_severity, risk_level, source_type, due_date, due_date_reminder_days, created_by, created_at) VALUES
(1, 'F12345', 1, 2, 'Uygunsuzluk: Yangın söndürücü eksik', 'Yangın söndürücüler yerinde ve dolgun mu?\n\nCevap: no\nNotlar: A Blok girişindeki yangın söndürücü eksik', 'A Blok Giriş', 401, 2, 'open', 'high', 20, 5, 4, 'very_high', 'field_tour', '2024-12-20', '[7,3,1]', 301, '2024-12-10 11:35:00'),
(2, 'F12345', 1, 3, 'Uygunsuzluk: Elektrik panosu kapağı açık', 'Elektrik panoları kapalı ve etiketli mi?\n\nCevap: no\nNotlar: Elektrik panosu kapağı açık, uyarı levhası yok', 'A Blok Elektrik Odası', 402, 3, 'in_progress', 'high', 16, 4, 4, 'high', 'field_tour', '2024-12-18', '[7,3,1]', 301, '2024-12-10 11:40:00'),
(3, 'F12345', NULL, NULL, 'Forklift FL-001 Periyodik Bakımı', 'FL-001 kodlu forklift 6 aylık periyodik bakım süresi dolmuş. Hidrolik sistem kontrolü, fren testi ve genel bakım yapılmalı.', 'Depo Alanı', 403, 2, 'open', 'high', 16, 4, 4, 'high', 'periodic_inspection', '2024-12-22', '[7,3,1]', 201, '2024-12-11 10:00:00'),
(4, 'F12345', NULL, NULL, 'KKE Eksikliği Giderilmesi', 'Üretim hattında çalışan 5 personelin baret ve eldivenleri yıpranmış. Yeni KKE temini yapılmalı.', 'Üretim Hattı 1', 404, 1, 'completed', 'medium', 9, 3, 3, 'medium', 'field_tour', '2024-12-05', '2024-12-04 16:30:00', 201, '2024-12-01 09:00:00'),
(5, 'F12345', NULL, NULL, 'Zemin Kayganlaştırıcı Temizliği', 'B Blok zemin kaygan, temizlik malzemesi değiştirilmeli', 'B Blok', 405, 4, 'pending_approval', 'medium', 12, 4, 3, 'medium', 'field_tour', '2024-12-15', 302, '2024-12-08 14:00:00'),
(6, 'F12345', NULL, NULL, 'Acil Durum Tatbikatı', 'Yıllık acil durum ve yangın tatbikatı yapılmalı', 'Tüm Tesis', 201, NULL, 'open', 'high', 15, 3, 5, 'high', 'third_party_audit', '2024-12-01', 1, 101, '2024-11-15 10:00:00');

-- Kapatma Talepleri
INSERT INTO action_closures (action_id, requested_by, closure_description, evidence_files, status, requires_upper_approval, created_at) VALUES
(5, 405, 'Zemin temizlik malzemesi değiştirildi. Kaymaz özellikli yeni ürün kullanılmaya başlandı. Personele bilgilendirme yapıldı.', '["https://files.misafirus.com/hse/closure-5-before.jpg","https://files.misafirus.com/hse/closure-5-after.jpg"]', 'pending', 0, '2024-12-12 16:00:00');

-- Bildirimler
INSERT INTO notifications (user_id, type, title, message, related_type, related_id, is_read, created_at) VALUES
(401, 'action_assigned', 'Yeni Aksiyon Atandı', 'Size yeni bir aksiyon atandı: Uygunsuzluk: Yangın söndürücü eksik', 'action', 1, 0, '2024-12-10 11:35:00'),
(402, 'action_assigned', 'Yeni Aksiyon Atandı', 'Size yeni bir aksiyon atandı: Uygunsuzluk: Elektrik panosu kapağı açık', 'action', 2, 1, '2024-12-10 11:40:00');

-- Periyodik Kontroller
INSERT INTO periodic_inspections (company_id, equipment_name, equipment_code, inspection_type, inspection_frequency, last_inspection_date, next_inspection_date, responsible_user_id, location, status, created_by) VALUES
('F12345', 'Forklift FL-001', 'FL-001', '6 Aylık Periyodik Bakım', 180, '2024-06-15', '2024-12-12', 403, 'Depo Alanı', 'active', 201),
('F12345', 'Vinç CR-001', 'CR-001', 'Yıllık Periyodik Kontrol', 365, '2024-01-10', '2025-01-10', 402, 'Üretim Alanı', 'active', 201);

-- Serbest Uygunsuzluklar
INSERT INTO free_nonconformities (company_id, title, description, location, assigned_to_user_ids, priority, risk_score, photos, status, due_date, created_by) VALUES
('F12345', 'Çalışma İzni Eksikliği', 'Yüksekte çalışma yapan ekipte çalışma izni belgesi bulunmuyor.', 'C Blok Çatı', '[301,201]', 'high', 15, '["https://files.misafirus.com/hse/free-1.jpg"]', 'open', '2024-12-25', 302);

-- ============================================
-- ÖZET BİLGİLER
-- ============================================
-- Firma: F12345
-- - 2 Aktif Checklist
-- - 1 Tamamlanmış Saha Turu
-- - 6 Aksiyon (1 tamamlanmış, 3 açık, 1 devam ediyor, 1 onay bekliyor)
-- - 1 Kapatma Talebi (beklemede)
-- - 2 Bildirim
-- - 2 Periyodik Kontrol
-- - 1 Serbest Uygunsuzluk
-- ============================================
