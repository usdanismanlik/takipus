#!/bin/bash

# Create nginx temp directories
mkdir -p /var/lib/nginx/tmp/client_body \
    /var/lib/nginx/tmp/proxy \
    /var/lib/nginx/tmp/fastcgi \
    /var/lib/nginx/tmp/uwsgi \
    /var/lib/nginx/tmp/scgi

# Set permissions
chown -R www-data:www-data /var/lib/nginx
chmod -R 755 /var/lib/nginx

# Start Nginx in foreground (PHP-FPM already running from base image)
exec nginx -g 'daemon off;'
