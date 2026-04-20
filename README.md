# 🛠️ Salon Booking System - Setup & Usage Guide

## 📋 Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## 🚀 Quick Start

### 1. Database Setup
```bash
# Create database
mysql -u root -p
CREATE DATABASE db_salon;
exit;
```

### 2. System Initialization
1. Place all files in your web server root
2. Open browser and go to: `http://localhost/salon_app/setup_complete.php`
3. This will create all tables and insert sample data

### 3. Test System
1. Go to: `http://localhost/salon_app/test_booking_flow.php`
2. Check for any issues and fix them

### 4. Start Using
1. **Admin Login**: `http://localhost/salon_app/auth/login.php`
   - Email: `admin@salon.com`
   - Password: `admin123`

2. **Customer Login**: `http://localhost/salon_app/auth/login.php`
   - Email: `customer@salon.com`
   - Password: `customer123`

## 📊 System Features

### ✅ Fully Working Features:
- **User Authentication** (Admin/Customer/Kasir)
- **Service Management** (CRUD operations)
- **Employee Management** (with skills & schedules)
- **Product Inventory** (with stock tracking)
- **Booking System** (with availability checking)
- **QRIS Payment** (simulated for testing)
- **Real-time Availability** (employee & product checks)

### 🎯 Booking Flow:
1. **Customer Login** → Select Services → Choose Date/Time → Pick Employee → Confirm Booking
2. **Payment** → QRIS Generation → Upload Payment Proof → Status Verification
3. **Success** → Booking Confirmed → Email/Service Notifications

## 🔧 Configuration Files

### Database (`config/db.php`)
```php
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "db_salon";
```

### Midtrans Payment (`config/midtrans_config.php`)
- Sandbox mode for testing
- Simulated payment responses
- QRIS code generation

### Constants (`config/constants.php`)
- Base URL configuration
- Role definitions
- Path constants

## 📁 File Structure
```
salon_app/
├── config/           # Configuration files
├── customer/         # Customer-facing pages
├── admin/           # Admin management pages
├── kasir/           # Cashier pages
├── auth/            # Authentication
├── assets/          # CSS, JS, Images
├── uploads/         # File uploads
├── vendor/          # Third-party libraries
└── setup_complete.php  # Initial setup
```

## 🧪 Testing Checklist

### Pre-Booking Tests:
- [ ] Database connection works
- [ ] Sample data loaded
- [ ] Admin login works
- [ ] Customer login works

### Booking Flow Tests:
- [ ] Service selection works
- [ ] Date/time selection shows availability
- [ ] Employee selection works
- [ ] Form validation prevents invalid submissions
- [ ] Booking creation succeeds

### Payment Flow Tests:
- [ ] QRIS code generates
- [ ] Payment proof upload works
- [ ] Status updates correctly
- [ ] Success page displays

## 🐛 Troubleshooting

### Common Issues:

#### 1. "No employees available"
**Solution**: Run `setup_complete.php` to create employee data

#### 2. "Service not available"
**Solution**: Check service status in admin panel

#### 3. "Payment failed"
**Solution**: Check Midtrans configuration and internet connection

#### 4. "File upload failed"
**Solution**: Check folder permissions on `uploads/` directory

#### 5. "Database connection failed"
**Solution**: Update database credentials in `config/db.php`

### Debug Mode:
Add this to any PHP file for debugging:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📞 Support

If you encounter issues:
1. Run `test_booking_flow.php` to diagnose problems
2. Check PHP error logs
3. Verify database connectivity
4. Ensure all required directories exist and are writable

## 🎉 Success Indicators

Your system is ready when:
- ✅ All tests in `test_booking_flow.php` pass
- ✅ You can login as admin and customer
- ✅ You can create a booking end-to-end
- ✅ Payment flow completes successfully
- ✅ No PHP errors in browser console

**Happy Booking! 🎊**