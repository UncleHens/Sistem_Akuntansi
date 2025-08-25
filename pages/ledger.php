<?php
// Get filter parameters
$filter_year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'monthly'; // 'monthly' or 'yearly'
$accountFilter = isset($_GET['account']) ? $_GET['account'] : null;

// Determine available years from transaction data
$years_query = "SELECT DISTINCT YEAR(tgl_transaksi) as year FROM transaksi ORDER BY year DESC";
$years_result = $conn->query($years_query);
$available_years = [];
if ($years_result->num_rows > 0) {
    while ($year_row = $years_result->fetch_assoc()) {
        $available_years[] = $year_row['year'];
    }
}
// Free the result
$years_result->close();

// If no years found or current filter year is not in list, use current year
if (empty($available_years) || !in_array($filter_year, $available_years)) {
    $filter_year = date('Y');
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">General Ledger</h1>

        <!-- Period Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Options</h5>
            </div>
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3">
                    <input type="hidden" name="page" value="ledger">
                    <?php if ($accountFilter): ?>
                        <input type="hidden" name="account" value="<?php echo $accountFilter; ?>">
                    <?php endif; ?>

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
                <div class="mb-4">
                    <select id="accountFilter" class="form-select" onchange="window.location.href='index.php?page=ledger&account='+this.value+'&period=<?php echo $filter_period; ?>&year=<?php echo $filter_year; ?>&month=<?php echo $filter_month; ?>'">
                        <option value="">All Accounts</option>
                        <?php
                        // Get account list
                        $sql = "SELECT * FROM akun ORDER BY id_akun";
                        $result = $conn->query($sql);

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $selected = ($accountFilter == $row['id_akun']) ? 'selected' : '';
                                echo "<option value='" . $row['id_akun'] . "' " . $selected . ">" . $row['id_akun'] . " - " . $row['nama_akun'] . "</option>";
                            }
                            $result->close();
                        }
                        ?>
                    </select>
                </div>

                <?php
                // Call the GetBukuBesar stored procedure
                $result = $conn->query("CALL GetBukuBesar()");

                // Filter the results based on user's criteria
                $ledgerData = [];
                $accountDetails = [];

                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        // Apply year filter
                        $rowYear = substr($row['bulan'], 0, 4);
                        if ($rowYear != $filter_year) {
                            continue;
                        }

                        // Apply month filter if in monthly mode
                        if ($filter_period == 'monthly' && !empty($filter_month)) {
                            $monthMap = [
                                'Jan' => 1,
                                'Feb' => 2,
                                'Mar' => 3,
                                'Apr' => 4,
                                'May' => 5,
                                'Jun' => 6,
                                'Jul' => 7,
                                'Aug' => 8,
                                'Sep' => 9,
                                'Oct' => 10,
                                'Nov' => 11,
                                'Dec' => 12
                            ];
                            $rowMonth = $monthMap[substr($row['bulan'], -3)];
                            if ($rowMonth != $filter_month) {
                                continue;
                            }
                        }

                        // Group data by account
                        if (!isset($ledgerData[$row['nama_akun']])) {
                            $ledgerData[$row['nama_akun']] = [];
                        }

                        $ledgerData[$row['nama_akun']][] = $row;
                    }

                    // Free the result
                    $result->close();

                    // Clear any remaining results from the stored procedure
                    while ($conn->more_results()) {
                        $conn->next_result();
                        if ($res = $conn->store_result()) {
                            $res->free();
                        }
                    }
                }

                // Get account details if we need them for filtering
                if ($accountFilter) {
                    $sql = "SELECT id_akun, nama_akun, aktiva_pasiva FROM akun";
                    $result = $conn->query($sql);
                    while ($acct = $result->fetch_assoc()) {
                        $accountDetails[$acct['id_akun']] = $acct;
                    }
                    $result->close();
                }

                // Display either a single account or all accounts
                if ($accountFilter) {
                    // Get the account name
                    if (isset($accountDetails[$accountFilter])) {
                        $account = $accountDetails[$accountFilter];
                        echo "<h3>" . $accountFilter . " - " . $account['nama_akun'] . "</h3>";

                        // Determine normal balance
                        $normalBalance = ($account['aktiva_pasiva'] == 'A') ? 'D' : 'K';

                        echo "<div class='table-responsive'>
                                <table class='table table-striped table-hover'>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th class='right-align'>Debit (Rp)</th>
                                            <th class='right-align'>Credit (Rp)</th>
                                            <th class='right-align'>Balance (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody>";

                        $balance = 0;
                        $accountName = $account['nama_akun'];

                        if (isset($ledgerData[$accountName]) && !empty($ledgerData[$accountName])) {
                            foreach ($ledgerData[$accountName] as $row) {
                                // Calculate running balance
                                if ($row['Debit'] > 0) {
                                    if ($normalBalance == 'D') {
                                        $balance += $row['Debit'];
                                    } else {
                                        $balance -= $row['Debit'];
                                    }
                                    $debit = $row['Debit'];
                                    $credit = '';
                                } else {
                                    if ($normalBalance == 'K') {
                                        $balance += $row['Kredit'];
                                    } else {
                                        $balance -= $row['Kredit'];
                                    }
                                    $debit = '';
                                    $credit = $row['Kredit'];
                                }

                                echo "<tr>";
                                echo "<td>" . $row['bulan'] . " " . $row['tanggal'] . "</td>";
                                echo "<td>" . $row['nama_transaksi'] . "</td>";
                                echo "<td class='right-align'>" . ($debit ? number_format($debit, 0, ',', '.') : '') . "</td>";
                                echo "<td class='right-align'>" . ($credit ? number_format($credit, 0, ',', '.') : '') . "</td>";
                                echo "<td class='right-align'>" . number_format(abs($balance), 0, ',', '.') . " " .
                                    ($balance < 0 ? ($normalBalance == 'D' ? 'Kr' : 'Dr') : ($normalBalance == 'D' ? 'Dr' : 'Kr')) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No transactions found for this account in the selected period</td></tr>";
                        }

                        echo "</tbody></table></div>";
                    } else {
                        echo "<div class='alert alert-danger'>Invalid account selected</div>";
                    }
                } else {
                    // Display all accounts summary
                    // First, get all accounts
                    $sql = "SELECT * FROM akun ORDER BY id_akun";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        echo "<div class='accordion' id='ledgerAccordion'>";

                        while ($accountRow = $result->fetch_assoc()) {
                            $accountId = $accountRow['id_akun'];
                            $accountName = $accountRow['nama_akun'];
                            $normalBalance = ($accountRow['aktiva_pasiva'] == 'A') ? 'D' : 'K';

                            // Initialize totals
                            $balance = 0;
                            $debitTotal = 0;
                            $creditTotal = 0;

                            // Check if we have transactions for this account
                            $hasTransactions = isset($ledgerData[$accountName]) && !empty($ledgerData[$accountName]);

                            if ($hasTransactions) {
                                foreach ($ledgerData[$accountName] as $txRow) {
                                    if ($txRow['Debit'] > 0) {
                                        $debitTotal += $txRow['Debit'];
                                        if ($normalBalance == 'D') {
                                            $balance += $txRow['Debit'];
                                        } else {
                                            $balance -= $txRow['Debit'];
                                        }
                                    } else {
                                        $creditTotal += $txRow['Kredit'];
                                        if ($normalBalance == 'K') {
                                            $balance += $txRow['Kredit'];
                                        } else {
                                            $balance -= $txRow['Kredit'];
                                        }
                                    }
                                }
                            }

                            echo "<div class='accordion-item'>
                                    <h2 class='accordion-header' id='heading" . $accountId . "'>
                                        <button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' 
                                                data-bs-target='#collapse" . $accountId . "' aria-expanded='false' aria-controls='collapse" . $accountId . "'>
                                            <strong>" . $accountId . " - " . $accountName . "</strong>
                                            <span class='ms-auto'>Balance: Rp " . number_format(abs($balance), 0, ',', '.') . " " .
                                ($balance < 0 ? ($normalBalance == 'D' ? 'Kr' : 'Dr') : ($normalBalance == 'D' ? 'Dr' : 'Kr')) . "</span>
                                        </button>
                                    </h2>
                                    <div id='collapse" . $accountId . "' class='accordion-collapse collapse' aria-labelledby='heading" . $accountId . "' data-bs-parent='#ledgerAccordion'>
                                        <div class='accordion-body'>
                                            <div class='table-responsive'>
                                                <table class='table table-sm table-bordered'>
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Description</th>
                                                            <th class='right-align'>Debit (Rp)</th>
                                                            <th class='right-align'>Credit (Rp)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>";

                            if ($hasTransactions) {
                                foreach ($ledgerData[$accountName] as $txRow) {
                                    echo "<tr>
                                            <td>" . $txRow['bulan'] . " " . $txRow['tanggal'] . "</td>
                                            <td>" . $txRow['nama_transaksi'] . "</td>
                                            <td class='right-align'>" . ($txRow['Debit'] > 0 ? number_format($txRow['Debit'], 0, ',', '.') : '') . "</td>
                                            <td class='right-align'>" . ($txRow['Kredit'] > 0 ? number_format($txRow['Kredit'], 0, ',', '.') : '') . "</td>
                                        </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4'>No transactions found for this account in the selected period</td></tr>";
                            }

                            echo "<tr class='table-light'>
                                    <td colspan='2' class='right-align bold-text'>Total</td>
                                    <td class='right-align bold-text'>" . number_format($debitTotal, 0, ',', '.') . "</td>
                                    <td class='right-align bold-text'>" . number_format($creditTotal, 0, ',', '.') . "</td>
                                </tr>";

                            echo "</tbody></table>
                                    </div>
                                    <div class='mt-2'>
                                        <a href='index.php?page=ledger&account=" . $accountId . "&period=" . $filter_period . "&year=" . $filter_year . "&month=" . $filter_month . "' class='btn btn-sm btn-primary'>View Full Ledger</a>
                                    </div>
                                </div>
                            </div>
                        </div>";
                        }

                        echo "</div>";
                        $result->close();
                    } else {
                        echo "<div class='alert alert-info'>No accounts found</div>";
                    }
                }
                ?>
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