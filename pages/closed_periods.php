<?php

// Process manual closing if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_closing'])) {
    $month = $_POST['month'];
    $year = $_POST['year'];
    $posted_by = $_SESSION['user_id']; // Ambil ID user dari session

    try {
        // Check if period is already closed
        $check_sql = "SELECT 1 FROM closing WHERE bulan = ? AND tahun = ? LIMIT 1";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ii", $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $_SESSION['message'] = "Period ini sudah ditutup sebelumnya!";
            $_SESSION['message_type'] = "warning";
        } else {
            // Call the stored procedure dengan parameter posted_by
            $conn->query("CALL proses_closing($month, $year, $posted_by)");

            $_SESSION['message'] = "Period berhasil ditutup!";
            $_SESSION['message_type'] = "success";
        }
    } catch (Exception $e) {
        $_SESSION['message'] = "Error menutup period: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    // Redirect back to the same page with the same parameters
    echo '<script>window.location.replace("index.php?page=closed_periods");</script>';
    exit;
}

// Process reopening if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_reopening'])) {
    $month = $_POST['reopen_month'];
    $year = $_POST['reopen_year'];

    try {
        // Call the reopening stored procedure
        $conn->query("CALL proses_reopening($month, $year)");

        $_SESSION['message'] = "Period berhasil dibuka kembali!";
        $_SESSION['message_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['message'] = "Error membuka period: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    // Redirect back to the same page with the same parameters
    echo '<script>window.location.replace("index.php?page=closed_periods");</script>';
    exit;
}

// Check if viewing details
$view_details = isset($_GET['view_details']) && $_GET['view_details'] == 'true';
$detail_month = isset($_GET['detail_month']) ? intval($_GET['detail_month']) : 0;
$detail_year = isset($_GET['detail_year']) ? intval($_GET['detail_year']) : 0;

// Get current month and year
$current_month = date('n');
$current_year = date('Y');

// Get closed periods - CORRECTED QUERY
$closed_periods_sql = "SELECT DISTINCT 
                       bulan, 
                       tahun,
                       COUNT(*) as total_entries,
                       MIN(tanggal_closing) as tanggal_closing
                       FROM closing 
                       GROUP BY bulan, tahun
                       ORDER BY tahun DESC, bulan DESC";
$closed_periods = $conn->query($closed_periods_sql);

// If viewing details, get closing entries for that period
if ($view_details && $detail_month > 0 && $detail_year > 0) {
    $closing_details_sql = "SELECT * FROM closing WHERE bulan = ? AND tahun = ? ORDER BY id_closing";
    $stmt = $conn->prepare($closing_details_sql);
    $stmt->bind_param("ii", $detail_month, $detail_year);
    $stmt->execute();
    $closing_details = $stmt->get_result();

    // Get month name
    $month_names = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    $detail_month_name = $month_names[$detail_month];
}
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($view_details && $detail_month > 0 && $detail_year > 0): ?>
            <!-- Closing Details View -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Detail Jurnal Penutup <?php echo $detail_month_name . ' ' . $detail_year; ?></h1>
                <a href="index.php?page=closed_periods" class="btn btn-secondary">Kembali ke Daftar Period</a>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Entri Jurnal Penutup</h5>
                </div>
                <div class="card-body">
                    <?php if ($closing_details->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Akun</th>
                                        <th>Jenis Akun</th>
                                        <th>Debit</th>
                                        <th>Kredit</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_debit = 0;
                                    $total_credit = 0;

                                    while ($entry = $closing_details->fetch_assoc()):
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

                                        $total_debit += $entry['debit'];
                                        $total_credit += $entry['kredit'];
                                    ?>
                                        <tr>
                                            <td><?php echo $account_name; ?></td>
                                            <td>
                                                <?php
                                                $account_type = isset($entry['jenis_penyesuaian'])
                                                    ? ucfirst(str_replace('_', ' ', $entry['jenis_penyesuaian']))
                                                    : 'Tidak diketahui';
                                                echo $account_type;
                                                ?>
                                            </td>
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
                        <div class="alert alert-info">Tidak ada entri jurnal penutup untuk period ini.</div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Main Closed Periods View -->
            <h1 class="mb-4">Manajemen Period Closing</h1>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['message']); ?>
                <?php unset($_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Manual Closing Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">🔒 Tutup Period Akuntansi</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="month" class="form-label">Bulan</label>
                                <select class="form-select" id="month" name="month" required>
                                    <?php
                                    $months = [
                                        1 => 'Januari',
                                        2 => 'Februari',
                                        3 => 'Maret',
                                        4 => 'April',
                                        5 => 'Mei',
                                        6 => 'Juni',
                                        7 => 'Juli',
                                        8 => 'Agustus',
                                        9 => 'September',
                                        10 => 'Oktober',
                                        11 => 'November',
                                        12 => 'Desember'
                                    ];
                                    foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $num == $current_month ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="year" class="form-label">Tahun</label>
                                <select class="form-select" id="year" name="year" required>
                                    <?php for ($y = $current_year - 2; $y <= $current_year + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <strong>⚠️ Peringatan:</strong> Menutup period akan mencegah transaksi baru dimasukkan untuk period tersebut.
                            Pastikan semua transaksi sudah diinput dan diposting dengan benar.
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="submit_closing" class="btn btn-primary">
                                <i class="fas fa-lock"></i> Tutup Period
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Closed Periods List -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">📊 Daftar Period yang Sudah Ditutup</h5>
                </div>
                <div class="card-body">
                    <?php if ($closed_periods->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Period</th>
                                        <th>Tanggal Closing</th>
                                        <th>Total Entries</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    while ($row = $closed_periods->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td>
                                                <strong><?php echo $months[$row['bulan']] . ' ' . $row['tahun']; ?></strong>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_closing'])); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $row['total_entries']; ?> entries</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-lock"></i> Ditutup
                                                </span>
                                            </td>
                                            <td>
                                                <form method="post" style="display: inline;"
                                                    onsubmit="return confirm('Yakin ingin membuka period <?php echo $months[$row['bulan']] . ' ' . $row['tahun']; ?>?')">
                                                    <input type="hidden" name="reopen_month" value="<?php echo $row['bulan']; ?>">
                                                    <input type="hidden" name="reopen_year" value="<?php echo $row['tahun']; ?>">
                                                    <button type="submit" name="submit_reopening" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-unlock"></i> Buka
                                                    </button>
                                                </form>

                                                <a href="index.php?page=closed_periods&view_details=true&detail_month=<?php echo $row['bulan']; ?>&detail_year=<?php echo $row['tahun']; ?>"
                                                    class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                            <h5>Belum Ada Period yang Ditutup</h5>
                            <p>Silakan tutup period terlebih dahulu menggunakan form di atas.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>