-- HSE API Test Verileri
-- Bu dosya test için örnek veriler içerir

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

-- ============================================
-- CHECKLIST VERİLERİ
-- ============================================

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

-- Checklist 3: Elektrik Güvenliği (Farklı firma)
INSERT INTO checklists (id, company_id, name, description, status, general_responsible_id, created_by) VALUES 
(3, 'F67890', 'Elektrik Güvenliği Kontrolü', 'Haftalık elektrik güvenliği denetimi', 'active', 102, 102);

INSERT INTO checklist_questions (checklist_id, order_num, question_text, question_type, is_required, photo_required, responsible_user_ids) VALUES
(3, 1, 'Elektrik panoları kapalı mı?', 'yes_no', 1, 1, '[301]'),
(3, 2, 'Topraklama bağlantıları sağlam mı?', 'yes_no', 1, 1, '[301]');

-- ============================================
-- SAHA TURU VERİLERİ
-- ============================================

-- Saha Turu 1: Tamamlanmış (uygunsuzluk var)
INSERT INTO field_tours (id, company_id, checklist_id, inspector_user_id, status, started_at, completed_at, location, notes) VALUES
(1, 'F12345', 1, 301, 'completed', '2024-12-10 09:00:00', '2024-12-10 11:30:00', 'Üretim Alanı - A Blok', 'Genel durum iyi, birkaç uygunsuzluk tespit edildi');

-- Saha Turu 1 - Cevaplar
INSERT INTO field_tour_responses (field_tour_id, question_id, answer_type, answer_value, is_compliant, notes, photos, location, risk_score, priority) VALUES
(1, 1, 'yes_no', 'yes', 1, 'Tüm acil çıkışlar açık ve işaretli', '["https://files.misafirus.com/hse/tour1-q1.jpg"]', 'A Blok', NULL, 'low'),
(1, 2, 'yes_no', 'no', 0, 'A Blok girişindeki yangın söndürücü eksik', '["https://files.misafirus.com/hse/tour1-q2-1.jpg","https://files.misafirus.com/hse/tour1-q2-2.jpg"]', 'A Blok Giriş', 8, 'high'),
(1, 3, 'yes_no', 'no', 0, 'Elektrik panosu kapağı açık, uyarı levhası yok', '["https://files.misafirus.com/hse/tour1-q3.jpg"]', 'A Blok Elektrik Odası', 9, 'high'),
(1, 4, 'score', '7', 1, 'Genel temizlik iyi seviyede', NULL, 'A Blok', NULL, 'medium'),
(1, 5, 'text', 'Genel olarak güvenlik bilinci yüksek. Personel KKE kullanımına dikkat ediyor.', 1, NULL, NULL, 'A Blok', NULL, 'low');

-- Saha Turu 2: Devam ediyor
INSERT INTO field_tours (id, company_id, checklist_id, inspector_user_id, status, started_at, location) VALUES
(2, 'F12345', 2, 302, 'in_progress', '2024-12-13 14:00:00', 'B Blok Depo');

INSERT INTO field_tour_responses (field_tour_id, question_id, answer_type, answer_value, is_compliant, notes) VALUES
(2, 5, 'yes_no', 'yes', 1, 'Alarm sistemi test edildi, çalışıyor'),
(2, 6, 'yes_no', 'yes', 1, 'Sprinkler aktif');

-- ============================================
-- AKSİYON VERİLERİ
-- ============================================

-- Aksiyon 1: Saha turundan oluşan (Yangın söndürücü eksikliği)
INSERT INTO actions (id, company_id, field_tour_id, response_id, title, description, location, assigned_to_user_id, assigned_to_department_id, status, priority, risk_score, risk_probability, risk_severity, risk_level, source_type, due_date, due_date_reminder_days, created_by, created_at) VALUES
(1, 'F12345', 1, 2, 'Uygunsuzluk: Yangın söndürücü eksik', 'Yangın söndürücüler yerinde ve dolgun mu?\n\nCevap: no\nNotlar: A Blok girişindeki yangın söndürücü eksik', 'A Blok Giriş', 401, 2, 'open', 'high', 20, 5, 4, 'very_high', 'field_tour', '2024-12-20', '[7,3,1]', 301, '2024-12-10 11:35:00');

