# Deployment Guide

Complete guide for deploying Redeem-X Voucher System to production.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Server Requirements](#server-requirements)
- [Initial Setup](#initial-setup)
- [Environment Configuration](#environment-configuration)
- [Database Setup](#database-setup)
- [Building Assets](#building-assets)
- [Web Server Configuration](#web-server-configuration)
- [Queue Workers](#queue-workers)
- [Cron Jobs](#cron-jobs)
- [SSL/HTTPS Setup](#sslhttps-setup)
- [Post-Deployment](#post-deployment)
- [Monitoring](#monitoring)
- [Troubleshooting](#troubleshooting)

## Prerequisites

- Server with SSH access
- Domain name pointed to server IP
- Git installed on server
- Composer installed globally
- Node.js 18+ and npm installed
- Database server (MySQL 8.0+, PostgreSQL 13+, or SQLite)
- Redis (recommended for caching and queues)

## Server Requirements

### Minimum Specifications

- **CPU**: 2 cores
- **RAM**: 2GB
- **Storage**: 10GB SSD
- **OS**: Ubuntu 22.04 LTS (recommended) or similar

### Software Stack

- **PHP**: 8.2 or higher
- **Web Server**: Nginx or Apache
- **Database**: MySQL 8.0+, PostgreSQL 13+, or SQLite 3.35+
- **Cache/Queue**: Redis 6.0+ (recommended)

### PHP Extensions

Required extensions:
```bash
php8.2-cli
php8.2-fpm
php8.2-mysql (or php8.2-pgsql for PostgreSQL)
php8.2-sqlite3
php8.2-curl
php8.2-mbstring
php8.2-xml
php8.2-bcmath
php8.2-redis
```

Install on Ubuntu:
```bash
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-curl \
    php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-redis php8.2-sqlite3
```

## Initial Setup

### 1. Clone Repository

```bash
cd /var/www
sudo git clone https://github.com/your-org/redeem-x.git
cd redeem-x
sudo chown -R www-data:www-data /var/www/redeem-x
```

### 2. Install Dependencies

```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies
npm ci --production
```

### 3. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/redeem-x
sudo chmod -R 755 /var/www/redeem-x
sudo chmod -R 775 /var/www/redeem-x/storage
sudo chmod -R 775 /var/www/redeem-x/bootstrap/cache
```

## Environment Configuration

### 1. Create `.env` File

```bash
cp .env.example .env
nano .env  # or use your preferred editor
```

### 2. Configure Environment Variables

**Application Settings:**
```env
APP_NAME="Redeem-X"
APP_ENV=production
APP_KEY=  # Generate with: php artisan key:generate
APP_DEBUG=false
APP_TIMEZONE=Asia/Manila
APP_URL=https://your-domain.com
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_PH
```

**Database Settings:**

For MySQL/MariaDB:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=redeem_x
DB_USERNAME=redeem_x_user
DB_PASSWORD=your_secure_password
```

For PostgreSQL:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=redeem_x
DB_USERNAME=redeem_x_user
DB_PASSWORD=your_secure_password
```

For SQLite:
```env
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/redeem-x/database/database.sqlite
```

**Cache & Session:**
```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**WorkOS Authentication:**
```env
WORKOS_CLIENT_ID=client_your_production_id
WORKOS_API_KEY=sk_live_your_production_key
WORKOS_REDIRECT_URL=https://your-domain.com/auth/callback
```

**Mail Configuration:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

**Logging:**
```env
LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

## Database Setup

### 1. Create Database

**MySQL:**
```sql
CREATE DATABASE redeem_x CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'redeem_x_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON redeem_x.* TO 'redeem_x_user'@'localhost';
FLUSH PRIVILEGES;
```

**PostgreSQL:**
```sql
CREATE DATABASE redeem_x;
CREATE USER redeem_x_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE redeem_x TO redeem_x_user;
```

**SQLite:**
```bash
touch database/database.sqlite
chmod 664 database/database.sqlite
```

### 2. Run Migrations

```bash
php artisan migrate --force
```

### 3. Seed Database (Optional)

```bash
# Only if you need sample data
php artisan db:seed --force
```

## Building Assets

### 1. Build Frontend Assets

```bash
npm run build
```

For SSR support:
```bash
npm run build:ssr
```

### 2. Optimize Laravel

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Cache events
php artisan event:cache
```

## Web Server Configuration

### Nginx Configuration

Create `/etc/nginx/sites-available/redeem-x`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    root /var/www/redeem-x/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Max upload size
    client_max_body_size 10M;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json;

    # Access and error logs
    access_log /var/log/nginx/redeem-x-access.log;
    error_log /var/log/nginx/redeem-x-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 365d;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/redeem-x /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Apache Configuration

Create `/etc/apache2/sites-available/redeem-x.conf`:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/redeem-x/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem

    <Directory /var/www/redeem-x/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/redeem-x-error.log
    CustomLog ${APACHE_LOG_DIR}/redeem-x-access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2enmod rewrite ssl
sudo a2ensite redeem-x
sudo apache2ctl configtest
sudo systemctl reload apache2
```

## Queue Workers

### 1. Install Supervisor

```bash
sudo apt install supervisor
```

### 2. Create Worker Configuration

Create `/etc/supervisor/conf.d/redeem-x-worker.conf`:

```ini
[program:redeem-x-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/redeem-x/artisan queue:work redis --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/redeem-x/storage/logs/worker.log
stopwaitsecs=3600
```

### 3. Start Workers

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start redeem-x-worker:*
```

## Cron Jobs

Add to crontab:
```bash
sudo crontab -e -u www-data
```

Add this line:
```
* * * * * cd /var/www/redeem-x && php artisan schedule:run >> /dev/null 2>&1
```

## SSL/HTTPS Setup

### Using Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Test auto-renewal
sudo certbot renew --dry-run
```

## Post-Deployment

### 1. Clear and Warm Up Caches

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. Test the Application

```bash
# Run tests
php artisan test

# Check queue connection
php artisan queue:work --once

# Test email
php artisan tinker
>>> Mail::raw('Test email', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

### 3. Monitor Logs

```bash
# Watch Laravel logs
tail -f storage/logs/laravel.log

# Watch Nginx logs
sudo tail -f /var/log/nginx/redeem-x-error.log

# Watch worker logs
sudo tail -f /var/www/redeem-x/storage/logs/worker.log
```

## Monitoring

### Application Health

Create a monitoring endpoint:
```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'cache' => Cache::has('health-check') || Cache::put('health-check', true, 10) ? 'working' : 'failed',
    ]);
});
```

### Recommended Monitoring Tools

- **Uptime Monitoring**: UptimeRobot, Pingdom
- **Error Tracking**: Sentry, Bugsnag, Flare
- **Performance Monitoring**: New Relic, DataDog
- **Log Management**: Papertrail, Loggly

### Configure Error Tracking (Example: Sentry)

```bash
composer require sentry/sentry-laravel
```

Add to `.env`:
```env
SENTRY_LARAVEL_DSN=https://your-sentry-dsn@sentry.io/project
```

## Troubleshooting

### Common Issues

**500 Internal Server Error:**
- Check `.env` file exists and is readable
- Verify storage and cache directories are writable
- Check PHP error logs: `sudo tail -f /var/log/php8.2-fpm.log`

**Database Connection Failed:**
- Verify database credentials in `.env`
- Check database server is running: `sudo systemctl status mysql`
- Test connection: `php artisan tinker` then `DB::connection()->getPdo()`

**Assets Not Loading:**
- Run `npm run build` again
- Clear browser cache
- Check nginx/apache is serving from correct `public` directory

**Queue Jobs Not Processing:**
- Check supervisor status: `sudo supervisorctl status`
- Restart workers: `sudo supervisorctl restart redeem-x-worker:*`
- Check Redis connection: `redis-cli ping`

**Permission Errors:**
- Reset permissions:
  ```bash
  sudo chown -R www-data:www-data /var/www/redeem-x
  sudo chmod -R 755 /var/www/redeem-x
  sudo chmod -R 775 /var/www/redeem-x/storage
  sudo chmod -R 775 /var/www/redeem-x/bootstrap/cache
  ```

### Debug Mode (Temporary)

Only enable for troubleshooting:
```bash
# Edit .env
APP_DEBUG=true
php artisan config:clear

# Remember to disable after:
APP_DEBUG=false
php artisan config:cache
```

## Updates and Maintenance

### Deploying Updates

```bash
# 1. Enable maintenance mode
php artisan down

# 2. Pull latest code
git pull origin main

# 3. Update dependencies
composer install --no-dev --optimize-autoloader
npm ci --production

# 4. Rebuild assets
npm run build

# 5. Run migrations
php artisan migrate --force

# 6. Clear and rebuild caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Restart queue workers
sudo supervisorctl restart redeem-x-worker:*

# 8. Disable maintenance mode
php artisan up
```

### Automated Deployment

Consider using deployment tools:
- **Laravel Forge**: Managed hosting and deployment
- **Envoyer**: Zero-downtime deployment
- **GitHub Actions**: CI/CD pipeline
- **Deployer**: PHP deployment tool

### Backup Strategy

**Database Backup:**
```bash
# MySQL
mysqldump -u redeem_x_user -p redeem_x > backup-$(date +%Y%m%d).sql

# PostgreSQL
pg_dump -U redeem_x_user redeem_x > backup-$(date +%Y%m%d).sql
```

**Full Application Backup:**
```bash
tar -czf redeem-x-backup-$(date +%Y%m%d).tar.gz /var/www/redeem-x \
    --exclude=/var/www/redeem-x/node_modules \
    --exclude=/var/www/redeem-x/storage/logs
```

**Automated Backups:**
Add to crontab:
```bash
0 2 * * * mysqldump -u redeem_x_user -p'password' redeem_x > /backups/db-$(date +\%Y\%m\%d).sql
0 3 * * * find /backups -name "db-*.sql" -mtime +7 -delete
```

## Security Checklist

- [ ] APP_DEBUG=false in production
- [ ] Strong APP_KEY generated
- [ ] Database credentials secured
- [ ] File permissions set correctly (755/775)
- [ ] HTTPS enabled with valid SSL certificate
- [ ] Security headers configured
- [ ] Rate limiting enabled
- [ ] WorkOS production credentials configured
- [ ] Session driver set to redis/database
- [ ] CORS configured properly
- [ ] `.env` file not in version control
- [ ] Firewall configured (UFW or iptables)
- [ ] SSH key-based authentication only
- [ ] Regular security updates applied

## Support

For deployment assistance:
- Email: devops@your-domain.com
- Documentation: https://your-domain.com/docs
- GitHub Issues: https://github.com/your-org/redeem-x/issues

---

Last updated: 2025-01-09
