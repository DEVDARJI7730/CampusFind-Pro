<?php
/**
 * CampusFind Pro - MongoDB Seeder Script
 * Run this script via CLI to initialize database collections, default settings, categories, and superadmin accounts.
 * Usage: php database/seed_mongodb.php
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

echo "=== CampusFind Pro MongoDB Database Seeder ===\n";

try {
    $db = Database::getInstance();
    $manager = $db->getManager();
    $dbName = $db->getDbName();

    echo "Connection verified to database: $dbName\n";

    // 1. Seed Categories
    echo "Seeding default categories...\n";
    // Check if categories collection already has entries
    $existing_categories = $db->count('categories');
    if ($existing_categories === 0) {
        $categories = [
            ['name' => 'Electronics', 'icon' => 'fa-laptop', 'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Keys & Wallets', 'icon' => 'fa-key', 'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Books & Stationery', 'icon' => 'fa-book', 'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Clothing & Accessories', 'icon' => 'fa-shirt', 'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Cards & IDs', 'icon' => 'fa-id-card', 'created_at' => date('Y-m-d H:i:s')],
            ['name' => 'Others', 'icon' => 'fa-box-open', 'created_at' => date('Y-m-d H:i:s')]
        ];
        
        foreach ($categories as $cat) {
            $db->insert('categories', $cat);
        }
        echo "Successfully seeded " . count($categories) . " categories.\n";
    } else {
        echo "Categories collection already initialized. Skipping...\n";
    }

    // 2. Seed Settings
    echo "Seeding default settings configurations...\n";
    $existing_settings = $db->count('settings');
    if ($existing_settings === 0) {
        $settings = [
            ['setting_key' => 'site_name', 'setting_value' => 'CampusFind Pro', 'description' => 'Platform Portal Branding Title', 'updated_at' => date('Y-m-d H:i:s')],
            ['setting_key' => 'admin_email', 'setting_value' => 'support@campusfindpro.edu', 'description' => 'System support email contact', 'updated_at' => date('Y-m-d H:i:s')],
            ['setting_key' => 'session_timeout', 'setting_value' => '1800', 'description' => 'Idle session validation timeout in seconds', 'updated_at' => date('Y-m-d H:i:s')],
            ['setting_key' => 'require_verification', 'setting_value' => '0', 'description' => 'Toggle student email OTP code check (1=On, 0=Off)', 'updated_at' => date('Y-m-d H:i:s')]
        ];

        foreach ($settings as $set) {
            $db->insert('settings', $set);
        }
        echo "Successfully seeded " . count($settings) . " system settings.\n";
    } else {
        echo "Settings collection already initialized. Skipping...\n";
    }

    // 3. Seed Superadmin User Account
    echo "Checking for default administrator account...\n";
    $admin_email = 'admin@campusfindpro.edu';
    $existing_admin = $db->findOne('users', ['email' => $admin_email]);

    if (!$existing_admin) {
        $password_hash = password_hash('Admin123!', PASSWORD_BCRYPT);
        $admin_document = [
            'student_id' => 'ADMIN001',
            'google_id' => null,
            'name' => 'System Administrator',
            'email' => $admin_email,
            'password' => $password_hash,
            'phone' => '+1 (555) 019-2834',
            'avatar' => 'default-avatar.png',
            'role' => 'admin',
            'admin_details' => [
                'admin_level' => 'superadmin',
                'department' => 'Student Affairs',
                'last_login' => date('Y-m-d H:i:s')
            ],
            'status' => 'active',
            'is_verified' => 1,
            'verification_code' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $db->insert('users', $admin_document);
        echo "Successfully created Superadmin account:\n";
        echo "  - Email: $admin_email\n";
        echo "  - Password: Admin123!\n";
    } else {
        echo "Administrator profile already exists. Skipping...\n";
    }

    echo "=== Database Seeding Complete! ===\n";

} catch (Exception $e) {
    echo "Seeding Failed: " . $e->getMessage() . "\n";
    exit(1);
}
