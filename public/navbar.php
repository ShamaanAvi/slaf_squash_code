<?php
require_once __DIR__ . "/../config/functions.php";
safeSessionStart();

$current_page = $_SERVER['SCRIPT_NAME']; 
$current_file = basename($current_page);

$is_homepage = ($current_file == 'home.php');
$is_edit_page = ($current_file == 'edit.php');
$hide_nav_links = ($is_homepage || $is_edit_page);

$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? 'player';
$username = $_SESSION['username'] ?? 'Guest';
?>

<style>
    /* 1. Root Stability: Prevents horizontal shifts if scrollbar appears */
    html { overflow-y: scroll; } 

    .navbar {
        min-height: 64px;
        z-index: 1050;
        /* Ensure the navbar content doesn't shift */
        padding-right: calc(var(--bs-gutter-x, 1.5rem) * .5); 
    }

    /* 2. Dropdown Logic */
    .navbar .dropdown-menu {
        display: block !important; 
        visibility: hidden;
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.25s ease-out, transform 0.25s ease-out;
        pointer-events: none;
    }
    .navbar .dropdown-menu.show {
        visibility: visible;
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }

    /* 3. The User Pill - Locked Dimensions */
    .user-pill-btn {
        view-transition-name: user-menu-pill;
        background: rgba(255, 255, 255, 0.1) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
        
        /* Fixed Sizing to prevent text length from changing width */
        min-width: 140px; 
        height: 38px;
        
        padding: 0 16px;
        border-radius: 50px;
        text-decoration: none !important;
        display: inline-flex;
        align-items: center;
        justify-content: center; /* Keeps text centered regardless of name length */
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .user-pill-btn:hover, .user-pill-btn.show {
        background: rgba(255, 255, 255, 0.2) !important;
        border-color: rgba(255, 255, 255, 0.4) !important;
        color: #fff !important;
    }

    /* Ensure caret doesn't cause layout shift */
    .user-pill-btn::after {
        margin-left: 8px;
        display: inline-block;
        vertical-align: middle;
    }

    /* 4. Layout Fix: Ensures ms-auto behaves the same even if me-auto is empty */
    #mainNavbar {
        flex-basis: auto;
        flex-grow: 1;
        align-items: center;
    }

    @media (min-width: 992px) {
        #mainNavbar {
            display: flex !important;
        }
    }

    @media (max-width: 991.98px) {
        .navbar {
            height: auto;
            padding: 10px 12px;
        }

        .navbar .container-fluid {
            align-items: flex-start;
            gap: 8px;
        }

        .navbar-brand {
            min-width: 0 !important;
            max-width: calc(100vw - 92px);
            white-space: normal;
            line-height: 1.2;
            font-size: 0.98rem;
        }

        #mainNavbar {
            width: auto;
        }

        #mainNavbar.show,
        #mainNavbar.collapsing {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1045;
            width: 100%;
            max-height: calc(100vh - 64px);
            overflow-y: auto;
            padding: 12px;
            background: rgba(33, 37, 41, 0.98);
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.35);
        }

        #mainNavbar.collapsing {
            transition: none;
        }

        .navbar-nav {
            padding-top: 8px;
            width: 100%;
        }

        .navbar .nav-link {
            padding: 12px 10px !important;
            border-radius: 8px;
        }

        .navbar .nav-link:hover,
        .navbar .nav-link.active {
            background: rgba(255, 255, 255, 0.08);
        }

        .navbar .nav-link::after {
            display: none;
        }

        .navbar-nav.ms-auto {
            margin-left: 0 !important;
        }

        .user-pill-btn {
            width: 100%;
            min-width: 0;
            justify-content: flex-start;
            border-radius: 8px;
        }

        .navbar .dropdown-menu {
            position: static;
            width: 100%;
            margin-top: 8px !important;
            transform: none;
        }
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?php echo e(appUrl('home.php')); ?>" style="min-width: 150px;">
            <i class="bi bi-trophy-fill me-2"></i>Sri Lanka Squash Scoring System
        </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto">
                    <?php if (!$is_logged_in): ?>
                        <li class="nav-item"><a class="nav-link <?php echo isActive('rankings.php', $current_page); ?>" href="<?php echo e(appUrl('rankings.php')); ?>">Public Rankings</a></li>
                    <?php endif; ?>
                    <?php if ($is_logged_in && !$hide_nav_links): ?>
                        <?php if ($user_role === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('players/add.php', $current_page); ?>" href="<?php echo e(appUrl('players/add.php')); ?>">Players</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('tournaments/list.php', $current_page); ?>" href="<?php echo e(appUrl('tournaments/list.php')); ?>">Tournaments</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('tournaments/assign.php', $current_page); ?>" href="<?php echo e(appUrl('tournaments/assign.php')); ?>">Assign</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('scores/add.php', $current_page); ?>" href="<?php echo e(appUrl('scores/add.php')); ?>">Entry</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('scores/list.php', $current_page); ?>" href="<?php echo e(appUrl('scores/list.php')); ?>">Scores</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('admin/rankings.php', $current_page); ?>" href="<?php echo e(appUrl('admin/rankings.php')); ?>">Rankings</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('admin/reports.php', $current_page); ?>" href="<?php echo e(appUrl('admin/reports.php')); ?>">Reports</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('admin/settings.php', $current_page); ?>" href="<?php echo e(appUrl('admin/settings.php')); ?>">Settings</a></li>
                        <?php else: ?>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('scores/list.php', $current_page); ?>" href="<?php echo e(appUrl('scores/list.php')); ?>">My Scores</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo isActive('scores/view.php', $current_page); ?>" href="<?php echo e(appUrl('scores/view.php')); ?>">Rankings</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>

                <?php if ($is_logged_in): ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="dropdown-toggle user-pill-btn" 
                           href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-2"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userDropdown">
                            <li class="px-3 py-2 small text-muted text-uppercase fw-bold">Role: <?php echo $user_role; ?></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo e(appUrl('profile.php')); ?>"><i class="bi bi-person-gear me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo e(appUrl('logout.php')); ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
    </div>
</nav>
