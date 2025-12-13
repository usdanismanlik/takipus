# HSE İş Güvenliği Aksiyon Yönetim Sistemi API

## 🚀 Hızlı Başlangıç

### Kurulum

```bash
# Docker container'ları başlat
docker-compose up -d

# Composer bağımlılıklarını yükle
docker-compose exec php composer install

# Database'i oluştur
docker-compose exec mysql mysql -u root -proot_password hse_db < database/schema.sql

# Test verilerini yükle
docker-compose exec mysql mysql -u root -proot_password hse_db < database/test_data.sql
```

### Test

```bash
# Health check
curl http://localhost:8081/api/v1/health

# Checklist listesi
curl http://localhost:8081/api/v1/checklists?company_id=F12345
```

---

## 📋 Sistem Özellikleri

### ✅ Tamamlanan Modüller

1. **Checklist Yönetimi** - Kontrol listesi şablonları
2. **Saha Turu** - Checklist bazlı saha denetimleri
3. **Aksiyon Yönetimi** - Otomatik ve manuel aksiyon oluşturma
4. **Kapatma Süreci** - Onay mekanizmalı aksiyon kapatma
5. **Termin Yönetimi** - Otomatik uyarılar ve takip
6. **Risk Matrisi** - 5x5 risk değerlendirme sistemi
7. **Dashboard & Analytics** - Real-time istatistikler
8. **Periyodik Kontrol** - Ekipman kontrol takibi
9. **Raporlama** - Excel/CSV/JSON export
10. **Yetkilendirme** - JWT token bazlı rol sistemi
11. **Audit Log** - Tüm işlem kayıtları
12. **Bildirim Sistemi** - Otomatik bildirimler
13. **Dosya Yönetimi** - S3 entegrasyonu
14. **Serbest Uygunsuzluk** - Manuel uygunsuzluk kaydı

---

## 🔐 Yetkilendirme

### JWT Token Yapısı

```json
{
  "user_id": 301,
  "company_id": "F12345",
  "role": "hse",
  "permissions": [],
  "exp": 1734134400
}
```

### Roller

- `admin` - Sistem yöneticisi (tüm yetkiler)
- `hse` - HSE Uzmanı (checklist, risk, aksiyon yönetimi)
- `upper_management` - Üst Yönetim (onay, raporlama)
- `department_head` - Departman Sorumlusu (aksiyon yönetimi)
- `inspector` - Kontrolör (saha turu, gözlem)
- `action_owner` - Aksiyon Sahibi (kendi aksiyonları)

**Detaylı bilgi:** `/docs/AUTHORIZATION.md`

---

## 📡 API Endpoint'leri

### Checklist (6 endpoint)
```
GET    /api/v1/checklists
POST   /api/v1/checklists
GET    /api/v1/checklists/:id
PUT    /api/v1/checklists/:id
DELETE /api/v1/checklists/:id
GET    /api/v1/checklists/company/:companyId
```

### Saha Turu (5 endpoint)
```
POST   /api/v1/field-tours
GET    /api/v1/field-tours
GET    /api/v1/field-tours/:id
POST   /api/v1/field-tours/:id/responses
PUT    /api/v1/field-tours/:id/complete
```

### Aksiyon (5 endpoint)
```
POST   /api/v1/actions/manual
GET    /api/v1/actions
GET    /api/v1/actions/:id
PUT    /api/v1/actions/:id
PUT    /api/v1/actions/:id/complete
```

### Kapatma Süreci (4 endpoint)
```
POST   /api/v1/actions/:id/closure-request
GET    /api/v1/actions/:id/closures
PUT    /api/v1/actions/:id/closure/:closureId/approve
PUT    /api/v1/actions/:id/closure/:closureId/reject
```

### Dashboard (4 endpoint)
```
GET    /api/v1/dashboard/statistics
GET    /api/v1/dashboard/risk-matrix
GET    /api/v1/dashboard/actions/prioritized
GET    /api/v1/dashboard/actions/real-time
```

### Periyodik Kontrol (6 endpoint)
```
POST   /api/v1/periodic-inspections
GET    /api/v1/periodic-inspections
GET    /api/v1/periodic-inspections/upcoming
GET    /api/v1/periodic-inspections/overdue
POST   /api/v1/periodic-inspections/:id/complete
PUT    /api/v1/periodic-inspections/:id
```

