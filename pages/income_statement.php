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

// Build date filters
if ($filter_period == 'monthly' && !empty($filter_month)) {
    // Get the period dates for display
    $start_date = date('Y-m-01', strtotime("$filter_year-$filter_month-01"));
    $end_date = date('Y-m-t', strtotime("$filter_year-$filter_month-01"));
} else {
    // Get the period dates for display
    $start_date = "$filter_year-01-01";
    $end_date = "$filter_year-12-31";
}

// Call the GetLabaRugi stored procedure
$result = $conn->query("CALL GetLabaRugi()");

// Initialize arrays to store filtered data
$revenue_accounts = [];
$expense_accounts = [];
$total_revenue = 0;
$total_expenses = 0;

// Process results and apply filters
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Parse year and month from the bln field (format is "YYYY MMM")
        $date_parts = explode(' ', $row['bln']);
        $row_year = $date_parts[0];
        $row_month = date('n', strtotime($date_parts[1])); // Convert month abbreviation to number

        // Apply year filter
        if ($row_year != $filter_year) continue;

        // Apply month filter if in monthly mode
        if ($filter_period == 'monthly' && !empty($filter_month) && $row_month != $filter_month) continue;

        $account_name = $row['nama_akun'];
        $debit = $row['Debit'];
        $kredit = $row['Kredit'];

        // Revenue accounts (4xx)
        if (strpos($account_name, 'Revenue') !== false) {
            if (!isset($revenue_accounts[$account_name])) {
                $revenue_accounts[$account_name] = 0;
            }
            // For revenue: credit increases, debit decreases
            $revenue_accounts[$account_name] += ($kredit - $debit);
        }

        // Expense accounts (5xx)
        else if (strpos($account_name, 'Expense') !== false) {
            if (!isset($expense_accounts[$account_name])) {
                $expense_accounts[$account_name] = 0;
            }
            // For expenses: debit increases, credit decreases
            $expense_accounts[$account_name] += ($debit - $kredit);
        }
    }
}

// Calculate totals
foreach ($revenue_accounts as $amount) {
    $total_revenue += $amount;
}

foreach ($expense_accounts as $amount) {
    $total_expenses += $amount;
}

