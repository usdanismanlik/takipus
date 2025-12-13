# Tüm Roller ve Yetkiler - Detaylı Liste

## 📋 Tüm Yetki Kodları ve Açıklamaları

### **Checklist Yetkileri**

#### `create_checklist`
- **Açıklama:** Yeni checklist oluşturabilir
- **Kullanım:** Checklist şablonları oluşturma
- **Örnek:** Yeni "Yangın Güvenliği Kontrolü" checklist'i oluşturma

#### `update_checklist`
- **Açıklama:** Mevcut checklist'leri güncelleyebilir
- **Kullanım:** Checklist sorularını düzenleme, sıralama değiştirme
- **Örnek:** Checklist'e yeni soru ekleme veya mevcut soruyu güncelleme

#### `delete_checklist`
- **Açıklama:** Checklist'leri silebilir (arşivleyebilir)
- **Kullanım:** Kullanılmayan checklist'leri kaldırma
- **Örnek:** Eski checklist'i arşive taşıma

#### `view_checklist`
- **Açıklama:** Checklist'leri görüntüleyebilir
- **Kullanım:** Checklist listesini ve detaylarını görme
- **Örnek:** Tüm aktif checklist'leri listeleme

---

### **Saha Turu Yetkileri**

#### `start_field_tour`
- **Açıklama:** Yeni saha turu başlatabilir
- **Kullanım:** Checklist bazlı saha turu oluşturma
- **Örnek:** "Genel İş Güvenliği Denetimi" için saha turu başlatma

#### `complete_field_tour`
- **Açıklama:** Saha turunu tamamlayabilir
- **Kullanım:** Tüm soruları cevapladıktan sonra turu kapatma
- **Örnek:** Saha turunu "completed" durumuna alma

---

### **Aksiyon Yönetimi Yetkileri**

#### `create_action`
- **Açıklama:** Yeni aksiyon oluşturabilir
- **Kullanım:** Manuel veya saha turundan aksiyon oluşturma
- **Örnek:** "Yangın söndürücü eksikliği" aksiyonu oluşturma

#### `assign_action`
- **Açıklama:** Aksiyonu bir kullanıcıya veya departmana atayabilir
- **Kullanım:** Sorumlu belirleme, aksiyon dağıtımı
- **Örnek:** Elektrik aksiyonunu bakım departmanına atama
- **Kimler Kullanır:** HSE, Departman Sorumlusu, Admin

#### `update_action`
- **Açıklama:** Aksiyon bilgilerini güncelleyebilir
- **Kullanım:** Açıklama, lokasyon, durum güncelleme
- **Örnek:** Aksiyon açıklamasına ek bilgi ekleme

#### `change_due_date`
- **Açıklama:** Aksiyonun termin tarihini değiştirebilir
- **Kullanım:** Termin uzatma veya öne alma
- **Örnek:** Termin tarihini 2025-12-20'den 2025-12-25'e değiştirme
- **Kimler Kullanır:** HSE, Üst Yönetim, Departman Sorumlusu, Admin
- **Önemli:** Termin değişiklikleri audit log'a kaydedilir

#### `set_risk_score`
- **Açıklama:** Risk puanı (olasılık x şiddet) belirleyebilir
- **Kullanım:** Risk matrisi ile önceliklendirme
- **Örnek:** Olasılık: 5, Şiddet: 4 → Risk Puanı: 20 (Çok Yüksek)
- **Kimler Kullanır:** HSE, Kontrolör, Admin

#### `complete_action`
- **Açıklama:** Aksiyonu doğrudan tamamlayabilir
- **Kullanım:** Kapatma süreci olmadan direkt tamamlama
- **Örnek:** Basit aksiyonları hızlıca kapatma
- **Not:** Genelde kapatma süreci tercih edilir

---

### **Kapatma Süreci Yetkileri**

