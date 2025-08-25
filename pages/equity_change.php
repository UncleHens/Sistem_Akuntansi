<?php
include 'config/functions.php';

$filter_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : '';
$filter_period = isset($_GET['period']) ? $_GET['period'] : 'monthly';

$years_query = "SELECT DISTINCT tahun as year FROM closing ORDER BY year DESC";
$years_result = $conn->query($years_query);
$available_years = [];
while ($years_result && $year_row = $years_result->fetch_assoc()) {
    $available_years[] = $year_row['year'];
}
if (!in_array($filter_year, $available_years)) {
    $filter_year = date('Y');
}

$equity_data = [
    'awal' => 0,
    'laba_bersih' => 0,
    'prive' => 0,
    'modal_tambahan' => 0,
    'akhir' => 0,
    'period_closed' => false
];

$check_closed = "SELECT COUNT(*) as closed FROM closing WHERE tahun = $filter_year";
if ($filter_period == 'monthly' && !empty($filter_month)) {
    $check_closed .= " AND bulan = $filter_month";
}
$closed_result = $conn->query($check_closed);
if ($closed_result && $row = $closed_result->fetch_assoc()) {
    $equity_data['period_closed'] = $row['closed'] > 0;
}

if ($equity_data['period_closed']) {
    $where_bulan = ($filter_period == 'monthly' && !empty($filter_month)) ? " AND bulan = $filter_month" : '';

    $get_val = function ($jenis, $tipe, $is_sum = false) use ($conn, $filter_year, $where_bulan) {
        $field = $is_sum ? "SUM($tipe)" : $tipe;
        $sql = "SELECT $field as val FROM closing WHERE id_akun = 311 AND jenis_penyesuaian = '$jenis' AND tahun = $filter_year $where_bulan";
        $res = $conn->query($sql);
        $row = $res ? $res->fetch_assoc() : null;
        return $row ? floatval($row['val']) : 0;
    };

    if ($filter_period === 'monthly' && !empty($filter_month)) {
        if ($filter_month == 1) {
            $equity_data['awal'] = $get_val('modal_awal', 'kredit');
        } else {
            $prev_month = $filter_month - 1;
            $prev_year = $filter_year;
            if ($prev_month < 1) {
                $prev_month = 12;
                $prev_year -= 1;
            }
            $sql_prev = "SELECT kredit as modal_akhir FROM closing
                         WHERE id_akun = 311 AND jenis_penyesuaian = 'modal_akhir'
                         AND tahun = $prev_year AND bulan = $prev_month
                         ORDER BY tanggal_closing DESC LIMIT 1";
            $res_prev = $conn->query($sql_prev);
            $row_prev = $res_prev ? $res_prev->fetch_assoc() : null;
            $equity_data['awal'] = $row_prev ? floatval($row_prev['modal_akhir']) : 0;
        }

        $equity_data['modal_tambahan'] = $get_val('modal_tambahan', 'kredit', true);
        $equity_data['laba_bersih'] = $get_val('laba_rugi', 'CASE WHEN debit > 0 THEN -debit ELSE kredit END', true);
        $equity_data['prive'] = $get_val('prive', 'debit', true);
        $equity_data['akhir'] = $get_val('modal_akhir', 'kredit');
    }

    if ($filter_period === 'yearly') {
        // Modal awal: dari bulan Januari
        $sql_awal = "SELECT kredit as modal_awal FROM closing WHERE id_akun = 311 AND jenis_penyesuaian = 'modal_awal' AND tahun = $filter_year AND bulan = 1 LIMIT 1";
        $res_awal = $conn->query($sql_awal);
        $row_awal = $res_awal ? $res_awal->fetch_assoc() : null;
        $equity_data['awal'] = $row_awal ? floatval($row_awal['modal_awal']) : 0;

        // Modal tambahan, laba, prive, dan akhir dari seluruh tahun
        $equity_data['modal_tambahan'] = $get_val('modal_tambahan', 'kredit', true);
        $equity_data['laba_bersih'] = $get_val('laba_rugi', 'CASE WHEN debit > 0 THEN -debit ELSE kredit END', true);
        $equity_data['prive'] = $get_val('prive', 'debit', true);

        // Modal akhir: dari bulan Desember
        $sql_akhir = "SELECT kredit as modal_akhir FROM closing WHERE id_akun = 311 AND jenis_penyesuaian = 'modal_akhir' AND tahun = $filter_year AND bulan = 12 LIMIT 1";
        $res_akhir = $conn->query($sql_akhir);
        $row_akhir = $res_akhir ? $res_akhir->fetch_assoc() : null;
        $equity_data['akhir'] = $row_akhir ? floatval($row_akhir['modal_akhir']) : 0;
    }
}

function format_rp($number)
{
    return 'Rp ' . number_format($number, 0, ',', '.');
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Laporan Perubahan Modal</h1>

        <!-- Period Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Options</h5>
            </div>
            <div class="card-body">
                <form method="get" action="index.php" class="row g-3">
                    <input type="hidden" name="page" value="equity_change">

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

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Equity Change Statement -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Laporan Perubahan Modal</h4>
                <p class="mb-0">
                    Periode: <?= $filter_period == 'yearly' ? "Tahun $filter_year" : date('F Y', mktime(0, 0, 0, $filter_month ?: 1, 1, $filter_year)) ?>
                </p>
            </div>
            <div class="card-body">
                <?php if (!$equity_data['period_closed']): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Data untuk periode ini belum ditutup. Silakan lakukan proses closing terlebih dahulu.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Keterangan</th>
                                    <th class="text-end">Jumlah (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Modal Awal Periode</strong></td>
                                    <td class="text-end"><?= format_rp($equity_data['awal']) ?></td>
                                </tr>
                                <?php if ($equity_data['modal_tambahan'] > 0): ?>
                                    <tr>
                                        <td><strong>Tambahan Modal</strong></td>
                                        <td class="text-end"><?= format_rp($equity_data['modal_tambahan']) ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong>Laba/(Rugi) Bersih Periode Ini</strong></td>
                                    <td class="text-end"><?= format_rp($equity_data['laba_bersih']) ?></td>
                                </tr>
                                <?php if ($equity_data['prive'] > 0): ?>
                                    <tr>
                                        <td><strong>Prive/Pengambilan Pemilik</strong></td>
                                        <td class="text-end">(<?= format_rp($equity_data['prive']) ?>)</td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="table-primary">
                                    <td><strong>Modal Akhir Periode</strong></td>
                                    <td class="text-end"><?= format_rp($equity_data['akhir']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Print Button -->
                    <div class="mt-4">
                        <button onclick="window.print()" class="btn btn-success">
                            <i class="fas fa-print me-2"></i>Cetak Laporan
                        </button>
                    </div>
                <?php endif; ?>
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

<style>
    @media print {
        body * {
            visibility: hidden;
        }

        .card,
        .card * {
            visibility: visible;
        }

        .card {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            border: none;
        }

        .no-print {
            display: none !important;
        }

        .table-primary {
            background-color: #cfe2ff !important;
        }
    }
</style>