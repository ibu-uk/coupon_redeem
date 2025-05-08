# Coupon Management System

A comprehensive system for managing coupons, including creation, assignment, redemption, and reporting.

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- PDO PHP extension
- GD PHP extension
- ZIP PHP extension
- mod_rewrite enabled (for Apache)

## Installation Instructions

1. **Copy the project files** to your web server's document root (e.g., `htdocs` folder in XAMPP).

2. **Import the database**:
   - Create a new database named `coupon_db` in your MySQL server.
   - Import the SQL file from `database/coupon_db.sql` to set up the database schema and initial data.

3. **Install dependencies**:
   - Open your web browser and navigate to `http://localhost/coupon redeem/install_dependencies.php`
   - This script will automatically install the required TCPDF library for PDF generation.
   - If the automatic installation fails, you can manually download TCPDF from https://github.com/tecnickcom/TCPDF and place it in the `lib/tcpdf` directory.

4. **Configure the database connection**:
   - Open `config/database.php` and update the database credentials if needed.

5. **Access the application**:
   - Navigate to `http://localhost/coupon redeem/` in your web browser.
   - Login with the default admin credentials:
     - Username: admin@example.com
     - Password: admin123

## Coupon Management System

### Overview
This system manages coupons for various services, allowing administrators to create, assign, and redeem coupons. The system includes features for coupon creation, management, and redemption.

### Key Features
- Coupon creation and management
- Buyer and recipient assignment
- Coupon redemption with balance tracking
- Reporting functionality
- User authentication and role-based access control

### Coupon Types
- Black: 700 KD
- Gold: 500 KD
- Silver: 300 KD

### Recent Updates and Fixes
- Improved coupon lookup logic to handle all coupon formats
- Special handling for BLACK-1 and numeric-only coupon codes
- Enhanced validation for redemption amounts
- Fixed database queries for redemption logs
- Added client-side and server-side validation for balance checks

### Usage Instructions
1. **Login**: Use admin credentials to access the system
   - Username: admin
   - Password: admin123

2. **Coupon Lookup**:
   - Enter the coupon code (e.g., "BLACK-1") or just the number (e.g., "1")
   - The system will find the coupon and display its details

3. **Redemption Process**:
   - Fill in recipient information
   - Select a service (amount will be auto-filled)
   - Enter service description
   - Confirm redemption

4. **Balance Validation**:
   - The system prevents redemptions that exceed the available balance
   - Both client-side and server-side validation are implemented

### Important Notes
- Coupon codes are normalized (spaces replaced with hyphens)
- The system tracks remaining balances for each coupon
- Redemption logs store all transaction details

## Features

- **Coupon Management**: Create, view, and manage coupons of different types (Black, Gold, Silver).
- **User Management**: Manage buyers and recipients.
- **Coupon Assignment**: Assign coupons to buyers and recipients.
- **Redemption**: Redeem coupons for services.
- **Reporting**: Generate detailed reports on coupon usage and redemption.
- **Export Options**: Export reports to Excel and PDF formats.

## Important Notes

1. When transferring the project to another system, make sure to run the `install_dependencies.php` script to ensure all required libraries are installed.

2. The PDF export functionality requires the TCPDF library, which will be automatically installed by the `install_dependencies.php` script.

3. For security in a production environment, please change the default admin credentials after the first login.

## Troubleshooting

If you encounter any issues with PDF generation:
1. Make sure the TCPDF library is properly installed in the `lib/tcpdf` directory.
2. Run the `install_dependencies.php` script to automatically install the missing library.
3. Check that your PHP installation has the required extensions enabled (zip, gd).

## Digital Ocean Deployment Guide (Step-by-Step Commands)

### 1. Create a Droplet

1. Log in to your Digital Ocean account at https://cloud.digitalocean.com/login
2. Click "Create" > "Droplets"
3. Choose a LAMP stack image (Ubuntu 20.04 + LAMP)
4. Select Basic plan with 1GB RAM / 1 CPU ($6/month)
5. Choose a datacenter region close to you (e.g., Bangalore or Singapore)
6. Create a root password (make it secure and save it somewhere safe)
7. Click "Create Droplet"
8. Wait for the droplet to be created (about 1 minute)
9. Note down your droplet's IP address

### 2. Connect to Your Droplet

On Windows, download and install PuTTY from https://www.putty.org/

Open PuTTY and enter your droplet's IP address, then click "Open"

When prompted, enter:
- Username: `root`
- Password: (the password you created)

### 3. Update System Packages

```bash
# Update package lists
sudo apt update

# Upgrade installed packages
sudo apt upgrade -y

# Install essential tools
sudo apt install -y zip unzip git curl wget
```

### 4. Install Required PHP Extensions

```bash
# Install PHP extensions
sudo apt install -y php-mysql php-zip php-gd php-mbstring php-curl php-xml php-pdo

# Verify PHP version
php -v

# Verify PHP extensions
php -m | grep -E 'pdo|mysql|gd|zip'
```

### 5. Restart Apache/PHP

```bash
# Restart Apache
sudo systemctl restart apache2

# Check Apache status
sudo systemctl status apache2
```

### 6. Configure Database

```bash
# Access MySQL as root
sudo mysql
```

