# HSE API - Yetkilendirme Sistemi

## 🔐 JWT Token Yapısı

AuthApp'ten gelen JWT token şu bilgileri içermelidir:

```json
{
  "user_id": 301,
  "role": "hse",
  "permissions": [],
  "company_id": "F12345",
  "exp": 1734134400
}
```

### Token Gönderimi

**Header:**
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

---

## 👥 Kullanıcı Rolleri

### 1. **Admin** (`admin`)
- **Açıklama:** Tam yetkili sistem yöneticisi
- **Yetkiler:** Tüm işlemler

### 2. **HSE Uzmanı** (`hse`)
- **Açıklama:** İş sağlığı ve güvenliği uzmanı
- **Yetkiler:**
  - ✅ Checklist oluşturma/güncelleme
  - ✅ Saha turu başlatma
  - ✅ Aksiyon oluşturma ve atama
  - ✅ Risk puanı belirleme
  - ✅ Termin değiştirme
  - ✅ Kapatma talebi onaylama/reddetme
  - ✅ Dashboard ve raporlar
  - ❌ Üst yönetim onayı

### 3. **Üst Yönetim** (`upper_management`)
- **Açıklama:** Üst düzey yönetici
- **Yetkiler:**
  - ✅ Dashboard ve raporlar görüntüleme
  - ✅ Üst yönetim onayı verme
  - ✅ Termin değiştirme
  - ✅ Veri export
  - ❌ Checklist oluşturma
  - ❌ Saha turu yapma

### 4. **Departman Sorumlusu** (`department_head`)
- **Açıklama:** Departman yöneticisi
- **Yetkiler:**
  - ✅ Kendi departmanına aksiyon atama
  - ✅ Termin değiştirme
  - ✅ Kapatma talebi onaylama/reddetme
  - ✅ Dashboard ve raporlar
  - ❌ Checklist oluşturma
  - ❌ Risk puanı belirleme

### 5. **Kontrolör** (`inspector`)
- **Açıklama:** Saha turu yapan personel
- **Yetkiler:**
  - ✅ Saha turu başlatma ve tamamlama
  - ✅ Gözlem kaydetme
  - ✅ Risk puanı belirleme
  - ✅ Aksiyon oluşturma
  - ❌ Aksiyon atama
  - ❌ Termin değiştirme
  - ❌ Kapatma onayı

### 6. **Aksiyon Sahibi** (`action_owner`)
- **Açıklama:** Aksiyondan sorumlu personel
- **Yetkiler:**
  - ✅ Kendi aksiyonlarını görüntüleme
  - ✅ Aksiyon güncelleme
  - ✅ Kapatma talebi gönderme
  - ❌ Aksiyon atama
  - ❌ Termin değiştirme
  - ❌ Kapatma onayı

---

## 🔑 Yetki Matrisi

| İşlem | Admin | HSE | Üst Yönetim | Dept. Head | Kontrolör | Aksiyon Sahibi |
|-------|-------|-----|-------------|------------|-----------|----------------|
| **Checklist** |
| Checklist oluştur | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Checklist güncelle | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Checklist sil | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Checklist görüntüle | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Saha Turu** |
| Saha turu başlat | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| Saha turu tamamla | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| **Aksiyon** |
| Aksiyon oluştur | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| Aksiyon ata | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Aksiyon güncelle | ✅ | ✅ | ❌ | ✅ | ❌ | ✅* |
| Termin değiştir | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Risk puanı ver | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| **Kapatma** |
| Kapatma talebi | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| Kapatma onayı | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| Üst onay | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| **Raporlama** |
| Dashboard görüntüle | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Rapor görüntüle | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Veri export | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |

*Sadece kendi aksiyonları için

---

## 💻 Kod Örnekleri

### Controller'da Yetki Kontrolü

```php
use Src\Helpers\Permission;
use Src\Middleware\AuthMiddleware;

class ChecklistController
{
    public function store(): void
    {
        // Yetki kontrolü
        Permission::require(Permission::PERM_CREATE_CHECKLIST);
        
        // İşlem devam eder...
    }
    
    public function update(int $id): void
    {
        // Birden fazla yetkiden biri yeterli
        Permission::requireAny([
            Permission::PERM_UPDATE_CHECKLIST,
            Permission::PERM_MANAGE_PERMISSIONS
        ]);
        
        // İşlem devam eder...
    }
}
```

### Rol Kontrolü

```php
// Sadece HSE veya Admin
if (Permission::hasAnyRole(['hse', 'admin'])) {
    // İşlem yap
}

// Sadece Admin
if (Permission::hasRole('admin')) {
    // İşlem yap
}
```

### Yetki Kontrolü (Boolean)

```php
// Yetki var mı kontrol et
if (Permission::check(Permission::PERM_ASSIGN_ACTION)) {
    // Aksiyon atama butonu göster
}

// Birden fazla yetkiden biri
if (Permission::checkAny([
    Permission::PERM_APPROVE_CLOSURE,
    Permission::PERM_UPPER_APPROVE_CLOSURE
])) {
    // Onay butonu göster
}
```