-- Aksiyon 2: Saha turundan oluşan (Elektrik panosu)
INSERT INTO actions (id, company_id, field_tour_id, response_id, title, description, location, assigned_to_user_id, assigned_to_department_id, status, priority, risk_score, risk_probability, risk_severity, risk_level, source_type, due_date, due_date_reminder_days, created_by, created_at) VALUES
(2, 'F12345', 1, 3, 'Uygunsuzluk: Elektrik panosu kapağı açık', 'Elektrik panoları kapalı ve etiketli mi?\n\nCevap: no\nNotlar: Elektrik panosu kapağı açık, uyarı levhası yok', 'A Blok Elektrik Odası', 402, 3, 'in_progress', 'high', 16, 4, 4, 'high', 'field_tour', '2024-12-18', '[7,3,1]', 301, '2024-12-10 11:40:00');

-- Aksiyon 3: Manuel oluşturulan (Periyodik kontrol)
INSERT INTO actions (id, company_id, field_tour_id, response_id, title, description, location, assigned_to_user_id, assigned_to_department_id, status, priority, risk_score, risk_probability, risk_severity, risk_level, source_type, due_date, due_date_reminder_days, created_by, created_at) VALUES
(3, 'F12345', NULL, NULL, 'Forklift FL-001 Periyodik Bakımı', 'FL-001 kodlu forklift 6 aylık periyodik bakım süresi dolmuş. Hidrolik sistem kontrolü, fren testi ve genel bakım yapılmalı.', 'Depo Alanı', 403, 2, 'open', 'high', 16, 4, 4, 'high', 'periodic_inspection', '2024-12-22', '[7,3,1]', 201, '2024-12-11 10:00:00');

-- Aksiyon 4: Tamamlanmış aksiyon
INSERT INTO actions (id, company_id, field_tour_id, response_id, title, description, location, assigned_to_user_id, assigned_to_department_id, status, priority, risk_score, risk_probability, risk_severity, risk_level, source_type, due_date, completed_at, created_by, created_at) VALUES
(4, 'F12345', NULL, NULL, 'KKE Eksikliği Giderilmesi', 'Üretim hattında çalışan 5 personelin baret ve eldivenleri yıpranmış. Yeni KKE temini yapılmalı.', 'Üretim Hattı 1', 404, 1, 'completed', 'medium', 9, 3, 3, 'medium', 'field_tour', '2024-12-05', '2024-12-04 16:30:00', 201, '2024-12-01 09:00:00');

-- Aksiyon 5: Onay bekleyen aksiyon
INSERT INTO actions (id, company_id, field_tour_id, response_id, title, description, location, assigned_to_user_id, assigned_to_department_id, status, priority, risk_score, risk_probability, risk_severity, risk_level, source_type, due_date, created_by, created_at) VALUES
(5, 'F12345', NULL, NULL, 'Zemin Kayganlaştırıcı Temizliği', 'B Blok zemin kaygan, temizlik malzemesi değiştirilmeli', 'B Blok', 405, 4, 'pending_approval', 'medium', 12, 4, 3, 'medium', 'field_tour', '2024-12-15', 302, '2024-12-08 14:00:00');

-- Aksiyon 6: Gecikmiş aksiyon
INSERT INTO actions (id, company_id, field_tour_id, response_id, title, description, location, assigned_to_user_id, assigned_to_department_id, status, priority, risk_score, risk_probability, risk_severity, risk_level, source_type, due_date, is_overdue, created_by, created_at) VALUES
(6, 'F12345', NULL, NULL, 'Acil Durum Tatbikatı', 'Yıllık acil durum ve yangın tatbikatı yapılmalı', 'Tüm Tesis', 201, NULL, 'open', 'high', 15, 3, 5, 'high', 'third_party_audit', '2024-12-01', 1, 101, '2024-11-15 10:00:00');

-- ============================================
-- KAPATMA TALEPLERİ
-- ============================================

-- Aksiyon 5 için kapatma talebi (beklemede)
INSERT INTO action_closures (action_id, requested_by, closure_description, evidence_files, status, requires_upper_approval, created_at) VALUES
(5, 405, 'Zemin temizlik malzemesi değiştirildi. Kaymaz özellikli yeni ürün kullanılmaya başlandı. Personele bilgilendirme yapıldı.', '["https://files.misafirus.com/hse/closure-5-before.jpg","https://files.misafirus.com/hse/closure-5-after.jpg","https://files.misafirus.com/hse/closure-5-product.jpg"]', 'pending', 0, '2024-12-12 16:00:00');