#### `request_closure`
- **Açıklama:** Aksiyon kapatma talebi gönderebilir
- **Kullanım:** Düzeltici faaliyeti tamamladıktan sonra onay isteme
- **Örnek:** Fotoğraf ve açıklama ile kapatma talebi gönderme
- **Kimler Kullanır:** Aksiyon Sahibi, HSE, Admin

#### `approve_closure`
- **Açıklama:** Kapatma talebini onaylayabilir
- **Kullanım:** İlk kademe onayı (departman/HSE)
- **Örnek:** Kapatma talebini inceleyip onaylama
- **Kimler Kullanır:** HSE, Departman Sorumlusu, Admin

#### `reject_closure`
- **Açıklama:** Kapatma talebini reddedebilir
- **Kullanım:** Yetersiz düzeltici faaliyet durumunda red
- **Örnek:** "Uyarı levhaları yetersiz" gerekçesiyle red
- **Kimler Kullanır:** HSE, Departman Sorumlusu, Admin

#### `upper_approve_closure`
- **Açıklama:** Üst yönetim onayı verebilir (ikinci kademe)
- **Kullanım:** Kritik aksiyonlar için nihai onay
- **Örnek:** Yüksek riskli aksiyonların son onayı
- **Kimler Kullanır:** Üst Yönetim, Admin

---

### **Raporlama ve Dashboard Yetkileri**

#### `view_dashboard`
- **Açıklama:** Dashboard'u görüntüleyebilir
- **Kullanım:** İstatistikler, grafikler, özet bilgiler
- **Örnek:** Açık aksiyon sayısı, risk dağılımı görme
- **Kimler Kullanır:** Tüm roller

#### `view_reports`
- **Açıklama:** Detaylı raporları görüntüleyebilir
- **Kullanım:** Analiz raporları, trend analizleri
- **Örnek:** Aylık aksiyon raporu, departman performansı
- **Kimler Kullanır:** HSE, Üst Yönetim, Departman Sorumlusu, Admin

#### `export_data`
- **Açıklama:** Verileri Excel/CSV/JSON formatında dışa aktarabilir
- **Kullanım:** Raporlama, arşivleme, analiz
- **Örnek:** Tüm aksiyonları Excel'e aktarma
- **Kimler Kullanır:** HSE, Üst Yönetim, Admin

---

### **Sistem Yönetimi Yetkileri**

#### `manage_users`
- **Açıklama:** Kullanıcı yönetimi yapabilir
- **Kullanım:** Kullanıcı ekleme, düzenleme, silme
- **Örnek:** Yeni kontrolör ekleme
- **Kimler Kullanır:** Admin

#### `manage_permissions`
- **Açıklama:** Yetki yönetimi yapabilir
- **Kullanım:** Kullanıcılara özel yetki atama
- **Örnek:** Bir kontrolöre geçici "assign_action" yetkisi verme
- **Kimler Kullanır:** Admin

---

## 👥 Tüm Roller ve Yetkileri

### **1. Admin** (`admin`)

**Açıklama:** Sistem yöneticisi, tam yetkili

**Tüm Yetkiler:**
- ✅ `create_checklist` - Checklist oluşturma
- ✅ `update_checklist` - Checklist güncelleme
- ✅ `delete_checklist` - Checklist silme
- ✅ `view_checklist` - Checklist görüntüleme
- ✅ `start_field_tour` - Saha turu başlatma
- ✅ `complete_field_tour` - Saha turu tamamlama
- ✅ `create_action` - Aksiyon oluşturma
- ✅ `assign_action` - Aksiyon atama
- ✅ `update_action` - Aksiyon güncelleme
- ✅ `change_due_date` - Termin değiştirme
- ✅ `set_risk_score` - Risk puanı verme
- ✅ `complete_action` - Aksiyon tamamlama
- ✅ `request_closure` - Kapatma talebi
- ✅ `approve_closure` - Kapatma onayı
- ✅ `reject_closure` - Kapatma reddi
- ✅ `upper_approve_closure` - Üst onay
- ✅ `view_dashboard` - Dashboard görüntüleme
- ✅ `view_reports` - Rapor görüntüleme
- ✅ `export_data` - Veri export
- ✅ `manage_users` - Kullanıcı yönetimi
- ✅ `manage_permissions` - Yetki yönetimi