// Calculate net income/loss
$net_income = $total_revenue - $total_expenses;
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Income Statement</h1>

        <!-- Period Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Options</h5>
            </div>
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3">
                    <input type="hidden" name="page" value="income_statement">

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
        <div class="alert alert-info">
            <strong>Viewing: </strong>
            <?php
            if ($filter_period == 'monthly' && !empty($filter_month)) {
                $month_name = date('F', mktime(0, 0, 0, $filter_month, 1));
                echo $month_name . ' ' . $filter_year;
            } else {
                echo 'Full Year ' . $filter_year;
            }
            ?>
        </div>


        <div class="card">
            <div class="card-body">
                <h4>For the Period: <?php echo date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)); ?></h4>

                <div class="row">
                    <!-- Revenue and Expenses Tables - Modified for alignment -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Revenues</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0"> <!-- Added mb-0 to remove margin -->
                                    <thead>
                                        <tr>
                                            <th>Account</th>
                                            <th class="text-end">Amount (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $revenue_count = count($revenue_accounts);
                                        $expense_count = count($expense_accounts);
                                        $max_rows = max($revenue_count, $expense_count);

                                        if (!empty($revenue_accounts)):
                                            $i = 0;
                                            foreach ($revenue_accounts as $account_name => $amount):
                                                $i++;
                                        ?>
                                                <tr>
                                                    <td><?php echo $account_name; ?></td>
                                                    <td class="text-end"><?php echo number_format($amount, 0, ',', '.'); ?></td>
                                                </tr>
                                            <?php
                                            endforeach;
                                            // Add empty rows if needed
                                            while ($i < $max_rows) {
                                                echo '<tr><td>&nbsp;</td><td class="text-end">&nbsp;</td></tr>';
                                                $i++;
                                            }
                                        else:
                                            ?>
                                            <tr>
                                                <td colspan="2">No revenue found</td>
                                            </tr>
                                            <?php
                                            // Add empty rows if needed
                                            for ($i = 1; $i < $max_rows; $i++) {
                                                echo '<tr><td>&nbsp;</td><td class="text-end">&nbsp;</td></tr>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <td class="fw-bold">Total Revenue</td>
                                            <td class="text-end fw-bold"><?php echo number_format($total_revenue, 0, ',', '.'); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">Expenses</h5>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0"> <!-- Added mb-0 to remove margin -->
                                    <thead>
                                        <tr>
                                            <th>Account</th>
                                            <th class="text-end">Amount (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if (!empty($expense_accounts)):
                                            $i = 0;
                                            foreach ($expense_accounts as $account_name => $amount):
                                                $i++;
                                        ?>
                                                <tr>
                                                    <td><?php echo $account_name; ?></td>
                                                    <td class="text-end"><?php echo number_format($amount, 0, ',', '.'); ?></td>
                                                </tr>
                                            <?php
                                            endforeach;
                                            // Add empty rows if needed
                                            while ($i < $max_rows) {
                                                echo '<tr><td>&nbsp;</td><td class="text-end">&nbsp;</td></tr>';
                                                $i++;
                                            }
                                        else:
                                            ?>
                                            <tr>
                                                <td colspan="2">No expenses found</td>
                                            </tr>
                                            <?php
                                            // Add empty rows if needed
                                            for ($i = 1; $i < $max_rows; $i++) {
                                                echo '<tr><td>&nbsp;</td><td class="text-end">&nbsp;</td></tr>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <td class="fw-bold">Total Expenses</td>
                                            <td class="text-end fw-bold"><?php echo number_format($total_expenses, 0, ',', '.'); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Net Income Summary -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header <?php echo ($net_income >= 0) ? 'bg-success' : 'bg-danger'; ?> text-white">
                                <h5 class="mb-0">Net <?php echo ($net_income >= 0) ? 'Profit' : 'Loss'; ?></h5>
                            </div>
                            <div class="card-body text-center">
                                <h3 class="<?php echo ($net_income >= 0) ? 'text-success' : 'text-danger'; ?>">
                                    Rp <?php echo number_format(abs($net_income), 0, ',', '.'); ?>
                                </h3>
                                <p>
                                    <?php if ($net_income >= 0): ?>
                                        <i class="fas fa-arrow-up text-success"></i>
                                        The company is profitable for this period
                                    <?php else: ?>
                                        <i class="fas fa-arrow-down text-danger"></i>
                                        The company is operating at a loss for this period
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Visualization -->
                <div class="row mt-5">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                Income Statement Summary
                            </div>
                            <div class="card-body">
                                <div class="progress mb-2" style="height: 30px;">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: 100%;"
                                        aria-valuenow="<?php echo $total_revenue; ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="<?php echo $total_revenue; ?>">
                                        Revenue: Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?>
                                    </div>
                                </div>

                                <div class="progress mb-2" style="height: 30px;">
                                    <div class="progress-bar bg-danger" role="progressbar"
                                        style="width: <?php echo ($total_revenue > 0) ? (($total_expenses / $total_revenue) * 100) : 0; ?>%;"
                                        aria-valuenow="<?php echo $total_expenses; ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="<?php echo $total_revenue; ?>">
                                        Expenses: Rp <?php echo number_format($total_expenses, 0, ',', '.'); ?>
                                    </div>
                                </div>

                                <div class="progress" style="height: 30px;">
                                    <div class="progress-bar <?php echo ($net_income >= 0) ? 'bg-warning' : 'bg-danger'; ?>" role="progressbar"
                                        style="width: <?php echo ($total_revenue > 0) ? (abs($net_income) / $total_revenue) * 100 : 0; ?>%;"
                                        aria-valuenow="<?php echo abs($net_income); ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="<?php echo $total_revenue; ?>">
                                        <?php echo ($net_income >= 0) ? 'Net Income: ' : 'Net Loss: '; ?>
                                        Rp <?php echo number_format(abs($net_income), 0, ',', '.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                Key Performance Indicators
                            </div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Profit Margin
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $total_revenue > 0 ? number_format(($net_income / $total_revenue) * 100, 2) : 0; ?>%
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Expense Ratio
                                        <span class="badge bg-primary rounded-pill">
                                            <?php echo $total_revenue > 0 ? number_format(($total_expenses / $total_revenue) * 100, 2) : 0; ?>%
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rest of the code remains the same -->

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