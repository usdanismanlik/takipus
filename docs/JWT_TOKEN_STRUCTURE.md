# JWT Token Yapısı - HSE API

## 🔐 Beklenen JWT Token Formatı

### Minimum Gerekli Yapı

```json
{
  "user_id": 301,
  "company_id": "F12345",
  "role": "hse",
  "exp": 1734134400
}
```

### Tam Yapı (Önerilen)

```json
{
  "user_id": 301,
  "company_id": "F12345",
  "role": "hse",
  "permissions": ["create_checklist", "assign_action"],
  "name": "Ahmet Yılmaz",
  "email": "ahmet@misafirus.com",
  "department_id": 5,
  "iat": 1733529600,
  "exp": 1734134400
}
```

---

## 📋 Alan Açıklamaları

### **1. user_id** (Zorunlu)
- **Tip:** `integer`
- **Açıklama:** AuthApp'teki kullanıcı ID'si
- **Örnek:** `301`
- **Kullanım:** Tüm işlemlerde kullanıcı kimliği

```php
$userId = AuthMiddleware::getUserId(); // 301
```

---

### **2. company_id** (Zorunlu)
- **Tip:** `string`
- **Açıklama:** Firma ID'si (AuthApp'ten)
- **Format:** `"F" + sayı` veya özel kod
- **Örnek:** `"F12345"`, `"COMP001"`
- **Kullanım:** Veri izolasyonu, firma bazlı filtreleme

```php
$companyId = AuthMiddleware::getCompanyId(); // "F12345"
```

**Önemli:** Tüm API isteklerinde kullanıcının sadece kendi firmasının verilerini görmesi için kullanılır.

---

### **3. role** (Zorunlu)
- **Tip:** `string`
- **Açıklama:** Kullanıcının rolü
- **Geçerli Değerler:**
  - `"admin"` - Sistem yöneticisi
  - `"hse"` - HSE Uzmanı
  - `"upper_management"` - Üst Yönetim
  - `"department_head"` - Departman Sorumlusu
  - `"inspector"` - Kontrolör
  - `"action_owner"` - Aksiyon Sahibi

```php
$role = AuthMiddleware::getUserRole(); // "hse"
```

**Örnek Kullanım:**
```php
if (Permission::hasRole('hse')) {
    // HSE işlemleri
}
```

---

### **4. permissions** (Opsiyonel)
- **Tip:** `array` (string dizisi)
- **Açıklama:** Kullanıcıya özel ek yetkiler
- **Varsayılan:** `[]` (boş array)
- **Kullanım:** Rol dışında özel yetkiler vermek için

**Boş Array (Sadece rol yetkileri):**
```json
{
  "permissions": []
}
```

**Özel Yetkilerle:**
```json
{
  "permissions": [
    "create_checklist",
    "assign_action",
    "approve_closure"
  ]
}
```

```php
$permissions = AuthMiddleware::getUserPermissions(); 
// ["create_checklist", "assign_action"]
```

**Kullanım Senaryosu:**
- Bir "action_owner" rolündeki kullanıcıya geçici olarak "assign_action" yetkisi vermek
- Bir "inspector"e özel olarak "approve_closure" yetkisi vermek

---

### **5. exp** (Zorunlu)
- **Tip:** `integer` (Unix timestamp)
- **Açıklama:** Token son kullanma tarihi
- **Önerilen Süre:** 7 gün (604800 saniye)
- **Örnek:** `1734134400`

```php
$exp = time() + (60 * 60 * 24 * 7); // 7 gün
```

---

### **6. iat** (Opsiyonel ama önerilen)
- **Tip:** `integer` (Unix timestamp)
- **Açıklama:** Token oluşturulma zamanı
- **Örnek:** `1733529600`

```php
$iat = time();
```

---

### **7. name, email, department_id** (Opsiyonel)
- **Tip:** `string`, `string`, `integer`
- **Açıklama:** Kullanıcı bilgileri (loglama için)
- **Kullanım:** Audit log'da kullanıcı adı göstermek

---

## 🔧 AuthApp'te Token Oluşturma

### PHP Örneği

```php
<?php

use Firebase\JWT\JWT;

// Kullanıcı bilgilerini al
$user = User::find($userId);

// Token payload'ı oluştur
$payload = [
    // ZORUNLU ALANLAR
    'user_id' => $user->id,
    'company_id' => $user->company_id,  // "F12345" formatında
    'role' => $user->role,               // "hse", "inspector", vb.
    'exp' => time() + (60 * 60 * 24 * 7), // 7 gün
    
    // OPSIYONEL ALANLAR
    'permissions' => $user->custom_permissions ?? [], // Özel yetkiler
    'iat' => time(),
    'name' => $user->name,
    'email' => $user->email,
    'department_id' => $user->department_id,
];

// Token oluştur
$token = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

// Response
return [
    'token' => $token,
    'expires_in' => 604800, // 7 gün (saniye)
    'token_type' => 'Bearer'
];
```

---

## 📤 Token Gönderme

### HTTP Header

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjozMDEsImNvbXBhbnlfaWQiOiJGMTIzNDUiLCJyb2xlIjoiaHNlIiwicGVybWlzc2lvbnMiOltdLCJleHAiOjE3MzQxMzQ0MDB9.xxx
```

### JavaScript Örneği

```javascript
const token = localStorage.getItem('auth_token');