**Kullanım Senaryoları:**
- Tüm sistem ayarları
- Kullanıcı ve yetki yönetimi
- Acil durumlarda her türlü işlem

---

### **2. HSE Uzmanı** (`hse`)

**Açıklama:** İş sağlığı ve güvenliği uzmanı

**Yetkiler:**
- ✅ `create_checklist` - Checklist oluşturma
- ✅ `update_checklist` - Checklist güncelleme
- ✅ `view_checklist` - Checklist görüntüleme
- ✅ `start_field_tour` - Saha turu başlatma
- ✅ `complete_field_tour` - Saha turu tamamlama
- ✅ `create_action` - Aksiyon oluşturma
- ✅ `assign_action` - Aksiyon atama
- ✅ `update_action` - Aksiyon güncelleme
- ✅ `change_due_date` - Termin değiştirme
- ✅ `set_risk_score` - Risk puanı verme
- ✅ `approve_closure` - Kapatma onayı
- ✅ `reject_closure` - Kapatma reddi
- ✅ `view_dashboard` - Dashboard görüntüleme
- ✅ `view_reports` - Rapor görüntüleme
- ✅ `export_data` - Veri export

**Kullanım Senaryoları:**
- Checklist hazırlama ve yönetimi
- Saha turu yapma ve değerlendirme
- Aksiyon oluşturma ve atama
- Risk değerlendirmesi
- Kapatma onayları

---

### **3. Üst Yönetim** (`upper_management`)

**Açıklama:** Üst düzey yönetici

**Yetkiler:**
- ✅ `view_checklist` - Checklist görüntüleme
- ✅ `view_dashboard` - Dashboard görüntüleme
- ✅ `view_reports` - Rapor görüntüleme
- ✅ `export_data` - Veri export
- ✅ `upper_approve_closure` - Üst onay
- ✅ `change_due_date` - Termin değiştirme

**Kullanım Senaryoları:**
- Genel durum takibi
- Kritik aksiyonların nihai onayı
- Stratejik kararlar için termin uzatma
- Raporlama ve analiz

---

### **4. Departman Sorumlusu** (`department_head`)

**Açıklama:** Departman yöneticisi

**Yetkiler:**
- ✅ `view_checklist` - Checklist görüntüleme
- ✅ `assign_action` - Aksiyon atama (kendi departmanı)
- ✅ `update_action` - Aksiyon güncelleme
- ✅ `change_due_date` - Termin değiştirme
- ✅ `approve_closure` - Kapatma onayı
- ✅ `reject_closure` - Kapatma reddi
- ✅ `view_dashboard` - Dashboard görüntüleme
- ✅ `view_reports` - Rapor görüntüleme

**Kullanım Senaryoları:**
- Kendi departmanına gelen aksiyonları yönetme
- Ekip üyelerine aksiyon dağıtımı
- Kapatma taleplerini değerlendirme
- Termin ayarlamaları

---

### **5. Kontrolör** (`inspector`)

**Açıklama:** Saha turu yapan personel

**Yetkiler:**
- ✅ `view_checklist` - Checklist görüntüleme
- ✅ `start_field_tour` - Saha turu başlatma
- ✅ `complete_field_tour` - Saha turu tamamlama
- ✅ `create_action` - Aksiyon oluşturma
- ✅ `set_risk_score` - Risk puanı verme
- ✅ `view_dashboard` - Dashboard görüntüleme

**Kullanım Senaryoları:**
- Saha turları yapma
- Uygunsuzluk tespit etme
- Risk değerlendirmesi
- Aksiyon oluşturma (atama yapamaz)

---

### **6. Aksiyon Sahibi** (`action_owner`)

