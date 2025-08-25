<?php
include "config/functions.php";

// Get filter parameters
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$show_adjustments = isset($_GET['show_adjustments']);

// Determine available years
$years_query = "SELECT DISTINCT YEAR(tgl_transaksi) as year FROM transaksi ORDER BY year DESC";
$years_result = $conn->query($years_query);
$available_years = [];
if ($years_result->num_rows > 0) {
    while ($year_row = $years_result->fetch_assoc()) {
        $available_years[] = $year_row['year'];
    }
}
if (empty($available_years) || !in_array($filter_year, $available_years)) {
    $filter_year = date('Y');
}

// Get journal entries
$query = "SELECT 
            t.id_transaksi,
            t.tgl_transaksi,
            t.nama_transaksi,
            a.nama_akun,
            dt.debit_kredit,
            IF(dt.debit_kredit = 'D', dt.nilai, 0) AS Debit,
            IF(dt.debit_kredit = 'K', dt.nilai, 0) AS Kredit,
            dt.penyesuaian,
            jp.id AS adjustment_id
          FROM 
            transaksi t
          JOIN 
            detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
          JOIN 
            akun a ON dt.id_akun = a.id_akun
          LEFT JOIN
            jurnal_penyesuaian jp ON jp.referensi_jurnal = t.id_transaksi AND jp.akun_debit = dt.id_akun
          WHERE 
            YEAR(t.tgl_transaksi) = ? AND
            t.hapus = '0'";

if ($filter_period == 'monthly' && !empty($filter_month)) {
    $query .= " AND MONTH(t.tgl_transaksi) = ?";
}

if (!$show_adjustments) {
    $query .= " AND dt.penyesuaian = 0";
}

$query .= " ORDER BY t.tgl_transaksi, t.id_transaksi, dt.debit_kredit DESC";

$stmt = $conn->prepare($query);

if ($filter_period == 'monthly' && !empty($filter_month)) {
    $stmt->bind_param("ii", $filter_year, $filter_month);
} else {
    $stmt->bind_param("i", $filter_year);
}

$stmt->execute();
$result = $stmt->get_result();
$journal_entries = $result->fetch_all(MYSQLI_ASSOC);

