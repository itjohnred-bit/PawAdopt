# 🐾 PAWAdopt – Pet Adoption Platform

A full-stack pet adoption web application connecting **Adopters** and **veterinarys/Rescue Organizations**.

---

## ✅ Quick Start

### Requirements
- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Apache / Nginx with `mod_rewrite`
- OR: XAMPP / WAMP / Laragon (local development)
/c/xampp/php/php -S localhost:8085 -t public router.php
---

## 🗂️ Installation

### Step 1 – Place files
Copy the `PAWAdopt` folder into your web server root:
- **XAMPP**: `C:\xampp\htdocs\PAWAdopt\`
- **WAMP**: `C:\wamp64\www\PAWAdopt\`
- **Linux Apache**: `/var/www/html/PAWAdopt/`

### Step 2 – Create the database
1. Open **phpMyAdmin** (or MySQL CLI)
2. Create a new database called `pawadopt`
3. Import `sql/pawadopt.sql`

```sql
CREATE DATABASE pawadopt;
USE pawadopt;
SOURCE /path/to/PAWAdopt/sql/pawadopt.sql;
```

### Step 3 – Configure database
Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pawadopt');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
define('APP_URL', 'http://localhost/PAWAdopt');
```

### Step 4 – Set permissions
Make the uploads folder writable:
```bash
chmod -R 775 uploads/
```

### Step 5 – Open in browser
```
http://localhost/PAWAdopt/
```

---php -S localhost:8000 -t public router.php

## 👤 Demo Accounts

All demo accounts use the **same password**: `password`

| Role    | Username     | Email               | Password   |
|---------|-------------|---------------------|------------|
| Admin   | admin        | admin@pawadopt.com  | admin123  |
| Veterinary | Veterinary   | vet@tester.com     | veterinary123   |
| Adopter | Adopter     | adopter@test.com    | tester123   |

> ⚠️ **Change these passwords** before deploying to production!

---

## 🏗️ Project Structure

```
PAWAdopt/
├── index.php               ← Login / Register page
├── config/
│   └── database.php        ← DB config & connection
├── includes/
│   ├── functions.php       ← Auth, helpers, notifications
│   ├── header.php          ← Navbar, session check
│   └── footer.php          ← Footer, scripts
├── api/
│   ├── auth.php            ← Login / Register / Logout
│   ├── pets.php            ← Pet CRUD
│   ├── applications.php    ← Adoption applications
│   ├── messages.php        ← Messaging
│   ├── favorites.php       ← Favorites
│   ├── notifications.php   ← Notifications
│   └── admin.php           ← Admin actions
├── pages/
│   ├── adopter/
│   │   ├── dashboard.php
│   │   ├── browse.php
│   │   ├── pet-detail.php
│   │   ├── applications.php
│   │   ├── favorites.php
│   │   ├── messages.php
│   │   └── profile.php
│   ├── veterinary/
│   │   ├── dashboard.php
│   │   ├── pets.php
│   │   ├── add-pet.php
│   │   ├── edit-pet.php
│   │   ├── applications.php
│   │   ├── messages.php
│   │   └── profile.php
│   └── admin/
│       ├── dashboard.php
│       ├── users.php
│       ├── veterinarys.php
│       ├── pets.php
│       └── reports.php
├── assets/
│   ├── css/
│   │   ├── main.css        ← App-wide styles
│   │   └── auth.css        ← Login/Register styles
│   ├── js/
│   │   ├── app.js          ← Main JS (dropdowns, messaging, etc.)
│   │   └── auth.js         ← Auth page JS
│   └── images/
│       └── pet-placeholder.png
├── uploads/
│   └── pets/               ← Pet photo uploads
└── sql/
    └── pawadopt.sql        ← Database schema + seed data
```

---

## 🎨 UI Design

- **Color Palette**: Teal (`#0d9488`) + Light Gray (`#f3f4f6`)
- **Font**: Nunito (Google Fonts)
- **Icons**: Font Awesome 6
- **Style**: Rounded, modern, mobile-responsive
- **Decorations**: 🦴 pixel bone,  🐾 paw prints

---

## 🔐 User Roles

### Adopter
- Browse & search pet listings
- Save favorites
- Submit adoption applications
- Track application status
- Message veterinarys
- Receive notifications

### veterinary
- Post pet listings with photos
- Review adoption applications (Approve / Reject)
- Message adopters
- Manage veterinary profile
- Await admin verification

### Admin
- Verify/reject veterinarys
- Manage all users (activate/deactivate/delete)
- Moderate pet listings
- View reports & analytics
- Edit site content (About, Terms)

---

## 🗄️ Database Tables

| Table | Description |
|-------|-------------|
| `users` | All user accounts (Adopter/veterinary/Admin) |
| `adopter_profiles` | Adopter profile details |
| `veterinary_profiles` | veterinary info & verification status |
| `pets` | Pet listings |
| `pet_photos` | Multiple photos per pet |
| `adoption_applications` | Applications with status tracking |
| `favorites` | Adopter saved pets |
| `conversations` | Messaging threads |
| `messages` | Individual messages |
| `notifications` | In-app notifications |
| `veterinary_verifications` | Admin verification records |
| `site_content` | CMS for About/Terms text |

---

## 🔒 Security Features
- `password_hash()` with bcrypt (cost 10)
- PDO prepared statements (SQL injection prevention)
- `htmlspecialchars()` output escaping (XSS prevention)
- Role-based access control on every page
- CSRF protection available via `generateCsrfToken()`
- File upload validation (type + size)

---

## 🚀 Production Checklist
- [ ] Update `DB_USER`, `DB_PASS` in `config/database.php`
- [ ] Update `APP_URL` to your domain
- [ ] Change all demo account passwords
- [ ] Set `chmod 775 uploads/`
- [ ] Enable HTTPS
- [ ] Disable PHP error display (`display_errors = Off`)
- [ ] Set up email for password reset (update `api/auth.php`)

---

## 📧 Support
Built with ❤️ for PAWAdopt – *Finding forever homes, one paw at a time.* 🐶🐱🐰
