-- Multi-Temple Database Schema

CREATE DATABASE IF NOT EXISTS temple_management;
USE temple_management;

-- Temples Table (Master)
CREATE TABLE temples (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_code VARCHAR(20) UNIQUE NOT NULL,
    temple_name VARCHAR(200) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    pincode VARCHAR(10),
    phone VARCHAR(15),
    email VARCHAR(100),
    website VARCHAR(200),
    logo_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    subscription_plan ENUM('Free', 'Basic', 'Premium', 'Enterprise') DEFAULT 'Basic',
    subscription_expiry DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_temple_code (temple_code),
    INDEX idx_is_active (is_active)
);

-- Temple Configuration Table
CREATE TABLE temple_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    FOREIGN KEY (temple_id) REFERENCES temples(id) ON DELETE CASCADE,
    UNIQUE KEY unique_config (temple_id, config_key)
);

-- Users/Staff Table (Updated with temple_id)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    role ENUM('SuperAdmin', 'TempleAdmin', 'Manager', 'Staff', 'Counter') DEFAULT 'Staff',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id) ON DELETE CASCADE,
    UNIQUE KEY unique_username_temple (temple_id, username),
    INDEX idx_temple_user (temple_id, username)
);

-- Devotees Table (Updated with temple_id)
CREATE TABLE devotees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    devotee_code VARCHAR(50),
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    pincode VARCHAR(10),
    date_of_birth DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id) ON DELETE CASCADE,
    INDEX idx_temple_phone (temple_id, phone),
    INDEX idx_temple_devotee (temple_id, name)
);

-- Vazhipadu Types/Categories Table (Updated with temple_id)
CREATE TABLE vazhipadu_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id) ON DELETE CASCADE,
    INDEX idx_temple_active (temple_id, is_active)
);

-- Vazhipadu Bookings Table (Updated with temple_id)
CREATE TABLE vazhipadu_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    receipt_no VARCHAR(50) NOT NULL,
    devotee_id INT NOT NULL,
    vazhipadu_type_id INT NOT NULL,
    devotee_name VARCHAR(100) NOT NULL,
    star_name VARCHAR(50),
    booking_date DATE NOT NULL,
    seva_date DATE,
    amount DECIMAL(10,2) NOT NULL,
    payment_mode ENUM('Cash', 'Card', 'UPI', 'Online', 'Cheque') DEFAULT 'Cash',
    payment_status ENUM('Pending', 'Completed', 'Cancelled', 'Refunded') DEFAULT 'Completed',
    transaction_ref VARCHAR(100),
    remarks TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id) ON DELETE CASCADE,
    FOREIGN KEY (devotee_id) REFERENCES devotees(id),
    FOREIGN KEY (vazhipadu_type_id) REFERENCES vazhipadu_types(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY unique_receipt (temple_id, receipt_no),
    INDEX idx_temple_receipt (temple_id, receipt_no),
    INDEX idx_temple_booking_date (temple_id, booking_date),
    INDEX idx_temple_seva_date (temple_id, seva_date)
);

-- Daily Collections Summary Table (Updated with temple_id)
CREATE TABLE daily_collections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    collection_date DATE NOT NULL,
    total_bookings INT DEFAULT 0,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    cash_amount DECIMAL(10,2) DEFAULT 0.00,
    card_amount DECIMAL(10,2) DEFAULT 0.00,
    upi_amount DECIMAL(10,2) DEFAULT 0.00,
    online_amount DECIMAL(10,2) DEFAULT 0.00,
    cheque_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id) ON DELETE CASCADE,
    UNIQUE KEY unique_temple_date (temple_id, collection_date),
    INDEX idx_temple_date (temple_id, collection_date)
);

-- Audit Log Table
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_temple_audit (temple_id, created_at)
);

-- Insert Sample Temples
INSERT INTO temples (temple_code, temple_name, address, city, state, pincode, phone, is_active, subscription_plan) VALUES
('TEMP001', 'Sri Krishna Temple', 'Temple Road, Guruvayur', 'Guruvayur', 'Kerala', '680101', '+91 9876543210', TRUE, 'Premium'),
('TEMP002', 'Sree Padmanabhaswamy Temple', 'East Fort, Thiruvananthapuram', 'Thiruvananthapuram', 'Kerala', '695023', '+91 9876543211', TRUE, 'Basic'),
('TEMP003', 'Sabarimala Ayyappa Temple', 'Pathanamthitta District', 'Sabarimala', 'Kerala', '689711', '+91 9876543212', TRUE, 'Premium');

-- Insert default vazhipadu types for Temple 1
INSERT INTO vazhipadu_types (temple_id, name, description, amount, display_order) VALUES
(1, 'Archana', 'Regular archana with flowers', 50.00, 1),
(1, 'Sahasranamarchana', 'Chanting of 1000 names', 150.00, 2),
(1, 'Abhishekam', 'Holy bathing ceremony', 200.00, 3),
(1, 'Nivedyam', 'Food offering', 100.00, 4),
(1, 'Ganapathi Homam', 'Fire ritual for Lord Ganesha', 500.00, 5),
(1, 'Kalabhabhishekam', 'Special pooja in early morning', 300.00, 6),
(1, 'Annadanam', 'Food donation', 1000.00, 7),
(1, 'Deepam', 'Lamp offering', 25.00, 8);

-- Insert default vazhipadu types for Temple 2
INSERT INTO vazhipadu_types (temple_id, name, description, amount, display_order) VALUES
(2, 'Pushpanjali', 'Flower offering', 50.00, 1),
(2, 'Abhishekam', 'Holy bathing ceremony', 250.00, 2),
(2, 'Laksharchana', 'Special archana', 200.00, 3),
(2, 'Annadanam', 'Food donation', 1500.00, 4);

-- Insert Super Admin
INSERT INTO users (temple_id, username, password, full_name, email, role) VALUES
(1, 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'admin@templesystem.com', 'SuperAdmin');

-- Insert Temple Admins for each temple
INSERT INTO users (temple_id, username, password, full_name, email, role) VALUES
(1, 'krishna_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Krishna Temple Admin', 'admin@krishnatemple.com', 'TempleAdmin'),
(2, 'padmanabha_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Padmanabhaswamy Admin', 'admin@padmanabhaswamy.com', 'TempleAdmin'),
(3, 'sabarimala_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sabarimala Admin', 'admin@sabarimala.com', 'TempleAdmin');

-- All default passwords are: admin123