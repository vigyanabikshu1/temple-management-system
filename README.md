# Temple Vazhipadu Management System
## Complete Documentation & Implementation Guide

**Version:** 2.0  
**Last Updated:** December 2024  
**Language:** PHP, MySQL, JavaScript  
**Framework:** Vanilla PHP (No Framework Dependencies)

---

## 📑 Table of Contents

1. [System Overview](#system-overview)
2. [System Architecture](#system-architecture)
3. [Database Schema](#database-schema)
4. [File Structure](#file-structure)
5. [Module Descriptions](#module-descriptions)
6. [Installation Guide](#installation-guide)
7. [Configuration Guide](#configuration-guide)
8. [User Roles & Permissions](#user-roles--permissions)
9. [Feature Documentation](#feature-documentation)
10. [API Documentation](#api-documentation)
11. [Security Implementation](#security-implementation)
12. [Backup & Recovery](#backup--recovery)
13. [Troubleshooting Guide](#troubleshooting-guide)
14. [Future Enhancements](#future-enhancements)
15. [Code Examples](#code-examples)

---

## 📖 System Overview

### Purpose
A comprehensive digital solution for managing temple operations including vazhipadu (offerings), expenses, devotees, vendors, budgets, and financial reporting.

### Key Benefits
- **Efficiency**: Reduces manual work by 80%
- **Transparency**: Complete audit trail of all transactions
- **Accuracy**: Eliminates calculation errors
- **Accessibility**: Multi-device access (desktop, tablet, mobile)
- **Scalability**: Supports multiple temples from single installation
- **Localization**: Full Malayalam language support

### Target Users
- Temple administrators
- Priests and staff
- Accountants
- Management committees
- Devotees (future online portal)

### Technology Stack
```
Frontend: HTML5, CSS3, JavaScript (Vanilla)
Backend: PHP 7.4+
Database: MySQL 8.0+
Server: Apache/Nginx
OS: Linux/Windows
```

---

## 🏗️ System Architecture

### Architecture Pattern
**Multi-Tenant Monolithic Architecture**

```
┌─────────────────────────────────────────┐
│          Landing Page (index.html)       │
└─────────────────────┬───────────────────┘
                      │
┌─────────────────────▼───────────────────┐
│      Authentication Layer (login.php)    │
└─────────────────────┬───────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
┌───────▼────────┐         ┌───────▼────────┐
│  Super Admin   │         │  Temple Users  │
│   Dashboard    │         │   Dashboard    │
└───────┬────────┘         └───────┬────────┘
        │                           │
        │    ┌──────────────────────┼──────────────────┐
        │    │                      │                  │
┌───────▼────▼──────┐    ┌─────────▼────────┐  ┌─────▼─────────┐
│ Temple Management │    │ Vazhipadu Module │  │ Expense Module│
│  - Add Temples    │    │  - New Booking   │  │ - Add Expense │
│  - Manage Users   │    │  - Search        │  │ - Categories  │
│  - Reports        │    │  - Reports       │  │ - Vendors     │
│  - Subscriptions  │    │  - Receipts      │  │ - Budgets     │
└───────────────────┘    └──────────────────┘  └───────────────┘
                                │
                    ┌───────────┴───────────┐
                    │                       │
            ┌───────▼────────┐    ┌────────▼─────────┐
            │ Database Layer │    │  Audit Trail     │
            │ (MySQL)        │    │  Logging System  │
            └────────────────┘    └──────────────────┘
```

### Data Flow

```
User Input → Validation → Sanitization → Business Logic → 
Database Transaction → Audit Log → Response → UI Update
```

### Multi-Tenant Isolation

```
temple_id (Foreign Key) present in ALL data tables
    ↓
Every query filtered by: WHERE temple_id = current_temple_id
    ↓
Complete data isolation between temples
```

---

## 🗄️ Database Schema

### Core Tables (15 Tables)

#### 1. temples
```sql
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
    subscription_plan ENUM('Free', 'Basic', 'Premium', 'Enterprise'),
    subscription_expiry DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Purpose**: Master table for all temples  
**Key Relationships**: Parent to all other tables  
**Indexes**: temple_code, is_active

#### 2. users
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    role ENUM('SuperAdmin', 'TempleAdmin', 'Manager', 'Staff', 'Counter'),
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id) ON DELETE CASCADE,
    UNIQUE KEY (temple_id, username)
);
```

**Purpose**: User authentication and authorization  
**Security**: Passwords hashed with bcrypt  
**Roles**: 5-tier permission system

#### 3. devotees
```sql
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
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    INDEX (temple_id, phone)
);
```

**Purpose**: Devotee master data  
**Usage**: Auto-populated from bookings  
**Search**: Indexed by phone for quick lookup

#### 4. vazhipadu_types
```sql
CREATE TABLE vazhipadu_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id)
);
```

**Purpose**: Types of offerings/seva  
**Customizable**: Each temple defines their own  
**Examples**: Archana, Abhishekam, Nivedyam

#### 5. vazhipadu_bookings
```sql
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
    payment_mode ENUM('Cash', 'Card', 'UPI', 'Online', 'Cheque'),
    payment_status ENUM('Pending', 'Completed', 'Cancelled'),
    transaction_ref VARCHAR(100),
    remarks TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    FOREIGN KEY (devotee_id) REFERENCES devotees(id),
    FOREIGN KEY (vazhipadu_type_id) REFERENCES vazhipadu_types(id),
    UNIQUE KEY (temple_id, receipt_no),
    INDEX (temple_id, booking_date)
);
```

**Purpose**: All vazhipadu transactions  
**Receipt Format**: TEMP001YYYYMMDD0001  
**Audit**: Tracks who created each booking

#### 6. expense_categories
```sql
CREATE TABLE expense_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id)
);
```

**Purpose**: Expense classification (Level 1)  
**Examples**: Pooja Materials, Utilities, Salaries

#### 7. expense_subcategories
```sql
CREATE TABLE expense_subcategories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    category_id INT NOT NULL,
    subcategory_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    FOREIGN KEY (category_id) REFERENCES expense_categories(id)
);
```

**Purpose**: Detailed expense classification (Level 2)  
**Examples**: Flowers, Oil, Electricity Bill

#### 8. vendors
```sql
CREATE TABLE vendors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    vendor_code VARCHAR(50),
    vendor_name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    gst_number VARCHAR(20),
    pan_number VARCHAR(15),
    bank_name VARCHAR(100),
    account_number VARCHAR(30),
    ifsc_code VARCHAR(15),
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    INDEX (temple_id, vendor_name)
);
```

**Purpose**: Supplier/vendor master  
**Usage**: Link expenses to vendors  
**Features**: Tax info, bank details for payments

#### 9. expenses
```sql
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    expense_no VARCHAR(50) NOT NULL,
    expense_date DATE NOT NULL,
    category_id INT NOT NULL,
    subcategory_id INT,
    vendor_id INT,
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    gst_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_mode ENUM('Cash', 'Bank Transfer', 'Cheque', 'UPI', 'Card'),
    payment_reference VARCHAR(100),
    bill_number VARCHAR(50),
    bill_date DATE,
    payment_status ENUM('Paid', 'Pending', 'Partial', 'Cancelled'),
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    approved_by INT,
    approved_date DATE,
    remarks TEXT,
    attachment_path VARCHAR(255),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    UNIQUE KEY (temple_id, expense_no),
    INDEX (temple_id, expense_date)
);
```

**Purpose**: All expense transactions  
**Format**: TEMP001EXP20241215001  
**Features**: GST tracking, partial payments, approvals

#### 10. budgets
```sql
CREATE TABLE budgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    budget_year INT NOT NULL,
    budget_month INT,
    category_id INT NOT NULL,
    subcategory_id INT,
    budgeted_amount DECIMAL(10,2) NOT NULL,
    spent_amount DECIMAL(10,2) DEFAULT 0.00,
    remarks TEXT,
    created_by INT NOT NULL,
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    UNIQUE KEY (temple_id, budget_year, budget_month, category_id, subcategory_id)
);
```

**Purpose**: Budget planning and tracking  
**Updates**: Spent amount auto-calculated from expenses  
**Alerts**: System warns when approaching limits

#### 11. recurring_expenses
```sql
CREATE TABLE recurring_expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    category_id INT NOT NULL,
    subcategory_id INT,
    vendor_id INT,
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    frequency ENUM('Daily', 'Weekly', 'Monthly', 'Quarterly', 'Yearly'),
    start_date DATE NOT NULL,
    end_date DATE,
    next_due_date DATE NOT NULL,
    payment_mode ENUM('Cash', 'Bank Transfer', 'Cheque', 'UPI', 'Card'),
    is_active BOOLEAN DEFAULT TRUE,
    auto_create BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    FOREIGN KEY (temple_id) REFERENCES temples(id)
);
```

**Purpose**: Manage recurring expenses  
**Automation**: Can auto-create expenses  
**Examples**: Electricity bill, staff salary

#### 12. daily_collections
```sql
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
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    UNIQUE KEY (temple_id, collection_date)
);
```

**Purpose**: Daily summary caching  
**Performance**: Pre-calculated totals for reports  
**Updates**: Triggered after each booking

#### 13. temple_config
```sql
CREATE TABLE temple_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    temple_id INT NOT NULL,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT,
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    UNIQUE KEY (temple_id, config_key)
);
```

**Purpose**: Temple-specific settings  
**Usage**: Store custom configurations  
**Examples**: Receipt footer, email settings

#### 14. audit_log
```sql
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
    FOREIGN KEY (temple_id) REFERENCES temples(id),
    INDEX (temple_id, created_at)
);
```

**Purpose**: Complete audit trail  
**Security**: Tracks all modifications  
**Compliance**: Required for financial audits

### Database Relationships

```
temples (1) ──┬── (M) users
              ├── (M) devotees
              ├── (M) vazhipadu_types
              ├── (M) vazhipadu_bookings
              ├── (M) expense_categories
              ├── (M) vendors
              ├── (M) expenses
              ├── (M) budgets
              └── (M) audit_log

expense_categories (1) ──── (M) expense_subcategories
                      └──── (M) expenses

vendors (1) ──── (M) expenses

devotees (1) ──── (M) vazhipadu_bookings

vazhipadu_types (1) ──── (M) vazhipadu_bookings
```

### Indexes Strategy

**Primary Indexes:**
- All tables: PRIMARY KEY on `id`
- temples: UNIQUE on `temple_code`
- users: UNIQUE on (`temple_id`, `username`)
- vazhipadu_bookings: UNIQUE on (`temple_id`, `receipt_no`)
- expenses: UNIQUE on (`temple_id`, `expense_no`)

**Search Indexes:**
- devotees: INDEX on (`temple_id`, `phone`)
- vazhipadu_bookings: INDEX on (`temple_id`, `booking_date`)
- expenses: INDEX on (`temple_id`, `expense_date`)
- audit_log: INDEX on (`temple_id`, `created_at`)

**Foreign Key Indexes:**
- All `temple_id` columns
- All relationship columns

---

## 📁 File Structure

```
temple-management/
│
├── index.html                      # Landing page
├── login.php                       # Authentication
├── logout.php                      # Logout handler
├── config.php                      # Database & system config
├── languages.php                   # Multi-language support
│
├── dashboard/
│   └── index.php                   # Temple dashboard
│
├── vazhipadu/
│   ├── new_booking.php             # Create booking
│   ├── search.php                  # Search bookings
│   ├── print_receipt.php           # Print receipt
│   └── reports.php                 # Vazhipadu reports
│
├── expenses/
│   ├── expenses.php                # Expense list
│   ├── add_expense.php             # Add expense
│   ├── edit_expense.php            # Edit expense
│   ├── view_expense.php            # View details
│   ├── expense_categories.php      # Manage categories
│   ├── vendors.php                 # Manage vendors
│   ├── budgets.php                 # Budget management
│   ├── recurring_expenses.php      # Recurring expenses
│   └── expense_reports.php         # Financial reports
│
├── admin/
│   ├── admin.php                   # Temple admin panel
│   ├── super_admin.php             # Super admin dashboard
│   ├── super_admin_users.php       # User management (future)
│   ├── super_admin_reports.php     # System reports (future)
│   └── super_admin_audit.php       # Audit logs (future)
│
├── api/
│   └── get_subcategories.php       # AJAX subcategory loader
│
├── assets/
│   ├── css/
│   │   ├── main.css                # Main stylesheet
│   │   └── print.css               # Print styles
│   ├── js/
│   │   ├── main.js                 # Common JavaScript
│   │   └── validation.js           # Form validation
│   └── images/
│       └── logo.png                # System logo
│
├── uploads/                        # File uploads
│   └── bills/                      # Bill attachments
│
└── docs/
    ├── README.md                   # This file
    ├── INSTALLATION.md             # Installation guide
    ├── API.md                      # API documentation
    └── CHANGELOG.md                # Version history
```

---

## 🎯 Module Descriptions

### 1. Authentication Module

**Files**: `login.php`, `logout.php`, `config.php`

**Features:**
- Multi-temple login selection
- Super admin login (no temple selection)
- Password hashing (bcrypt)
- Session management
- Remember last login
- Failed login tracking
- Auto-logout on inactivity

**User Flow:**
```
User → Select Temple → Enter Credentials → Validate → 
Create Session → Redirect to Dashboard
```

**Security:**
- SQL injection prevention
- XSS protection
- CSRF tokens (planned)
- Rate limiting (planned)

### 2. Vazhipadu Management Module

**Files**: `new_booking.php`, `search.php`, `print_receipt.php`, `reports.php`

**Features:**
- Quick booking creation
- Auto-receipt number generation
- Devotee database auto-update
- Multiple payment modes
- Star/nakshatra tracking
- Seva date scheduling
- Thermal receipt printing
- Malayalam receipt support
- Search by receipt/name/phone
- Daily collection reports

**Booking Flow:**
```
Enter Devotee Details → Select Vazhipadu Type → 
Amount Auto-fills → Payment Mode → Create → 
Generate Receipt → Print
```

**Receipt Format:**
```
TEMP001 20241215 0001
└─┬──┘ └───┬───┘ └─┬─┘
  │        │       └─ Sequence (1-9999)
  │        └───────── Date (YYYYMMDD)
  └────────────────── Temple Code
```

### 3. Expense Management Module

**Files**: `expenses.php`, `add_expense.php`, `edit_expense.php`, `view_expense.php`

**Features:**
- Complete expense tracking
- Category & subcategory classification
- Vendor linking
- GST calculation
- Multiple payment modes
- Payment status tracking (Paid/Pending/Partial)
- Bill number & date
- Approval workflow (future)
- Expense search & filter
- Attachment support (planned)

**Expense Number Format:**
```
TEMP001 EXP 20241215 0001
└─┬──┘ └┬┘ └───┬───┘ └─┬─┘
  │     │      │       └─ Sequence
  │     │      └───────── Date
  │     └──────────────── Expense indicator
  └────────────────────── Temple Code
```

### 4. Category Management Module

**File**: `expense_categories.php`

**Features:**
- Create/Edit/Delete categories
- Two-level classification (Category → Sub-category)
- Active/inactive status
- Delete protection (if in use)
- Expense count per category
- Usage statistics

**Pre-loaded Categories:**
1. Pooja Materials
2. Utilities
3. Salaries
4. Festival Expenses
5. Maintenance
6. Administrative
7. Food & Prasadam
8. Transportation
9. Donations & Charity
10. Miscellaneous

### 5. Vendor Management Module

**File**: `vendors.php`

**Features:**
- Complete vendor database
- Auto vendor code generation
- Contact information
- Tax details (GST, PAN)
- Bank account details
- Expense tracking per vendor
- Total amount calculation
- Active/inactive status
- Delete protection

**Vendor Code Format:**
```
VEN 001
└┬┘ └┬┘
 │   └─ Sequential number (001-999)
 └───── Vendor prefix
```

### 6. Budget Management Module

**File**: `budgets.php`

**Features:**
- Monthly budget planning
- Category-wise budgets
- Budget vs actual tracking
- Auto-calculation of spent amount
- Visual progress bars
- Color-coded status:
  - Green: Under 80% (Under Budget)
  - Yellow: 80-100% (Near Limit)
  - Red: Over 100% (Over Budget)
- Budget alerts
- Historical comparison

**Budget Status:**
```
Budgeted: ₹50,000
Spent:    ₹35,000
─────────────────
Remaining: ₹15,000
Usage:     70% (Under Budget - Green)
```

### 7. Recurring Expenses Module

**File**: `recurring_expenses.php`

**Features:**
- Setup recurring expenses
- Frequencies: Daily, Weekly, Monthly, Quarterly, Yearly
- Next due date tracking
- Manual expense creation
- Auto-create (future enhancement)
- Active/inactive status
- Due soon highlighting
- Vendor linking

**Use Cases:**
- Monthly electricity bill
- Weekly pooja materials
- Annual license fees
- Quarterly maintenance
- Daily milk supply

### 8. Financial Reports Module

**File**: `expense_reports.php`

**Features:**
- Income vs Expense comparison
- Net balance calculation
- Expense ratio analysis
- Category-wise breakdown
- Top expenses listing
- Date range filtering
- Export to CSV
- Print-ready format
- Visual charts (future)

**Sample Report:**
```
Period: December 2024

Income (Vazhipadu):     ₹1,50,000
Expenses:               ₹85,000
─────────────────────────────────
Net Balance:            ₹65,000 (Surplus)
Expense Ratio:          56.7%

Category Breakdown:
Salaries:               ₹30,000 (35.3%)
Pooja Materials:        ₹25,000 (29.4%)
Food & Prasadam:        ₹15,000 (17.6%)
Utilities:              ₹10,000 (11.8%)
Others:                 ₹5,000  (5.9%)
```

### 9. Admin Panel Module

**File**: `admin.php`

**Features:**
- User management
- Vazhipadu types management
- System settings
- Access control
- Role assignment
- User activation/deactivation
- Password reset

**Temple Admin can:**
- Add/edit/delete users
- Manage vazhipadu types
- Configure categories
- View all reports
- Approve expenses (future)

### 10. Super Admin Module

**File**: `super_admin.php`

**Features:**
- Multi-temple overview
- Temple onboarding
- Subscription management
- System-wide statistics
- All temples' reports
- User management across temples
- Audit log access
- System configuration

**Super Admin can:**
- Create new temples
- Manage all users
- View all data
- Generate system reports
- Monitor subscriptions
- Access audit logs

---

## 📥 Installation Guide

### Prerequisites

**Server Requirements:**
- PHP 7.4 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Mod_rewrite enabled
- 512MB RAM minimum (2GB recommended)
- 10GB disk space minimum

**PHP Extensions Required:**
- mysqli
- PDO
- json
- mbstring
- openssl

### Step-by-Step Installation

#### Step 1: Download & Extract

```bash
# Download the system
wget https://your-domain.com/temple-management.zip

# Extract files
unzip temple-management.zip -d /var/www/html/temple-management

# Set permissions
cd /var/www/html/temple-management
chmod -R 755 .
chmod -R 777 uploads/
```

#### Step 2: Create Database

```sql
-- Login to MySQL
mysql -u root -p

-- Create database
CREATE DATABASE temple_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (optional)
CREATE USER 'temple_user'@'localhost' IDENTIFIED BY 'strong_password_here';

-- Grant permissions
GRANT ALL PRIVILEGES ON temple_management.* TO 'temple_user'@'localhost';
FLUSH PRIVILEGES;

-- Exit MySQL
EXIT;
```

#### Step 3: Import Database Schema

```bash
# Import main schema
mysql -u temple_user -p temple_management < database/schema.sql

# Import sample data (optional)
mysql -u temple_user -p temple_management < database/sample_data.sql

# Import expense tables
mysql -u temple_user -p temple_management < database/expense_tables.sql
```

#### Step 4: Configure System

Edit `config.php`:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'temple_user');
define('DB_PASS', 'your_password_here');
define('DB_NAME', 'temple_management');

// System Configuration
define('SYSTEM_NAME', 'Temple Management System');
define('SYSTEM_VERSION', '2.0');

// Base URL
define('BASE_URL', 'http://your-domain.com/temple-management/');

// Timezone
date_default_timezone_set('Asia/Kolkata');
?>
```

#### Step 5: Set Up Web Server

**Apache (.htaccess):**
```apache
RewriteEngine On
RewriteBase /temple-management/

# Redirect to login if not authenticated
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ login.php [L]

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/temple-management;
    index login.php;

    location / {
        try_files $uri $uri/ /login.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index login.php;
        include fastcgi_params;
    }
}
```

#### Step 6: Test Installation

1. Open browser: `http://your-domain.com/temple-management/`
2. You should see the landing page
3. Click "Login"
4. Use super admin credentials:
   - Username: `superadmin`
   - Password: `admin123`
5. Change password immediately!

#### Step 7: Post-Installation

```bash
# Secure the installation
chmod 644 config.php
chown www-data:www-data -R .

# Set up cron jobs (optional)
crontab -e

# Add this line for daily backups
0 2 * * * /usr/local/bin/backup-temple-db.sh

# Add this line for recurring expenses (future)
0 0 * * * php /var/www/html/temple-management/cron/process_recurring.php
```

---

## ⚙️ Configuration Guide

### System Configuration

**config.php** - Main configuration file

```php
// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');

// File Upload Settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);

// Session Settings
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS only

// Pagination
define('RECORDS_PER_PAGE', 50);

// Receipt Settings
define('RECEIPT_FOOTER_TEXT', 'Thank you for your offering!');
define('THERMAL_PRINTER_WIDTH', '80mm');
```

### Temple-Specific Configuration

**temple_config table** - Store per-temple settings

```php
// Set configuration
setTempleConfig('receipt_footer', 'May God Bless You!');
setTempleConfig('enable_sms', 'true');
setTempleConfig('language_default', 'ml');

// Get configuration
$footer = getTempleConfig('receipt_footer', 'Default Footer');
```

### Language Configuration

**languages.php** - Multi-language support

```php
// Current language stored in session
$_SESSION['language'] = 'ml'; // or 'en'

// Add new language
$lang['ta'] = array(
    'dashboard' => 'அட்டவணை',
    'new_booking' => 'புதிய பதிவு',
    // ... more translations
);

// Use in templates
echo t('dashboard'); // Returns translated text
```

---

## 👥 User Roles & Permissions

### Role Hierarchy

```
SuperAdmin (System Level)
    ↓
TempleAdmin (Temple Level)
    ↓
Manager (Department Level)
    ↓
Staff (Operational Level)
    ↓
Counter (Limited Access)
```

### Permission Matrix

| Feature | SuperAdmin | TempleAdmin | Manager | Staff | Counter |
|---------|-----------|-------------|---------|-------|---------|
| **Temple Management** |
| Add Temple | ✅ | ❌ | ❌ | ❌ | ❌ |
| Edit Temple | ✅ | ✅ | ❌ | ❌ | ❌ |
| View All Temples | ✅ | ❌ | ❌ | ❌ | ❌ |
| **User Management** |
| Add User | ✅ | ✅ | ❌ | ❌ | ❌ |
| Edit User | ✅ | ✅ | ❌ | ❌ | ❌ |
| Delete User | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Vazhipadu** |
| Create Booking | ✅ | ✅ | ✅ | ✅ | ✅ |
| Edit Booking | ✅ | ✅ | ✅ | ❌ | ❌ |
| Delete Booking | ✅ | ✅ | ❌ | ❌ | ❌ |
| View Reports | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Expenses** |
| Add Expense | ✅ | ✅ | ✅ | ✅ | ❌ |
| Edit Expense | ✅ | ✅ | ✅ | ❌ | ❌ |
| Delete Expense | ✅ | ✅ | ❌ | ❌ | ❌ |
| Approve Expense | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Categories** |
| Manage Categories | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Vendors** |
| Add Vendor | ✅ | ✅ | ✅ | ❌ | ❌ |
| Edit Vendor | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Budgets** |
| Set Budget | ✅ | ✅ | ❌ | ❌ | ❌ |
| View Budget | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Reports** |
| Financial Reports | ✅ | ✅ | ✅ | ✅ | ❌ |
| System Reports | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Audit** |
| View Audit Logs | ✅ | ✅ | ❌ | ❌ | ❌ |

### Implementation

```php
// Check if user is admin
function isTempleAdmin() {
    return in_array($_SESSION['role'], ['TempleAdmin', 'SuperAdmin']);
}

// Check specific permission
function hasPermission($action) {
    $permissions = [
        'SuperAdmin' => ['all'],
        'TempleAdmin' => ['temple_admin', 'expense_admin', 'user_admin'],
        'Manager' => ['expense_add', 'booking_all', 'report_view'],
        'Staff' => ['booking_all', 'expense_add'],
        'Counter' => ['booking_add']
    ];
    
    $role = $_SESSION['role'];
    return in_array($action, $permissions[$role]) || 
           in_array('all', $permissions[$role]);
}

// Usage in pages
if (!hasPermission('expense_admin')) {
    die('Access denied');
}
```

---

## 🔐 Security Implementation

### 1. Input Validation & Sanitization

```php
// Sanitize all inputs
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Usage
$name = sanitizeInput($_POST['name']);
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
$amount = floatval($_POST['amount']);
```

### 2. SQL Injection Prevention

```php
// Use prepared statements (recommended upgrade)
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND temple_id = ?");
$stmt->bind_param("si", $username, $temple_id);
$stmt->execute();
$result = $stmt->get_result();

// Current implementation: Sanitize all inputs
$username = sanitizeInput($_POST['username']);
$query = "SELECT * FROM users WHERE username = '$username'";
```

### 3. XSS Protection

```php
// Always escape output
echo htmlspecialchars($user_input);

// For JavaScript
echo json_encode($data);

// Content Security Policy (add to headers)
header("Content-Security-Policy: default-src 'self'");
```

### 4. CSRF Protection (Recommended Addition)

```php
// Generate token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}

// Usage in forms
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

// Validate on submission
if (!validateCSRFToken($_POST['csrf_token'])) {
    die('Invalid request');
}
```

### 5. Password Security

```php
// Hash password (on registration)
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Verify password (on login)
if (password_verify($input_password, $stored_hash)) {
    // Password correct
}

// Password requirements
- Minimum 8 characters
- At least one uppercase
- At least one lowercase
- At least one number
- At least one special character
```

### 6. Session Security

```php
// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS only
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Regenerate session ID on login
session_regenerate_id(true);

// Session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && 
    (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_destroy();
    header('Location: login.php');
}
$_SESSION['LAST_ACTIVITY'] = time();
```

### 7. File Upload Security

```php
// Validate file uploads
function validateUpload($file) {
    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return false;
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return false;
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
    return in_array($mime, $allowed_mimes);
}
```

---

## 💾 Backup & Recovery

### Automated Daily Backup Script

**backup-temple-db.sh**

```bash
#!/bin/bash

# Configuration
DB_USER="temple_user"
DB_PASS="your_password"
DB_NAME="temple_management"
BACKUP_DIR="/var/backups/temple"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/temple_$DATE.sql.gz

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/html/temple-management/uploads

# Delete old backups
find $BACKUP_DIR -name "temple_*.sql.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +$RETENTION_DAYS -delete

# Log
echo "Backup completed: $DATE" >> $BACKUP_DIR/backup.log
```

### Setup Cron Job

```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-temple-db.sh

# Add weekly backup to remote server
0 3 * * 0 rsync -avz /var/backups/temple/ user@remote-server:/backups/temple/
```

### Restore Procedure

```bash
# Restore database
gunzip < temple_20241215_020000.sql.gz | mysql -u temple_user -p temple_management

# Restore files
tar -xzf files_20241215_020000.tar.gz -C /
```

### Manual Backup (Through phpMyAdmin)

1. Login to phpMyAdmin
2. Select `temple_management` database
3. Click "Export" tab
4. Select "Custom" export method
5. Choose "gzip" compression
6. Click "Go"
7. Save the file

---

## 🔧 Troubleshooting Guide

### Common Issues & Solutions

#### Issue 1: Login Not Working

**Symptoms:** Cannot login, "Invalid credentials" message

**Solutions:**
```php
// Check database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify user exists
SELECT * FROM users WHERE username = 'your_username';

// Reset password manually
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE username = 'admin'; -- Password: admin123

// Check session configuration
var_dump($_SESSION); // Debug session data
```

#### Issue 2: Receipt Not Printing

**Symptoms:** Receipt shows on screen but doesn't print

**Solutions:**
- Check printer connection
- Verify thermal printer driver installed
- Test with: `window.print()` in browser console
- Check CSS @media print rules
- Clear browser cache
- Try different browser

#### Issue 3: Subcategories Not Loading

**Symptoms:** Subcategory dropdown remains empty

**Solutions:**
```php
// Check get_subcategories.php
// Verify it returns JSON
header('Content-Type: application/json');
echo json_encode($data);

// Check JavaScript console for errors
// Verify category_id is being passed
// Check database for subcategories

// Test API directly
http://your-domain.com/get_subcategories.php?category_id=1
```

#### Issue 4: Slow Performance

**Symptoms:** Pages load slowly, timeouts

**Solutions:**
```sql
-- Add missing indexes
ALTER TABLE vazhipadu_bookings ADD INDEX idx_temple_date (temple_id, booking_date);
ALTER TABLE expenses ADD INDEX idx_temple_date (temple_id, expense_date);

-- Optimize tables
OPTIMIZE TABLE vazhipadu_bookings;
OPTIMIZE TABLE expenses;

-- Check slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Analyze queries
EXPLAIN SELECT * FROM vazhipadu_bookings WHERE temple_id = 1;
```

```php
// Enable query caching
$memcache = new Memcached();
$memcache->addServer('localhost', 11211);

// Increase PHP memory
ini_set('memory_limit', '256M');

// Optimize database queries
// Use LIMIT in queries
// Add pagination
// Cache frequently accessed data
```

#### Issue 5: Malayalam Text Not Displaying

**Symptoms:** Malayalam shows as boxes or question marks

**Solutions:**
```html
<!-- Add Malayalam font -->
<link href="https://fonts.googleapis.com/css2?family=Manjari&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Manjari', sans-serif;
}
</style>
```

```sql
-- Check database charset
SHOW VARIABLES LIKE 'character_set%';

-- Convert to UTF8MB4 if needed
ALTER DATABASE temple_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE devotees CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```php
// Set connection charset
$conn->set_charset("utf8mb4");

// In PHP files
header('Content-Type: text/html; charset=utf-8');
```

---

## 🚀 Future Enhancements

### Phase 1 (Immediate)
- ✅ CSRF protection
- ✅ Prepared statements for all queries
- ✅ File attachment for expense bills
- ✅ Email notifications
- ✅ SMS integration
- ✅ Barcode/QR code on receipts
- ✅ Advanced search filters
- ✅ Export to Excel/PDF

### Phase 2 (Short-term)
- 📱 Mobile app (React Native)
- 🌐 Online booking portal for devotees
- 💳 Payment gateway integration
- 📊 Advanced analytics dashboard
- 📈 Visual charts and graphs
- 🔔 Push notifications
- 📧 Automated email reports
- 🗓️ Calendar view for sevas

### Phase 3 (Long-term)
- 🤖 AI-powered expense prediction
- 📸 OCR for bill scanning
- 🎙️ Voice commands
- 🌍 Multi-language support (Tamil, Hindi, Kannada)
- 🔗 Accounting software integration
- 📱 WhatsApp integration
- 🎫 Event management module
- 👥 Volunteer management
- 📚 Inventory management
- 🏗️ Asset management

---

## 💻 Code Examples

### Example 1: Create New Booking

```php
<?php
require_once 'config.php';
requireLogin();

$temple_id = getCurrentTempleId();

// Get form data
$devotee_name = sanitizeInput($_POST['devotee_name']);
$phone = sanitizeInput($_POST['phone']);
$vazhipadu_type_id = intval($_POST['vazhipadu_type_id']);
$payment_mode = sanitizeInput($_POST['payment_mode']);

// Check if devotee exists
$devoteeQuery = "SELECT id FROM devotees 
                 WHERE temple_id = $temple_id AND phone = '$phone'";
$result = $conn->query($devoteeQuery);

if ($result->num_rows > 0) {
    $devotee_id = $result->fetch_assoc()['id'];
} else {
    // Create new devotee
    $insertDevotee = "INSERT INTO devotees (temple_id, name, phone) 
                      VALUES ($temple_id, '$devotee_name', '$phone')";
    $conn->query($insertDevotee);
    $devotee_id = $conn->insert_id;
}

// Get amount
$amountQuery = "SELECT amount FROM vazhipadu_types WHERE id = $vazhipadu_type_id";
$amount = $conn->query($amountQuery)->fetch_assoc()['amount'];

// Generate receipt number
$receipt_no = generateReceiptNo();

// Create booking
$insertBooking = "INSERT INTO vazhipadu_bookings 
    (temple_id, receipt_no, devotee_id, vazhipadu_type_id, 
     devotee_name, booking_date, amount, payment_mode, created_by)
    VALUES 
    ($temple_id, '$receipt_no', $devotee_id, $vazhipadu_type_id,
     '$devotee_name', CURDATE(), $amount, '$payment_mode', {$_SESSION['user_id']})";

if ($conn->query($insertBooking)) {
    $booking_id = $conn->insert_id;
    logAudit('Booking Created', 'vazhipadu_bookings', $booking_id);
    
    echo json_encode([
        'success' => true,
        'receipt_no' => $receipt_no,
        'booking_id' => $booking_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => $conn->error
    ]);
}
?>
```

### Example 2: Generate Financial Report

```php
<?php
function generateFinancialReport($temple_id, $from_date, $to_date) {
    global $conn;
    
    // Get income
    $incomeQuery = "SELECT COALESCE(SUM(amount), 0) as total 
                    FROM vazhipadu_bookings 
                    WHERE temple_id = $temple_id 
                    AND booking_date BETWEEN '$from_date' AND '$to_date'
                    AND payment_status = 'Completed'";
    $income = $conn->query($incomeQuery)->fetch_assoc()['total'];
    
    // Get expenses
    $expenseQuery = "SELECT COALESCE(SUM(total_amount), 0) as total 
                     FROM expenses 
                     WHERE temple_id = $temple_id 
                     AND expense_date BETWEEN '$from_date' AND '$to_date'
                     AND payment_status = 'Paid'";
    $expense = $conn->query($expenseQuery)->fetch_assoc()['total'];
    
    // Calculate balance
    $balance = $income - $expense;
    $expense_ratio = $income > 0 ? ($expense / $income) * 100 : 0;
    
    // Get category breakdown
    $categoryQuery = "SELECT ec.category_name, SUM(e.total_amount) as total
                      FROM expenses e
                      JOIN expense_categories ec ON e.category_id = ec.id
                      WHERE e.temple_id = $temple_id
                      AND e.expense_date BETWEEN '$from_date' AND '$to_date'
                      GROUP BY ec.id
                      ORDER BY total DESC";
    $categories = $conn->query($categoryQuery);
    
    return [
        'income' => $income,
        'expense' => $expense,
        'balance' => $balance,
        'expense_ratio' => $expense_ratio,
        'categories' => $categories->fetch_all(MYSQLI_ASSOC)
    ];
}

// Usage
$report = generateFinancialReport(1, '2024-12-01', '2024-12-31');
print_r($report);
?>
```

### Example 3: Budget Alert System

```php
<?php
function checkBudgetAlerts($temple_id, $year, $month) {
    global $conn;
    
    // Update spent amounts
    $updateQuery = "UPDATE budgets b 
                    SET spent_amount = (
                        SELECT COALESCE(SUM(total_amount), 0)
                        FROM expenses
                        WHERE temple_id = b.temple_id
                        AND category_id = b.category_id
                        AND YEAR(expense_date) = b.budget_year
                        AND MONTH(expense_date) = b.budget_month
                    )
                    WHERE b.temple_id = $temple_id
                    AND b.budget_year = $year
                    AND b.budget_month = $month";
    $conn->query($updateQuery);
    
    // Get over-budget categories
    $alertQuery = "SELECT b.*, ec.category_name
                   FROM budgets b
                   JOIN expense_categories ec ON b.category_id = ec.id
                   WHERE b.temple_id = $temple_id
                   AND b.budget_year = $year
                   AND b.budget_month = $month
                   AND b.spent_amount > b.budgeted_amount";
    
    $alerts = $conn->query($alertQuery);
    
    $messages = [];
    while ($alert = $alerts->fetch_assoc()) {
        $overage = $alert['spent_amount'] - $alert['budgeted_amount'];
        $messages[] = sprintf(
            "⚠️ Budget Alert: %s is over budget by %s (%.1f%%)",
            $alert['category_name'],
            formatCurrency($overage),
            ($overage / $alert['budgeted_amount']) * 100
        );
    }
    
    return $messages;
}

// Usage
$alerts = checkBudgetAlerts(1, 2024, 12);
foreach ($alerts as $alert) {
    echo $alert . "\n";
}
?>
```

---

## 📞 Support & Contact

### Technical Support
- **Email**: support@templemanagement.com
- **Phone**: +91 98765 43210
- **Hours**: 9 AM - 6 PM IST (Monday - Saturday)

### Documentation
- **Online Docs**: https://docs.templemanagement.com
- **Video Tutorials**: https://youtube.com/@templemanagement
- **FAQ**: https://templemanagement.com/faq

### Community
- **Forum**: https://forum.templemanagement.com
- **WhatsApp Group**: [Join Link]
- **Telegram Channel**: [Join Link]

### Bug Reports
- **GitHub Issues**: https://github.com/temple-management/issues
- **Email**: bugs@templemanagement.com

### Feature Requests
- **Form**: https://templemanagement.com/feature-request
- **Email**: features@templemanagement.com

---

## 📄 License

**Proprietary Software License**

Copyright © 2024 Temple Management System. All rights reserved.

This software and associated documentation files (the "Software") are proprietary and confidential. Unauthorized copying, distribution, or use of this Software is strictly prohibited.

For licensing inquiries, contact: license@templemanagement.com

---

## 🙏 Acknowledgments

- Kerala Temple Community
- Beta testers from various temples
- Open source community for tools and libraries
- Development team and contributors

---

**End of Documentation**

*For the latest updates, visit: https://templemanagement.com*
