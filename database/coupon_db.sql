-- Coupon Management System Database Schema

-- Drop existing tables if they exist
DROP TABLE IF EXISTS redemption_logs;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS coupon_types;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS services;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    civil_id VARCHAR(20),
    mobile_number VARCHAR(20),
    file_number VARCHAR(50),
    purchase_date DATE,
    entry_date DATE,
    role ENUM('admin', 'buyer', 'recipient') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create coupon_types table
CREATE TABLE coupon_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name ENUM('Black', 'Gold', 'Silver') NOT NULL,
    description TEXT,
    value DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create services table
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    default_price DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create coupons table
CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    coupon_type_id INT NOT NULL,
    buyer_id INT,
    recipient_id INT,
    initial_balance DECIMAL(10, 2) NOT NULL,
    current_balance DECIMAL(10, 2) NOT NULL,
    issue_date DATE,
    status ENUM('available', 'assigned', 'fully_redeemed') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_type_id) REFERENCES coupon_types(id),
    FOREIGN KEY (buyer_id) REFERENCES users(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id),
    INDEX (code),
    INDEX (buyer_id),
    INDEX (recipient_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create redemption_logs table
CREATE TABLE redemption_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    redeemed_by INT NOT NULL,
    service_id INT,
    service_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    remaining_balance DECIMAL(10, 2) NOT NULL,
    recipient_name VARCHAR(100),
    recipient_civil_id VARCHAR(20),
    recipient_mobile VARCHAR(20),
    recipient_file_number VARCHAR(50),
    redemption_date DATE NOT NULL,
    redemption_time TIME NOT NULL,
    service_description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id),
    FOREIGN KEY (redeemed_by) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    INDEX (coupon_id),
    INDEX (redeemed_by),
    INDEX (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default coupon types
INSERT INTO coupon_types (name, description, value) VALUES
('Black', 'Premium coupon with maximum benefits', 700.00),
('Gold', 'High-value coupon with extended validity', 500.00),
('Silver', 'Standard coupon with good value', 300.00);

-- Insert default services
INSERT INTO services (name, description, default_price) VALUES
('Consultation', 'General medical consultation', 50.00),
('Dental Cleaning', 'Basic dental cleaning service', 80.00),
('Physical Therapy', 'Standard physical therapy session', 60.00),
('Dermatology', 'Skin consultation and treatment', 90.00),
('Nutrition Counseling', 'Dietary and nutrition advice', 45.00);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, email, full_name, role) VALUES
('admin', '$2y$10$S5Jxj4UvqtyDWBB0K7R3luBYUE/RZupkMKc8/QQMzj5fkwQVQPJwC', 'admin@example.com', 'System Administrator', 'admin');

-- Pre-create batch coupons (1-10) for each type
-- Black coupons (1-10)
INSERT INTO coupons (code, coupon_type_id, initial_balance, current_balance, status) VALUES
('BLACK-1', 1, 700.00, 700.00, 'available'),
('BLACK-2', 1, 700.00, 700.00, 'available'),
('BLACK-3', 1, 700.00, 700.00, 'available'),
('BLACK-4', 1, 700.00, 700.00, 'available'),
('BLACK-5', 1, 700.00, 700.00, 'available'),
('BLACK-6', 1, 700.00, 700.00, 'available'),
('BLACK-7', 1, 700.00, 700.00, 'available'),
('BLACK-8', 1, 700.00, 700.00, 'available'),
('BLACK-9', 1, 700.00, 700.00, 'available'),
('BLACK-10', 1, 700.00, 700.00, 'available');

-- Gold coupons (1-10)
INSERT INTO coupons (code, coupon_type_id, initial_balance, current_balance, status) VALUES
('GOLD-1', 2, 500.00, 500.00, 'available'),
('GOLD-2', 2, 500.00, 500.00, 'available'),
('GOLD-3', 2, 500.00, 500.00, 'available'),
('GOLD-4', 2, 500.00, 500.00, 'available'),
('GOLD-5', 2, 500.00, 500.00, 'available'),
('GOLD-6', 2, 500.00, 500.00, 'available'),
('GOLD-7', 2, 500.00, 500.00, 'available'),
('GOLD-8', 2, 500.00, 500.00, 'available'),
('GOLD-9', 2, 500.00, 500.00, 'available'),
('GOLD-10', 2, 500.00, 500.00, 'available');

-- Silver coupons (1-10)
INSERT INTO coupons (code, coupon_type_id, initial_balance, current_balance, status) VALUES
('SILVER-1', 3, 300.00, 300.00, 'available'),
('SILVER-2', 3, 300.00, 300.00, 'available'),
('SILVER-3', 3, 300.00, 300.00, 'available'),
('SILVER-4', 3, 300.00, 300.00, 'available'),
('SILVER-5', 3, 300.00, 300.00, 'available'),
('SILVER-6', 3, 300.00, 300.00, 'available'),
('SILVER-7', 3, 300.00, 300.00, 'available'),
('SILVER-8', 3, 300.00, 300.00, 'available'),
('SILVER-9', 3, 300.00, 300.00, 'available'),
('SILVER-10', 3, 300.00, 300.00, 'available');
