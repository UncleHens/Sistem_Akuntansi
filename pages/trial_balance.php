<?php
// Get filter parameters
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'monthly'; // 'monthly' or 'yearly'

// Determine available years from transaction data
$years_query = "SELECT DISTINCT YEAR(tgl_transaksi) as year FROM transaksi ORDER BY year DESC";
$years_result = $conn->query($years_query);
$available_years = [];
if ($years_result->num_rows > 0) {
    while ($year_row = $years_result->fetch_assoc()) {
        $available_years[] = $year_row['year'];
    }
}
// If no years found or current filter year is not in list, use current year
if (empty($available_years) || !in_array($filter_year, $available_years)) {
    $filter_year = date('Y');
}

// Build WHERE clause for filtering
$where_clause = "";
if ($filter_period == 'monthly' && !empty($filter_month)) {
    $period_label = date('F', mktime(0, 0, 0, $filter_month, 1)) . ' ' . $filter_year;
    $where_clause = " WHERE YEAR(t.tgl_transaksi) = '$filter_year' AND MONTH(t.tgl_transaksi) = '$filter_month'";
} else {
    $period_label = 'Full Year ' . $filter_year;
    $where_clause = " WHERE YEAR(t.tgl_transaksi) = '$filter_year'";
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="page-header mb-4">Trial Balance</h1>

        <!-- Period Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Filter Options</h5>
            </div>
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3">
                    <input type="hidden" name="page" value="trial_balance">

                    <div class="col-md-3">
                        <label for="period" class="form-label">Period Type</label>
                        <select name="period" id="period" class="form-select" onchange="toggleMonthVisibility()">
                            <option value="monthly" <?php echo ($filter_period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                            <option value="yearly" <?php echo ($filter_period == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="year" class="form-label">Year</label>
                        <select name="year" id="year" class="form-select">
                            <?php foreach ($available_years as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($year == $filter_year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3" id="month-selector" <?php echo ($filter_period == 'yearly') ? 'style="display:none;"' : ''; ?>>
                        <label for="month" class="form-label">Month</label>
                        <select name="month" id="month" class="form-select">
                            <option value="">All Months</option>
                            <option value="1" <?php echo ($filter_month == '1') ? 'selected' : ''; ?>>January</option>
                            <option value="2" <?php echo ($filter_month == '2') ? 'selected' : ''; ?>>February</option>
                            <option value="3" <?php echo ($filter_month == '3') ? 'selected' : ''; ?>>March</option>
                            <option value="4" <?php echo ($filter_month == '4') ? 'selected' : ''; ?>>April</option>
                            <option value="5" <?php echo ($filter_month == '5') ? 'selected' : ''; ?>>May</option>
                            <option value="6" <?php echo ($filter_month == '6') ? 'selected' : ''; ?>>June</option>
                            <option value="7" <?php echo ($filter_month == '7') ? 'selected' : ''; ?>>July</option>
                            <option value="8" <?php echo ($filter_month == '8') ? 'selected' : ''; ?>>August</option>
                            <option value="9" <?php echo ($filter_month == '9') ? 'selected' : ''; ?>>September</option>
                            <option value="10" <?php echo ($filter_month == '10') ? 'selected' : ''; ?>>October</option>
                            <option value="11" <?php echo ($filter_month == '11') ? 'selected' : ''; ?>>November</option>
                            <option value="12" <?php echo ($filter_month == '12') ? 'selected' : ''; ?>>December</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Display period info -->
        <div class="alert alert-info mb-4">
            <strong>Viewing: </strong><?php echo $period_label; ?>
        </div>

        <!-- Trial Balance Card -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Trial Balance Statement</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Assets Section -->
                    <div class="col-md-6 pe-md-4">
                        <div class="card mb-4">
                            <div class="card-header bg-primary">
                                <h5 class="mb-0">Assets</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Account</th>
                                                <th class="right-align">Amount (Rp)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Call GetNeracaSaldo procedure and process results for assets
                                            $assets_sql = "CALL GetNeracaSaldo()";
                                            $assets_result = $conn->query($assets_sql);

                                            $total_assets = 0;
                                            $assets_data = [];

                                            if ($assets_result) {
                                                // Store all results first
                                                while ($row = $assets_result->fetch_assoc()) {
                                                    if ($row['kelompok'] == 'AKTIVA') {
                                                        // Apply year/month filter
                                                        $row_year = date('Y', strtotime($row['bln']));
                                                        $row_month = date('m', strtotime($row['bln']));

                                                        if ($filter_period == 'monthly' && !empty($filter_month)) {
                                                            if ($row_year == $filter_year && $row_month == $filter_month) {
                                                                if (!isset($assets_data[$row['nama_akun']])) {
                                                                    $assets_data[$row['nama_akun']] = 0;
                                                                }
                                                                $assets_data[$row['nama_akun']] += ($row['Debit'] - $row['Kredit']);
                                                            }
                                                        } else {
                                                            if ($row_year == $filter_year) {
                                                                if (!isset($assets_data[$row['nama_akun']])) {
                                                                    $assets_data[$row['nama_akun']] = 0;
                                                                }
                                                                $assets_data[$row['nama_akun']] += ($row['Debit'] - $row['Kredit']);
                                                            }
                                                        }
                                                    }
                                                }

                                                // Free result set
                                                $assets_result->free();
                                                $conn->next_result();

                                                // Display filtered assets
                                                foreach ($assets_data as $account => $balance) {
                                                    if ($balance != 0) {
                                                        echo "<tr>";
                                                        echo "<td>" . $account . "</td>";
                                                        echo "<td class='right-align'>" . number_format($balance, 0, ',', '.') . "</td>";
                                                        echo "</tr>";
                                                        $total_assets += $balance;
                                                    }
                                                }
                                            }

                                            if (empty($assets_data)) {
                                                echo "<tr><td colspan='2'>No assets found for the selected period</td></tr>";
                                            }
                                            ?>
                                            <tr class="table-light">
                                                <td class="bold-text">Total Assets</td>
                                                <td class="right-align bold-text"><?php echo number_format($total_assets, 0, ',', '.'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liabilities & Equity Section -->
                    <div class="col-md-6 ps-md-4">
                        <div class="card mb-4">
                            <div class="card-header bg-success">
                                <h5 class="mb-0">Liabilities & Equity</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Account</th>
                                                <th class="right-align">Amount (Rp)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Call GetNeracaSaldo procedure and process results for liabilities and equity
                                            $liabilities_sql = "CALL GetNeracaSaldo()";
                                            $liabilities_result = $conn->query($liabilities_sql);

                                            $total_liabilities_equity = 0;
                                            $liabilities_data = [];

                                            if ($liabilities_result) {
                                                // Store all results first
                                                while ($row = $liabilities_result->fetch_assoc()) {
                                                    if ($row['kelompok'] == 'PASIVA') {
                                                        // Apply year/month filter
                                                        $row_year = date('Y', strtotime($row['bln']));
                                                        $row_month = date('m', strtotime($row['bln']));

                                                        if ($filter_period == 'monthly' && !empty($filter_month)) {
                                                            if ($row_year == $filter_year && $row_month == $filter_month) {
                                                                if (!isset($liabilities_data[$row['nama_akun']])) {
                                                                    $liabilities_data[$row['nama_akun']] = 0;
                                                                }
                                                                $liabilities_data[$row['nama_akun']] += ($row['Kredit'] - $row['Debit']);
                                                            }
                                                        } else {
                                                            if ($row_year == $filter_year) {
                                                                if (!isset($liabilities_data[$row['nama_akun']])) {
                                                                    $liabilities_data[$row['nama_akun']] = 0;
                                                                }
                                                                $liabilities_data[$row['nama_akun']] += ($row['Kredit'] - $row['Debit']);
                                                            }
                                                        }
                                                    }
                                                }

                                                // Free result set
                                                $liabilities_result->free();
                                                $conn->next_result();

                                                // Display filtered liabilities and equity
                                                foreach ($liabilities_data as $account => $balance) {
                                                    if ($balance != 0) {
                                                        echo "<tr>";
                                                        echo "<td>" . $account . "</td>";
                                                        echo "<td class='right-align'>" . number_format($balance, 0, ',', '.') . "</td>";
                                                        echo "</tr>";
                                                        $total_liabilities_equity += $balance;
                                                    }
                                                }
                                            }

                                            // Calculate net income correctly
                                            $net_income = 0;
                                            $revenue = 0;
                                            $expenses = 0;

                                            // Get revenue (accounts starting with 4)
                                            $revenue_sql = "SELECT 
                                                SUM(IF(dt.debit_kredit = 'K', dt.nilai, 0)) as revenue
                                                FROM detail_transaksi dt
                                                JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
                                                JOIN akun a ON dt.id_akun = a.id_akun
                                                WHERE SUBSTRING(a.id_akun, 1, 1) = '4'
                                                AND YEAR(t.tgl_transaksi) = '$filter_year'";

                                            if ($filter_period == 'monthly' && !empty($filter_month)) {
                                                $revenue_sql .= " AND MONTH(t.tgl_transaksi) = '$filter_month'";
                                            }

                                            $revenue_result = $conn->query($revenue_sql);
                                            if ($revenue_result && $revenue_row = $revenue_result->fetch_assoc()) {
                                                $revenue = $revenue_row['revenue'];
                                            }
                                            if ($revenue_result) $revenue_result->free();

                                            // Get expenses (accounts starting with 5)
                                            $expenses_sql = "SELECT 
                                                SUM(IF(dt.debit_kredit = 'D', dt.nilai, 0)) as expenses
                                                FROM detail_transaksi dt
                                                JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
                                                JOIN akun a ON dt.id_akun = a.id_akun
                                                WHERE SUBSTRING(a.id_akun, 1, 1) = '5'
                                                AND YEAR(t.tgl_transaksi) = '$filter_year'";

                                            if ($filter_period == 'monthly' && !empty($filter_month)) {
                                                $expenses_sql .= " AND MONTH(t.tgl_transaksi) = '$filter_month'";
                                            }

                                            $expenses_result = $conn->query($expenses_sql);
                                            if ($expenses_result && $expenses_row = $expenses_result->fetch_assoc()) {
                                                $expenses = $expenses_row['expenses'];
                                            }
                                            if ($expenses_result) $expenses_result->free();

                                            $net_income = $revenue - $expenses;

                                            if ($net_income != 0) {
                                                echo "<tr>";
                                                echo "<td>Retained Earnings (Net Income)</td>";
                                                echo "<td class='right-align'>" . number_format($net_income, 0, ',', '.') . "</td>";
                                                echo "</tr>";
                                                $total_liabilities_equity += $net_income;
                                            }

                                            if (empty($liabilities_data) && $net_income == 0) {
                                                echo "<tr><td colspan='2'>No liabilities or equity found for the selected period</td></tr>";
                                            }
                                            ?>
                                            <tr class="table-light">
                                                <td class="bold-text">Total Liabilities & Equity</td>
                                                <td class="right-align bold-text"><?php echo number_format($total_liabilities_equity, 0, ',', '.'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Balance Notification -->
                <div class="mt-4">
                    <?php
                    $balance_diff = $total_assets - $total_liabilities_equity;
                    if ($balance_diff == 0) {
                        echo '<div class="alert alert-success">Trial Balance is balanced! (Assets = Liabilities + Equity)</div>';
                    } else {
                        echo '<div class="alert alert-danger">Trial Balance is not balanced! Difference: Rp ' . number_format(abs($balance_diff), 0, ',', '.') . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to toggle month visibility based on period selection -->
<script>
    function toggleMonthVisibility() {
        const periodSelect = document.getElementById('period');
        const monthSelector = document.getElementById('month-selector');

        if (periodSelect.value === 'yearly') {
            monthSelector.style.display = 'none';
        } else {
            monthSelector.style.display = 'block';
        }
    }
</script>