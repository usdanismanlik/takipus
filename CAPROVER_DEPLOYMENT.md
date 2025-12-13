# HSE API - CapRover Deployment Rehberi

Bu doküman, HSE API'nin CapRover üzerinde nasıl deploy edileceğini açıklar.

---

## 📋 Gereksinimler

- CapRover kurulu bir sunucu
- CapRover CLI (`npm install -g caprover`)
- Git repository
- MySQL veritabanı (CapRover üzerinde)

---

## 🚀 Hızlı Başlangıç

### 1. CapRover CLI Kurulumu

```bash
npm install -g caprover
```

### 2. CapRover'a Bağlanma

```bash
caprover login
```

Sunucu bilgilerinizi girin:
- CapRover URL: `https://captain.yourdomain.com`
- Password: CapRover admin şifreniz
- Machine name: `hse-production` (veya istediğiniz isim)

### 3. Uygulama Oluşturma

CapRover dashboard'dan:
1. **Apps** > **One-Click Apps/Databases** > **MySQL**
2. MySQL veritabanı oluşturun
3. Veritabanı bilgilerini kaydedin

Yeni uygulama oluşturun:
1. **Apps** > **Create New App**
2. App Name: `hse-api`
3. **Has Persistent Data**: ✅ (storage için)

### 4. Environment Variables Ayarlama

CapRover dashboard'da `hse-api` uygulamasına gidin ve **App Configs** > **Environment Variables** bölümünden:

```bash
# Database
DB_HOST=srv-captain--mysql-db
DB_PORT=3306
DB_NAME=hse_db
DB_USER=hse_user
DB_PASS=your_secure_password

# JWT
JWT_SECRET=your-super-secret-jwt-key-change-in-production

# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hse-api.yourdomain.com

# File Upload
UPLOAD_MAX_SIZE=10485760
UPLOAD_PATH=/var/www/html/storage/uploads
```

### 5. Persistent Directory Ayarlama

**App Configs** > **Persistent Directories**:

```
Path in App: /var/www/html/storage
Label: hse-storage
```

### 6. Deploy Etme

Proje dizininde:

```bash
# İlk deployment
caprover deploy

# Veya belirli bir makineye
caprover deploy -a hse-api -m hse-production
```

### 7. HTTPS Aktifleştirme

1. **HTTP Settings** > **Enable HTTPS**: ✅
2. **Force HTTPS**: ✅
3. **Redirect HTTP to HTTPS**: ✅

### 8. Domain Bağlama

1. **HTTP Settings** > **Custom Domains**
2. Domain ekleyin: `api.yourdomain.com`
3. DNS ayarlarınızda A kaydı oluşturun

---

## 📁 Dosya Yapısı

```
hse-api/
├── captain-definition          # CapRover config
├── Dockerfile.caprover         # Production Dockerfile
├── .dockerignore              # Docker ignore rules
├── docker/
│   └── caprover/
│       ├── nginx.conf         # Nginx ana config
│       ├── default.conf       # Site config
│       └── supervisord.conf   # Process manager
├── public/
│   └── index.php             # Entry point
├── src/                      # Application code
├── storage/                  # Persistent storage
│   ├── logs/
│   └── uploads/
├── .env                      # Environment variables
└── composer.json             # PHP dependencies
```

---

## 🔧 Deployment Süreci

### captain-definition

```json
{
  "schemaVersion": 2,
  "dockerfilePath": "./Dockerfile.caprover"
}
```

Bu dosya CapRover'a hangi Dockerfile'ı kullanacağını söyler.

### Dockerfile.caprover

Multi-stage build ile optimize edilmiş production image:

1. **PHP 8.2 FPM Alpine** - Hafif base image
2. **Nginx** - Web server
3. **Supervisor** - Process manager (PHP-FPM + Nginx)
4. **Composer** - Dependency management
5. **PHP Extensions** - GD, PDO, MySQL

### Nginx Configuration

- Port 80'de dinler (CapRover proxy arkasında)
- PHP-FPM ile FastCGI
- Static file caching
- Security headers
- 10MB max upload size

### Supervisor

İki process'i yönetir:
- `php-fpm` - PHP FastCGI Process Manager
- `nginx` - Web server

---

## 🗄️ Veritabanı Kurulumu

### MySQL Container Oluşturma

CapRover'da MySQL one-click app:

```yaml
App Name: mysql-db
MySQL Version: 8.0
Root Password: strong_root_password
Database: hse_db
User: hse_user
Password: strong_user_password
```

### Schema Import

SSH ile sunucuya bağlanın:

```bash
# Container'a bağlan
docker exec -it $(docker ps -qf "name=srv-captain--mysql-db") bash

# MySQL'e gir
mysql -u root -p

# Database seç
USE hse_db;

# Schema'yı import et (local'den kopyaladıktan sonra)
SOURCE /path/to/schema.sql;
```

Veya CapRover dashboard'dan:

1. MySQL app'e gir
2. **Deployment** > **App Configs** > **Service Update Override**
3. Volume ekle: `./database:/docker-entrypoint-initdb.d`

---

## 🔐 Güvenlik

### Önerilen Ayarlar

1. **Environment Variables**:
   - Tüm hassas bilgileri env variable olarak saklayın
   - `.env` dosyasını asla commit etmeyin

2. **HTTPS**:
   - Her zaman HTTPS kullanın
   - Let's Encrypt otomatik sertifika

