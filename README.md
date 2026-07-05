# CampusFind Pro 🔍

> **CampusFind Pro** is a modern, enterprise-grade Lost & Found Management SaaS web application tailored for college campuses and academic institutions. 

Built with secure Object-Oriented PHP 8, MySQL, and styled with premium Glassmorphism, Stripe-inspired CSS, and dynamic animations, the system streamlines the reporting, searching, claiming, and verification lifecycle of lost or found campus assets.

---

## 🚀 Key Features

### 👤 Student Dashboard
* **Sleek Portal:** View reported items, submitted claim requests, and activity timelines with dynamic Chart.js breakdowns.
* **Smart Reporting:** Report lost or found items with location tags, date logs, image uploads, dynamic categories, and optional cash rewards.
* **Claim Registry:** File ownership claim requests with description proof statements and receipt/photo attachments.
* **Instant Inbox:** System notifications for claim status updates (Approved/Rejected) and coordinator comments.
* **Profile Manager:** Edit names, phone contacts, passwords, and upload custom circular avatar graphics.

### 🛡️ Administrative Console
* **Moderation Panels:** View, search, freeze student accounts, or delete invalid student records.
* **Registry moderation:** UNION-queried database moderation table of all active lost & found assets with deletion overrides.
* **Claim Verification:** Expansive row overlays to audit claim statements, verify image attachments, input pick-up instructions, and approve/reject claims.
* **Reports Centre:** Generate instant exports (Active Lost, Logged Found, Claims audits, Student registries) and save static snapshots on disk.
* **Settings Engine:** Adjust platform portal name, admin support email, custom idle session timeouts, and toggle student email verification codes.
* **Audit Trail:** Comprehensive security log timeline indexing actors, IP addresses, and database actions.

---

## 🛠️ Technology Stack

* **Frontend:** HTML5, CSS3, Bootstrap 5, Vanilla JS, AJAX, FontAwesome 6, Google Fonts (Outfit & Plus Jakarta Sans), Chart.js (Interactive widgets), AOS (Scroll animations).
* **Backend:** PHP 8 (Object-Oriented Architecture, MVC folder layout).
* **Database:** MySQL 8 (Fully normalized tables, PDO Prepared Statements, Transactions).
* **Environment:** XAMPP (Apache & MySQL).

---

## 📁 Directory Structure

```
CampusFind-Pro/
├── assets/
│   ├── css/
│   │   └── style.css            # Premium Glassmorphic custom stylesheet
│   └── js/
│       └── app.js               # Theme toggler, AJAX helpers, custom Toasts
├── config/
│   ├── config.php               # System environment configs & variables
│   ├── database.php             # PDO Singleton connection helper
│   └── session.php              # Secure Session check & hijacking defense
├── database/
│   └── schema.sql               # Normalized tables & Seed SQL script
├── includes/
│   ├── helpers.php              # Input sanitization, logging, & flash helpers
│   ├── navbar.php               # Common responsive navbar header
│   └── footer.php               # Script compilers & footer layout
├── auth/
│   ├── login.php                # Authentication page with security checks
│   ├── register.php             # Student register form
│   ├── logout.php               # Destroys sessions and cookies
│   ├── verify.php               # Simulates email code verification
│   └── forgot-password.php      # Recover password writing links to logs
├── dashboard/
│   ├── index.php                # Student dashboard, statistics & claims logs
│   └── profile.php              # Details editing, password modifications, photo uploads
├── lost/
│   ├── report.php / edit.php    # Create/edit lost reports
│   ├── delete.php               # Deletes lost reports and cleans assets
│   ├── search.php               # Paginated lost catalog search
│   └── view.php                 # Details page with client QR Code generator
├── found/
│   ├── report.php / edit.php    # Create/edit found logs
│   ├── delete.php               # Deletes found logs
│   ├── search.php               # Paginated found catalog search
│   └── view.php                 # Details page with client QR Code generator
├── claims/
│   ├── submit.php               # Submit proof logs (verifies ownership checks)
│   ├── view.php                 # Individual claim details & decision remarks
│   └── process.php              # Transactional approval/rejection handler
├── notifications/
│   └── list.php                 # Dynamic notification alerts inbox
├── uploads/                     # Uploads destination directory (Avatar & items images)
└── admin/
    ├── dashboard.php            # Console home & Chart.js series trends
    ├── users.php / items.php    # Accounts & asset moderation panels
    ├── claims.php               # Inline claims validation row reviews
    ├── reports.php / export.php # Generates XLS formats & printable PDF reports
    ├── settings.php             # System configurations forms
    └── logs.php                 # Security timeline logs
```

---

## 🔒 Security Architectures
* **Prepared Statements:** 100% of queries compiled via PDO prepared statements preventing SQL Injection attacks.
* **XSS Defense:** Strict custom recursive sanitization helper on user inputs.
* **CSRF Countermeasures:** Dynamic token generation and verification checks on state-changing requests.
* **Hijacking Shield:** Automatic IP/User-Agent fingerprint checksum checking.
* **Idle Timeout:** Automatically invalidates user sessions after inactive thresholds (default 30 mins).
* **Safe Image Filters:** Rigid MIME type (JPEG/PNG/WEBP) and file size constraints on uploads.

---

## ⚙️ Installation & Setup

1. **Start XAMPP Server:** Start Apache and MySQL in your XAMPP Control Panel.
2. **Move files:** Copy the `CampusFind-Pro` project directory into your local XAMPP `htdocs` directory (typically `C:\xampp\htdocs\`).
3. **Database Import:**
   * Open **`http://localhost/phpmyadmin/`** in your browser.
   * Click **New** in the sidebar and create a database named: `campusfind_pro`.
   * Click the new database, go to the **Import** tab, choose the schema file [schema.sql](database/schema.sql), and click **Go/Import**.
4. **Access the Portal:** Open your browser and navigate to: **`http://localhost/CampusFind-Pro/index.php`**.

---

## 🔑 Pre-seeded Logins

### 🛡️ Platform Administrator
* **Email:** `admin@campusfindpro.edu`
* **Password:** `Admin123!`
* **Admin Level:** Superadmin

---

## 📧 Simulated Testing logs
Since this runs in local XAMPP environments, external SMTP triggers are simulated. Account confirmation codes and password recovery links are logged to:
`uploads/mock_emails.log`

Open this log file locally to complete mock registrations or reset test student credentials!

---

## 📄 License
This application is distributed under the SaaS University MIT License.
