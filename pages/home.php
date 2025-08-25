<?php
// pages/home.php

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Get current month and year
$currentMonth = date('n');
$currentYear = date('Y');

// Database queries for dashboard metrics
$conn = $GLOBALS['conn'];

// 1. Get total transactions count
$totalTransactions = 0;
$sql = "SELECT COUNT(*) as total FROM transaksi WHERE hapus = '0'";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $totalTransactions = $row['total'];
}

// 2. Get unposted transactions count
$unpostedTransactions = 0;
$sql = "SELECT COUNT(*) as total FROM transaksi WHERE post = '0' AND hapus = '0'";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $unpostedTransactions = $row['total'];
}

// 3. Get adjusting entries count for current month
$adjustingEntries = 0;
$sql = "SELECT COUNT(*) as total FROM jurnal_penyesuaian 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $adjustingEntries = $row['total'];
}

// 4. Get pending adjusting entries for current month
$pendingAdjustments = 0;
$pendingAdjustmentsList = [];
$sql = "SELECT COUNT(*) as total FROM jurnal_penyesuaian 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $row = $result->fetch_assoc()) {
    $pendingAdjustments = $row['total'];
}

// Get list of pending adjustments
$sql = "SELECT jp.id, jp.keterangan, a1.nama_akun as akun_debit, a2.nama_akun as akun_kredit, jp.jumlah
        FROM jurnal_penyesuaian jp
        JOIN akun a1 ON jp.akun_debit = a1.id_akun
        JOIN akun a2 ON jp.akun_kredit = a2.id_akun
        WHERE MONTH(jp.tanggal) = ? AND YEAR(jp.tanggal) = ? AND jp.status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingAdjustmentsList[] = $row;
    }
}

// 5. Get recent transactions (last 5)
$recentTransactions = [];
$sql = "SELECT id_transaksi, tgl_transaksi, nama_transaksi, post 
        FROM transaksi 
        WHERE hapus = '0' 
        ORDER BY tgl_transaksi DESC, id_transaksi DESC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentTransactions[] = $row;
    }
}

?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard | <i class="fas fa-calendar-alt me-1"></i> <?php echo date('F Y'); ?></h1>
    </div>

    <!-- Notification for pending adjustments -->
    <?php if ($pendingAdjustments > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Peringatan!</strong> Ada <?php echo $pendingAdjustments; ?> jurnal penyesuaian bulan ini yang masih pending.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>

            <div class="mt-2">
                <small>Daftar jurnal penyesuaian pending:</small>
                <ul class="mb-0">
                    <?php foreach ($pendingAdjustmentsList as $adj): ?>
                        <li>
                            <?php echo htmlspecialchars($adj['keterangan']); ?> -
                            Debit: <?php echo htmlspecialchars($adj['akun_debit']); ?> |
                            Kredit: <?php echo htmlspecialchars($adj['akun_kredit']); ?> |
                            Jumlah: <?php echo number_format($adj['jumlah'], 2); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row">
        <!-- Total Transactions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Transactions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($totalTransactions); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unposted Transactions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Unposted Transactions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($unpostedTransactions); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Adjusting Entries Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Adjusting Entries (This Month)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($adjustingEntries); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-adjust fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Adjustments Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Pending Adjustments</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($pendingAdjustments); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions and Quick Actions -->
    <div class="row">
        <!-- Recent Transactions -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold ">Recent Transactions</h6>
                    <a href="index.php?page=input_transaction" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> New Transaction
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($transaction['tgl_transaksi'])); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['nama_transaksi']); ?></td>
                                        <td>
                                            <?php if ($transaction['post'] == '1'): ?>
                                                <span class="badge bg-success">Posted</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Unposted</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="index.php?page=view_transaction&id=<?php echo $transaction['id_transaksi']; ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No recent transactions found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold ">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="index.php?page=input_transaction" class="btn btn-primary mb-3">
                            <i class="fas fa-plus-circle me-2"></i> New Transaction
                        </a>

                        <a href="index.php?page=adjusting_journal" class="btn btn-info mb-3">
                            <i class="fas fa-adjust me-2"></i> Create Adjusting Entry
                        </a>

                        <?php if ($pendingAdjustments > 0): ?>
                            <a href="index.php?page=adjusting_journal" class="btn btn-danger mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i> Complete Pending Adjustments (<?php echo $pendingAdjustments; ?>)
                            </a>
                        <?php endif; ?>

                        <a href="index.php?page=posting" class="btn btn-warning mb-3">
                            <i class="fas fa-check-square me-2"></i> Post Transactions
                        </a>

                        <a href="index.php?page=closed_periods" class="btn btn-secondary mb-3">
                            <i class="fas fa-lock me-2"></i> Period Closing
                        </a>

                        <a href="index.php?page=journal" class="btn btn-success mb-3">
                            <i class="fas fa-book me-2"></i> View General Journal
                        </a>

                        <a href="index.php?page=income_statement" class="btn btn-danger mb-3">
                            <i class="fas fa-chart-bar me-2"></i> View Income Statement
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Dismiss the alert when close button is clicked
    $(document).on('click', '.alert .close', function() {
        $(this).closest('.alert').alert('close');
    });
</script>