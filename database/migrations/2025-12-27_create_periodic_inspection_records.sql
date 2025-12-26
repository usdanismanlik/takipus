-- Periyodik Kontrol Kayıtları Tablosu
-- Ekipmanların gerçekleştirilen kontrol kayıtlarını tutar

CREATE TABLE IF NOT EXISTS periodic_inspection_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id VARCHAR(50) NOT NULL,
    inspection_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    inspector_user_id INT NOT NULL,
    status ENUM('completed', 'failed', 'partial') DEFAULT 'completed',
    findings TEXT,
    photos JSON,
    next_inspection_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_inspection_id (inspection_id),
    INDEX idx_company_date (company_id, inspection_date),
    INDEX idx_inspector (inspector_user_id),
    
    FOREIGN KEY (inspection_id) 
        REFERENCES periodic_inspections(id) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek veri
INSERT INTO periodic_inspection_records 
(company_id, inspection_id, inspection_date, inspector_user_id, status, findings, photos, next_inspection_date)
VALUES 
('F9946', 1, '2025-12-20', 2399, 'completed', 'Ekipman normal durumda', '[]', '2025-12-27'),
('F9946', 1, '2025-12-27', 2399, 'completed', 'Hafif aşınma tespit edildi', '[]', '2026-01-03');
