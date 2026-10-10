# Temple Management System - Multi-Temple Setup Guide

## 🕉️ Overview

This is a **Multi-Tenant Temple Management System** that allows you to onboard and manage multiple temples from a single installation. Each temple has its own isolated data, users, and configuration.

## 🎯 Key Features

### Multi-Temple Architecture
- **Centralized Management**: One super admin manages all temples
- **Data Isolation**: Each temple's data is completely separate
- **Custom Configuration**: Each temple can have unique settings
- **Subscription Management**: Track subscription plans and expiry dates
- **Independent Users**: Each temple has its own admin and staff

### Role-Based Access Control
1. **SuperAdmin** - System-wide access, manages all temples
2. **TempleAdmin** - Full access to their temple only
3. **Manager** - Can manage bookings and reports
4. **Staff** - Can create bookings and view reports
5. **Counter** - Limited to booking creation only

### Subscription Plans
- **Free** - Basic features, limited bookings
- **Basic** - Standard temple management features
- **Premium** - Advanced features + reporting
- **Enterprise** - Full features + custom integrations

## 📦 Installation Steps

### 1. Database Setup
```bash
# Import the multi-temple database schema
mysql -u root -p < temple_db_schema.sql
```

This creates:
- 3 sample temples (Krishna Temple, Padmanabhaswamy Temple, Sabarimala)
- Super admin account
- Temple admin accounts for each temple
- Sample vazhipadu types

### 2. Configuration
Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'temple_management');
```

### 3. File Structure
```
/temple-management/
├── config.php                 # Database & system config
├── login.php                  # Multi-temple login
├── logout.php                 # Logout handler
├── index.php                  # Temple dashboard
├── new_booking.php            # Create bookings
├── print_receipt.php          # Print receipts
├── search.php                 # Search bookings
├── reports.php                # Temple reports
├── admin.php                  # Temple admin panel
├── super_admin.php            # Super admin dashboard
├── super_admin_users.php      # All users management
├── super_admin_reports.php    # System-wide reports
└── super_admin_audit.php      # Audit logs
```

## 🔐 Default Login Credentials

### Super Admin Login
```
URL: login.php
Click: "Super Admin Login"
Username: superadmin
Password: admin123
```

### Temple Admin Logins
```
Temple: Sri Krishna Temple
Username: temp001_admin
Password: admin123 or try password

Temple: Padmanabhaswamy Temple
Username: temp002_admin
Password: admin123

Temple: Sabarimala Ayyappa Temple
Username: temp003_admin
Password: admin123
```

## 🏛️ Onboarding New Temples

### Step 1: Super Admin Login
1. Login as super admin
2. Navigate to Super Admin Dashboard

### Step 2: Add New Temple
1. Click "**+ Onboard New Temple**"
2. Fill in temple details:
   - **Temple Code**: Unique code (e.g., TEMP004) - cannot be changed later
   - **Temple Name**: Full name of the temple
   - **Address**: Complete address
   - **City & State**: Location details
   - **Contact**: Phone and email
   - **Subscription Plan**: Choose appropriate plan
   - **Subscription Expiry**: Set expiry date

### Step 3: Temple Admin Account
The system automatically creates:
- Admin username: `[temple_code]_admin` (e.g., `temp004_admin`)
- Default password: `admin123`
- Role: TempleAdmin

### Step 4: Temple Setup
Temple admin should:
1. Login using their credentials
2. Change default password
3. Go to Admin Panel
4. Add Vazhipadu Types
5. Add Staff Users
6. Start creating bookings

## 🔄 Data Flow

```
SuperAdmin
    ├── Manages all temples
    ├── Views system-wide statistics
    ├── Controls subscriptions
    └── Manages temple activation

TempleAdmin (Temple 1)
    ├── Manages only Temple 1 data
    ├── Adds vazhipadu types
    ├── Manages temple staff
    └── Views temple reports

Staff (Temple 1)
    ├── Creates bookings for Temple 1
    ├── Prints receipts
    └── Searches bookings