3. **Database**:
   - Güçlü şifreler kullanın
   - Root kullanıcısını kullanmayın
   - Sadece gerekli yetkileri verin

4. **File Uploads**:
   - Upload size limit: 10MB
   - Sadece izin verilen dosya tipleri
   - Persistent storage kullanın

### Firewall

CapRover otomatik olarak yönetir, ek ayar gerekmez.

---

## 📊 Monitoring & Logs

### Logları Görüntüleme

CapRover dashboard:
1. App'e gir
2. **Deployment** > **View Logs**

CLI ile:
```bash
caprover logs -a hse-api -f
```

### Nginx Logs

```bash
# Container'a bağlan
docker exec -it $(docker ps -qf "name=srv-captain--hse-api") sh

# Logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

### PHP Logs

```bash
# Container içinde
tail -f /var/www/html/storage/logs/*.log
```

---

## 🔄 Güncelleme ve Yeniden Deploy

### Kod Güncellemesi

```bash
# Git'te değişiklik yap
git add .
git commit -m "Update: feature xyz"
git push

# Deploy et
caprover deploy
```

### Zero-Downtime Deployment

CapRover otomatik olarak zero-downtime deployment yapar:
1. Yeni container başlatılır
2. Health check yapılır
3. Başarılıysa trafik yeni container'a yönlendirilir
4. Eski container kapatılır

### Rollback

```bash
# Önceki versiyona dön
caprover deploy --imageName captain/hse-api:previous
```

Veya dashboard'dan:
1. **Deployment** > **Previous Builds**
2. İstediğiniz versiyonu seçin

---

## 🧪 Test Etme

### Health Check

```bash
curl https://api.yourdomain.com/api/v1/health
```

### API Test

```bash
# Login
curl -X POST https://api.yourdomain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@hse.com","password":"test123"}'

# Checklist listesi
curl https://api.yourdomain.com/api/v1/admin/checklists \
  -H "Authorization: Bearer {token}"
```

---

## 🐛 Troubleshooting

### Container Başlamıyor

```bash
# Logs kontrol et
caprover logs -a hse-api

# Container'ı yeniden başlat
caprover restart -a hse-api
```

### Database Bağlantı Hatası

1. Environment variables'ı kontrol edin
2. MySQL container'ın çalıştığından emin olun
3. Network bağlantısını test edin:

```bash
docker exec -it $(docker ps -qf "name=srv-captain--hse-api") sh
ping srv-captain--mysql-db
```

### 502 Bad Gateway

- PHP-FPM çalışıyor mu kontrol edin
- Nginx config'i doğru mu kontrol edin
- Logs'u inceleyin

### Upload Çalışmıyor

- Persistent directory doğru ayarlandı mı?
- Permissions doğru mu? (775)
- Upload size limit yeterli mi?

---

## 📈 Performance Optimization

### PHP-FPM Tuning

Container'da `/usr/local/etc/php-fpm.d/www.conf` düzenleyin:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

### Nginx Caching

Static dosyalar için 30 gün cache (zaten aktif).

### Database Connection Pooling

PDO persistent connections kullanılıyor.

---

## 💾 Backup

### Database Backup

```bash
# Otomatik backup script
docker exec $(docker ps -qf "name=srv-captain--mysql-db") \
  mysqldump -u hse_user -p hse_db > backup_$(date +%Y%m%d).sql
```

### Storage Backup

```bash
# Persistent volume backup
docker run --rm \
  -v srv-captain--hse-api-hse-storage:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/storage_backup_$(date +%Y%m%d).tar.gz /data
```

### Otomatik Backup (Cron)

CapRover sunucusunda cron job:

```bash
# /etc/cron.daily/hse-backup.sh
#!/bin/bash
# Database backup
docker exec $(docker ps -qf "name=srv-captain--mysql-db") \
  mysqldump -u hse_user -pPASSWORD hse_db | \
  gzip > /backups/hse_db_$(date +%Y%m%d).sql.gz

# Keep last 7 days
find /backups -name "hse_db_*.sql.gz" -mtime +7 -delete
```

---

## 🔗 Faydalı Komutlar

```bash
# Deploy
caprover deploy -a hse-api

# Logs
caprover logs -a hse-api -f

# Restart
caprover restart -a hse-api

# Shell access
docker exec -it $(docker ps -qf "name=srv-captain--hse-api") sh

# Database shell
docker exec -it $(docker ps -qf "name=srv-captain--mysql-db") mysql -u hse_user -p

# Container stats
docker stats $(docker ps -qf "name=srv-captain--hse-api")

# Remove old images
docker image prune -a
```

---

## 📚 Kaynaklar

- [CapRover Documentation](https://caprover.com/docs)
- [CapRover CLI](https://github.com/caprover/caprover-cli)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)

---

## ✅ Deployment Checklist

- [ ] CapRover kurulu ve çalışıyor
- [ ] MySQL database oluşturuldu
- [ ] Environment variables ayarlandı
- [ ] Persistent directory yapılandırıldı
- [ ] Schema import edildi
- [ ] HTTPS aktifleştirildi
- [ ] Domain bağlandı
- [ ] Health check başarılı
- [ ] API testleri geçti
- [ ] Backup stratejisi kuruldu
- [ ] Monitoring aktif

---

**Son Güncelleme**: 14 Aralık 2025  
**CapRover Version**: 1.10+  
**PHP Version**: 8.2  
**Nginx Version**: Latest Alpine
