<?php
include "config/functions.php";

// Inisialisasi variabel
$message = '';
$success = false;
$reaksi_list = [];
$accounts = [];

// Ambil daftar akun
$sql = "SELECT * FROM akun ORDER BY id_akun";
$result = $conn->query($sql);
if ($result) {
    $accounts = $result->fetch_all(MYSQLI_ASSOC);
}

// Ambil daftar template transaksi (reaksi)
$sql = "SELECT r.*, dr.id_akun, dr.debit_kredit 
        FROM reaksi r 
        LEFT JOIN detail_reaksi dr ON r.id_reaksi = dr.id_reaksi 
        ORDER BY r.id_reaksi, dr.id_detail_reaksi";
$result = $conn->query($sql);
if ($result) {
    $temp_reaksi = [];
    while ($row = $result->fetch_assoc()) {
        $id = $row['id_reaksi'];
        if (!isset($temp_reaksi[$id])) {
            $temp_reaksi[$id] = [
                'id_reaksi' => $row['id_reaksi'],
                'nama_reaksi' => $row['nama_reaksi'],
                'template_details' => []
            ];
        }
        if ($row['id_akun']) {
            $temp_reaksi[$id]['template_details'][] = [
                'id_akun' => $row['id_akun'],
                'debit_kredit' => $row['debit_kredit']
            ];
        }
    }
    $reaksi_list = array_values($temp_reaksi);
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_transaction'])) {
    // Validasi input
    $tgl_transaksi = $_POST['tgl_transaksi'];
    $nama_transaksi = $_POST['nama_transaksi'];
    $needs_adjustment = isset($_POST['needs_adjustment']) ? 1 : 0;
    $adjustment_start_date = $_POST['adjustment_start_date'] ?? null;
    $adjustment_end_date = $_POST['adjustment_end_date'] ?? null;

    $debit_accounts = $_POST['debit_account'] ?? [];
    $debit_amounts = $_POST['debit_amount'] ?? [];
    $credit_accounts = $_POST['credit_account'] ?? [];
    $credit_amounts = $_POST['credit_amount'] ?? [];

    // Validasi jumlah debit dan kredit
    $total_debit = array_sum($debit_amounts);
    $total_credit = array_sum($credit_amounts);

    if ($total_debit !== $total_credit) {
        $message = "Transaksi tidak seimbang. Total debit: " . number_format($total_debit) .
            ", Total kredit: " . number_format($total_credit);
        $success = false;
    } else {
        // Mulai transaksi database
        $conn->begin_transaction();

        try {
            // 1. Insert ke tabel transaksi
            $sql = "INSERT INTO transaksi (tgl_transaksi, nama_transaksi, post, hapus, 
                    needs_adjustment, adjustment_start_date, adjustment_end_date) 
                    VALUES (?, ?, '0', '0', ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssiss",
                $tgl_transaksi,
                $nama_transaksi,
                $needs_adjustment,
                $adjustment_start_date,
                $adjustment_end_date
            );
            $stmt->execute();
            $id_transaksi = $stmt->insert_id;
            $stmt->close();

            // 2. Insert detail transaksi debit
            foreach ($debit_accounts as $index => $id_akun) {
                $nilai = $debit_amounts[$index];
                $sql = "INSERT INTO detail_transaksi (id_transaksi, id_akun, debit_kredit, nilai) 
                        VALUES (?, ?, 'D', ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iid", $id_transaksi, $id_akun, $nilai);
                $stmt->execute();
                $stmt->close();
            }

            // 3. Insert detail transaksi kredit
            foreach ($credit_accounts as $index => $id_akun) {
                $nilai = $credit_amounts[$index];
                $sql = "INSERT INTO detail_transaksi (id_transaksi, id_akun, debit_kredit, nilai) 
                        VALUES (?, ?, 'K', ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iid", $id_transaksi, $id_akun, $nilai);
                $stmt->execute();
                $stmt->close();
            }

            // 4. Jika needs_adjustment, buat entri di prepaid_balances dan jurnal_penyesuaian
            if ($needs_adjustment && $adjustment_start_date && $adjustment_end_date) {
                // Calculate monthly adjustment amount and period
                $start_date = new DateTime($adjustment_start_date);
                $end_date = new DateTime($adjustment_end_date);
                $interval = $start_date->diff($end_date);
                $months = ($interval->y * 12) + $interval->m + 1; // Include both start and end months

                // For prepaid expenses, we should have exactly 1 debit account (the prepaid account)
                $prepaid_account = $debit_accounts[0];
                $total_amount = $debit_amounts[0];
                $monthly_amount = $total_amount / $months;

                // Find the corresponding expense account
                $expense_account = null;
                $stmt = $conn->prepare("SELECT expense_account_id FROM prepaid_adjustment_templates WHERE prepaid_account_id = ?");
                $stmt->bind_param("i", $prepaid_account);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $expense_account = $row['expense_account_id'];
                }
                $stmt->close();

                if (!$expense_account) {
                    throw new Exception("No matching expense account found for prepaid account");
                }

                // Create prepaid balance record
                $sql = "INSERT INTO prepaid_balances 
                        (account_id, expense_account_id, current_balance, 
                         original_transaction_id, original_amount, adjusted_amount, 
                         remaining_amount, adjustments_made, total_adjustments_needed,
                         period_covered, period_unit, adjustment_date, next_adjustment_date, status) 
                        VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?, ?, 'month', ?, ?, 'active')";
                $stmt = $conn->prepare($sql);

                $next_adjustment_date = date('Y-m-d', strtotime('+1 month', strtotime($adjustment_start_date)));

                $stmt->bind_param(
                    "iiiddiiiss",
                    $prepaid_account,
                    $expense_account,
                    $total_amount, // current_balance
                    $id_transaksi,
                    $total_amount, // original_amount
                    $total_amount, // remaining_amount
                    $months, // total_adjustments_needed
                    $months, // period_covered
                    $tgl_transaksi,
                    $next_adjustment_date
                );
                $stmt->execute();
                $prepaid_balance_id = $stmt->insert_id;
                $stmt->close();

                // Create adjustment journal entries for each month
                $current_date = new DateTime($adjustment_start_date);
                $end_date = new DateTime($adjustment_end_date);

                while ($current_date <= $end_date) {
                    $adjustment_date = $current_date->format('Y-m-d');

                    $sql = "INSERT INTO jurnal_penyesuaian 
                            (tanggal, akun_debit, akun_kredit, jumlah, keterangan, 
                             referensi_jurnal, prepaid_balance_id, dibuat_oleh, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql);

                    $keterangan = "Penyesuaian bulanan untuk $nama_transaksi (Bulan " . $current_date->format('F Y') . ")";

                    $stmt->bind_param(
                        "siidsiii",
                        $adjustment_date,
                        $expense_account,
                        $prepaid_account,
                        $monthly_amount,
                        $keterangan,
                        $id_transaksi,
                        $prepaid_balance_id,
                        $_SESSION['user_id']
                    );
                    $stmt->execute();
                    $stmt->close();

                    $current_date->modify('+1 month');
                }
            }

            // Commit transaksi
            $conn->commit();

            $message = "Transaksi berhasil disimpan" . ($needs_adjustment ? " dan akan diproses di jurnal penyesuaian" : "");
            $success = true;
        } catch (Exception $e) {
            // Rollback jika ada error
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $success = false;
        }
    }
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="mb-4">Input Transaksi Baru</h1>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $success ? 'success' : 'danger' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Transaksi</label>
                                <input type="date" name="tgl_transaksi" class="form-control"
                                    value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Keterangan Transaksi</label>
                                <input type="text" name="nama_transaksi" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check mt-4">
                                    <input type="checkbox" name="needs_adjustment" id="needs_adjustment"
                                        class="form-check-input" value="1">
                                    <label for="needs_adjustment" class="form-check-label">Needs Adjustment</label>
                                </div>
                            </div>
                        </div>

                        <div id="adjustmentFields" style="display:none;">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Adjustment Start Date</label>
                                    <input type="date" name="adjustment_start_date" class="form-control"
                                        value="<?= date('Y-m-d', strtotime('+1 month')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Adjustment End Date</label>
                                    <input type="date" name="adjustment_end_date" class="form-control"
                                        value="<?= date('Y-m-d', strtotime('+2 month')) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Transaction Template Section -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Template Transaksi (Opsional)</label>
                                <select id="transaction_template" class="form-select">
                                    <option value="">-- Pilih Template --</option>
                                    <?php foreach ($reaksi_list as $reaksi): ?>
                                        <option value="<?= $reaksi['id_reaksi'] ?>"><?= $reaksi['nama_reaksi'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h4>Debit</h4>
                                <div id="debitEntries">
                                    <div class="row mb-2">
                                        <div class="col-md-8">
                                            <select name="debit_account[]" class="form-select" required>
                                                <option value="">Pilih Akun</option>
                                                <?php foreach ($accounts as $account): ?>
                                                    <option value="<?= $account['id_akun'] ?>">
                                                        <?= $account['id_akun'] ?> - <?= $account['nama_akun'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" name="debit_amount[]" class="form-control" min="0" required>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="addDebit" class="btn btn-sm btn-primary">+ Tambah Debit</button>
                            </div>

                            <div class="col-md-6">
                                <h4>Kredit</h4>
                                <div id="creditEntries">
                                    <div class="row mb-2">
                                        <div class="col-md-8">
                                            <select name="credit_account[]" class="form-select" required>
                                                <option value="">Pilih Akun</option>
                                                <?php foreach ($accounts as $account): ?>
                                                    <option value="<?= $account['id_akun'] ?>">
                                                        <?= $account['id_akun'] ?> - <?= $account['nama_akun'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" name="credit_amount[]" class="form-control" min="0" required>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="addCredit" class="btn btn-sm btn-primary">+ Tambah Kredit</button>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h3>Total Debit: <span id="totalDebit">0</span></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h3>Total Kredit: <span id="totalCredit">0</span></h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-12">
                                <div id="balanceStatus" class="alert d-none"></div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" name="submit_transaction" class="btn btn-primary">Simpan Transaksi</button>
                            <a href="index.php?page=journal" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle adjustment fields
        document.getElementById('needs_adjustment').addEventListener('change', function() {
            document.getElementById('adjustmentFields').style.display = this.checked ? 'block' : 'none';
        });

        // Add debit row
        document.getElementById('addDebit').addEventListener('click', function() {
            const newRow = document.querySelector('#debitEntries .row').cloneNode(true);
            newRow.querySelectorAll('input, select').forEach(el => el.value = '');
            document.getElementById('debitEntries').appendChild(newRow);
        });

        // Add credit row
        document.getElementById('addCredit').addEventListener('click', function() {
            const newRow = document.querySelector('#creditEntries .row').cloneNode(true);
            newRow.querySelectorAll('input, select').forEach(el => el.value = '');
            document.getElementById('creditEntries').appendChild(newRow);
        });

        // Template selection
        document.getElementById('transaction_template').addEventListener('change', function() {
            const templateId = this.value;
            if (!templateId) return;

            const template = <?= json_encode($reaksi_list) ?>.find(t => t.id_reaksi == templateId);
            if (!template) return;

            // Clear existing rows (except first one)
            document.querySelectorAll('#debitEntries .row:not(:first-child)').forEach(el => el.remove());
            document.querySelectorAll('#creditEntries .row:not(:first-child)').forEach(el => el.remove());

            // Clear first row values
            document.querySelector('#debitEntries .row').querySelectorAll('input, select').forEach(el => el.value = '');
            document.querySelector('#creditEntries .row').querySelectorAll('input, select').forEach(el => el.value = '');

            // Group by debit/credit
            const debitAccounts = template.template_details.filter(d => d.debit_kredit === 'D');
            const creditAccounts = template.template_details.filter(d => d.debit_kredit === 'K');

            // Populate debit entries
            if (debitAccounts.length > 0) {
                const firstDebitRow = document.querySelector('#debitEntries .row');
                firstDebitRow.querySelector('select').value = debitAccounts[0].id_akun;

                for (let i = 1; i < debitAccounts.length; i++) {
                    const newRow = firstDebitRow.cloneNode(true);
                    newRow.querySelector('select').value = debitAccounts[i].id_akun;
                    document.getElementById('debitEntries').appendChild(newRow);
                }
            }

            // Populate credit entries
            if (creditAccounts.length > 0) {
                const firstCreditRow = document.querySelector('#creditEntries .row');
                firstCreditRow.querySelector('select').value = creditAccounts[0].id_akun;

                for (let i = 1; i < creditAccounts.length; i++) {
                    const newRow = firstCreditRow.cloneNode(true);
                    newRow.querySelector('select').value = creditAccounts[i].id_akun;
                    document.getElementById('creditEntries').appendChild(newRow);
                }
            }

            // Calculate totals
            calculateTotals();
        });

        // Calculate totals
        function calculateTotals() {
            let totalDebit = 0;
            let totalCredit = 0;

            document.querySelectorAll('input[name="debit_amount[]"]').forEach(input => {
                totalDebit += parseFloat(input.value) || 0;
            });

            document.querySelectorAll('input[name="credit_amount[]"]').forEach(input => {
                totalCredit += parseFloat(input.value) || 0;
            });

            document.getElementById('totalDebit').textContent = totalDebit.toLocaleString();
            document.getElementById('totalCredit').textContent = totalCredit.toLocaleString();

            const balanceStatus = document.getElementById('balanceStatus');
            if (totalDebit > 0 && totalCredit > 0) {
                balanceStatus.classList.remove('d-none');
                if (totalDebit === totalCredit) {
                    balanceStatus.classList.remove('alert-danger');
                    balanceStatus.classList.add('alert-success');
                    balanceStatus.textContent = '✓ Transaksi seimbang';
                } else {
                    balanceStatus.classList.remove('alert-success');
                    balanceStatus.classList.add('alert-danger');
                    balanceStatus.textContent = '⚠ Transaksi tidak seimbang. Selisih: ' +
                        Math.abs(totalDebit - totalCredit).toLocaleString();
                }
            } else {
                balanceStatus.classList.add('d-none');
            }
        }

        // Recalculate totals when amounts change
        document.addEventListener('input', function(e) {
            if (e.target.name === 'debit_amount[]' || e.target.name === 'credit_amount[]') {
                calculateTotals();
            }
        });
    });
</script>