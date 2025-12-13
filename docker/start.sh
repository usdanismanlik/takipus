#!/bin/bash

# Create nginx temp directories
mkdir -p /var/lib/nginx/tmp/client_body \
    /var/lib/nginx/tmp/proxy \
    /var/lib/nginx/tmp/fastcgi \
    /var/lib/nginx/tmp/uwsgi \
    /var/lib/nginx/tmp/scgi

# Set permissions
chown -R www-data:www-data /var/lib/nginx /var/www/html
chmod -R 755 /var/lib/nginx

# Start PHP-FPM in background
php-fpm -D

# Start Nginx in foreground
exec nginx -g 'daemon off;'
