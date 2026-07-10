# CampusFind Pro 🔍

CampusFind Pro is a premium, modern, and secure lost-and-found portal designed specifically for university campuses. Built with **PHP 8+**, **MongoDB Atlas**, **Google OAuth 2.0**, **Bootstrap 5**, and integration for transactional email delivery via **Brevo** and **Resend** APIs.

---

## 🌟 Key Features

- **Google OAuth Login:** Quick, secure authentication mapped automatically to campus student profiles.
- **Lost & Found Reporting:** Log detailed item reports with photos, categories, dates, and reward estimates.
- **Interactive Dashboard:** View real-time personal statistics, pending/approved claims, notifications, and recent activity logs.
- **Administrative Control Panel:** Manage users, edit/delete logs, approve claims, and customize system settings.
- **Email Notifications:** Automatic dispatch of password reset links and registration verification codes using Brevo or Resend HTTP APIs.
- **Dynamic Styling & Dark Mode:** A sleek glassmorphic layout supporting a responsive user interface and manual dark mode toggling.

---

## 🛠️ Tech Stack

- **Backend:** PHP 8+ (using the Native MongoDB Driver extension)
- **Database:** MongoDB Atlas (Cloud NoSQL)
- **CSS Framework:** Bootstrap 5 (with Custom Glassmorphism styles)
- **Authentication:** Google OAuth 2.0 Client Library & Native Session Handlers
- **Email APIs:** Brevo (Sendinblue) API & Resend.com API

---

## 💻 Local Setup (using XAMPP)

### Prerequisites
1. Install **XAMPP** (with PHP 8+).
2. Install the **MongoDB PHP Extension** (`php_mongodb.dll` for Windows) and add `extension=mongodb` to your `php.ini`.
3. Start Apache in XAMPP.

### Installation Steps

1. **Clone the Repository:**
   Move the project files to your XAMPP htdocs directory:
   ```bash
   C:\xampp\htdocs\CampusFind-Pro
   ```

2. **Configure Environment Variables:**
   Create a `.env` file in the root folder (`CampusFind-Pro/.env`) and add your credentials:
   ```ini
   # MongoDB Atlas Database Credentials
   MONGODB_URI="mongodb+srv://<username>:<password>@cluster0.mongodb.net/campusfind_pro"
   MONGODB_DB="campusfind_pro"

   # Google OAuth Credentials
   GOOGLE_CLIENT_ID="your-client-id.apps.googleusercontent.com"
   GOOGLE_CLIENT_SECRET="your-client-secret"

   # SMTP Backup Mailer
   SMTP_HOST="smtp.gmail.com"
   SMTP_PORT=465
   SMTP_USER="your-email@gmail.com"
   SMTP_PASS="your-app-password"
   SMTP_SECURE="ssl"

   # Brevo.com HTTP API Credentials (Allows sending to any recipient)
   BREVO_API_KEY="your-brevo-api-key"
   BREVO_SENDER_EMAIL="your-verified-sender@domain.com"
   BREVO_SENDER_NAME="CampusFind Pro"

   # Resend.com HTTP API Credentials (Fallback)
   RESEND_API_KEY="your-resend-api-key"
   RESEND_FROM_EMAIL="onboarding@resend.dev"
   ```

3. **Seed Database Schema:**
   To populate initial default categories (e.g., Electronics, Documents, Keys) and create the administrator user account, open a terminal in the root directory and run the database seeder:
   ```bash
   php database/seed_mongodb.php
   ```
   - **Default Admin Account:** `admin@campusfindpro.edu`
   - **Default Admin Password:** `admin123` *(**IMPORTANT:** Change this password immediately upon your first login for production security!)*

4. **Run the Project:**
   Open your browser and navigate to the live deployment link:
   ```url
   https://campusfind-pro.onrender.com/
   ```

---

## 🚀 Cloud Deployment (Render.com)

1. Connect your GitHub repository to a new **Render Web Service**.
2. **Environment Variable Configuration:**
   Map the following variables under the **Environment** settings tab on Render:
   - `MONGODB_URI`
   - `MONGODB_DB`
   - `GOOGLE_CLIENT_ID`
   - `GOOGLE_CLIENT_SECRET`
   - `BREVO_API_KEY`
   - `BREVO_SENDER_EMAIL`
3. Click **Save Changes** and deploy the web service. Render will automatically compile and launch your application!