### Export (3 endpoint)
```
GET    /api/v1/export/actions/excel
GET    /api/v1/export/actions/csv
GET    /api/v1/export/actions/json
```

### Dosya Yükleme (2 endpoint)
```
POST   /api/v1/upload
DELETE /api/v1/upload
```

### Serbest Uygunsuzluk (5 endpoint)
```
POST   /api/v1/free-nonconformities
GET    /api/v1/free-nonconformities
GET    /api/v1/free-nonconformities/:id
PUT    /api/v1/free-nonconformities/:id
DELETE /api/v1/free-nonconformities/:id
```

**Toplam: 41 Endpoint**

---

## 🧪 Test Senaryoları

### 1. Checklist Oluşturma

```bash
curl -X POST http://localhost:8081/api/v1/checklists \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": "F12345",
    "name": "Yangın Güvenliği Kontrolü",
    "description": "Aylık yangın güvenliği denetimi",
    "status": "active",
    "general_responsible_id": 101,
    "created_by": 101,
    "questions": [
      {
        "question_text": "Yangın söndürücüler yerinde mi?",
        "question_type": "yes_no",
        "is_required": 1,
        "photo_required": 1,
        "responsible_user_ids": [201, 202]
      }
    ]
  }'
```

### 2. Saha Turu Başlatma

```bash
curl -X POST http://localhost:8081/api/v1/field-tours \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": "F12345",
    "checklist_id": 1,
    "inspector_user_id": 301,
    "location": "Üretim Alanı"
  }'
```

### 3. Uygunsuzluk Kaydı (Otomatik Aksiyon)

```bash
curl -X POST http://localhost:8081/api/v1/field-tours/1/responses \
  -H "Content-Type: application/json" \
  -d '{
    "question_id": 1,
    "answer_value": "no",
    "is_compliant": 0,
    "notes": "Yangın söndürücü eksik",
    "risk_probability": 5,
    "risk_severity": 4,
    "priority": "high",
    "assigned_to_user_id": 401,
    "due_date": "2025-12-20"
  }'
```

### 4. Manuel Aksiyon Oluşturma

```bash
curl -X POST http://localhost:8081/api/v1/actions/manual \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": "F12345",
    "title": "Forklift Periyodik Bakımı",
    "description": "FL-001 kodlu forklift bakım süresi dolmuş",
    "source_type": "periodic_inspection",
    "risk_probability": 4,
    "risk_severity": 3,
    "assigned_to_user_id": 401,
    "due_date": "2025-12-25",
    "created_by": 201
  }'
```

### 5. Dashboard İstatistikleri

```bash
curl "http://localhost:8081/api/v1/dashboard/statistics?company_id=F12345"
```

### 6. Risk Matrisi

```bash
curl http://localhost:8081/api/v1/dashboard/risk-matrix
```

### 7. Excel Export

```bash
curl "http://localhost:8081/api/v1/export/actions/excel?company_id=F12345" \
  -o aksiyonlar.csv
```

---

## 🗄️ Database Yapısı

### Tablolar (15 adet)

1. **checklists** - Kontrol listesi şablonları
2. **checklist_questions** - Checklist soruları
3. **field_tours** - Saha turları
4. **field_tour_responses** - Saha turu cevapları
5. **actions** - Aksiyonlar
6. **action_closures** - Kapatma talepleri
7. **notifications** - Bildirimler
8. **free_nonconformities** - Serbest uygunsuzluklar
9. **periodic_inspections** - Periyodik kontroller
10. **audit_logs** - İşlem kayıtları

---

## 🎯 Risk Matrisi

### 5x5 Risk Değerlendirme

```
Şiddet ↑
  5  │  5   10   15   20   25
  4  │  4    8   12   16   20
  3  │  3    6    9   12   15
  2  │  2    4    6    8   10
  1  │  1    2    3    4    5
     └─────────────────────────→ Olasılık
        1    2    3    4    5
```

