# HSE Backend - Production Deployment

## Docker Deployment

### Prerequisites

- Docker
- Docker Compose
- MySQL 8.0 (veya container kullanın)

### Quick Start

```bash
# 1. Clone repository
git clone <repo-url>
cd hse-api

# 2. Environment variables ayarla
cp .env.example .env
nano .env  # Production değerlerini girin

# 3. Build ve başlat
docker-compose build
docker-compose up -d

# 4. Database migration
docker exec hse-api-mysql mysql -u root -p<ROOT_PASSWORD> hse_db < database/schema.sql

# 5. Test
curl http://localhost:8081/api/v1/auth/login
```

### Environment Variables

`.env` dosyasında ayarlanması gerekenler:

```env
# Application
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=<STRONG_SECRET_KEY>  # Değiştirin!

# Database
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=hse_db
DB_USERNAME=hse_user
DB_PASSWORD=<STRONG_PASSWORD>  # Değiştirin!
DB_ROOT_PASSWORD=<STRONG_ROOT_PASSWORD>  # Değiştirin!
```

## Production Architecture

```
┌─────────────┐
│   Nginx     │ :80
│  (Alpine)   │
└──────┬──────┘
       │ fastcgi_pass
       │
┌──────▼──────┐
│  PHP-FPM    │ :9000
│   (8.2)     │
└──────┬──────┘
       │
┌──────▼──────┐
│   MySQL     │ :3306
│   (8.0)     │
└─────────────┘
```

## Container'lar

### 1. hse-api-nginx
- **Image**: nginx:1.26-alpine
- **Port**: 8081:80
- **Role**: Reverse proxy, static files
- **Config**: `docker/nginx/`

### 2. hse-api-php
- **Image**: php:8.2-fpm
- **Port**: 9000 (internal)
- **Role**: PHP application
- **Config**: `docker/php/Dockerfile`

### 3. hse-api-mysql
- **Image**: mysql:8.0
- **Port**: 3306 (internal)
- **Role**: Database
- **Volume**: `mysql_data` (persistent)

## Useful Commands

```bash
# Logs
docker-compose logs -f
docker-compose logs -f nginx
docker-compose logs -f php

# Restart
docker-compose restart

# Rebuild
docker-compose down
docker-compose build --no-cache
docker-compose up -d

# Database backup
docker exec hse-api-mysql mysqldump -u root -p<PASSWORD> hse_db > backup.sql

# Database restore
docker exec -i hse-api-mysql mysql -u root -p<PASSWORD> hse_db < backup.sql

# Shell access
docker exec -it hse-api-php bash
docker exec -it hse-api-nginx sh
docker exec -it hse-api-mysql bash
```

## Security Checklist

- [ ] `.env` dosyasında güçlü şifreler kullanıldı
- [ ] JWT_SECRET değiştirildi
- [ ] APP_DEBUG=false production'da
- [ ] Database root password değiştirildi
- [ ] CORS production domain için ayarlandı
- [ ] HTTPS kullanılıyor (reverse proxy ile)
- [ ] File upload limitleri ayarlandı
- [ ] Rate limiting eklendi (opsiyonel)

## Monitoring

### Health Check

```bash
# API health
curl http://localhost:8081/api/v1/auth/login

# Container status
docker-compose ps

# Resource usage
docker stats
```

### Logs

```bash
# Application logs
docker-compose logs -f php

# Nginx access logs
docker exec hse-api-nginx tail -f /var/log/nginx/access.log

# Nginx error logs
docker exec hse-api-nginx tail -f /var/log/nginx/error.log
```

## Scaling

### Horizontal Scaling (Multiple PHP Containers)

```yaml
# docker-compose.yml
php:
  deploy:
    replicas: 3
```

### Load Balancer

Nginx upstream configuration:

```nginx
upstream php_backend {
    server php1:9000;
    server php2:9000;
    server php3:9000;
}
```

## Troubleshooting

### Container başlamıyor
```bash
docker-compose logs <service-name>
docker inspect <container-name>
```

### Database connection error
```bash
# MySQL container çalışıyor mu?
docker-compose ps mysql

# Network bağlantısı
docker exec hse-api-php ping mysql
```

### Permission errors
```bash
# Storage permissions
docker exec hse-api-php chown -R www-data:www-data /var/www/html/storage
docker exec hse-api-php chmod -R 775 /var/www/html/storage
```

## Production Tips

1. **Reverse Proxy**: Nginx/Caddy ile HTTPS
2. **Backup**: Otomatik database backup
3. **Monitoring**: Prometheus + Grafana
4. **Logging**: ELK Stack veya Loki
5. **CI/CD**: GitHub Actions / GitLab CI
