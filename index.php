<?php
include "config/connect.php";
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
// Get page from URL parameter
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Ambil data user dari database untuk mendapatkan ID
$user_id = 0;
$nama_lengkap = $_SESSION['nama_lengkap'] ?? $_SESSION['username'];
$username = $_SESSION['username'];

$sql = "SELECT id FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $user_id = $user['id'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CatatCepat - Accounting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Noto+Serif+JP:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <!-- User Info -->
    <div class="user-info">
        <i class="fas fa-user-circle"></i>
        <span><?php echo htmlspecialchars($nama_lengkap); ?> (ID: <?php echo htmlspecialchars($user_id); ?>)</span>
    </div>

    <!-- Sidebar Toggle Button -->
    <button id="sidebarToggle" class="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <!-- Minimize Button -->
        <button class="minimize-toggle" id="minimizeToggle">
            <i class="fas fa-angle-left"></i>
        </button>

        <div class="sidebar-brand">
            <span class="brand-full">CatatCepat</span>
            <span class="brand-short">CC</span>
        </div>

        <ul class="sidebar-nav">
            <!-- Dashboard Section -->
            <li class="nav-section-title">Dashboard</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'home' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-home"></i>
                    <span>Home Page</span>
                    <div class="tooltip-text">Home Page</div>
                </a>
            </li>

            <!-- Master Data Section -->
            <li class="nav-section-title">Master Data</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'accounts' ? 'active' : ''; ?>" href="index.php?page=accounts">
                    <i class="fas fa-list-alt"></i>
                    <span>Accounts List</span>
                    <div class="tooltip-text">Accounts List</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'reaksi' ? 'active' : ''; ?>" href="index.php?page=reaksi">
                    <i class="fas fa-copy"></i>
                    <span>Templates Transaction</span>
                    <div class="tooltip-text">Templates Transaction</div>
                </a>
            </li>

            <!-- Transaction Section -->
            <li class="nav-section-title">Transactions</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'input_transaction' ? 'active' : ''; ?>" href="index.php?page=input_transaction">
                    <i class="fas fa-plus-circle"></i>
                    <span>New Transaction</span>
                    <div class="tooltip-text">New Transaction</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'adjusting_journal' ? 'active' : ''; ?>" href="index.php?page=adjusting_journal">
                    <i class="fas fa-edit"></i>
                    <span>Adjusting Journal</span>
                    <div class="tooltip-text">Adjusting Journal</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'posting' ? 'active' : ''; ?>" href="index.php?page=posting">
                    <i class="fas fa-check-square"></i>
                    <span>Transaction Posting</span>
                    <div class="tooltip-text">Transaction Posting</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'prepaid_balances' ? 'active' : ''; ?>" href="index.php?page=prepaid_balances">
                    <i class="fas fa-calendar-check"></i>
                    <span>Prepaid Balances</span>
                    <div class="tooltip-text">Prepaid Balances</div>
                </a>
            </li>

            <!-- Reports Section -->
            <li class="nav-section-title">Reports</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'journal' ? 'active' : ''; ?>" href="index.php?page=journal">
                    <i class="fas fa-book"></i>
                    <span>General Journal</span>
                    <div class="tooltip-text">General Journal</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'ledger' ? 'active' : ''; ?>" href="index.php?page=ledger">
                    <i class="fas fa-clipboard-list"></i>
                    <span>General Ledger</span>
                    <div class="tooltip-text">General Ledger</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'trial_balance' ? 'active' : ''; ?>" href="index.php?page=trial_balance">
                    <i class="fas fa-balance-scale"></i>
                    <span>Trial Balance</span>
                    <div class="tooltip-text">Trial Balance</div>
                </a>
            </li>

            <!-- Financial Statements Section -->
            <li class="nav-section-title">Financial Statements</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'income_statement' ? 'active' : ''; ?>" href="index.php?page=income_statement">
                    <i class="fas fa-chart-bar"></i>
                    <span>Income Statement</span>
                    <div class="tooltip-text">Income Statement</div>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'equity_change' ? 'active' : ''; ?>" href="index.php?page=equity_change">
                    <i class="fas fa-chart-line"></i>
                    <span>Equity Change</span>
                    <div class="tooltip-text">Equity Change</div>
                </a>
            </li>

            <!-- System Section -->
            <li class="nav-section-title">System</li>
            <li class="nav-item">
                <a class="nav-link <?php echo $page == 'closed_periods' ? 'active' : ''; ?>" href="index.php?page=closed_periods">
                    <i class="fas fa-lock"></i>
                    <span>Closed Periods</span>
                    <div class="tooltip-text">Closed Periods</div>
                </a>
            </li>
        </ul>

        <div class="logout-container">
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
                <div class="tooltip-text">Logout</div>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <?php
            // Include the appropriate page content based on the 'page' parameter
            switch ($page) {
                case 'accounts':
                    include 'pages/accounts.php';
                    break;
                case 'accounts_form':
                    include 'pages/accounts_form.php';
                    break;
                case 'reaksi':
                    include 'pages/reaksi.php';
                    break;
                case 'reaksi_form':
                    include 'pages/reaksi_form.php';
                    break;
                case 'reaksi_detail':
                    include 'pages/reaksi_detail.php';
                    break;
                case 'input_transaction':
                    include 'input_transaction.php';
                    break;
                case 'adjusting_journal':
                    include 'pages/adjusting_journal.php';
                    break;
                case 'posting':
                    include 'pages/posting.php';
                    break;
                case 'prepaid_balances':
                    include 'pages/prepaid_balances.php';
                    break;
                case 'process_adjustment':
                    include 'pages/process_adjustment.php';
                    break;
                case 'edit_transaction':
                    include 'pages/edit_transaction.php';
                    break;
                case 'view_transaction':
                    include 'pages/view_transaction.php';
                    break;
                case 'journal':
                    include 'pages/journal.php';
                    break;
                case 'ledger':
                    include 'pages/ledger.php';
                    break;
                case 'trial_balance':
                    include 'pages/trial_balance.php';
                    break;
                case 'income_statement':
                    include 'pages/income_statement.php';
                    break;
                case 'equity_change':
                    include 'pages/equity_change.php';
                    break;
                case 'closed_periods':
                    include 'pages/closed_periods.php';
                    break;
                case 'view_closing':
                    include 'pages/view_closing.php';
                    break;
                default:
                    include 'pages/home.php';
                    break;
            }
            ?>

            <div class="footer">
                <p>CatatCepat Accounting System &copy; <?php echo date('Y'); ?></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar state management
        let sidebarMinimized = localStorage.getItem('sidebarMinimized') === 'true';
        const sidebar = document.getElementById('sidebar');
        const minimizeToggle = document.getElementById('minimizeToggle');
        const minimizeIcon = minimizeToggle.querySelector('i');

        // Apply saved state on page load
        if (sidebarMinimized) {
            sidebar.classList.add('minimized');
            minimizeIcon.classList.remove('fa-angle-left');
            minimizeIcon.classList.add('fa-angle-right');
        }

        // Minimize/Maximize sidebar
        minimizeToggle.addEventListener('click', function() {
            sidebar.classList.toggle('minimized');
            sidebarMinimized = sidebar.classList.contains('minimized');

            // Save state to localStorage
            localStorage.setItem('sidebarMinimized', sidebarMinimized);

            // Update icon
            if (sidebarMinimized) {
                minimizeIcon.classList.remove('fa-angle-left');
                minimizeIcon.classList.add('fa-angle-right');
            } else {
                minimizeIcon.classList.remove('fa-angle-right');
                minimizeIcon.classList.add('fa-angle-left');
            }
        });

        // Toggle sidebar on mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            sidebar.classList.toggle('show');
            document.body.classList.toggle('sidebar-open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebarToggle = document.getElementById('sidebarToggle');

            if (window.innerWidth <= 991) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target) && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                }
            }
        });

        // Adjust sidebar for window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 991) {
                sidebar.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                // Close mobile sidebar when clicking a link
                if (window.innerWidth <= 991) {
                    sidebar.classList.remove('show');
                    document.body.classList.remove('sidebar-open');
                }
            });
        });
    </script>
</body>

</html>

<?php
// Close the database connection
$conn->close();
?>