// Calculate totals
$grand_total_debit = 0;
$grand_total_credit = 0;
$current_transaction = null;
$transaction_debit = 0;
$transaction_credit = 0;
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="mb-4">General Journal Entries</h1>

        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Options</h5>
            </div>
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3">
                    <input type="hidden" name="page" value="journal">

                    <div class="col-md-3">
                        <label for="period" class="form-label">Period Type</label>
                        <select name="period" id="period" class="form-select" onchange="toggleMonthVisibility()">
                            <option value="monthly" <?= $filter_period == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            <option value="yearly" <?= $filter_period == 'yearly' ? 'selected' : '' ?>>Yearly</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="year" class="form-label">Year</label>
                        <select name="year" id="year" class="form-select">
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?= $year ?>" <?= $year == $filter_year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3" id="month-selector" <?= $filter_period == 'yearly' ? 'style="display:none;"' : '' ?>>
                        <label for="month" class="form-label">Month</label>
                        <select name="month" id="month" class="form-select">
                            <option value="">All Months</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $filter_month == $i ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="show_adjustments" name="show_adjustments" <?= $show_adjustments ? 'checked' : '' ?>>
                            <label class="form-check-label" for="show_adjustments">
                                Show Adjustments
                            </label>
                        </div>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Journal Entries Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction Description And Account</th>
                                <th></th>
                                <th class="text-end">Debit (Rp)</th>
                                <th class="text-end">Credit (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($journal_entries)): ?>
                                <?php foreach ($journal_entries as $entry): ?>
                                    <?php if ($current_transaction != $entry['id_transaksi']): ?>
                                        <?php if ($current_transaction !== null): ?>
                                            <!-- Show transaction totals -->
                                            <tr class="table-secondary">
                                                <td colspan="3" class="text-end"><strong>Transaction Total</strong></td>
                                                <td class="text-end"><strong><?= number_format($transaction_debit, 0, ',', '.') ?></strong></td>
                                                <td class="text-end"><strong><?= number_format($transaction_credit, 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td colspan="5"></td>
                                            </tr>
                                        <?php endif; ?>

                                        <!-- New transaction header -->
                                        <tr class="table-light">
                                            <td><?= date('d M Y', strtotime($entry['tgl_transaksi'])) ?></td>
                                            <td colspan="4">
                                                <strong><?= $entry['nama_transaksi'] ?></strong>
                                                <?php if (isset($entry['penyesuaian']) && $entry['penyesuaian']): ?>
                                                    <span class="badge bg-warning text-dark ms-2">Adjustment</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <?php
                                        $current_transaction = $entry['id_transaksi'];
                                        $transaction_debit = 0;
                                        $transaction_credit = 0;
                                        ?>
                                    <?php endif; ?>

                                    <!-- Journal entry row -->
                                    <tr <?= (isset($entry['penyesuaian']) && $entry['penyesuaian']) ? 'class="table-warning"' : '' ?>>
                                        <td></td>
                                        <td><?= (isset($entry['debit_kredit']) && $entry['debit_kredit'] == 'K') ? '&nbsp;&nbsp;&nbsp;&nbsp;' : '' ?><?= $entry['nama_akun'] ?></td>
                                        <td></td>
                                        <?php if (isset($entry['Debit']) && $entry['Debit'] > 0): ?>
                                            <td class="text-end"><?= number_format($entry['Debit'], 0, ',', '.') ?></td>
                                            <td></td>
                                            <?php
                                            $transaction_debit += $entry['Debit'];
                                            $grand_total_debit += $entry['Debit'];
                                            ?>
                                        <?php elseif (isset($entry['Kredit']) && $entry['Kredit'] > 0): ?>
                                            <td></td>
                                            <td class="text-end"><?= number_format($entry['Kredit'], 0, ',', '.') ?></td>
                                            <?php
                                            $transaction_credit += $entry['Kredit'];
                                            $grand_total_credit += $entry['Kredit'];
                                            ?>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Final transaction totals -->
                                <tr class="table-secondary">
                                    <td colspan="3" class="text-end"><strong>Transaction Total</strong></td>
                                    <td class="text-end"><strong><?= number_format($transaction_debit, 0, ',', '.') ?></strong></td>
                                    <td class="text-end"><strong><?= number_format($transaction_credit, 0, ',', '.') ?></strong></td>
                                </tr>

                                <!-- Grand totals -->
                                <tr>
                                    <td colspan="5"></td>
                                </tr>
                                <tr class="table-primary">
                                    <td colspan="3" class="text-end"><strong>Grand Total</strong></td>
                                    <td class="text-end"><strong><?= number_format($grand_total_debit, 0, ',', '.') ?></strong></td>
                                    <td class="text-end"><strong><?= number_format($grand_total_credit, 0, ',', '.') ?></strong></td>
                                </tr>

                                <!-- Balance check -->
                                <?php if ($grand_total_debit != $grand_total_credit): ?>
                                    <tr class="table-danger">
                                        <td colspan="5" class="text-center">
                                            <strong>Warning: Journal is not balanced! Difference: <?= number_format(abs($grand_total_debit - $grand_total_credit), 0, ',', '.') ?></strong>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr class="table-success">
                                        <td colspan="5" class="text-center"><strong>Journal is balanced</strong></td>
                                    </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No journal entries found for the selected period</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleMonthVisibility() {
        const periodSelect = document.getElementById('period');
        const monthSelector = document.getElementById('month-selector');
        monthSelector.style.display = periodSelect.value === 'yearly' ? 'none' : 'block';
    }
</script>