In MySQL prompt, enter these commands one by one (replace 'your_secure_password' with a strong password):
```sql
CREATE DATABASE coupon_db;
CREATE USER 'coupon_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON coupon_db.* TO 'coupon_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 7. Prepare Web Directory

```bash
# Create directory for the application
sudo mkdir -p /var/www/html/coupon-redeem

# Set proper permissions
sudo chmod -R 755 /var/www/html/coupon-redeem
sudo chown -R www-data:www-data /var/www/html/coupon-redeem
```

### 8. Upload Project Files

Option 1: Using FileZilla (SFTP)
1. Download and install FileZilla from https://filezilla-project.org/
2. Connect to your server using:
   - Host: sftp://your_droplet_ip
   - Username: root
   - Password: your_root_password
   - Port: 22
3. Navigate to /var/www/html/coupon-redeem on the remote site
4. Upload all files from your local coupon redeem folder

Option 2: Using Git (if your project is on GitHub/GitLab)
```bash
# Navigate to web directory
cd /var/www/html

# Clone repository (replace with your repository URL)
sudo git clone https://github.com/yourusername/coupon-redeem.git coupon-redeem

# Set proper permissions
sudo chown -R www-data:www-data coupon-redeem
sudo chmod -R 755 coupon-redeem
```

Option 3: Using SCP (from your local machine)
```bash
# On your local machine (run this from the directory containing your project)
scp -r coupon\ redeem/* root@your_droplet_ip:/var/www/html/coupon-redeem/
```

### 9. Import Database

```bash
# Navigate to the database directory
cd /var/www/html/coupon-redeem/database

# Import the database (enter password when prompted)
sudo mysql -u coupon_user -p coupon_db < coupon_db.sql
```

### 10. Update Configuration

```bash
# Edit database configuration file
sudo nano /var/www/html/coupon-redeem/config/database.php
```

Update the database credentials (use Ctrl+O to save, Ctrl+X to exit):
```php
private $host = "localhost";
private $db_name = "coupon_db";
private $username = "coupon_user";
private $password = "your_secure_password"; // Use the password you created in step 6
```

### 11. Configure Apache Virtual Host

```bash
# Create a new virtual host configuration file
sudo nano /etc/apache2/sites-available/coupon-redeem.conf
```

Add the following configuration (use Ctrl+O to save, Ctrl+X to exit):
```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    ServerName your_droplet_ip
    DocumentRoot /var/www/html/coupon-redeem
    
    <Directory /var/www/html/coupon-redeem>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/coupon-error.log
    CustomLog ${APACHE_LOG_DIR}/coupon-access.log combined
</VirtualHost>
```

### 12. Enable the Site and Restart Apache

```bash
# Enable the site
sudo a2ensite coupon-redeem.conf

# Disable the default site
sudo a2dissite 000-default.conf

# Enable rewrite module
sudo a2enmod rewrite

# Check configuration for errors
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

### 13. Update Base URL in Configuration

```bash
# Edit config.php file
sudo nano /var/www/html/coupon-redeem/config/config.php
```

Update the BASE_URL to match your server (use Ctrl+O to save, Ctrl+X to exit):
```php
// Find this section and update it
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host . '/';

// Define constants
define('BASE_URL', $baseUrl);
```

### 14. Set Proper Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/html/coupon-redeem

# Set directory permissions
sudo find /var/www/html/coupon-redeem -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/html/coupon-redeem -type f -exec chmod 644 {} \;
```

### 15. Install Dependencies

```bash
# Create lib directory if it doesn't exist
sudo mkdir -p /var/www/html/coupon-redeem/lib

# Download TCPDF
cd /var/www/html/coupon-redeem/lib
sudo wget https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.4.4.zip
sudo unzip 6.4.4.zip
sudo mv TCPDF-6.4.4 tcpdf
sudo rm 6.4.4.zip

# Set proper permissions
sudo chown -R www-data:www-data /var/www/html/coupon-redeem/lib
```

Alternatively, access the dependency installer through your browser:
```
http://your_droplet_ip/install_dependencies.php
```

### 16. Secure Your Installation

```bash
# Install Certbot for HTTPS (if you have a domain name)
sudo apt install -y certbot python3-certbot-apache

# Get SSL certificate (replace with your actual domain)
# Only run this if you have a domain pointed to your server
# sudo certbot --apache -d your-domain.com -d www.your-domain.com

# Set up basic firewall
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable
```

### 17. Test Your Installation

Open your web browser and navigate to:
```
http://your_droplet_ip/
```

Login with the default admin credentials:
- Username: admin
- Password: admin123

### 18. Troubleshooting Common Issues

```bash
# Check Apache error logs
sudo tail -100 /var/log/apache2/error.log

# Check coupon application error logs
sudo tail -100 /var/log/apache2/coupon-error.log

# Check PHP configuration
php -i | grep 'display_errors'

# Restart Apache after configuration changes
sudo systemctl restart apache2
```

### 19. Regular Maintenance Commands

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Restart Apache
sudo systemctl restart apache2

# Backup database
sudo mysqldump -u coupon_user -p coupon_db > /var/backups/coupon_db_backup_$(date +%Y%m%d).sql

# Backup application files
sudo tar -czf /var/backups/coupon_app_backup_$(date +%Y%m%d).tar.gz /var/www/html/coupon-redeem
```
