<?php
/**
 * CampusFind Pro - Google OAuth 2.0 Callback Handler
 * Handles access token exchange, profile retrieval, registration, and login.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/config/database.php';

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$saved_state = $_SESSION['oauth_state'] ?? '';

// Clear OAuth state from session immediately
unset($_SESSION['oauth_state']);

// 1. Verify CSRF OAuth State
if (empty($code) || empty($state) || empty($saved_state) || !hash_equals($saved_state, $state)) {
    $_SESSION['success_msg'] = 'Google Authentication failed: Security mismatch.';
    $_SESSION['success_msg_class'] = 'danger';
    redirect('auth/login.php');
}

try {
    // 2. Exchange Authorization Code for Access Token
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_fields = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
        'code' => $code
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Keep production security active
    
    $token_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception("Failed to exchange code. Response: " . $token_response);
    }

    $token_data = json_decode($token_response, true);
    $access_token = $token_data['access_token'] ?? '';

    if (empty($access_token)) {
        throw new Exception("Access token missing in Google response.");
    }

    // 3. Fetch User Info from Google Profile endpoint
    $userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $userinfo_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $profile_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception("Failed to fetch user profile. Response: " . $profile_response);
    }

    $profile = json_decode($profile_response, true);
    $google_id = $profile['id'] ?? '';
    $email = filter_var($profile['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $name = trim($profile['name'] ?? '');
    $picture_url = $profile['picture'] ?? '';

    if (empty($google_id) || !$email) {
        throw new Exception("Google profile missing essential fields.");
    }

    // 4. Database User Lookup / Match
    $db = Database::getInstance()->getConnection();
    
    // Check if user already linked Google account
    $stmt = $db->prepare("SELECT * FROM users WHERE google_id = :gid LIMIT 1");
    $stmt->execute([':gid' => $google_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // If not found by google_id, check if email matches manual registration
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Link Google account to existing user
            $link_stmt = $db->prepare("UPDATE users SET google_id = :gid WHERE id = :uid");
            $link_stmt->execute([':gid' => $google_id, ':uid' => $user['id']]);
            
            // Refresh local user data
            $user['google_id'] = $google_id;
            logActivity($user['id'], 'OAUTH_LINK', 'Linked Google OAuth credentials to existing profile.');
        } else {
            // Register new student automatically via Google OAuth details
            // Save/Download Google Profile Image locally
            $local_avatar = 'default-avatar.png';
            if (!empty($picture_url)) {
                $avatar_data = @file_get_contents($picture_url);
                if ($avatar_data !== false) {
                    $local_avatar = 'avatar-google-' . time() . '-' . rand(1000, 9999) . '.jpg';
                    if (!is_dir(UPLOAD_PATH)) {
                        mkdir(UPLOAD_PATH, 0777, true);
                    }
                    file_put_contents(UPLOAD_PATH . '/' . $local_avatar, $avatar_data);
                }
            }

            // Generate random secure password (never used directly, but satisfies constraint)
            $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            
            // Generate dynamic unique student ID
            $random_student_id = 'GGL-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            // Insert new user
            $insert_stmt = $db->prepare("
                INSERT INTO users (student_id, google_id, name, email, password, avatar, role, is_verified, status) 
                VALUES (:sid, :gid, :name, :email, :pass, :avatar, 'student', 1, 'active')
            ");
            $insert_stmt->execute([
                ':sid' => $random_student_id,
                ':gid' => $google_id,
                ':name' => $name,
                ':email' => $email,
                ':pass' => $random_password,
                ':avatar' => $local_avatar,
            ]);

            $new_user_id = $db->lastInsertId();
            
            // Fetch newly created user
            $stmt = $db->prepare("SELECT * FROM users WHERE id = :uid LIMIT 1");
            $stmt->execute([':uid' => $new_user_id]);
            $user = $stmt->fetch();

            logActivity($new_user_id, 'OAUTH_REGISTER', 'Created student profile automatically via Google OAuth.');
            addNotification($new_user_id, 'Welcome to CampusFind Pro', "Hello $name, your profile was successfully created using your Google account!");
        }
    }

    // 5. Establish Session & Log In User
    if ($user['status'] === 'suspended') {
        $_SESSION['success_msg'] = 'Your account has been suspended by administration.';
        $_SESSION['success_msg_class'] = 'danger';
        redirect('auth/login.php');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'];
    $_SESSION['last_activity'] = time();

    // Log Activity
    logActivity($user['id'], 'LOGIN_OAUTH', 'User successfully logged in via Google OAuth.');

    // Redirect based on role
    if ($user['role'] === 'admin') {
        // Fetch Admin level
        $_SESSION['admin_level'] = 'moderator';
        $admin_info_stmt = $db->prepare("SELECT admin_level FROM admins WHERE user_id = :uid LIMIT 1");
        $admin_info_stmt->execute([':uid' => $user['id']]);
        $admin_info = $admin_info_stmt->fetch();
        if ($admin_info) {
            $_SESSION['admin_level'] = $admin_info['admin_level'];
        }
        redirect('admin/dashboard.php');
    } else {
        redirect('dashboard/index.php');
    }

} catch (Exception $e) {
    error_log("Google OAuth Callback Failure: " . $e->getMessage());
    $_SESSION['success_msg'] = 'Google Authentication failed. Please try again.';
    $_SESSION['success_msg_class'] = 'danger';
    redirect('auth/login.php');
}
