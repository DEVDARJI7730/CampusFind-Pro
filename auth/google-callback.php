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
    $db = Database::getInstance();
    
    // Check if user already linked Google account
    $user = $db->findOne('users', ['google_id' => $google_id]);

    if (!$user) {
        // If not found by google_id, check if email matches manual registration
        $user = $db->findOne('users', ['email' => $email]);

        if ($user) {
            // Link Google account to existing user
            $db->update('users', ['_id' => $user['_id']], ['google_id' => $google_id]);
            
            // Refresh local user data
            $user['google_id'] = $google_id;
            $userIdStr = (string)$user['_id'];
            logActivity($userIdStr, 'OAUTH_LINK', 'Linked Google OAuth credentials to existing profile.');
        } else {
            // Register new student automatically via Google OAuth details
            // Use remote Google Profile image URL directly, fall back to default-avatar.png
            $local_avatar = !empty($picture_url) ? $picture_url : 'default-avatar.png';

            // Generate random secure password (never used directly, but satisfies constraint)
            $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            
            // Generate dynamic unique student ID
            $random_student_id = 'GGL-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            // Insert new user
            $user_document = [
                'student_id' => $random_student_id,
                'google_id' => $google_id,
                'name' => $name,
                'email' => $email,
                'password' => $random_password,
                'avatar' => $local_avatar,
                'role' => 'student',
                'status' => 'active',
                'is_verified' => 1,
                'verification_code' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $new_user_id = $db->insert('users', $user_document);
            $user = $db->findOne('users', ['_id' => new MongoDB\BSON\ObjectId($new_user_id)]);

            logActivity($new_user_id, 'OAUTH_REGISTER', 'Created student profile automatically via Google OAuth.');
            addNotification($new_user_id, 'Welcome to CampusFind Pro', "Hello $name, your profile was successfully created using your Google account!");
        }
    }

    // Auto-update or repair user avatar to live remote URL if they previously registered with a local/broken file
    if ($user && !empty($picture_url)) {
        if (empty($user['avatar']) || $user['avatar'] === 'default-avatar.png' || (strpos($user['avatar'], 'avatar-google-') === 0 && strpos($user['avatar'], 'http') === false)) {
            $db->update('users', ['_id' => $user['_id']], ['avatar' => $picture_url]);
            $user['avatar'] = $picture_url;
        }
    }

    // 5. Establish Session & Log In User
    if ($user['status'] === 'suspended') {
        $_SESSION['success_msg'] = 'Your account has been suspended by administration.';
        $_SESSION['success_msg_class'] = 'danger';
        redirect('auth/login.php');
    }

    $userIdStr = (string)$user['_id'];
    $_SESSION['user_id'] = $userIdStr;
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'];
    $_SESSION['last_activity'] = time();

    // Log Activity
    logActivity($userIdStr, 'LOGIN_OAUTH', 'User successfully logged in via Google OAuth.');

    // Redirect based on role
    if ($user['role'] === 'admin') {
        // Fetch Admin level
        $admin_details = $user['admin_details'] ?? [];
        $_SESSION['admin_level'] = $admin_details['admin_level'] ?? 'moderator';
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
