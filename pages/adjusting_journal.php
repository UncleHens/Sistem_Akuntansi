<?php
include "config/functions.php";

// Get current user ID from session
$user_id = $_SESSION['user_id'] ?? 0;

// Get filter parameters
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : date('n');
$filter_year = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : date('Y');

// Check if period is closed
$is_period_closed = false;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM closing WHERE bulan = ? AND tahun = ?");
$stmt->bind_param("ii", $filter_month, $filter_year);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$is_period_closed = ($row['count'] > 0);

// Process adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'process_adjustment') {
            $journal_id = (int)$_POST['journal_id'];
            $adjustment_date = $_POST['adjustment_date'];
            $amount = (float)$_POST['amount'];
            $description = $_POST['description'];

            try {
                $conn->begin_transaction();

                // 1. Get the adjustment journal details
                $stmt = $conn->prepare("SELECT jp.*, pb.id as balance_id, pb.account_id, pb.expense_account_id, 
                                        pb.original_amount, pb.adjusted_amount, pb.remaining_amount,
                                        pb.adjustments_made, pb.total_adjustments_needed
                                        FROM jurnal_penyesuaian jp
                                        LEFT JOIN prepaid_balances pb ON jp.prepaid_balance_id = pb.id
                                        WHERE jp.id = ?");
                $stmt->bind_param("i", $journal_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $journal = $result->fetch_assoc();
                $stmt->close();

                if (!$journal) {
                    throw new Exception("Journal not found");
                }

                // 2. Create the actual adjustment transaction
                $stmt = $conn->prepare("INSERT INTO transaksi 
                    (tgl_transaksi, nama_transaksi, post, penyesuaian) 
                    VALUES (?, ?, '1', '1')");
                $stmt->bind_param("ss", $adjustment_date, $description);
                $stmt->execute();
                $adjustment_trans_id = $stmt->insert_id;
                $stmt->close();

                // 3. Create debit entry (expense account)
                $stmt = $conn->prepare("INSERT INTO detail_transaksi 
                    (id_transaksi, id_akun, debit_kredit, nilai, penyesuaian) 
                    VALUES (?, ?, 'D', ?, '1')");
                $stmt->bind_param("iid", $adjustment_trans_id, $journal['akun_debit'], $amount);
                $stmt->execute();
                $stmt->close();

                // 4. Create credit entry (prepaid account)
                $stmt = $conn->prepare("INSERT INTO detail_transaksi 
                    (id_transaksi, id_akun, debit_kredit, nilai, penyesuaian) 
                    VALUES (?, ?, 'K', ?, '1')");
                $stmt->bind_param("iid", $adjustment_trans_id, $journal['akun_kredit'], $amount);
                $stmt->execute();
                $stmt->close();

                // 5. Update the prepaid balance record
                $new_adjusted = $journal['adjusted_amount'] + $amount;
                $new_remaining = $journal['remaining_amount'] - $amount;
                $new_adjustments_made = $journal['adjustments_made'] + 1;

                $status = ($new_adjustments_made >= $journal['total_adjustments_needed']) ? 'fully_adjusted' : 'active';

                $stmt = $conn->prepare("UPDATE prepaid_balances 
                    SET adjusted_amount = ?, 
                        remaining_amount = ?, 
                        adjustments_made = ?,
                        status = ?,
                        next_adjustment_date = DATE_ADD(next_adjustment_date, INTERVAL 1 MONTH)
                    WHERE id = ?");
                $stmt->bind_param("ddisi", $new_adjusted, $new_remaining, $new_adjustments_made, $status, $journal['balance_id']);
                $stmt->execute();
                $stmt->close();

                // 6. Update the journal entry
                $stmt = $conn->prepare("UPDATE jurnal_penyesuaian 
                    SET jumlah = ?, 
                        keterangan = ?, 
                        referensi_jurnal = ?, 
                        tanggal_eksekusi = NOW(), 
                        status = 'processed' 
                    WHERE id = ?");
                $stmt->bind_param("dsii", $amount, $description, $adjustment_trans_id, $journal_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $_SESSION['message'] = "Adjustment processed successfully";
                $_SESSION['success'] = true;
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "Error: " . $e->getMessage();
                $_SESSION['success'] = false;
            }
            echo '<script>window.location.replace("index.php?page=adjusting_journal");</script>';
            exit;
        } elseif ($_POST['action'] === 'delete_adjustment') {
            $journal_id = (int)$_POST['journal_id'];

            try {
                $conn->begin_transaction();

                // Check if journal exists and is pending
                $stmt = $conn->prepare("SELECT status FROM jurnal_penyesuaian WHERE id = ?");
                $stmt->bind_param("i", $journal_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $journal = $result->fetch_assoc();
                $stmt->close();

                if (!$journal) {
                    throw new Exception("Journal not found");
                }

                if ($journal['status'] === 'processed') {
                    throw new Exception("Cannot delete a processed adjustment");
                }

                // Delete the journal entry
                $stmt = $conn->prepare("DELETE FROM jurnal_penyesuaian WHERE id = ?");
                $stmt->bind_param("i", $journal_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $_SESSION['message'] = "Adjustment deleted successfully";
                $_SESSION['success'] = true;
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "Error: " . $e->getMessage();
                $_SESSION['success'] = false;
            }
            echo '<script>window.location.replace("index.php?page=adjusting_journal");</script>';
            exit;
        }
    }
}

// Get adjustment journals
$adjustment_journals = [];
$sql = "SELECT jp.*, 
        a_debit.nama_akun as nama_akun_debit,
        a_kredit.nama_akun as nama_akun_kredit,
        u.username as dibuat_oleh,
        t.nama_transaksi as nama_transaksi_asal,
        pb.status as prepaid_status
        FROM jurnal_penyesuaian jp
        LEFT JOIN akun a_debit ON jp.akun_debit = a_debit.id_akun
        LEFT JOIN akun a_kredit ON jp.akun_kredit = a_kredit.id_akun
        LEFT JOIN users u ON jp.dibuat_oleh = u.id
        LEFT JOIN transaksi t ON jp.referensi_jurnal = t.id_transaksi
        LEFT JOIN prepaid_balances pb ON jp.prepaid_balance_id = pb.id
        WHERE MONTH(jp.tanggal) = ?
        AND YEAR(jp.tanggal) = ?
        AND (pb.status IS NULL OR pb.status != 'fully_adjusted')
        ORDER BY jp.tanggal DESC, jp.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $filter_month, $filter_year);
$stmt->execute();
$result = $stmt->get_result();
$adjustment_journals = $result->fetch_all(MYSQLI_ASSOC);

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
            <h1 class="mb-4">Adjusting Journal Entries</h1>

            <?php if ($is_period_closed): ?>
                <div class="alert alert-warning">
                    This period has been closed. No adjustments can be made.
                </div>
            <?php endif; ?>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Filter</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row">
                        <input type="hidden" name="page" value="adjusting_journal">

                        <div class="col-md-3">
                            <label class="form-label">Year</label>
                            <select name="filter_year" class="form-select">
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= $year ?>" <?= ($filter_year == $year) ? 'selected' : '' ?>><?= $year ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Month</label>
                            <select name="filter_month" class="form-select">
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

            <!-- Adjustment Journals Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Adjustment Journals for <?= date('F Y', mktime(0, 0, 0, $filter_month, 1, $filter_year)) ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($adjustment_journals)): ?>
                        <div class="alert alert-info">
                            No adjustment journals found for this period.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Debit Account</th>
                                        <th>Credit Account</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Original Transaction</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adjustment_journals as $journal): ?>
                                        <tr>
                                            <td><?= date('d/m/Y', strtotime($journal['tanggal'])) ?></td>
                                            <td><?= $journal['keterangan'] ?></td>
                                            <td><?= $journal['akun_debit'] ?> - <?= $journal['nama_akun_debit'] ?></td>
                                            <td><?= $journal['akun_kredit'] ?> - <?= $journal['nama_akun_kredit'] ?></td>
                                            <td class="text-end">Rp <?= number_format($journal['jumlah'], 0, ',', '.') ?></td>
                                            <td>
                                                <?php if ($journal['status'] === 'processed'): ?>
                                                    <span class="badge bg-success">Processed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $journal['referensi_jurnal'] ? $journal['referensi_jurnal'] . ' - ' . $journal['nama_transaksi_asal'] : 'N/A' ?>
                                            </td>
                                            <td>
                                                <?php if ($journal['status'] !== 'processed' && !$is_period_closed): ?>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                        data-bs-target="#processModal"
                                                        onclick="setProcessData(<?= $journal['id'] ?>, '<?= $journal['keterangan'] ?>', <?= $journal['jumlah'] ?>)">
                                                        Process
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                        data-bs-target="#deleteModal"
                                                        onclick="setDeleteData(<?= $journal['id'] ?>)">
                                                        Delete
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Process Adjustment Modal -->
<div class="modal fade" id="processModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Process Adjustment</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="process_adjustment">
                <input type="hidden" name="journal_id" id="modal_journal_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Adjustment Date</label>
                        <input type="date" name="adjustment_date" class="form-control" id="modal_adjustment_date"
                            value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" id="modal_amount" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" id="modal_description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Adjustment Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="delete_adjustment">
                <input type="hidden" name="journal_id" id="delete_journal_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete this adjustment journal?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function setProcessData(journalId, description, amount) {
        document.getElementById('modal_journal_id').value = journalId;
        document.getElementById('modal_description').value = description;
        document.getElementById('modal_amount').value = amount;
        document.getElementById('modal_adjustment_date').value = '<?= date('Y-m-d') ?>';
    }

    function setDeleteData(journalId) {
        document.getElementById('delete_journal_id').value = journalId;
    }
</script>