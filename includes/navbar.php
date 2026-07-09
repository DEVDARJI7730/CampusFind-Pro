<?php
/**
 * CampusFind Pro - Common Navbar Header
 * Requires session.php and config.php to be loaded beforehand.
 */
$current_page = basename($_SERVER['PHP_SELF']);
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME . ' | Lost & Found System'; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom Style CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body>

<!-- Dynamic Navbar -->
<nav class="navbar navbar-expand-lg glass-navbar sticky-top">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo SITE_URL; ?>/index.php">
            <i class="fa-solid fa-compass-drafting fs-3 text-primary"></i>
            <span>CampusFind <span style="font-weight: 400; font-size: 0.95rem; opacity: 0.85;">Pro</span></span>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars fs-4 text-primary"></i>
        </button>

        <!-- Navbar Links -->
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4 gap-2">
                <li class="nav-item">
                    <a class="nav-link fw-600 <?php echo ($current_page == 'index.php') ? 'active text-primary' : 'text-secondary'; ?>" href="<?php echo SITE_URL; ?>/index.php">
                        Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-600 <?php echo ($current_page == 'search.php' && strpos($_SERVER['REQUEST_URI'], '/lost/') !== false) ? 'active text-primary' : 'text-secondary'; ?>" href="<?php echo SITE_URL; ?>/lost/search.php">
                        Lost Items
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-600 <?php echo ($current_page == 'search.php' && strpos($_SERVER['REQUEST_URI'], '/found/') !== false) ? 'active text-primary' : 'text-secondary'; ?>" href="<?php echo SITE_URL; ?>/found/search.php">
                        Found Items
                    </a>
                </li>
            </ul>

            <!-- Right-Side Buttons & Dashboard Links -->
            <div class="d-flex align-items-center gap-3">
                <!-- Theme Toggler -->
                <button id="theme-toggle" class="btn btn-link p-2 text-decoration-none" title="Toggle Theme" aria-label="Theme Toggle">
                    <i class="fa-solid fa-moon fs-5"></i>
                </button>

                <?php if (isLoggedIn()): ?>
                    <!-- Notification Bell -->
                    <?php
                    // Count unread notifications
                    $unread_count = 0;
                    try {
                        $db = Database::getInstance();
                        $unread_count = $db->count('notifications', [
                            'user_id' => (string)$_SESSION['user_id'],
                            'status' => 'unread'
                        ]);
                    } catch (Exception $e) {}
                    ?>
                    <a href="<?php echo SITE_URL; ?>/notifications/list.php" class="position-relative p-2 text-secondary" title="Notifications">
                        <i class="fa-regular fa-bell fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.65rem; padding: 3px 6px;">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <a class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle text-secondary" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $_SESSION['user_avatar'] ?? 'default-avatar.png'; ?>" alt="Profile" class="rounded-circle border border-primary border-2" style="width: 36px; height: 36px; object-fit: cover;">
                            <span class="d-none d-md-inline fw-600 text-truncate" style="max-width: 100px;">
                                <?php echo sanitize($_SESSION['user_name']); ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end glass-panel p-2 border-0 shadow-lg mt-2" aria-labelledby="userMenu">
                            <li>
                                <div class="dropdown-header px-3 py-2">
                                    <span class="d-block fw-700 text-primary"><?php echo sanitize($_SESSION['user_name']); ?></span>
                                    <span class="d-block text-muted" style="font-size: 0.75rem;"><?php echo sanitize($_SESSION['user_email']); ?></span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider opacity-10"></li>
                            <?php if (isAdmin()): ?>
                                <li>
                                    <a class="dropdown-item rounded px-3 py-2 fw-500" href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                                        <i class="fa-solid fa-chart-line me-2 text-primary"></i> Admin Panel
                                    </a>
                                </li>
                            <?php else: ?>
                                <li>
                                    <a class="dropdown-item rounded px-3 py-2 fw-500" href="<?php echo SITE_URL; ?>/dashboard/index.php">
                                        <i class="fa-solid fa-grip me-2 text-primary"></i> My Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item rounded px-3 py-2 fw-500" href="<?php echo SITE_URL; ?>/dashboard/profile.php">
                                        <i class="fa-regular fa-user me-2 text-primary"></i> Edit Profile
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider opacity-10"></li>
                            <li>
                                <a class="dropdown-item rounded px-3 py-2 fw-500 text-danger" href="<?php echo SITE_URL; ?>/auth/logout.php">
                                    <i class="fa-solid fa-arrow-right-from-bracket me-2"></i> Log Out
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <!-- Authenticated Links -->
                    <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-premium-outline btn-sm px-3 py-2 rounded">Sign In</a>
                    <a href="<?php echo SITE_URL; ?>/auth/register.php" class="btn btn-premium btn-sm px-3 py-2 rounded">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Container for Toast Notifications -->
<div class="toast-container-custom"></div>
