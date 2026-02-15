<?php
/**
 * Common header - Sidebar (collapsible hamburger), top bar
 */
if (!isset($page_title)) $page_title = 'Parking Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Parking Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-width: 260px; --sidebar-width-collapsed: 72px; --header-height: 56px; --primary: #16a34a; --primary-light: #22c55e; --primary-dark: #15803d; }
        body { min-height: 100vh; background: #f0fdf4; transition: margin-left .25s ease; overflow-y: auto; font-family: 'Google Sans Flex', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .sidebar {
            position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh;
            background: #ffffff; z-index: 1020; overflow-x: hidden; overflow-y: auto;
            transition: width .25s ease;
        }
        .sidebar.collapsed { width: var(--sidebar-width-collapsed); }
        .sidebar.collapsed .nav-link .nav-text { display: none; }
        .sidebar.collapsed .nav-link { justify-content: center; padding: .75rem; }
        .sidebar.collapsed .nav-link i { margin: 0 !important; }
        .sidebar.collapsed .brand img { margin: 0 auto; }
        .sidebar.collapsed .brand .brand-fallback .nav-text { display: none; }
        .sidebar.collapsed .brand .brand-fallback .bi { margin: 0; }
        .sidebar.collapsed .nav-item.pt-2 .nav-link { margin-left: 0; margin-right: 0; }
        .sidebar .nav-link {
            color: #111827;
            padding: .75rem 1.25rem;
            margin: 0 0.5rem;
            border-radius: 10px;
            border-left: 3px solid transparent;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link:hover {
            color: var(--primary);
            background: #dcfce7;
        }
        .sidebar .nav-link.active {
            color: var(--primary-dark);
            background: #bbf7d0;
            border-left-color: var(--primary);
        }
        .sidebar .brand { padding: 0.75rem 1.25rem; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; gap: 0.6rem; }
        .sidebar.collapsed .brand { padding: 0.75rem; justify-content: center; }
        .sidebar .brand img.brand-logo-full { height: 34px; width: auto; display: block; }
        .sidebar .brand .brand-icon { display: none; font-size: 1.25rem; color: var(--primary); }
        .sidebar .brand .brand-text { display: inline-block; align-items: center; gap: 0.35rem; font-weight: 600; font-size: 0.95rem; color: #16a34a; }
        .sidebar.collapsed .brand img.brand-logo-full, .sidebar.collapsed .brand .brand-text { display: none; }
        .sidebar.collapsed .brand .brand-icon { display: inline-flex; }
        .sidebar-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,.4); z-index: 1015; display: none;
            transition: opacity .25s ease;
        }
        .sidebar-overlay.show { display: block; }
        .main-content {
            margin-left: var(--sidebar-width); min-height: 100vh; padding-top: calc(var(--header-height) + 24px);
            transition: margin-left .25s ease;
        }
        .main-content.sidebar-collapsed { margin-left: var(--sidebar-width-collapsed); }
        .top-bar {
            position: fixed; top: 0; left: var(--sidebar-width); right: 0; height: var(--header-height);
            background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.06); z-index: 1000;
            display: flex; align-items: center; justify-content: space-between; padding: 0 1rem 0 0.5rem;
            transition: left .25s ease;
        }
        .top-bar.sidebar-collapsed { left: var(--sidebar-width-collapsed); }
        .btn-hamburger {
            height: 40px; border: none; background: transparent;
            color: #374151; border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-size: 1.35rem; cursor: pointer; gap: 0.5rem; padding: 0 0.75rem;
        }
        .btn-hamburger:hover { background: #f3f4f6; color: var(--primary); }
        .btn-hamburger-text { font-size: 0.95rem; font-weight: 600; display: none; }
        @media (max-width: 767px) {
            .btn-hamburger-text { display: inline-block; }
        }
        .dashboard-page { max-width: none; width: 100%; margin-left: 0; margin-right: 0; }
        /* Make Bootstrap containers span the full available width so pages don't appear centered */
        .container {
            max-width: 100% !important;
            width: 100% !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
        /* Ensure pages use available horizontal space instead of centering boxed content */
        /* Targets common centered wrappers used across pages (keeps sidebar/top-bar intact) */
        .main-content [style*="margin: 0 auto"],
        .main-content [style*="margin:0 auto"],
        .main-content .book-slot-page,
        .main-content .book-slot-main,
        .main-content .book-slot-card-wrap,
        .main-content .stats-wrap,
        .main-content .features-hero-wrap,
        .main-content .everything-wrap,
        .main-content .landing-footer .footer-inner,
        .main-content .login-brand,
        .main-content .centered {
            max-width: 100% !important;
            width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        .widget-card { border-radius: .75rem; border: none; box-shadow: 0 1px 3px rgba(0,0,0,.06); transition: transform .2s; }
        .widget-card:hover { transform: translateY(-2px); }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-light); border-color: var(--primary-light); }
        .slot-available { background: #dcfce7; color: #166534; }
        .slot-occupied { background: #fecaca; color: #991b1b; }
        .status-badge { padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; border: none; display: inline-block; text-align: center; }
        .status-badge.status-pending { background: #ffedd5; color: #c2410c; }
        .status-badge.status-parked { background: #166534; color: #fff; }
        .status-badge.status-completed { background: #dcfce7; color: #4d7c5c; }
        .status-badge.status-cancelled { background: #fecaca; color: #991b1b; }
        /* Profile dropdown styles */
        .profile-pill { display: inline-flex; align-items: center; gap: 0.5rem; background: #ecfdf5; border: 1px solid #bbf7d0; padding: 0.35rem 0.9rem; border-radius: 999px; min-height:36px; }
        .profile-pill .profile-avatar-sm { width:34px; height:34px; font-size:0.85rem; }
        .profile-dropdown { min-width: 260px; }
        .profile-card { background:#fff; border-radius:12px; box-shadow: 0 8px 28px rgba(2,6,23,0.08); }
        .profile-avatar-lg { width:48px; height:48px; font-size:1rem; }
        .sidebar .nav-link .badge { align-self: center; }
        /* Improve alert visibility site-wide */
        .alert { border-radius: 10px; padding: .75rem 1rem; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
        .alert-success { background: #dcfce7; color: #064e3b; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #7f1d1d; border: 1px solid #fecaca; }
        .alert-warning { background: #fff7ed; color: #92400e; border: 1px solid #ffedd5; }
        @media (min-width: 769px) {
            .sidebar-overlay { display: none !important; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width); }
            .sidebar.mobile-open { transform: translateX(0); width: var(--sidebar-width); }
            .main-content { margin-left: 0; }
            .main-content.sidebar-collapsed { margin-left: 0; }
            .top-bar { left: 0; }
            .top-bar.sidebar-collapsed { left: 0; }
        }
    </style>
</head>
<body>
    <!-- <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div> -->
    <nav class="sidebar" id="sidebar" aria-label="Main navigation">
        <a href="<?= BASE_URL ?>/index.php" class="brand">
            <?php
                // Prefer a custom PNG logo if uploaded; otherwise fall back to the bundled SVG
                $customLogoPath = '/assets/parkit.png';                $svgLogoPath = '/assets/parkit.png';
                $logoSrc = file_exists(dirname(__DIR__) . $customLogoPath) ? $customLogoPath : $svgLogoPath;
            ?>
            <img class="brand-logo-full" src="<?= BASE_URL . $logoSrc ?>" alt="Parking Management System" onerror="this.style.display='none';">
            <span class="brand-icon" aria-hidden="true"><i class="bi bi-car-front"></i></span>
        </a>
        <?php
            $adminPendingCount = 0;
                if (isLoggedIn()) {
                    try {
                        $pdo = getDB();
                        if (isAdmin()) {
                            // admin: pending bookings (kept for admin badge)
                            $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
                            $adminPendingCount = (int) $stmt->fetchColumn();
                        }
                    } catch (Exception $e) {
                        // ignore
                    }
                }
        ?>
        <ul class="nav flex-column py-2">
            <?php if (!isLoggedIn()): ?>
                <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>/auth/login.php" title="Login"><i class="bi bi-box-arrow-in-right me-2"></i><span class="nav-text">Login</span></a></li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php" title="Dashboard">
                        <i class="bi bi-bar-chart me-2"></i>
                        <span class="nav-text">Dashboard</span>
                        <?php if ($adminPendingCount > 0): ?>
                            <span class="badge bg-danger ms-auto" style="margin-left:8px; font-size:0.75rem;"><?= $adminPendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <!-- Notifications removed from sidebar -->
                <?php if (!isAdmin()): ?>
                    <li class="nav-item"><a class="nav-link <?= ($current_page ?? '') === 'register-car' ? 'active' : '' ?>" href="<?= BASE_URL ?>/user/register-car.php" title="My Vehicles"><i class="bi bi-car-front me-2"></i><span class="nav-text">My Vehicles</span></a></li>
                    <li class="nav-item"><a class="nav-link <?= ($current_page ?? '') === 'book' ? 'active' : '' ?>" href="<?= BASE_URL ?>/user/book.php" title="Book Slot"><i class="bi bi-p-circle me-2"></i><span class="nav-text">Book Slot</span></a></li>
                    <li class="nav-item"><a class="nav-link <?= ($current_page ?? '') === 'booking-history' ? 'active' : '' ?>" href="<?= BASE_URL ?>/user/booking-history.php" title="My Bookings"><i class="bi bi-journal-text me-2"></i><span class="nav-text">My Bookings</span></a></li>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <li class="nav-item"><a class="nav-link <?= ($current_page ?? '') === 'admin-slots' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/slots.php" title="Manage Slots"><i class="bi bi-grid-3x3 me-2"></i><span class="nav-text">Manage Slots</span></a></li>
                    <li class="nav-item"><a class="nav-link <?= ($current_page ?? '') === 'admin-monitor' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/monitor.php" title="Monitor Vehicles"><i class="bi bi-camera-video me-2"></i><span class="nav-text">Monitor Vehicles</span></a></li>
                    <li class="nav-item"><a class="nav-link <?= ($current_page ?? '') === 'admin-history' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/parking-history.php" title="Parking History"><i class="bi bi-clock-history me-2"></i><span class="nav-text">Parking History</span></a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link <?= ($current_page ?? '') === 'map' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parking-map.php" title="Parking Map"><i class="bi bi-map me-2"></i><span class="nav-text">Parking Map</span></a></li>
                <!-- Logout moved to profile dropdown in top bar -->
            <?php endif; ?>
        </ul>
    </nav>
    <div class="top-bar" id="topBar">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn-hamburger" id="btnHamburger" aria-label="Toggle sidebar">
                <i class="bi bi-list"></i>
                <span class="btn-hamburger-text">Menu</span>
            </button>
            <h5 class="mb-0 text-dark fw-bold"><?= htmlspecialchars($page_title) ?></h5>
        </div>
        <?php if (isLoggedIn()): ?>
            <?php
                // Prepare profile values
                $profileName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? '');
                $profileEmail = '';
                if (isset($_SESSION['user_id'])) {
                    try {
                        $pdo = getDB();
                        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                        $stmt->execute([$_SESSION['user_id']]);
                        $r = $stmt->fetch();
                        if ($r && !empty($r['email'])) $profileEmail = htmlspecialchars($r['email']);
                    } catch (Exception $e) {
                        // ignore DB errors in header
                    }
                }
                $profileRoleLabel = isAdmin() ? 'Admin' : 'Driver';
                // initials for avatar
                $initials = trim(array_reduce(explode(' ', $profileName), function($carry, $part){ return $carry . ($part? strtoupper($part[0]) : ''); }, '')) ?: 'U';
            ?>

            <div class="dropdown">
                <button class="btn p-0 d-flex align-items-center profile-toggle" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="profile-pill d-none d-sm-inline-flex align-items-center gap-2">
                        <div class="profile-avatar-sm bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;font-weight:700;"><?= htmlspecialchars(substr($initials,0,2)) ?></div>
                        <div class="text-start">
                            <div style="font-weight:700; font-size:0.95rem; line-height:1; color:#064e3b"><?= $profileName ?></div>
                            <div style="font-size:0.8rem; color:#166534; opacity:0.9;"><?= $profileRoleLabel ?></div>
                        </div>
                        <i class="bi bi-chevron-down ms-2" style="color:#16a34a;"></i>
                    </div>
                    <i class="bi bi-person-circle d-inline d-sm-none" style="font-size:1.35rem;color:#374151;"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end profile-dropdown p-0" aria-labelledby="profileMenu">
                    <div class="profile-card p-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="profile-avatar-lg bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:48px;height:48px;font-weight:700;font-size:1rem;"><?= htmlspecialchars(substr($initials,0,2)) ?></div>
                            <div class="flex-grow-1">
                                <div style="font-weight:700; font-size:1rem; color:#111827"><?= $profileName ?></div>
                                <div style="font-size:0.9rem; color:#6b7280"><?= $profileEmail ?: htmlspecialchars($_SESSION['username'] ?? '') ?></div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="list-group list-group-flush">
                            <?php if (isAdmin()): ?>
                                <a class="list-group-item list-group-item-action d-flex align-items-center" href="<?= BASE_URL ?>/admin/settings.php"><i class="bi bi-gear me-2"></i> Admin Settings</a>
                            <?php else: ?>
                                <a class="list-group-item list-group-item-action d-flex align-items-center" href="<?= BASE_URL ?>/user/profile.php"><i class="bi bi-gear me-2"></i> Account Settings</a>
                            <?php endif; ?>
                            <a class="list-group-item list-group-item-action d-flex align-items-center text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <main class="main-content p-4" id="mainContent">
    <!-- Global Alert Modal -->
    <div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0" style="border-radius: 14px;">
                <div class="modal-body p-0" style="min-height: auto;">
                    <div class="p-4" id="alertContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Global alert modal system
    function showAlert(message, type = 'success') {
        const contentEl = document.getElementById('alertContent');
        const icons = {
            'success': 'bi-check-circle-fill',
            'danger': 'bi-exclamation-triangle-fill',
            'warning': 'bi-exclamation-circle-fill',
            'info': 'bi-info-circle-fill'
        };
        
        const colors = {
            'success': { bg: '#dcfce7', border: '#bbf7d0', text: '#064e3b', icon: '#16a34a' },
            'danger': { bg: '#fee2e2', border: '#fecaca', text: '#7f1d1d', icon: '#dc2626' },
            'warning': { bg: '#fff7ed', border: '#ffedd5', text: '#92400e', icon: '#f59e0b' },
            'info': { bg: '#dbeafe', border: '#bfdbfe', text: '#0c4a6e', icon: '#2563eb' }
        };
        
        const color = colors[type] || colors['info'];
        const icon = icons[type] || icons['info'];
        
        contentEl.innerHTML = `
            <div style="text-align: center;">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: ${color.bg}; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; border: 2px solid ${color.border};">
                    <i class="bi ${icon}" style="font-size: 1.75rem; color: ${color.icon};"></i>
                </div>
                <div style="color: ${color.text}; font-size: 0.95rem; line-height: 1.6; margin: 0.75rem 0;">${message}</div>
                <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="margin-top: 1rem; background: ${color.bg}; color: ${color.icon}; border: 1px solid ${color.border}; border-radius: 8px; padding: 0.5rem 1.25rem; font-weight: 600;">OK</button>
            </div>
        `;
        
        const modal = new bootstrap.Modal(document.getElementById('alertModal'));
        modal.show();
    }
    </script>
    <!-- Notifications feature removed; global container and scripts deleted -->