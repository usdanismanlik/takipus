#!/bin/bash

# HSE API - Canlı Veritabanı Migration Scripti
# Bu script canlı veritabanında çalıştırılması gereken migration'ları içerir

echo "🚀 HSE API - Canlı Veritabanı Migration'ları"
echo "=============================================="
echo ""

# Veritabanı bilgileri
DB_HOST="your-production-host"
DB_USER="your-production-user"
DB_PASS="your-production-password"
DB_NAME="your-production-db"

echo "⚠️  DİKKAT: Bu script canlı veritabanında değişiklik yapacak!"
echo "Devam etmek için 'EVET' yazın:"
read -r confirmation

if [ "$confirmation" != "EVET" ]; then
    echo "❌ İşlem iptal edildi."
    exit 1
fi

echo ""
echo "📋 Migration'lar uygulanıyor..."
echo ""

# Migration 1: photos kolonu ekle
echo "1️⃣  actions tablosuna 'photos' kolonu ekleniyor..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
ALTER TABLE actions 
ADD COLUMN photos JSON COMMENT 'Aksiyon fotoğrafları (URL array)' 
AFTER description;
EOF

if [ $? -eq 0 ]; then
    echo "   ✅ photos kolonu eklendi"
else
    echo "   ⚠️  photos kolonu zaten var veya hata oluştu"
fi

echo ""

# Migration 2: İki aşamalı onay sistemi
echo "2️⃣  İki aşamalı onay sistemi için kolonlar ekleniyor..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
-- checklist_id ve upper_approver_id ekle
ALTER TABLE actions 
ADD COLUMN checklist_id INT NULL COMMENT 'İlişkili checklist ID (field tour aksiyonları için)' AFTER response_id,
ADD COLUMN upper_approver_id INT NULL COMMENT 'Manuel aksiyonlarda üst amir ID (ikinci onay için)' AFTER assigned_to_department_id;

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
EOF

if [ $? -eq 0 ]; then
    echo "   ✅ İki aşamalı onay kolonları eklendi"
else
    echo "   ⚠️  Kolonlar zaten var veya hata oluştu"
fi

echo ""

# Migration 3: source_type enum güncelle
echo "3️⃣  source_type enum'ına 'manual' ekleniyor..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
ALTER TABLE actions 
MODIFY COLUMN source_type ENUM('field_tour', 'periodic_inspection', 'third_party_audit', 'free_nonconformity', 'manual', 'other') 
DEFAULT 'field_tour' 
COMMENT 'Aksiyon kaynağı';
EOF

if [ $? -eq 0 ]; then
    echo "   ✅ source_type güncellendi"
else
    echo "   ⚠️  source_type zaten güncel veya hata oluştu"
fi

echo ""

# Migration 4: Notification type enum güncelle
echo "4️⃣  notifications tablosu type enum'ı güncelleniyor..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
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
EOF

if [ $? -eq 0 ]; then
    echo "   ✅ Notification type enum güncellendi"
else
    echo "   ⚠️  Enum zaten güncel veya hata oluştu"
fi

echo ""
echo "=============================================="
echo "✅ Tüm migration'lar tamamlandı!"
echo ""
echo "📊 Kontrol için çalıştırılabilecek sorgular:"
echo ""
echo "-- actions tablosunu kontrol et"
echo "DESCRIBE actions;"
echo ""
echo "-- action_closures status değerlerini kontrol et"
echo "SHOW COLUMNS FROM action_closures LIKE 'status';"
echo ""
echo "-- notifications type değerlerini kontrol et"
echo "SHOW COLUMNS FROM notifications LIKE 'type';"
echo ""
