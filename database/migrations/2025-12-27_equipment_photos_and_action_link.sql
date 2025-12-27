-- Ekipman Fotoğraf ve Aksiyon Bağlantısı Migration
-- Tarih: 2025-12-27

-- 1. Ekipman fotoğrafları için kolon
ALTER TABLE periodic_inspections 
ADD COLUMN photos JSON DEFAULT NULL AFTER notes;

-- 2. Aksiyon-Ekipman bağlantısı
ALTER TABLE actions 
ADD COLUMN periodic_inspection_id INT NULL AFTER checklist_id;

-- İndeks ekle
ALTER TABLE actions 
ADD INDEX idx_periodic_inspection (periodic_inspection_id);

-- Foreign key ekle (opsiyonel, ilişkisel bütünlük için)
-- ALTER TABLE actions 
-- ADD FOREIGN KEY (periodic_inspection_id) 
--     REFERENCES periodic_inspections(id) ON DELETE SET NULL;
