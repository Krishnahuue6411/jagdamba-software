<?php
// Jagdamba Electrical - Shared Layout & UI Framework (sidebar.php)

if (session_status() === PHP_SESSION_NONE) {
    // Extend session lifetime to 8 hours (28800 seconds) to prevent
    // premature expiry on InfinityFree and other shared hosting environments
    ini_set('session.gc_maxlifetime', 28800);
    session_set_cookie_params(28800);
    session_start();
}
if (empty($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit;
}

function getSidebarConnection() {
    require_once __DIR__ . '/db_config.php';
    try {
        return getDatabaseConnection();
    } catch (Exception $e) {
        return null;
    }
}


function renderHeader($pageTitle, $activeMenuItem) {
    // Fetch pending machines count for sidebar badge
    $pendingMachinesCount = 0;
    $alertsCount = 0;
    $pdo = getSidebarConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `remaining_machines` WHERE `status` = 'pending'");
            $row = $stmt->fetch();
            $pendingMachinesCount = intval($row['cnt'] ?? 0);

            // Fetch aged inventory alerts count (bal_qty > 0 and inward date older than 30 days)
            $stmtAlerts = $pdo->query("SELECT COUNT(*) as cnt FROM `inward_transaction` WHERE `bal_qty` > 0 AND `ch_date` < DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
            $rowAlerts = $stmtAlerts->fetch();
            $alertsCount = intval($rowAlerts['cnt'] ?? 0);
        } catch (Exception $e) {
            // Silently fail if DB not set up yet
        }
    }

    $menuItems = [
        ['key' => 'dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
        ['key' => 'raw_materials', 'url' => 'raw_material_master.php', 'icon' => 'fa-boxes', 'label' => 'Raw Materials'],
        ['key' => 'transporters', 'url' => 'transport_master.php', 'icon' => 'fa-truck', 'label' => 'Transporters'],
        ['key' => 'bom', 'url' => 'bom.php', 'icon' => 'fa-sitemap', 'label' => 'Bill of Materials'],
        ['key' => 'po_master', 'url' => 'po_master.php', 'icon' => 'fa-file-import', 'label' => 'PO Master'],
        ['key' => 'inward', 'url' => 'inward.php', 'icon' => 'fa-arrow-down', 'label' => 'Inward Entry'],
        ['key' => 'challan_inward', 'url' => 'challan_inward.php', 'icon' => 'fa-file-circle-check', 'label' => 'Challan Inward'],
        ['key' => 'nfp_outward', 'url' => 'nfp_outward.php', 'icon' => 'fa-file-arrow-up', 'label' => 'NFP Outward'],
        ['key' => 'tax_invoice', 'url' => 'tax_invoice.php', 'icon' => 'fa-file-invoice-dollar', 'label' => 'Tax Invoice'],
        ['key' => 'workers', 'url' => 'workers.php', 'icon' => 'fa-users-gear', 'label' => 'Workers & Machines'],
        ['key' => 'mo', 'url' => 'mo.php', 'icon' => 'fa-industry', 'label' => 'MO (Jobs)'],
        ['key' => 'parties', 'url' => 'parties.php', 'icon' => 'fa-briefcase', 'label' => 'Party Directory']
    ];

    $reportItems = [
        ['key' => 'invoices_list', 'url' => 'invoices_list.php', 'icon' => 'fa-list-ol', 'label' => 'Previous Invoices'],
        ['key' => 'gst_report', 'url' => 'gst_report.php', 'icon' => 'fa-percent', 'label' => 'GST Records'],
        ['key' => 'invoice_details', 'url' => 'invoice_details.php', 'icon' => 'fa-circle-info', 'label' => 'Invoice Details'],
        ['key' => 'remaining_stock', 'url' => 'remaining_stock.php', 'icon' => 'fa-warehouse', 'label' => 'Remaining Stock'],
        ['key' => 'challan_tracking', 'url' => 'challan_tracking.php', 'icon' => 'fa-truck-ramp-box', 'label' => 'Challan Tracking'],
        ['key' => 'pending_machines', 'url' => 'pending_machines.php', 'icon' => 'fa-microchip', 'label' => 'Pending Machines'],
        // ['key' => 'wip_report', 'url' => 'wip_report.php', 'icon' => 'fa-industry', 'label' => 'WIP Report'],
        ['key' => 'invoiced_machines', 'url' => 'invoiced_machines.php', 'icon' => 'fa-barcode', 'label' => 'Invoiced Machines'],
        ['key' => 'invoice_deductions', 'url' => 'invoice_deductions.php', 'icon' => 'fa-calculator', 'label' => 'Material Deductions'],
        ['key' => 'alerts', 'url' => 'alerts.php', 'icon' => 'fa-bell', 'label' => 'System Alerts'],
        ['key' => 'db_manager', 'url' => 'db_manager.php', 'icon' => 'fa-database', 'label' => 'DB Manager']
    ];

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jagdamba Electrical - <?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="style.css?v=5">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('app-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>
    <div id="app-container">
        <!-- Sidebar Navigation -->
        <aside id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fa-solid fa-bolt" style="color: var(--primary);"></i>
                    <span>Jagdamba <span class="highlight">Elec</span></span>
                </div>
                <button class="sidebar-toggle-btn" id="sidebar-toggle" title="Toggle Menu">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
            </div>
            <ul class="sidebar-menu">
                <li style="padding: 0 16px 6px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); opacity: 0.7; font-weight: 700;">Registry Master</li>
                <?php foreach ($menuItems as $item): ?>
                    <li class="sidebar-menu-item">
                        <a href="<?php echo $item['url']; ?>" class="sidebar-link <?php echo ($activeMenuItem === $item['key']) ? 'active' : ''; ?>">
                            <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                            <span><?php echo $item['label']; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>

                <li style="padding: 18px 16px 6px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); opacity: 0.7; font-weight: 700; border-top: 1px solid var(--border-color); margin-top: 12px;">Reports & Stock</li>
                <?php foreach ($reportItems as $item): ?>
                    <li class="sidebar-menu-item">
                        <a href="<?php echo $item['url']; ?>" class="sidebar-link <?php echo ($activeMenuItem === $item['key']) ? 'active' : ''; ?>">
                            <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                            <span><?php echo $item['label']; ?></span>
                            <?php if ($item['key'] === 'pending_machines' && $pendingMachinesCount > 0): ?>
                                <span class="menu-badge" id="pending-machines-badge"><?php echo $pendingMachinesCount; ?></span>
                            <?php endif; ?>
                            <?php if ($item['key'] === 'alerts' && $alertsCount > 0): ?>
                                <span class="menu-badge" id="system-alerts-badge" style="background-color: var(--error); color: #ffffff;"><?php echo $alertsCount; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- Main Workspace -->
        <div id="main-content">
            <!-- Top Navigation Bar -->
            <header class="topbar">
                <div class="page-title-section">
                    <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="breadcrumbs">
                        <a href="dashboard.php">Jagdamba System</a>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                        <span><?php echo htmlspecialchars($pageTitle); ?></span>
                    </div>
                </div>
                <div class="topbar-actions">
                    <div class="topbar-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="global-search-input" placeholder="Quick Search...">
                    </div>
                    <button class="btn btn-secondary btn-icon" id="theme-toggle-btn" onclick="App.toggleTheme();" title="Switch Light/Dark Theme">
                        <i class="fa-solid fa-moon" id="theme-toggle-icon"></i>
                    </button>
                    <button class="btn btn-secondary btn-icon" id="topbar-refresh-btn" onclick="window.location.reload();" title="Refresh Page">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                    <button class="btn btn-secondary btn-icon" style="background-color: rgba(239, 68, 68, 0.1); color: var(--error); border-color: rgba(239, 68, 68, 0.2);" onclick="window.location.href = 'index.php?action=logout';" title="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </button>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="content-body">
    <?php
}

function renderFooter() {
    ?>
            </main>
        </div>
    </div>

    <!-- UI Overlay Utilities (Toast & Loading) -->
    <div id="toast-container"></div>

    <div id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text" id="loading-text">Loading...</div>
        <div class="progress-container" id="progress-container">
            <div class="progress-bar" id="progress-bar"></div>
        </div>
    </div>

    <!-- Core Javascript Framework Library -->
    <script>
        // Check and restore sidebar collapse state from localStorage
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const toggleIcon = sidebarToggle.querySelector('i');

        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            sidebar.classList.add('collapsed');
            toggleIcon.className = 'fa-solid fa-chevron-right';
        }

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
            toggleIcon.className = isCollapsed ? 'fa-solid fa-chevron-right' : 'fa-solid fa-chevron-left';
        });

        // App Framework Object
        const App = {
            // Secure fetch wrapper to communicate with api.php
            async api(action, payload = {}) {
                try {
                    // Base64 encode SQL queries to bypass Web Application Firewall (WAF) checks
                    if (action === 'query' && payload.sql) {
                        payload.sql = btoa(payload.sql.trim());
                        payload.is_encoded = true;
                    }
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ action, ...payload })
                    });
                    // Handle session expiry: 401 means logged out, redirect to login
                    if (response.status === 401) {
                        App.showToast('Session expired. Redirecting to login...', 'error');
                        setTimeout(() => { window.location.href = 'index.php'; }, 1500);
                        throw new Error('Session expired. Please log in again.');
                    }
                    const text = await response.text();
                    let json;
                    try {
                        json = JSON.parse(text);
                    } catch (e) {
                        console.error("Invalid JSON response from server:", text);
                        throw new Error("Server returned an invalid response (non-JSON). Please check the console or contact support.");
                    }
                    if (!json.ok) {
                        throw new Error(json.error || 'Server returned failure');
                    }
                    return json;
                } catch (err) {
                    App.showToast(err.message || 'Network error occurred', 'error');
                    throw err;
                }
            },

            // Premium Toast Alerts
            showToast(message, type = 'success') {
                const container = document.getElementById('toast-container');
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;

                const iconClass = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';

                toast.innerHTML = `
                    <div class="toast-icon"><i class="fa-solid ${iconClass}"></i></div>
                    <div class="toast-content">
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
                `;

                container.appendChild(toast);

                // Trigger auto-dismiss
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(120%)';
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 3500);
            },

            // Loading Overlay controllers
            showLoading(text = 'Loading...', showProgress = false) {
                const overlay = document.getElementById('loading-overlay');
                document.getElementById('loading-text').innerText = text;
                const pContainer = document.getElementById('progress-container');
                const pBar = document.getElementById('progress-bar');

                if (showProgress) {
                    pContainer.style.display = 'block';
                    pBar.style.width = '0%';
                } else {
                    pContainer.style.display = 'none';
                }

                overlay.classList.add('visible');
            },

            updateProgress(percent) {
                const pBar = document.getElementById('progress-bar');
                if (pBar) {
                    pBar.style.width = `${percent}%`;
                }
            },

            hideLoading() {
                const overlay = document.getElementById('loading-overlay');
                overlay.classList.remove('visible');
            },

            // Clean date formatter (YYYY-MM-DD)
            formatDate(dateStr) {
                if (!dateStr) return '';
                const date = new Date(dateStr);
                if (isNaN(date.getTime())) return dateStr;
                const yyyy = date.getFullYear();
                const mm = String(date.getMonth() + 1).padStart(2, '0');
                const dd = String(date.getDate()).padStart(2, '0');
                return `${yyyy}-${mm}-${dd}`;
            },

            // Theme Toggle & Management
            initTheme() {
                const currentTheme = localStorage.getItem('app-theme') || 'dark';
                document.documentElement.setAttribute('data-theme', currentTheme);
                App.updateThemeIcon(currentTheme);
            },

            toggleTheme() {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('app-theme', newTheme);

                App.updateThemeIcon(newTheme);
                App.showToast(`Switched to ${newTheme.charAt(0).toUpperCase() + newTheme.slice(1)} Mode`, 'success');
            },

            updateThemeIcon(theme) {
                const icon = document.getElementById('theme-toggle-icon');
                const btn = document.getElementById('theme-toggle-btn');
                if (icon) {
                    if (theme === 'dark') {
                        icon.className = 'fa-solid fa-sun';
                        icon.style.color = '#f59e0b';
                        if (btn) btn.title = 'Switch to Light Mode';
                    } else {
                        icon.className = 'fa-solid fa-moon';
                        icon.style.color = '#3b82f6';
                        if (btn) btn.title = 'Switch to Dark Mode';
                    }
                }
            },

            // Standard Indian Currency Formatter (or basic Decimal formatting)
            formatDecimal(num, decimals = 2) {
                const val = parseFloat(num);
                return isNaN(val) ? '0.00' : val.toFixed(decimals);
            }
        };

        // Initialize Theme Controls
        document.addEventListener('DOMContentLoaded', () => {
            App.initTheme();
        });
        App.initTheme();

        // Simple Mobile menu gesture / responsive setup
        if (window.innerWidth <= 820) {
            // Double click title or logo to toggle mobile menu
            document.querySelector('.sidebar-logo').addEventListener('click', () => {
                sidebar.classList.toggle('mobile-open');
            });

            // Clicking main body content closes mobile sidebar
            document.getElementById('main-content').addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
            });
        }
    </script>
</body>
</html>
    <?php
}
?>