**Risk Seviyeleri:**
- 🔴 **20-25**: Çok Yüksek - Acil müdahale
- 🟠 **15-19**: Yüksek - 24 saat içinde
- 🟡 **10-14**: Orta - 1 hafta içinde
- 🟢 **5-9**: Düşük - 1 ay içinde
- ⚪ **1-4**: Çok Düşük - Rutin kontrol

---

## 📊 Örnek Kullanım Akışı

### Senaryo: Saha Turunda Uygunsuzluk Tespit Edilmesi

```
1. HSE Uzmanı saha turu başlatır
   POST /api/v1/field-tours

2. Kontrolör soruları cevaplar
   POST /api/v1/field-tours/1/responses
   
3. Uygunsuzluk tespit edilir (is_compliant: 0)
   → Otomatik aksiyon oluşturulur
   → Risk puanı hesaplanır (Olasılık × Şiddet)
   → Sorumluya bildirim gönderilir
   
4. Aksiyon Sahibi düzeltici faaliyet yapar
   PUT /api/v1/actions/1
   
5. Kapatma talebi gönderir
   POST /api/v1/actions/1/closure-request
   
6. Departman Sorumlusu onaylar
   PUT /api/v1/actions/1/closure/1/approve
   
7. Aksiyon tamamlanır
   → Status: completed
   → Bildirimler gönderilir
   → Audit log kaydedilir
```

---

## 🔧 Konfigürasyon

### Environment Variables

```env
# Database
DB_HOST=mysql
DB_PORT=3306
DB_NAME=hse_db
DB_USER=hse_user
DB_PASSWORD=hse_password

# JWT
JWT_SECRET=your_secret_key_here

# S3 (MinIO/DigitalOcean Spaces)
S3_ENDPOINT=https://files-api.apps.misafirus.com
S3_BUCKET=takipus
S3_REGION=us-east-1
S3_ACCESS_KEY=your_access_key
S3_SECRET_KEY=your_secret_key

# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hse-api.misafirus.com
```

---

## 📚 Dokümantasyon

- `/docs/AUTHORIZATION.md` - Yetkilendirme sistemi
- `/docs/JWT_TOKEN_STRUCTURE.md` - JWT token yapısı
- `/docs/PERMISSIONS_AND_ROLES.md` - Roller ve yetkiler
- `/docs/API_TEST_DOCUMENTATION.md` - API test dokümantasyonu

---

## 🛠️ Teknolojiler

- **Backend:** PHP 8.2
- **Database:** MySQL 8.0
- **Web Server:** Nginx
- **Container:** Docker
- **JWT:** firebase/php-jwt
- **S3:** aws/aws-sdk-php
- **Deployment:** CapRover

---

## 📈 Sistem İstatistikleri

- **41** API Endpoint
- **15** Database Tablosu
- **6** Kullanıcı Rolü
- **22** Yetki Tanımı
- **5x5** Risk Matrisi
- **7+** Bildirim Tipi
- **3** Export Formatı

---

## 🚀 Production Deployment

### CapRover

```bash
# captain-definition dosyası mevcut
# Dockerfile.caprover ile deploy

# CapRover CLI ile deploy
caprover deploy
```

**Detaylı bilgi:** `/CAPROVER_DEPLOYMENT.md`

---

## 🐛 Hata Ayıklama

### Logları Görüntüleme

```bash
# PHP logs
docker-compose logs -f php

# Nginx logs
docker-compose logs -f nginx

# MySQL logs
docker-compose logs -f mysql
```

### Database Bağlantı Testi

```bash
docker-compose exec mysql mysql -u hse_user -phse_password hse_db -e "SELECT 1"
```

---

## 📞 Destek

- **Email:** support@misafirus.com
- **Dokümantasyon:** `/docs/`

---

## 📝 Lisans

Proprietary - MisafirUS © 2024

---

## 🎉 Versiyon

**v1.0.0** - İlk production release (14.12.2024)

### Özellikler
- ✅ Tam checklist yönetimi
- ✅ Saha turu ve aksiyon sistemi
- ✅ Risk matrisi ve önceliklendirme
- ✅ Kapatma süreci ve onay mekanizması
- ✅ Periyodik kontrol takibi
- ✅ Dashboard ve raporlama
- ✅ JWT yetkilendirme
- ✅ Audit log sistemi
- ✅ S3 dosya yönetimi
