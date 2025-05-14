# Coupon Management System - Digital Ocean Deployment Guide

This guide will help you deploy the Coupon Management System to a Digital Ocean droplet and set up the necessary components for production use.

## Prerequisites

- A [Digital Ocean](https://www.digitalocean.com/) account
- Git installed on your local machine
- A GitHub repository for your project

## Step 1: Prepare Your Project for Deployment

### Create a GitHub Repository

1. Create a new repository on GitHub
2. Initialize Git in your local project folder (if not already done):
   ```bash
   cd /path/to/coupon redeem
   git init
   ```
3. Add your files to Git (excluding sensitive data and unnecessary files):
   ```bash
   # Create a .gitignore file
   echo "config/database.php" > .gitignore
   echo "vendor/" >> .gitignore
   echo "node_modules/" >> .gitignore
   echo ".env" >> .gitignore
   echo "*.log" >> .gitignore
   
   # Add and commit your files
   git add .
   git commit -m "Initial commit"
   ```
4. Connect your local repository to GitHub:
   ```bash
   git remote add origin https://github.com/yourusername/coupon-management.git
   git push -u origin main
   ```

### Create a Database Configuration Template

1. Create a database configuration template file:
   ```bash
   cp config/database.php config/database.template.php
   ```
2. Edit `config/database.template.php` to use environment variables:
   ```php
   <?php
   // Database configuration
   class Database {
       private $host = "DB_HOST";
       private $db_name = "DB_NAME";
       private $username = "DB_USER";
       private $password = "DB_PASSWORD";
       private $conn;
       
       // Rest of the file remains the same
   }
   ?>
   ```
3. Add this template to your Git repository:
   ```bash
   git add config/database.template.php
   git commit -m "Add database configuration template"
   git push origin main
   ```

## Step 2: Create a Digital Ocean Droplet

1. Log in to your Digital Ocean account
2. Click on "Create" and select "Droplet"
3. Choose an image: Ubuntu 20.04 LTS
4. Select a plan: Basic (Shared CPU)
   - $5/mo (1GB RAM / 1 CPU) for testing
   - $10/mo (2GB RAM / 1 CPU) recommended for production
5. Choose a datacenter region close to your users
6. Authentication: SSH keys (recommended) or Password
7. Click "Create Droplet"

## Step 3: Set Up Your Server

### Connect to Your Server

```bash
ssh root@your_server_ip
```

### Update System Packages

```bash
apt update
apt upgrade -y
```

### Install Required Software

```bash
# Install Apache, MySQL, PHP, and required extensions
apt install -y apache2 mysql-server php libapache2-mod-php php-mysql php-gd php-zip php-mbstring php-curl php-xml php-pdo git unzip

# Enable Apache modules
a2enmod rewrite
systemctl restart apache2
```

### Configure MySQL

```bash
# Secure MySQL installation
mysql_secure_installation

# Create database and user
mysql -u root -p
```

In the MySQL prompt:
```sql
CREATE DATABASE coupon_db;
CREATE USER 'coupon_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON coupon_db.* TO 'coupon_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## Step 4: Deploy Your Application

### Clone Your Repository

```bash
# Remove default Apache page
rm -rf /var/www/html/*

# Clone your repository
cd /var/www/html
git clone https://github.com/yourusername/coupon-management.git .
```

### Configure the Application

1. Create the database configuration file:
   ```bash
   cp config/database.template.php config/database.php
   ```

2. Edit the database configuration:
   ```bash
   nano config/database.php
   ```
   
   Update with your MySQL credentials:
   ```php
   private $host = "localhost";
   private $db_name = "coupon_db";
   private $username = "coupon_user";
   private $password = "your_strong_password";
   ```

3. Update the base URL in `config/config.php`:
   ```bash
   nano config/config.php
   ```
   
   Change the base URL to match your domain:
   ```php
   $baseUrl = $protocol . $host . '/';
   ```

### Import the Database

```bash
# Import the database schema
mysql -u coupon_user -p coupon_db < database/coupon_db.sql
```

### Set Proper Permissions

```bash
# Set ownership
chown -R www-data:www-data /var/www/html

# Set proper permissions
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;

# Make uploads directory writable
chmod -R 775 /var/www/html/uploads
```

## Step 5: Configure Apache Virtual Host

1. Create a new virtual host configuration:
   ```bash
   nano /etc/apache2/sites-available/coupon-management.conf
   ```

2. Add the following configuration:
   ```apache
   <VirtualHost *:80>
       ServerAdmin webmaster@yourdomain.com
       ServerName yourdomain.com
       ServerAlias www.yourdomain.com
       DocumentRoot /var/www/html
       
       <Directory /var/www/html>
           Options Indexes FollowSymLinks MultiViews
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/error.log
       CustomLog ${APACHE_LOG_DIR}/access.log combined
   </VirtualHost>
   ```

3. Enable the virtual host and restart Apache:
   ```bash
   a2ensite coupon-management.conf
   a2dissite 000-default.conf
   systemctl restart apache2
   ```

## Step 6: Set Up SSL (Optional but Recommended)

```bash
# Install Certbot
apt install -y certbot python3-certbot-apache

# Obtain and install SSL certificate
certbot --apache -d yourdomain.com -d www.yourdomain.com
```

## Step 7: Set Up Automatic Updates (Recommended)

```bash
# Install unattended-upgrades
apt install -y unattended-upgrades apt-listchanges

# Configure automatic updates
dpkg-reconfigure -plow unattended-upgrades
```

## Step 8: Set Up Backup (Recommended)

### Database Backup Script

1. Create a backup script:
   ```bash
   nano /root/backup_db.sh
   ```

2. Add the following content:
   ```bash
   #!/bin/bash
   
   # Database credentials
   DB_USER="coupon_user"
   DB_PASS="your_strong_password"
   DB_NAME="coupon_db"
   
   # Backup directory
   BACKUP_DIR="/root/backups"
   
   # Create backup directory if it doesn't exist
   mkdir -p $BACKUP_DIR
   
   # Backup filename with date
   BACKUP_FILE="$BACKUP_DIR/coupon_db_$(date +%Y%m%d_%H%M%S).sql"
   
   # Create backup
   mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_FILE
   
   # Compress backup
   gzip $BACKUP_FILE
   
   # Keep only the last 7 backups
   ls -tp $BACKUP_DIR/*.sql.gz | grep -v '/$' | tail -n +8 | xargs -I {} rm -- {}
   ```

3. Make the script executable:
   ```bash
   chmod +x /root/backup_db.sh
   ```

4. Set up a cron job to run the backup script daily:
   ```bash
   crontab -e
   ```
   
   Add the following line:
   ```
   0 2 * * * /root/backup_db.sh
   ```

## Step 9: File Structure Reference

Here's a reference of the key files and directories in your application:

```
/var/www/html/
├── admin/                 # Admin panel files
├── ajax/                  # AJAX handlers
├── api/                   # API endpoints
├── buyer/                 # Buyer portal
├── config/                # Configuration files
│   ├── config.php         # Main configuration
│   └── database.php       # Database configuration
├── database/              # Database schema files
│   └── coupon_db.sql      # Database schema
├── includes/              # Common include files
├── lib/                   # External libraries
├── models/                # Data models
├── uploads/               # Uploaded files
└── index.php              # Main entry point
```

## Troubleshooting

### Common Issues and Solutions

1. **500 Internal Server Error**
   - Check Apache error logs: `tail -f /var/log/apache2/error.log`
   - Ensure proper file permissions
   - Verify PHP configuration

2. **Database Connection Issues**
   - Verify database credentials in `config/database.php`
   - Check if MySQL service is running: `systemctl status mysql`

3. **File Upload Issues**
   - Check permissions on the uploads directory
   - Verify PHP file upload settings in `php.ini`

## Maintenance

### Updating Your Application

```bash
# Pull the latest changes
cd /var/www/html
git pull origin main

# Reset permissions if needed
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;
chmod -R 775 /var/www/html/uploads
```

### Monitoring Your Server

Consider setting up monitoring with tools like:
- Digital Ocean Monitoring
- New Relic
- Datadog

## Security Recommendations

1. **Firewall Configuration**
   ```bash
   # Install and configure UFW
   apt install -y ufw
   ufw allow ssh
   ufw allow http
   ufw allow https
   ufw enable
   ```

2. **Fail2Ban Installation**
   ```bash
   apt install -y fail2ban
   systemctl enable fail2ban
   systemctl start fail2ban
   ```

3. **Regular Security Updates**
   ```bash
   apt update
   apt upgrade -y
   ```

4. **Change Default Admin Credentials**
   - Log in with the default credentials
   - Immediately change the admin password to a strong, unique password

---

By following this guide, you should have a fully functional Coupon Management System running on your Digital Ocean droplet. For additional support or questions, please refer to the main README.md file or contact the system administrator.
