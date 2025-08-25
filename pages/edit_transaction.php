<?php

// Check if ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "No transaction ID provided!";
    $_SESSION['message_type'] = "danger";
    echo '<script>window.location.replace("index.php?page=posting");</script>';
    exit;
}

$transaction_id = intval($_GET['id']);

// Fetch the transaction details
$transaction_query = "SELECT id_transaksi, tgl_transaksi, nama_transaksi, post, hapus FROM transaksi WHERE id_transaksi = ?";
$stmt = $conn->prepare($transaction_query);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Transaction not found!";
    $_SESSION['message_type'] = "danger";
    echo '<script>window.location.replace("index.php?page=posting");</script>';
    exit;
}

$transaction = $result->fetch_assoc();

// Check if transaction is posted - only unposted transactions can be edited
if ($transaction['post'] == '1') {
    $_SESSION['message'] = "Posted transactions cannot be edited. Please unpost the transaction first.";
    $_SESSION['message_type'] = "warning";
    echo '<script>window.location.replace("index.php?page=posting");</script>';
    exit;
}

// Check if transaction is deleted
if ($transaction['hapus'] == '1') {
    $_SESSION['message'] = "Deleted transactions cannot be edited. Please restore the transaction first.";
    $_SESSION['message_type'] = "warning";
    echo '<script>window.location.replace("index.php?page=posting");</script>';
    exit;
}

// Fetch transaction details
$details_query = "SELECT dt.id_detail_transaksi, dt.id_akun, a.nama_akun, dt.debit_kredit, dt.nilai 
                 FROM detail_transaksi dt 
                 JOIN akun a ON dt.id_akun = a.id_akun 
                 WHERE dt.id_transaksi = ? 
                 ORDER BY dt.id_detail_transaksi";
$stmt = $conn->prepare($details_query);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result_details = $stmt->get_result();

