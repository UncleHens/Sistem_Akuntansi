<?php
// Get month and year from URL
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;

if ($month < 1 || $month > 12 || $year < 2000) {
    die("Invalid period specified");
}

// Get closing entries for this period
$closing_query = "SELECT * FROM closing WHERE bulan = ? AND tahun = ? ORDER BY id_closing";
$stmt = $conn->prepare($closing_query);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$closing_result = $stmt->get_result();

// Get month name
$month_name = date('F', mktime(0, 0, 0, $month, 1));
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Closing Entries for <?php echo $month_name . ' ' . $year; ?></h1>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Closing Journal Entries</h5>
            </div>
            <div class="card-body">
                <?php if ($closing_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Account Type</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_debit = 0;
                                $total_credit = 0;

                                while ($entry = $closing_result->fetch_assoc()):
                                    // Get account name
                                    $account_name = $entry['id_akun'];
                                    if ($entry['id_akun'] != '399') { // Skip ikhtisar laba rugi
                                        $account_query = "SELECT nama_akun FROM akun WHERE id_akun = ?";
                                        $account_stmt = $conn->prepare($account_query);
                                        $account_stmt->bind_param("i", $entry['id_akun']);
                                        $account_stmt->execute();
                                        $account_result = $account_stmt->get_result();
                                        if ($account_result->num_rows > 0) {
                                            $account_name = $account_result->fetch_assoc()['nama_akun'];
                                        }
                                    } else {
                                        $account_name = "Ikhtisar Laba Rugi";
                                    }

                                    // Get account type from 'jenis_penyesuaian'
                                    $account_type = isset($entry['jenis_penyesuaian'])
                                        ? ucfirst(str_replace('_', ' ', $entry['jenis_penyesuaian']))
                                        : 'Tidak diketahui';

                                    $total_debit += $entry['debit'];
                                    $total_credit += $entry['kredit'];
                                ?>
                                    <tr>
                                        <td><?php echo $account_name; ?></td>
                                        <td><?php echo $account_type; ?></td>
                                        <td><?php echo number_format($entry['debit'], 2); ?></td>
                                        <td><?php echo number_format($entry['kredit'], 2); ?></td>
                                        <td><?php echo $entry['keterangan']; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr class="table-secondary fw-bold">
                                    <td colspan="2">Total</td>
                                    <td><?php echo number_format($total_debit, 2); ?></td>
                                    <td><?php echo number_format($total_credit, 2); ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No closing entries found for this period.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <a href="index.php?page=closed_periods" class="btn btn-secondary">Back to Closed Periods</a>
        </div>
    </div>
</div>