**Açıklama:** Aksiyondan sorumlu personel

**Yetkiler:**
- ✅ `view_checklist` - Checklist görüntüleme
- ✅ `update_action` - Aksiyon güncelleme (sadece kendi aksiyonları)
- ✅ `request_closure` - Kapatma talebi
- ✅ `view_dashboard` - Dashboard görüntüleme

**Kullanım Senaryoları:**
- Kendine atanan aksiyonları görme
- Aksiyon durumunu güncelleme
- Düzeltici faaliyet sonrası kapatma talebi gönderme

---

## 📊 Yetki Karşılaştırma Tablosu

| Yetki | Admin | HSE | Üst Yönetim | Dept. Head | Kontrolör | Aksiyon Sahibi |
|-------|-------|-----|-------------|------------|-----------|----------------|
| `create_checklist` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `update_checklist` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `delete_checklist` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `view_checklist` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `start_field_tour` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| `complete_field_tour` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| `create_action` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| `assign_action` | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| `update_action` | ✅ | ✅ | ❌ | ✅ | ❌ | ✅* |
| `change_due_date` | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `set_risk_score` | ✅ | ✅ | ❌ | ❌ | ✅ | ❌ |
| `complete_action` | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `request_closure` | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| `approve_closure` | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| `reject_closure` | ✅ | ✅ | ❌ | ✅ | ❌ | ❌ |
| `upper_approve_closure` | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| `view_dashboard` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `view_reports` | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `export_data` | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `manage_users` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `manage_permissions` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

*Sadece kendi aksiyonları için

---

## 🎯 Özel Yetki Kullanım Örnekleri

### **Senaryo 1: Kontrolöre Geçici Aksiyon Atama Yetkisi**

Normalde kontrolör aksiyon atayamaz, ama acil durumlarda:

```json
{
  "user_id": 302,
  "role": "inspector",
  "permissions": ["assign_action"],
  "company_id": "F12345"
}
```

### **Senaryo 2: Aksiyon Sahibine Termin Değiştirme Yetkisi**

Güvenilir bir çalışana özel yetki:

```json
{
  "user_id": 305,
  "role": "action_owner",
  "permissions": ["change_due_date"],
  "company_id": "F12345"
}
```

### **Senaryo 3: Departman Sorumlusuna Checklist Oluşturma**

Deneyimli departman sorumlusuna:

```json
{
  "user_id": 303,
  "role": "department_head",
  "permissions": ["create_checklist", "update_checklist"],
  "company_id": "F12345"
}
```

---

## 📝 Yetki Kontrol Örnekleri

### Kod İçinde Kullanım

```php
// Tek yetki kontrolü
if (Permission::check('assign_action')) {
    // Aksiyon atama butonu göster
}

// Termin değiştirme yetkisi
if (Permission::check('change_due_date')) {
    // Termin değiştirme formu göster
}

// Birden fazla yetkiden biri
if (Permission::checkAny(['approve_closure', 'upper_approve_closure'])) {
    // Onay butonu göster
}

// Zorunlu kontrol (yetki yoksa 403 hatası)
Permission::require('set_risk_score');
```

---

## 🔍 Yetki Sorgulama

Kullanıcının tüm yetkilerini görmek için:

```php
$permissions = Permission::getUserPermissions();
// ["create_checklist", "assign_action", "change_due_date", ...]
```

Rol açıklamalarını görmek için:

```php
$roles = Permission::getRoleDescriptions();
```

---

## ⚠️ Önemli Notlar

1. **Admin her zaman yetkili:** Admin rolü tüm yetki kontrollerini geçer
2. **Özel yetkiler eklenir:** `permissions` array'deki yetkiler rol yetkilerine eklenir
3. **Büyük/küçük harf:** Tüm yetki kodları küçük harfle yazılmalı
4. **Audit log:** Tüm yetki kontrolleri loglanır
5. **Company izolasyonu:** Her kullanıcı sadece kendi firmasının verilerini görür