// Get all accounts for dropdown
$accounts_query = "SELECT id_akun, nama_akun FROM akun ORDER BY id_akun";
$accounts_result = $conn->query($accounts_query);
$accounts = [];
while ($row = $accounts_result->fetch_assoc()) {
    $accounts[$row['id_akun']] = $row['nama_akun'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();

    try {
        // Update transaction name
        $nama_transaksi = $_POST['nama_transaksi'];
        $tgl_transaksi = $_POST['tgl_transaksi'];

        $update_trans = "UPDATE transaksi SET nama_transaksi = ?, tgl_transaksi = ? WHERE id_transaksi = ?";
        $stmt = $conn->prepare($update_trans);
        $stmt->bind_param("ssi", $nama_transaksi, $tgl_transaksi, $transaction_id);
        $stmt->execute();

        // Update transaction details
        $account_ids = $_POST['account'];
        $debit_kredits = $_POST['debit_kredit'];
        $nilais = $_POST['nilai'];
        $detail_ids = $_POST['detail_id'];

        // Check if debits and credits balance
        $total_debit = 0;
        $total_kredit = 0;

        for ($i = 0; $i < count($detail_ids); $i++) {
            $nilai = (int)$nilais[$i];
            if ($debit_kredits[$i] == 'D') {
                $total_debit += $nilai;
            } else {
                $total_kredit += $nilai;
            }
        }

        if ($total_debit != $total_kredit) {
            throw new Exception("Total debits and credits must be equal! Debit: " . number_format($total_debit) . ", Credit: " . number_format($total_kredit));
        }

        // Update each detail
        for ($i = 0; $i < count($detail_ids); $i++) {
            $detail_id = $detail_ids[$i];
            $account_id = $account_ids[$i];
            $debit_kredit = $debit_kredits[$i];
            $nilai = $nilais[$i];

            $update_detail = "UPDATE detail_transaksi 
                             SET id_akun = ?, debit_kredit = ?, nilai = ? 
                             WHERE id_detail_transaksi = ? AND id_transaksi = ?";
            $stmt = $conn->prepare($update_detail);
            $stmt->bind_param("isiii", $account_id, $debit_kredit, $nilai, $detail_id, $transaction_id);
            $stmt->execute();
        }

        // Commit the transaction
        $conn->commit();

        $_SESSION['message'] = "Transaction #$transaction_id has been updated successfully!";
        $_SESSION['message_type'] = "success";
        echo "<script>window.location.href='index.php?page=posting';</script>";
        exit;
    } catch (Exception $e) {
        // Roll back the transaction in case of error
        $conn->rollback();

        $_SESSION['message'] = "Error updating transaction: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Edit Transaction #<?php echo $transaction_id; ?></h1>

        <?php
        // Display any session messages
        if (isset($_SESSION['message'])) {
            echo "<div class='alert alert-{$_SESSION['message_type']} alert-dismissible fade show' role='alert'>
                    {$_SESSION['message']}
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                  </div>";
            // Clear the message
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
        }
        ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transaction Details</h5>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="editTransactionForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tgl_transaksi" class="form-label">Transaction Date</label>
                            <input type="date" class="form-control" id="tgl_transaksi" name="tgl_transaksi"
                                value="<?php echo $transaction['tgl_transaksi']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nama_transaksi" class="form-label">Transaction Description</label>
                            <input type="text" class="form-control" id="nama_transaksi" name="nama_transaksi"
                                value="<?php echo htmlspecialchars($transaction['nama_transaksi']); ?>" required>
                        </div>
                    </div>

                    <h5 class="mt-4 mb-3">Transaction Entries</h5>

                    <div class="table-responsive">
                        <table class="table table-bordered" id="entriesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Account</th>
                                    <th width="120">Type</th>
                                    <th width="200">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_debit = 0;
                                $total_kredit = 0;

                                while ($detail = $result_details->fetch_assoc()) {
                                    if ($detail['debit_kredit'] == 'D') {
                                        $total_debit += $detail['nilai'];
                                    } else {
                                        $total_kredit += $detail['nilai'];
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="detail_id[]" value="<?php echo $detail['id_detail_transaksi']; ?>">
                                            <select name="account[]" class="form-select" required>
                                                <?php foreach ($accounts as $id => $name): ?>
                                                    <option value="<?php echo $id; ?>" <?php echo ($id == $detail['id_akun']) ? 'selected' : ''; ?>>
                                                        <?php echo $id . ' - ' . $name; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="debit_kredit[]" class="form-select debit-kredit" required>
                                                <option value="D" <?php echo ($detail['debit_kredit'] == 'D') ? 'selected' : ''; ?>>Debit</option>
                                                <option value="K" <?php echo ($detail['debit_kredit'] == 'K') ? 'selected' : ''; ?>>Credit</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="nilai[]" class="form-control nilai-input"
                                                value="<?php echo $detail['nilai']; ?>" min="1" required>
                                        </td>
                                    </tr>
                                <?php
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <th colspan="2" class="text-end">Total Debit:</th>
                                    <th id="total-debit"><?php echo number_format($total_debit); ?></th>
                                </tr>
                                <tr class="table-info">
                                    <th colspan="2" class="text-end">Total Credit:</th>
                                    <th id="total-kredit"><?php echo number_format($total_kredit); ?></th>
                                </tr>
                                <tr id="balance-row" class="<?php echo ($total_debit == $total_kredit) ? 'table-success' : 'table-danger'; ?>">
                                    <th colspan="2" class="text-end">Balance:</th>
                                    <th id="balance"><?php echo ($total_debit == $total_kredit) ? 'BALANCED' : 'NOT BALANCED'; ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="index.php?page=posting" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Transactions
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-muted">
                <small>
                    <i class="fas fa-info-circle me-1"></i>
                    Make sure the transaction is balanced (total debits = total credits) before saving.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Function to update totals
        function updateTotals() {
            const rows = document.querySelectorAll('#entriesTable tbody tr');
            let totalDebit = 0;
            let totalKredit = 0;

            rows.forEach(row => {
                const debitKredit = row.querySelector('.debit-kredit').value;
                const nilai = parseInt(row.querySelector('.nilai-input').value) || 0;

                if (debitKredit === 'D') {
                    totalDebit += nilai;
                } else {
                    totalKredit += nilai;
                }
            });

            // Update displays
            document.getElementById('total-debit').textContent = totalDebit.toLocaleString();
            document.getElementById('total-kredit').textContent = totalKredit.toLocaleString();

            // Check balance
            const balanceRow = document.getElementById('balance-row');
            const balanceDisplay = document.getElementById('balance');

            if (totalDebit === totalKredit) {
                balanceRow.className = 'table-success';
                balanceDisplay.textContent = 'BALANCED';
                document.getElementById('submitBtn').disabled = false;
            } else {
                balanceRow.className = 'table-danger';
                balanceDisplay.textContent = 'NOT BALANCED';
                document.getElementById('submitBtn').disabled = true;
            }
        }

        // Add event listeners for all input and select elements
        document.querySelectorAll('.nilai-input, .debit-kredit').forEach(element => {
            element.addEventListener('change', updateTotals);
            element.addEventListener('input', updateTotals);
        });

        // Form submission validation
        document.getElementById('editTransactionForm').addEventListener('submit', function(e) {
            // Get total debit and kredit
            const totalDebit = parseInt(document.getElementById('total-debit').textContent.replace(/,/g, ''));
            const totalKredit = parseInt(document.getElementById('total-kredit').textContent.replace(/,/g, ''));

            // Check if balanced
            if (totalDebit !== totalKredit) {
                e.preventDefault();
                alert('Transaction must be balanced before saving! Total Debit: ' +
                    totalDebit.toLocaleString() + ', Total Credit: ' + totalKredit.toLocaleString());
            }
        });

        // Initial calculation
        updateTotals();
    });
</script>