fetch('https://hse-api.misafirus.com/api/v1/checklists', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    company_id: 'F12345',
    name: 'Yeni Checklist'
  })
});
```

---

## 🎯 Kullanım Örnekleri

### Örnek 1: HSE Uzmanı (Özel Yetki Yok)

```json
{
  "user_id": 301,
  "company_id": "F12345",
  "role": "hse",
  "permissions": [],
  "exp": 1734134400
}
```

**Yetkiler:**
- Rol bazlı tüm HSE yetkileri
- Checklist oluşturma ✅
- Aksiyon atama ✅
- Risk puanı verme ✅

---

### Örnek 2: Kontrolör (Özel Yetkilerle)

```json
{
  "user_id": 302,
  "company_id": "F12345",
  "role": "inspector",
  "permissions": ["assign_action", "change_due_date"],
  "exp": 1734134400
}
```

**Yetkiler:**
- Rol bazlı kontrolör yetkileri
- Saha turu yapma ✅
- Risk puanı verme ✅
- **Özel:** Aksiyon atama ✅ (normalde yok)
- **Özel:** Termin değiştirme ✅ (normalde yok)

---

### Örnek 3: Departman Sorumlusu

```json
{
  "user_id": 303,
  "company_id": "F12345",
  "role": "department_head",
  "permissions": [],
  "department_id": 5,
  "exp": 1734134400
}
```

**Yetkiler:**
- Kendi departmanına aksiyon atama ✅
- Kapatma onayı ✅
- Termin değiştirme ✅

---

### Örnek 4: Admin (Tam Yetki)

```json
{
  "user_id": 101,
  "company_id": "F12345",
  "role": "admin",
  "permissions": [],
  "exp": 1734134400
}
```

**Yetkiler:**
- Tüm işlemler ✅✅✅

---

## ⚠️ Önemli Notlar

### 1. **company_id Formatı**
```
✅ Doğru: "F12345", "COMP001", "ABC123"
❌ Yanlış: 12345 (integer), null, ""
```

### 2. **permissions Array Formatı**
```json
✅ Doğru: []
✅ Doğru: ["create_checklist", "assign_action"]
❌ Yanlış: null
❌ Yanlış: "create_checklist,assign_action" (string)
```

### 3. **role Değerleri**
```
✅ Doğru: "hse", "inspector", "admin"
❌ Yanlış: "HSE", "Inspector", "ADMIN" (büyük harf)
❌ Yanlış: "user", "member" (tanımsız rol)
```

### 4. **Token Süresi**
```php
// Önerilen: 7 gün
$exp = time() + (60 * 60 * 24 * 7);

// Çok kısa: 1 saat (kullanıcı deneyimi kötü)
$exp = time() + 3600;

// Çok uzun: 30 gün (güvenlik riski)
$exp = time() + (60 * 60 * 24 * 30);
```

---

## 🔍 Token Decode Örneği

### HSE API'de Token Nasıl Okunuyor

```php
// src/Middleware/AuthMiddleware.php

$token = JWT::getTokenFromHeader();
$payload = JWT::decode($token);

// Payload içeriği:
// {
//   "user_id": 301,
//   "company_id": "F12345",
//   "role": "hse",
//   "permissions": [],
//   "exp": 1734134400
// }

// Global değişkenlere ata
$GLOBALS['auth_user_id'] = $payload->user_id;           // 301
$GLOBALS['auth_company_id'] = $payload->company_id;     // "F12345"
$GLOBALS['auth_user_role'] = $payload->role;            // "hse"
$GLOBALS['auth_user_permissions'] = $payload->permissions ?? []; // []
```

---

## 🧪 Test Token'ları

### Test için örnek token'lar (JWT_SECRET = "test_secret")

**HSE Uzmanı:**
```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjozMDEsImNvbXBhbnlfaWQiOiJGMTIzNDUiLCJyb2xlIjoiaHNlIiwicGVybWlzc2lvbnMiOltdLCJleHAiOjE3MzQxMzQ0MDB9.xxx
```

**Kontrolör:**
```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjozMDIsImNvbXBhbnlfaWQiOiJGMTIzNDUiLCJyb2xlIjoiaW5zcGVjdG9yIiwicGVybWlzc2lvbnMiOltdLCJleHAiOjE3MzQxMzQ0MDB9.xxx
```

**Admin:**
```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxMDEsImNvbXBhbnlfaWQiOiJGMTIzNDUiLCJyb2xlIjoiYWRtaW4iLCJwZXJtaXNzaW9ucyI6W10sImV4cCI6MTczNDEzNDQwMH0.xxx
```

---

## 📝 Checklist

AuthApp'te token oluştururken kontrol et:

- [ ] `user_id` integer olarak gönderildi mi?
- [ ] `company_id` string olarak gönderildi mi?
- [ ] `role` küçük harfle ve geçerli değerlerden biri mi?
- [ ] `permissions` array olarak gönderildi mi? (boş olsa bile `[]`)
- [ ] `exp` gelecek bir tarih mi?
- [ ] JWT_SECRET her iki tarafta da aynı mı?
- [ ] Token "Bearer " prefix'i ile gönderiliyor mu?

---

## 🔗 İlgili Dosyalar

- `/src/Middleware/AuthMiddleware.php` - Token decode
- `/src/Helpers/Permission.php` - Yetki kontrolü
- `/docs/AUTHORIZATION.md` - Yetkilendirme dokümantasyonu
