# Canlı Veritabanı Migration Rehberi

## 📋 Çalıştırılması Gereken Migration'lar

Canlı veritabanında aşağıdaki 4 migration'ı sırayla çalıştırmalısınız:

### 1. ✅ photos Kolonu Ekle
**Dosya**: `database/migrations/add_photos_to_actions.sql`
**Açıklama**: Aksiyonlara fotoğraf ekleme özelliği için

```sql
ALTER TABLE actions 
ADD COLUMN photos JSON COMMENT 'Aksiyon fotoğrafları (URL array)' 
AFTER description;
```

### 2. ✅ İki Aşamalı Onay Sistemi
**Dosya**: `database/migrations/2025-12-23_two_stage_approval.sql`
**Açıklama**: Üst yönetici onayı için gerekli kolonlar

```sql
-- checklist_id ve upper_approver_id ekle
ALTER TABLE actions 
ADD COLUMN checklist_id INT NULL COMMENT 'İlişkili checklist ID' AFTER response_id,
ADD COLUMN upper_approver_id INT NULL COMMENT 'Üst amir ID' AFTER assigned_to_department_id;

-- Foreign key ekle
ALTER TABLE actions
ADD FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE SET NULL;

-- action_closures status enum güncelle
ALTER TABLE action_closures 
MODIFY COLUMN status ENUM('pending', 'first_approved', 'approved', 'rejected') DEFAULT 'pending';

-- Mevcut field tour aksiyonları için checklist_id doldur
UPDATE actions a
JOIN field_tours ft ON a.field_tour_id = ft.id
SET a.checklist_id = ft.checklist_id
WHERE a.field_tour_id IS NOT NULL AND a.checklist_id IS NULL;
```

### 3. ✅ source_type Enum Güncelle
**Dosya**: `database/migrations/add_manual_to_source_type.sql`
**Açıklama**: Manuel aksiyonlar için 'manual' tipi ekle

```sql
ALTER TABLE actions 
MODIFY COLUMN source_type ENUM(
    'field_tour', 
    'periodic_inspection', 
    'third_party_audit', 
    'free_nonconformity', 
    'manual', 
    'other'
) DEFAULT 'field_tour';
```

### 4. ✅ Notification Type Enum Güncelle
**Dosya**: `database/migrations/add_closure_notification_types.sql`
**Açıklama**: Kapatma süreci için yeni bildirim tipleri

```sql
ALTER TABLE notifications 
MODIFY COLUMN type ENUM(
    'action_created',
    'action_assigned', 
    'checklist_nonconformity', 
    'action_completed', 
    'action_overdue', 
    'action_due_reminder', 
    'action_status_changed',
    'closure_requested',
    'closure_approved',
    'closure_rejected',
    'closure_completed',
    'upper_approval_required'
) NOT NULL;
```

---

## 🚀 Çalıştırma Yöntemleri

### Yöntem 1: Tek Dosyadan Çalıştırma (ÖNERİLEN)

Tüm migration'ları tek seferde çalıştırmak için:

```bash
# Canlı veritabanına bağlan ve migration'ları çalıştır
mysql -h your-host -u your-user -p your-database < database/PRODUCTION_MIGRATIONS.sql
```

### Yöntem 2: Her Migration'ı Ayrı Çalıştırma

```bash
# 1. photos kolonu
mysql -h your-host -u your-user -p your-database < database/migrations/add_photos_to_actions.sql

# 2. İki aşamalı onay
mysql -h your-host -u your-user -p your-database < database/migrations/2025-12-23_two_stage_approval.sql

# 3. source_type güncelle
mysql -h your-host -u your-user -p your-database < database/migrations/add_manual_to_source_type.sql

# 4. notification type güncelle
mysql -h your-host -u your-user -p your-database < database/migrations/add_closure_notification_types.sql
```

### Yöntem 3: Bash Script ile (Otomatik)

```bash
# Script'i çalıştırılabilir yap
chmod +x run-production-migrations.sh

# Script'i çalıştır (veritabanı bilgilerini script içinde güncelleyin)
./run-production-migrations.sh
```

---

## ⚠️ Önemli Notlar

### Çalıştırmadan Önce

1. **Veritabanı Yedeği Alın**
   ```bash
   mysqldump -h your-host -u your-user -p your-database > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Test Ortamında Deneyin**
   - Önce test veritabanında çalıştırın
   - Sorun olmadığından emin olun

3. **Downtime Planlayın**
   - Migration'lar genellikle hızlıdır ama büyük tablolarda zaman alabilir
   - Düşük trafikli saatlerde çalıştırın

### Çalıştırdıktan Sonra

1. **Kontrol Sorguları Çalıştırın**
   ```sql
   -- Kolonları kontrol et
   DESCRIBE actions;
   
   -- Enum değerlerini kontrol et
   SHOW COLUMNS FROM action_closures LIKE 'status';
   SHOW COLUMNS FROM notifications LIKE 'type';
   
   -- Yeni kolonların varlığını kontrol et
   SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT 
   FROM INFORMATION_SCHEMA.COLUMNS 
   WHERE TABLE_NAME = 'actions' 
       AND COLUMN_NAME IN ('photos', 'checklist_id', 'upper_approver_id');
   ```

2. **Uygulama Loglarını İzleyin**
   - API loglarında hata olup olmadığını kontrol edin
   - İlk birkaç aksiyon oluşturma işlemini test edin

---

## 🔄 Rollback (Geri Alma)

Eğer bir sorun olursa, migration'ları geri almak için:

```sql
-- UYARI: Sadece gerekirse kullanın!

-- Migration 4 rollback
ALTER TABLE notifications 
MODIFY COLUMN type ENUM(
    'action_assigned', 
    'checklist_nonconformity', 
    'action_completed', 
    'action_overdue', 
    'action_due_reminder', 
    'action_status_changed'
) NOT NULL;

-- Migration 3 rollback
ALTER TABLE actions 
MODIFY COLUMN source_type ENUM(
    'field_tour', 
    'periodic_inspection', 
    'third_party_audit', 
    'free_nonconformity', 
    'other'
) DEFAULT 'field_tour';

-- Migration 2 rollback
ALTER TABLE action_closures 
MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending';
ALTER TABLE actions DROP FOREIGN KEY fk_actions_checklist;
ALTER TABLE actions DROP COLUMN upper_approver_id;
ALTER TABLE actions DROP COLUMN checklist_id;

-- Migration 1 rollback
ALTER TABLE actions DROP COLUMN photos;
```

---

## 📁 Dosyalar

- `database/PRODUCTION_MIGRATIONS.sql` - Tüm migration'ları içeren tek dosya
- `run-production-migrations.sh` - Otomatik çalıştırma scripti
- `database/migrations/` - Bireysel migration dosyaları

---

## ✅ Checklist

- [ ] Veritabanı yedeği alındı
- [ ] Test ortamında denendi
- [ ] Downtime planlandı
- [ ] Migration'lar çalıştırıldı
- [ ] Kontrol sorguları çalıştırıldı
- [ ] Uygulama logları kontrol edildi
- [ ] İlk test aksiyonu oluşturuldu
- [ ] Bildirimler doğru çalışıyor
