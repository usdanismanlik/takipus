-- ============================================
-- HSE API - Canlı Veritabanı Migration'ları
-- ============================================
-- 
-- Bu dosya canlı veritabanında çalıştırılması gereken
-- tüm migration'ları içerir.
--
-- UYARI: Bu script'i çalıştırmadan önce:
-- 1. Veritabanı yedeği alın
-- 2. Test ortamında deneyin
-- 3. Canlı ortamda çalıştırın
--
-- ============================================

-- Migration 1: actions tablosuna photos kolonu ekle
-- Tarih: 2025-12-14
-- Açıklama: Aksiyon fotoğrafları için JSON kolonu

ALTER TABLE actions 
ADD COLUMN IF NOT EXISTS photos JSON COMMENT 'Aksiyon fotoğrafları (URL array)' 
AFTER description;

-- ============================================

-- Migration 2: İki aşamalı onay sistemi
-- Tarih: 2025-12-23
-- Açıklama: Üst yönetici onayı için gerekli kolonlar

-- 2a. checklist_id ve upper_approver_id kolonlarını ekle
ALTER TABLE actions 
ADD COLUMN IF NOT EXISTS checklist_id INT NULL COMMENT 'İlişkili checklist ID (field tour aksiyonları için)' AFTER response_id,
ADD COLUMN IF NOT EXISTS upper_approver_id INT NULL COMMENT 'Manuel aksiyonlarda üst amir ID (ikinci onay için)' AFTER assigned_to_department_id;

-- 2b. checklist_id için foreign key ekle
-- Not: Eğer foreign key zaten varsa hata verebilir, o zaman bu satırı atlayın
ALTER TABLE actions
ADD CONSTRAINT fk_actions_checklist 
FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE SET NULL;

-- 2c. action_closures status enum'ını güncelle
ALTER TABLE action_closures 
MODIFY COLUMN status ENUM('pending', 'first_approved', 'approved', 'rejected') DEFAULT 'pending';

-- 2d. Mevcut field tour aksiyonları için checklist_id doldur
UPDATE actions a
JOIN field_tours ft ON a.field_tour_id = ft.id
SET a.checklist_id = ft.checklist_id
WHERE a.field_tour_id IS NOT NULL AND a.checklist_id IS NULL;

-- ============================================

-- Migration 3: source_type enum'ına 'manual' ekle
-- Tarih: 2025-12-14
-- Açıklama: Manuel aksiyonlar için kaynak tipi

ALTER TABLE actions 
MODIFY COLUMN source_type ENUM(
    'field_tour', 
    'periodic_inspection', 
    'third_party_audit', 
    'free_nonconformity', 
    'manual', 
    'other'
) DEFAULT 'field_tour' COMMENT 'Aksiyon kaynağı';

-- ============================================

-- Migration 4: Notification type enum'ını güncelle
-- Tarih: 2025-12-25
-- Açıklama: Kapatma süreci için yeni bildirim tipleri

ALTER TABLE notifications 
MODIFY COLUMN type ENUM(
    'action_created',           -- YENİ: Aksiyon oluşturuldu (üst yöneticiye)
    'action_assigned', 
    'checklist_nonconformity', 
    'action_completed', 
    'action_overdue', 
    'action_due_reminder', 
    'action_status_changed',
    'closure_requested',        -- YENİ: Kapatma talebi gönderildi
    'closure_approved',         -- YENİ: Kapatma talebi onaylandı
    'closure_rejected',         -- YENİ: Kapatma talebi reddedildi
    'closure_completed',        -- YENİ: Kapatma tamamlandı
    'upper_approval_required'   -- YENİ: Üst yönetici onayı gerekli
) NOT NULL;

-- ============================================
-- KONTROL SORULARI
-- ============================================

-- actions tablosunu kontrol et
-- DESCRIBE actions;

-- action_closures status değerlerini kontrol et
-- SHOW COLUMNS FROM action_closures LIKE 'status';

-- notifications type değerlerini kontrol et
-- SHOW COLUMNS FROM notifications LIKE 'type';

-- Yeni kolonların var olup olmadığını kontrol et
-- SELECT 
--     COLUMN_NAME, 
--     DATA_TYPE, 
--     COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'actions' 
--     AND COLUMN_NAME IN ('photos', 'checklist_id', 'upper_approver_id');

-- ============================================
-- ROLLBACK (GEREKİRSE)
-- ============================================

-- UYARI: Sadece gerekirse kullanın!

-- Migration 4 rollback
-- ALTER TABLE notifications 
-- MODIFY COLUMN type ENUM(
--     'action_assigned', 
--     'checklist_nonconformity', 
--     'action_completed', 
--     'action_overdue', 
--     'action_due_reminder', 
--     'action_status_changed'
-- ) NOT NULL;

-- Migration 3 rollback
-- ALTER TABLE actions 
-- MODIFY COLUMN source_type ENUM(
--     'field_tour', 
--     'periodic_inspection', 
--     'third_party_audit', 
--     'free_nonconformity', 
--     'other'
-- ) DEFAULT 'field_tour';

-- Migration 2 rollback
-- ALTER TABLE action_closures 
-- MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending';
-- ALTER TABLE actions DROP FOREIGN KEY fk_actions_checklist;
-- ALTER TABLE actions DROP COLUMN upper_approver_id;
-- ALTER TABLE actions DROP COLUMN checklist_id;

-- Migration 1 rollback
-- ALTER TABLE actions DROP COLUMN photos;