---

## 🔒 Endpoint Yetkilendirme Örnekleri

### Checklist Oluşturma
```bash
POST /api/v1/checklists
Authorization: Bearer <token>

# Gerekli Rol: HSE, Admin
# Gerekli Yetki: create_checklist
```

### Aksiyon Atama
```bash
PUT /api/v1/actions/1
Authorization: Bearer <token>

# Gerekli Rol: HSE, Department Head, Admin
# Gerekli Yetki: assign_action
```

### Termin Değiştirme
```bash
PUT /api/v1/actions/1
{
  "due_date": "2025-12-25"
}

# Gerekli Rol: HSE, Upper Management, Department Head, Admin
# Gerekli Yetki: change_due_date
```

### Kapatma Onayı
```bash
PUT /api/v1/actions/1/closure/1/approve

# İlk Onay - Gerekli Rol: HSE, Department Head, Admin
# Gerekli Yetki: approve_closure

# Üst Onay - Gerekli Rol: Upper Management, Admin
# Gerekli Yetki: upper_approve_closure
```

---

## 🚀 AuthApp Entegrasyonu

### Token Oluşturma (AuthApp'te)

```php
use Firebase\JWT\JWT;

$payload = [
    'user_id' => $user->id,
    'role' => $user->role,  // 'hse', 'inspector', vb.
    'permissions' => $user->custom_permissions ?? [],
    'company_id' => $user->company_id,
    'exp' => time() + (60 * 60 * 24 * 7) // 7 gün
];

$token = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
```

### Token Gönderme (Frontend)

```javascript
// API isteği
fetch('https://hse-api.misafirus.com/api/v1/checklists', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(data)
});
```

---

## 📋 Yetki Kodları Listesi

```php
// Checklist
Permission::PERM_CREATE_CHECKLIST
Permission::PERM_UPDATE_CHECKLIST
Permission::PERM_DELETE_CHECKLIST
Permission::PERM_VIEW_CHECKLIST

// Saha Turu
Permission::PERM_START_FIELD_TOUR
Permission::PERM_COMPLETE_FIELD_TOUR

// Aksiyon
Permission::PERM_CREATE_ACTION
Permission::PERM_ASSIGN_ACTION
Permission::PERM_UPDATE_ACTION
Permission::PERM_CHANGE_DUE_DATE
Permission::PERM_SET_RISK_SCORE
Permission::PERM_COMPLETE_ACTION

// Kapatma
Permission::PERM_REQUEST_CLOSURE
Permission::PERM_APPROVE_CLOSURE
Permission::PERM_REJECT_CLOSURE
Permission::PERM_UPPER_APPROVE_CLOSURE

// Raporlama
Permission::PERM_VIEW_DASHBOARD
Permission::PERM_VIEW_REPORTS
Permission::PERM_EXPORT_DATA

// Yönetim
Permission::PERM_MANAGE_USERS
Permission::PERM_MANAGE_PERMISSIONS
```

---

## ⚠️ Hata Kodları

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthorized",
  "error": "Token bulunamadı veya geçersiz"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Bu işlem için yetkiniz bulunmamaktadır",
  "error": "Forbidden"
}
```

---

## 🧪 Test Senaryoları

### 1. HSE Uzmanı - Checklist Oluşturma
```bash
# Token: role=hse
POST /api/v1/checklists
✅ Başarılı - Yetki var
```

### 2. Kontrolör - Checklist Oluşturma
```bash
# Token: role=inspector
POST /api/v1/checklists
❌ 403 Forbidden - Yetki yok
```

### 3. Departman Sorumlusu - Aksiyon Atama
```bash
# Token: role=department_head
PUT /api/v1/actions/1
{
  "assigned_to_user_id": 302
}
✅ Başarılı - Yetki var
```

### 4. Aksiyon Sahibi - Kapatma Talebi
```bash
# Token: role=action_owner, user_id=301
POST /api/v1/actions/1/closure-request
✅ Başarılı - Yetki var
```

### 5. Üst Yönetim - Üst Onay
```bash
# Token: role=upper_management
PUT /api/v1/actions/1/closure/1/approve
{
  "is_upper_approval": true
}
✅ Başarılı - Yetki var
```

---

## 📝 Notlar

1. **Token Süresi:** Token'lar 7 gün geçerlidir
2. **Refresh Token:** AuthApp'te refresh token mekanizması kullanılmalı
3. **Özel Yetkiler:** Kullanıcılara rol dışında özel yetkiler atanabilir
4. **Company ID:** Token'da company_id zorunludur
5. **Audit Log:** Tüm yetki kontrolleri audit log'a kaydedilir

---

## 🔄 Güncelleme Geçmişi

- **v1.0.0** - İlk yetkilendirme sistemi (14.12.2024)
