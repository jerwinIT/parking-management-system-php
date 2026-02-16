<?php
/**
 * Landing Page - Layout: hero, stats, features, footer. Logo unchanged (user-provided).
 */
if (!defined('PARKING_ACCESS')) define('PARKING_ACCESS', true);
require_once __DIR__ . '/config/init.php';

$available = $occupied = $total_slots = $maintenance = 0;
try {
    $pdo = getDB();
    $settings = $pdo->query('SELECT total_slots FROM parking_settings LIMIT 1')->fetch();
    $total_slots = (int) ($settings['total_slots'] ?? 0);
    $available = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'available'")->fetchColumn();
    $occupied = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'occupied'")->fetchColumn();
    $maintenance = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'maintenance'")->fetchColumn();
    if ($total_slots && ($available + $occupied + $maintenance) != $total_slots) {
        $available = max(0, $total_slots - $occupied - $maintenance);
    }
} catch (Exception $e) {
    $total_slots = 20;
    $available = 18;
    $occupied = 2;
    $maintenance = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Management System — Fast, Simple, Secure</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #16a34a;
            --primary-light: #22c55e;
            --primary-dark: #15803d;
            --danger: #dc2626;
            --blue: #2563eb;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Google Sans Flex', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            color: #1e293b;
            background: linear-gradient(
                180deg,
                #c4f7d4 0%,
                #dcfce7 25%,
                #eefdf4 55%,
                #f9fdfb 80%,
                #ffffff 100%
            );
            overflow-x: hidden;
        }
        .landing-nav {
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
        }
        .landing-nav .brand { display: inline-flex; align-items: center; text-decoration: none; }
        .landing-nav .brand img { height: 44px; width: auto; display: block; }
        .landing-nav .brand-fallback { display: none; align-items: center; gap: 0.5rem; font-weight: 700; font-size: 1.1rem; color: #166534; }
        .nav-actions { display: flex; align-items: center; gap: 1rem; }
        .nav-actions .link-login { color: var(--muted); text-decoration: none; font-weight: 500; }
        .nav-actions .link-login:hover { color: var(--primary); }
        .nav-actions .btn-signup {
            background: var(--primary);
            color: #fff !important;
            padding: 0.5rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: transform .15s, box-shadow .15s;
        }
        .nav-actions .btn-signup:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(22,163,74,.4); }
        .hero-wrap {
            max-width: 1200px;
            margin: 2.5rem auto 3rem;
            padding: 3.5rem 1.5rem 4.5rem;
        }
        .hero-content h1 {
            font-size: clamp(2.8rem, 5vw, 3.6rem);
            font-weight: 700;
            color: #1e293b;
            line-height: 1.15;
            margin-bottom: 1.25rem;
        }
        .hero-content .lead {
            font-size: 1.15rem;
            color: var(--muted);
            line-height: 1.8;
            margin-bottom: 0.75rem;
        }
        .hero-content .tagline {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1.75rem;
        }
        .hero-cta { display: flex; flex-wrap: wrap; gap: 1.1rem; }
        .btn-book {
            background: var(--primary);
            color: #fff !important;
            padding: 0.9rem 1.9rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform .15s, box-shadow .15s;
        }
        .btn-book:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(22,163,74,.35); }
        .btn-create {
            background: #fff;
            color: var(--primary);
            padding: 0.9rem 1.9rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 2px solid var(--primary);
            transition: background .15s, color .15s;
        }
        .btn-create:hover { background: var(--primary); color: #fff; }
        .hero-visual {
            background: linear-gradient(145deg, #dcfce7 0%, #f0fdf4 100%);
            border-radius: 18px;
            min-height: 430px;
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }
        .hero-visual i {
            font-size: 4rem;
            opacity: .6;
        }
        .stats-section { 
            background: linear-gradient(180deg, #f8fafc 0%, #eefdf4 100%); 
            padding: 3.5rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
        }
        .stats-wrap { 
            max-width: 1100px; 
            margin: 0 auto; 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); 
            gap: 2rem; 
        }
        .stat-card {
            background: linear-gradient(135deg, #fff 0%, #f9fafb 100%);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
            border-left: 5px solid;
            text-align: left;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: flex-start;
            gap: 1.25rem;
            border: 1px solid #e5e7eb;
        }
        .stat-card:hover {
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12), 0 2px 4px rgba(0, 0, 0, 0.08);
            transform: translateY(-4px);
            border-color: #d1d5db;
        }
        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            flex-shrink: 0;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .stat-card.available { border-left-color: var(--primary); }
        .stat-card.available .stat-icon {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: var(--primary);
        }
        .stat-card.available .stat-num { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card.booked { border-left-color: var(--danger); }
        .stat-card.booked .stat-icon {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: var(--danger);
        }
        .stat-card.booked .stat-num { 
            background: linear-gradient(135deg, var(--danger) 0%, #ef4444 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card.total { border-left-color: var(--blue); }
        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: var(--blue);
        }
        .stat-card.total .stat-num { 
            background: linear-gradient(135deg, var(--blue) 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-content {
            flex: 1;
        }
        .stat-card .stat-label { 
            font-size: 0.75rem; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            color: #9ca3af; 
            margin-bottom: 0.5rem; 
        }
        .stat-card .stat-num { 
            font-size: 2.25rem; 
            font-weight: 800; 
            line-height: 1.1;
        }
        .stat-card .stat-sub { 
            font-size: 0.875rem; 
            color: #6b7280; 
            margin-top: 0.5rem; 
        }
        .section-title { text-align: center; font-size: 1.75rem; font-weight: 700; color: #1e293b; margin-bottom: 2.5rem; }
        .features-hero-wrap { max-width: 1100px; margin: 0 auto; padding: 3rem 1.5rem; }
        .feature-block { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; margin-bottom: 3rem; }
        .feature-block.reverse { direction: rtl; }
        .feature-block.reverse > * { direction: ltr; }
        .feature-block h3 { font-size: 1.35rem; font-weight: 700; color: #1e293b; margin-bottom: 0.75rem; }
        .feature-block p { color: var(--muted); line-height: 1.65; margin-bottom: 1rem; }
        .feature-block ul { list-style: none; padding: 0; margin: 0; }
        .feature-block ul li { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; color: #334155; }
        .feature-block ul li i { color: var(--primary); }
        .feature-mock { background: linear-gradient(145deg, #dcfce7 0%, #f0fdf4 100%); border-radius: 12px; min-height: 220px; display: flex; align-items: center; justify-content: center; }
        .feature-mock i { font-size: 3rem; color: var(--primary); opacity: .7; }
        .everything-wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
        .everything-wrap .section-title { margin-bottom: 2rem; }
        .need-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
        .need-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 2px 14px rgba(0,0,0,.06);
            border: 1px solid #f1f5f9;
            transition: transform .2s, box-shadow .2s;
        }
        .need-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .need-card .icon-wrap { width: 48px; height: 48px; border-radius: 50%; background: #dcfce7; color: var(--primary); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 1.25rem; }
        .need-card h4 { font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 0.35rem; }
        .need-card p { font-size: 0.875rem; color: var(--muted); margin: 0; line-height: 1.5; }
        .landing-footer {
            background: var(--primary-dark);
            color: #fff;
            padding: 2.5rem 1.5rem 2rem;
        }
        .landing-footer .footer-inner { max-width: 1100px; margin: 0 auto; }
        .landing-footer .footer-top { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 2rem; margin-bottom: 2rem; }
        .landing-footer .footer-brand img { height: 40px; width: auto; margin-bottom: 0.5rem; }
        .landing-footer .footer-brand .tagline { font-size: 0.9rem; opacity: .9; }
        .landing-footer .footer-links { display: flex; gap: 3rem; flex-wrap: wrap; }
        .landing-footer .footer-links div h5 { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; opacity: .9; }
        .landing-footer .footer-links a { color: #fff; text-decoration: none; font-size: 0.9rem; display: block; margin-bottom: 0.4rem; opacity: .85; }
        .landing-footer .footer-links a:hover { opacity: 1; }
        .landing-footer .footer-bottom { text-align: center; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,.2); font-size: 0.875rem; opacity: .85; }
        @media (max-width: 992px) {
            .feature-block { grid-template-columns: 1fr; }
            .feature-block.reverse { direction: ltr; }
            .need-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-wrap { grid-template-columns: 1fr; }
            .stat-card {
                flex-direction: column;
            }
            .need-grid { grid-template-columns: 1fr; }
            .hero-wrap .row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <nav class="landing-nav">
        <a href="<?= BASE_URL ?>/index.php" class="brand">
            <img src="<?= BASE_URL ?>/assets/parkit.png" alt="Parking Management System" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
            <span class="brand-fallback"><i class="bi bi-p-square-fill"></i> Parking Management System</span>
        </a>
        <div class="nav-actions">
            <a href="<?= BASE_URL ?>/auth/login.php" class="link-login">Login</a>
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn-signup">Sign Up</a>
        </div>
    </nav>

    <section class="hero-wrap mt-4">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6 hero-content">
                    <h1>Welcome to ParkIt</h1>
                    <p class="lead">Track parking slots, manage vehicles, book in seconds, and handle payments in one place. Drivers get a simple dashboard; admins have full control.</p>
                    <p class="tagline">Fast. Simple. Secure parking management.</p>
                    <div class="hero-cta">
                        <a href="<?= BASE_URL ?>/auth/login.php?role=user" class="btn-book">Book now <i class="bi bi-arrow-right"></i></a>
                        <a href="<?= BASE_URL ?>/auth/register.php" class="btn-create">Create account</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-visual">
                        <i class="bi bi-p-square"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="stats-wrap">
            <div class="stat-card available">
                <div class="stat-icon"><i class="bi bi-p-circle-fill"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Available Slots</div>
                    <div class="stat-num"><?= $available ?></div>
                    <div class="stat-sub">Out of <?= $total_slots ?></div>
                </div>
            </div>
            <div class="stat-card booked">
                <div class="stat-icon"><i class="bi bi-p-square-fill"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Occupied Slots</div>
                    <div class="stat-num"><?= $occupied ?></div>
                    <div class="stat-sub">Currently parked</div>
                </div>
            </div>
            <div class="stat-card total">
                <div class="stat-icon"><i class="bi bi-grid-3x2"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Capacity</div>
                    <div class="stat-num"><?= $total_slots ?></div>
                    <div class="stat-sub">Parking lot size</div>
                </div>
            </div>
        </div>
    </section>

    <section class="features-hero-wrap" id="features">
        <h2 class="section-title">Powerful Features for Everyone</h2>
        <div class="feature-block">
            <div class="feature-mock"><i class="bi bi-speedometer2"></i></div>
            <div>
                <h3>Real-Time Dashboard & Monitoring</h3>
                <p>Get instant visibility into your parking lot with our advanced dashboard. Track available slots, monitor bookings, and manage payments all in one place.</p>
                <ul>
                    <li><i class="bi bi-check2-circle-fill"></i> Real-time slot availability tracking</li>
                    <li><i class="bi bi-check2-circle-fill"></i> Advanced analytics and reporting</li>
                    <li><i class="bi bi-check2-circle-fill"></i> Comprehensive activity monitoring</li>
                </ul>
            </div>
        </div>
        <div class="feature-block reverse">
            <div class="feature-mock"><i class="bi bi-phone"></i></div>
            <div>
                <h3>Mobile-First Booking Experience</h3>
                <p>Reserve parking spots on the go with our intuitive system. Quick, simple, and secure—book a parking slot in just seconds.</p>
                <ul>
                    <li><i class="bi bi-check2-circle-fill"></i> One-tap parking slot reservation</li>
                    <li><i class="bi bi-check2-circle-fill"></i> Instant confirmation and notifications</li>
                    <li><i class="bi bi-check2-circle-fill"></i> Secure integrated payments</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="everything-wrap">
        <h2 class="section-title">Everything You Need</h2>
        <div class="need-grid">
            <div class="need-card">
                <div class="icon-wrap"><i class="bi bi-p-circle"></i></div>
                <h4>Real-time availability</h4>
                <p>See available parking slots instantly</p>
            </div>
            <div class="need-card">
                <div class="icon-wrap"><i class="bi bi-clock"></i></div>
                <h4>Quick bookings</h4>
                <p>Reserve a slot in seconds with instant confirmation</p>
            </div>
            <div class="need-card">
                <div class="icon-wrap"><i class="bi bi-shield-check"></i></div>
                <h4>Admin controls</h4>
                <p>Full role-based access and monitoring capabilities</p>
            </div>
            <div class="need-card">
                <div class="icon-wrap"><i class="bi bi-credit-card"></i></div>
                <h4>Easy payments</h4>
                <p>Secure payment processing integrated</p>
            </div>
            <div class="need-card">
                <div class="icon-wrap"><i class="bi bi-geo-alt"></i></div>
                <h4>Visual parking map</h4>
                <p>See the layout and status of all parking spaces</p>
            </div>
            <div class="need-card">
                <div class="icon-wrap"><i class="bi bi-bar-chart"></i></div>
                <h4>Monitoring dashboard</h4>
                <p>Track all vehicles and parking activity in real-time</p>
            </div>
            <div class="need-card">
                <div class="icon-wrap"><i class="bi bi-car-front"></i></div>
                <h4>Car registration</h4>
                <p>Store vehicle details like plate number and model</p>
            </div>
            <div class="need-card">
                <div class="icon-wrap"><i class="bi bi-clock-history"></i></div>
                <h4>Booking history</h4>
                <p>View past bookings with detailed status tracking</p>
            </div>
        </div>
    </section>

    <footer class="landing-footer">
        <div class="footer-inner">
            <div class="footer-top">
                <div class="footer-brand">
                    <img src="<?= BASE_URL ?>/assets/parkit.svg" alt="Parking Management System" onerror="this.style.display='none';">
                    <p class="tagline mb-0">Modern parking management system</p>
                </div>
                <div class="footer-links">
                    <div>
                        <h5>Product</h5>
                        <a href="#features">Features</a>
                        <a href="<?= BASE_URL ?>/index.php">Pricing</a>
                        <a href="#features">Security</a>
                    </div>
                    <div>
                        <h5>Company</h5>
                        <a href="#">About</a>
                        <a href="#">Contact</a>
                        <a href="#">Support</a>
                    </div>
                    <div>
                        <h5>Legal</h5>
                        <a href="#">Privacy</a>
                        <a href="#">Terms</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">© <?= date('Y') ?> Parking Management System. All rights reserved.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