[All data isolated by temple_id]
```

## 📊 Database Schema Highlights

### Key Tables
- **temples** - Master list of all temples
- **temple_config** - Temple-specific configurations
- **users** - All users (linked to temple_id)
- **devotees** - Devotees (linked to temple_id)
- **vazhipadu_types** - Offering types (per temple)
- **vazhipadu_bookings** - All bookings (per temple)
- **audit_log** - Complete audit trail

### Data Isolation
Every table (except `temples`) has a `temple_id` foreign key ensuring complete data separation between temples.

## 🎨 Customization Per Temple

Each temple can customize:
- **Vazhipadu Types**: Define their own offerings
- **Pricing**: Set custom amounts
- **Receipt Format**: Configure receipt details
- **Users**: Add unlimited staff members
- **Reports**: Generate temple-specific reports

## 📈 System Statistics

### Super Admin Dashboard Shows:
- Total active temples
- System-wide user count
- Total bookings across all temples
- Combined revenue from all temples

### Temple Dashboard Shows:
- Today's bookings (temple-specific)
- Today's collection (temple-specific)
- Payment mode breakdown
- Recent bookings

## 🔒 Security Features

1. **Role-Based Access Control**: Users only see their temple's data
2. **Password Hashing**: All passwords secured with bcrypt
3. **Audit Logging**: Every action is logged with user and IP
4. **Session Management**: Secure session handling
5. **SQL Injection Prevention**: All inputs sanitized
6. **Subscription Validation**: Automatic subscription checks

## 🚀 Advanced Features

### Audit Trail
All actions are logged:
- Who performed the action
- What was changed
- When it happened
- From which IP address

### Subscription Management
- Track expiry dates
- Automatic warnings before expiry
- Disable temple access on expiry
- Multiple plan tiers

### Receipt Numbering
Format: `[TEMPLE_CODE][YYYYMMDD][SEQUENCE]`
Example: `TEMP001202411230001`
- Unique per temple
- Date-based
- Sequential numbering

## 🛠️ Maintenance

### Adding Features
When adding new features, always include:
```php
$temple_id = getCurrentTempleId();
```
in queries to ensure data isolation.

### Database Backups
Recommended schedule:
- Daily: Automated backups
- Weekly: Full backup with verification
- Monthly: Off-site backup storage

### Updates
Before updating:
1. Backup database
2. Test in staging environment
3. Notify all temple admins
4. Schedule during low-traffic hours

## 📞 Support

### For Temple Admins
Contact your Super Admin for:
- Account issues
- Subscription renewals
- Technical support

### For Super Admin
- System documentation in `/docs`
- Technical support: Contact developer
- Feature requests: Submit via admin panel

## 🎓 Training Materials

### For Temple Staff
1. Login and navigation
2. Creating bookings
3. Printing receipts
4. Searching records

### For Temple Admins
1. User management
2. Vazhipadu type setup
3. Report generation
4. Configuration options

### For Super Admin
1. Onboarding temples
2. Subscription management
3. System monitoring
4. Audit log review

## 📝 Best Practices

1. **Regular Password Changes**: Enforce every 90 days
2. **User Deactivation**: Disable inactive users immediately
3. **Audit Reviews**: Monthly review of audit logs
4. **Backup Verification**: Test backup restoration quarterly
5. **Subscription Monitoring**: Renew 30 days before expiry

## 🔄 Migration from Single Temple

If you're upgrading from the single-temple version:

1. Backup existing database
2. Create new multi-temple database
3. Insert your temple into `temples` table
4. Update all user records with `temple_id`
5. Run data migration script
6. Verify all data is accessible
7. Test thoroughly before going live

## 📊 Performance Optimization

- All queries include `temple_id` in WHERE clause
- Indexes on `temple_id` + frequently queried columns
- Connection pooling for better performance
- Cached temple configuration
- Optimized receipt generation

---

## ✅ Quick Start Checklist

- [ ] Database imported successfully
- [ ] config.php configured
- [ ] Super admin login working
- [ ] Sample temples accessible
- [ ] First test booking created
- [ ] Receipt printing working
- [ ] Reports generating correctly
- [ ] New temple onboarding tested
- [ ] User management tested
- [ ] Audit logs recording

---

**Version**: 2.0  
**Last Updated**: November 2024  
**License**: Proprietary - Contact for licensing

For additional support or custom features, contact the development team.