-- Aksiyon 4 için kapatma talebi (onaylanmış)
INSERT INTO action_closures (action_id, requested_by, closure_description, evidence_files, status, reviewed_by, review_notes, reviewed_at, created_at) VALUES
(4, 404, 'Yeni KKE malzemeleri temin edildi ve personele dağıtıldı. Eski malzemeler imha edildi.', '["https://files.misafirus.com/hse/closure-4-new-ppe.jpg","https://files.misafirus.com/hse/closure-4-distribution.jpg"]', 'approved', 201, 'KKE kalitesi uygun, dağıtım kayıtları tam. Onaylandı.', '2024-12-04 16:30:00', '2024-12-04 15:00:00');

-- ============================================
-- BİLDİRİMLER
-- ============================================

-- Aksiyon atama bildirimleri
INSERT INTO notifications (user_id, type, title, message, related_type, related_id, is_read, created_at) VALUES
(401, 'action_assigned', 'Yeni Aksiyon Atandı', 'Size yeni bir aksiyon atandı: Uygunsuzluk: Yangın söndürücü eksik', 'action', 1, 0, '2024-12-10 11:35:00'),
(402, 'action_assigned', 'Yeni Aksiyon Atandı', 'Size yeni bir aksiyon atandı: Uygunsuzluk: Elektrik panosu kapağı açık', 'action', 2, 1, '2024-12-10 11:40:00'),
(403, 'action_assigned', 'Yeni Aksiyon Atandı', 'Size yeni bir aksiyon atandı: Forklift FL-001 Periyodik Bakımı', 'action', 3, 0, '2024-12-11 10:00:00');

