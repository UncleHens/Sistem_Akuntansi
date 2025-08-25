<?php
include "config/functions.php";

// Get filter parameters
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : 0;
$filter_year = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : 0;

// Get all prepaid accounts (131-145)
$prepaid_accounts = [];
$result = $conn->query("SELECT id_akun, nama_akun FROM akun WHERE id_akun BETWEEN 131 AND 145 ORDER BY id_akun");
while ($row = $result->fetch_assoc()) {
    $prepaid_accounts[] = $row;
}

// Get adjustment history for each account
foreach ($prepaid_accounts as &$account) {
    $account_id = $account['id_akun'];

    // Get current balance
    $query = "SELECT 
                SUM(CASE WHEN dt.debit_kredit = 'D' THEN dt.nilai ELSE 0 END) as total_debit,
                SUM(CASE WHEN dt.debit_kredit = 'K' THEN dt.nilai ELSE 0 END) as total_kredit
              FROM detail_transaksi dt
              JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
              WHERE dt.id_akun = ? AND t.hapus = '0'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $balance = $result->fetch_assoc();
    $account['current_balance'] = $balance['total_debit'] - $balance['total_kredit'];

    // Get adjustment history
    $query = "SELECT 
                jp.*,
                a_debit.nama_akun as nama_akun_debit,
                a_kredit.nama_akun as nama_akun_kredit,
                t.nama_transaksi as original_transaction,
                adj_t.nama_transaksi as adjustment_transaction
              FROM jurnal_penyesuaian jp
              LEFT JOIN akun a_debit ON jp.akun_debit = a_debit.id_akun
              LEFT JOIN akun a_kredit ON jp.akun_kredit = a_kredit.id_akun
              LEFT JOIN transaksi t ON jp.referensi_jurnal = t.id_transaksi
              LEFT JOIN transaksi adj_t ON jp.referensi_jurnal = adj_t.id_transaksi
              WHERE jp.akun_kredit = ?";

    if ($filter_year > 0) {
        $query .= " AND YEAR(jp.tanggal) = ?";
        if ($filter_month > 0) {
            $query .= " AND MONTH(jp.tanggal) = ?";
        }
    }

    $query .= " ORDER BY jp.tanggal DESC";

    $stmt = $conn->prepare($query);
    if ($filter_year > 0) {
        if ($filter_month > 0) {
            $stmt->bind_param("iii", $account_id, $filter_year, $filter_month);
        } else {
            $stmt->bind_param("ii", $account_id, $filter_year);
        }
    } else {
        $stmt->bind_param("i", $account_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $account['history'] = $result->fetch_all(MYSQLI_ASSOC);

    // Calculate totals
    $account['total_adjusted'] = 0;
    $account['pending_count'] = 0;
    foreach ($account['history'] as $entry) {
        $account['total_adjusted'] += $entry['jumlah'];
        if ($entry['status'] !== 'processed') {
            $account['pending_count']++;
        }
    }
}

// Get available years for filter
$years = [];
$result = $conn->query("SELECT DISTINCT YEAR(tanggal) as year FROM jurnal_penyesuaian ORDER BY year DESC");
while ($row = $result->fetch_assoc()) {
    $years[] = $row['year'];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mb-4">Prepaid Expenses Monitoring</h1>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Filter</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row">
                        <input type="hidden" name="page" value="prepaid_balances">

                        <div class="col-md-3">
                            <label class="form-label">Year</label>
                            <select name="filter_year" class="form-select">
                                <option value="">All Years</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= $year ?>" <?= ($filter_year == $year) ? 'selected' : '' ?>><?= $year ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Month</label>
                            <select name="filter_month" class="form-select">
                                <option value="">All Months</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($filter_month == $i) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Apply Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Prepaid Accounts Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Prepaid Accounts Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Current Balance</th>
                                    <th>Total Adjusted</th>
                                    <th>Pending Adjustments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prepaid_accounts as $account): ?>
                                    <tr>
                                        <td><?= $account['id_akun'] ?> - <?= $account['nama_akun'] ?></td>
                                        <td class="text-end">Rp <?= number_format($account['current_balance'], 0, ',', '.') ?></td>
                                        <td class="text-end">Rp <?= number_format($account['total_adjusted'], 0, ',', '.') ?></td>
                                        <td>
                                            <?php if ($account['pending_count'] > 0): ?>
                                                <span class="badge bg-warning"><?= $account['pending_count'] ?> pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">All processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Adjustment History -->
            <?php foreach ($prepaid_accounts as $account): ?>
                <?php if (!empty($account['history'])): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Adjustment History - <?= $account['id_akun'] ?> <?= $account['nama_akun'] ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Debit Account</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($account['history'] as $entry): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($entry['tanggal'])) ?></td>
                                                <td><?= $entry['keterangan'] ?></td>
                                                <td><?= $entry['akun_debit'] ?> - <?= $entry['nama_akun_debit'] ?></td>
                                                <td class="text-end">Rp <?= number_format($entry['jumlah'], 0, ',', '.') ?></td>
                                                <td>
                                                    <?php if ($entry['status'] === 'processed'): ?>
                                                        <span class="badge bg-success">Processed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>