-- Uygunsuzluk bildirimleri
INSERT INTO notifications (user_id, type, title, message, related_type, related_id, is_read, created_at) VALUES
(201, 'checklist_nonconformity', 'Yeni Uygunsuzluk Tespit Edildi', '''Genel İş Güvenliği Denetimi'' checklist''inde uygunsuzluk tespit edildi.', 'action', 1, 1, '2024-12-10 11:35:00'),
(201, 'checklist_nonconformity', 'Yeni Uygunsuzluk Tespit Edildi', '''Genel İş Güvenliği Denetimi'' checklist''inde uygunsuzluk tespit edildi.', 'action', 2, 1, '2024-12-10 11:40:00');

-- Kapatma talebi bildirimi
INSERT INTO notifications (user_id, type, title, message, related_type, related_id, is_read, created_at) VALUES
(201, 'action_status_changed', 'Kapatma Talebi Aldınız', '''Zemin Kayganlaştırıcı Temizliği'' aksiyonu için kapatma talebi gönderildi. Lütfen inceleyin.', 'action', 5, 0, '2024-12-12 16:00:00');

-- Tamamlama bildirimi
INSERT INTO notifications (user_id, type, title, message, related_type, related_id, is_read, created_at) VALUES
(201, 'action_completed', 'Kapatma Talebi Onaylandı', '''KKE Eksikliği Giderilmesi'' aksiyonunun kapatma talebi onaylanarak tamamlandı.', 'action', 4, 1, '2024-12-04 16:30:00');

-- Termin uyarısı
INSERT INTO notifications (user_id, type, title, message, related_type, related_id, is_read, created_at) VALUES
(401, 'action_due_reminder', 'Termin Uyarısı: 3 Gün Kaldı', '''Uygunsuzluk: Yangın söndürücü eksik'' aksiyonunun termin tarihi 3 gün sonra (2024-12-20).', 'action', 1, 0, '2024-12-17 09:00:00');

-- Termin aşımı
INSERT INTO notifications (user_id, type, title, message, related_type, related_id, is_read, created_at) VALUES
(201, 'action_overdue', '⚠️ KRİTİK: Termin Aşımı!', 'KRİTİK: ''Acil Durum Tatbikatı'' aksiyonunun termin tarihi 12 gün önce geçti! Acil aksiyon gerekiyor.', 'action', 6, 0, '2024-12-13 09:00:00');

-- ============================================
-- PERİYODİK KONTROLLER
-- ============================================

INSERT INTO periodic_inspections (company_id, equipment_name, equipment_code, inspection_type, inspection_frequency, last_inspection_date, next_inspection_date, responsible_user_id, location, status, notes, created_by) VALUES
('F12345', 'Forklift FL-001', 'FL-001', '6 Aylık Periyodik Bakım', 180, '2024-06-15', '2024-12-12', 403, 'Depo Alanı', 'active', 'Hidrolik sistem ve fren kontrolü yapılmalı', 201),
('F12345', 'Forklift FL-002', 'FL-002', '6 Aylık Periyodik Bakım', 180, '2024-08-20', '2025-02-16', 403, 'Depo Alanı', 'active', NULL, 201),
('F12345', 'Vinç CR-001', 'CR-001', 'Yıllık Periyodik Kontrol', 365, '2024-01-10', '2025-01-10', 402, 'Üretim Alanı', 'active', 'Yetkili servis tarafından kontrol edilmeli', 201),
('F12345', 'Kompresör KM-001', 'KM-001', '3 Aylık Bakım', 90, '2024-11-01', '2025-01-30', 402, 'Teknik Oda', 'active', NULL, 201),
('F12345', 'Yangın Söndürücü YS-A01', 'YS-A01', 'Yıllık Kontrol', 365, '2024-03-15', '2025-03-15', 201, 'A Blok Giriş', 'active', 'Dolum kontrolü ve test', 201);

-- ============================================
-- SERBEST UYGUNSUZLUKLAR
-- ============================================

INSERT INTO free_nonconformities (company_id, title, description, location, assigned_to_user_ids, priority, risk_score, photos, status, due_date, created_by) VALUES
('F12345', 'Çalışma İzni Eksikliği', 'Yüksekte çalışma yapan ekipte çalışma izni belgesi bulunmuyor. İzin belgesi alınmalı ve personele eğitim verilmeli.', 'C Blok Çatı', '[301,201]', 'high', 15, '["https://files.misafirus.com/hse/free-1-photo1.jpg"]', 'open', '2024-12-25', 302),
('F12345', 'Kimyasal Depolama Hatası', 'Kimyasal maddeler uygun olmayan ortamda depolanıyor. MSDS bilgileri eksik.', 'Kimyasal Depo', '[201]', 'high', 20, '["https://files.misafirus.com/hse/free-2-photo1.jpg","https://files.misafirus.com/hse/free-2-photo2.jpg"]', 'in_progress', '2024-12-20', 201);

-- ============================================
-- AUDIT LOG ÖRNEKLERİ
-- ============================================

INSERT INTO audit_logs (user_id, action, endpoint, resource_type, resource_id, new_values, ip_address, user_agent, created_at) VALUES
(101, 'POST', '/api/v1/checklists', 'checklist', 1, '{"name":"Genel İş Güvenliği Denetimi","status":"active"}', '192.168.1.100', 'Mozilla/5.0', '2024-12-01 10:00:00'),
(301, 'POST', '/api/v1/field-tours', 'field_tour', 1, '{"checklist_id":1,"location":"Üretim Alanı - A Blok"}', '192.168.1.101', 'Mozilla/5.0', '2024-12-10 09:00:00'),
(301, 'POST', '/api/v1/field-tours/1/responses', 'field_tour_response', 2, '{"question_id":2,"is_compliant":0}', '192.168.1.101', 'Mozilla/5.0', '2024-12-10 11:35:00'),
(405, 'POST', '/api/v1/actions/5/closure-request', 'action_closure', 1, '{"action_id":5,"closure_description":"Zemin temizlik malzemesi değiştirildi"}', '192.168.1.105', 'Mozilla/5.0', '2024-12-12 16:00:00');

-- ============================================
-- ÖZET BİLGİLER
-- ============================================

-- Firma: F12345
-- - 2 Aktif Checklist
-- - 2 Saha Turu (1 tamamlanmış, 1 devam ediyor)
-- - 6 Aksiyon (1 tamamlanmış, 2 açık, 1 devam ediyor, 1 onay bekliyor, 1 gecikmiş)
-- - 2 Kapatma Talebi (1 beklemede, 1 onaylanmış)
-- - 8 Bildirim
-- - 5 Periyodik Kontrol
-- - 2 Serbest Uygunsuzluk

-- Test Kullanıcıları (AuthApp'ten gelecek):
-- 101: HSE Uzmanı (Ahmet Yılmaz)
-- 201: HSE Uzmanı (Mehmet Demir)
-- 301: Kontrolör (Ayşe Kaya)
-- 302: Kontrolör (Fatma Şahin)
-- 401-405: Aksiyon Sahipleri
