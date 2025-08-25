<?php

// Fetch transaction details
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Get transaction header
    $sql = "SELECT t.*, 
            CASE 
                WHEN t.hapus = '1' THEN 'Deleted'
                WHEN t.post = '1' THEN 'Posted'
                ELSE 'Unposted'
            END as status
            FROM transaksi t
            WHERE t.id_transaksi = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $transaction = $result->fetch_assoc();

        // Get transaction details
        $detail_sql = "SELECT dt.*, a.nama_akun 
                       FROM detail_transaksi dt
                       JOIN akun a ON dt.id_akun = a.id_akun
                       WHERE dt.id_transaksi = ?
                       ORDER BY dt.id_detail_transaksi";

        $detail_stmt = $conn->prepare($detail_sql);
        $detail_stmt->bind_param("i", $id);
        $detail_stmt->execute();
        $detail_result = $detail_stmt->get_result();
        $transaction_details = [];

        $total_debit = 0;
        $total_kredit = 0;

        while ($detail = $detail_result->fetch_assoc()) {
            $transaction_details[] = $detail;
            if ($detail['debit_kredit'] == 'D') {
                $total_debit += $detail['nilai'];
            } else {
                $total_kredit += $detail['nilai'];
            }
        }

        $detail_stmt->close();
    } else {
        $_SESSION['message'] = "Transaction not found!";
        $_SESSION['message_type'] = "danger";
        echo '<script>window.location.replace("index.php?page=posting");</script>';
        exit;
    }

    $stmt->close();
} else {
    $_SESSION['message'] = "No transaction ID provided!";
    $_SESSION['message_type'] = "danger";
    echo '<script>window.location.replace("index.php?page=posting");</script>';
    exit;
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Transaction Details</h1>
            <a href="index.php?page=posting" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Transactions
            </a>
        </div>

        <?php if (isset($transaction)): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Transaction #<?php echo $transaction['id_transaksi']; ?></h5>
                        <span class="badge <?php
                                            if ($transaction['hapus'] == '1') echo 'bg-danger';
                                            elseif ($transaction['post'] == '1') echo 'bg-success';
                                            else echo 'bg-warning';
                                            ?>">
                            <?php echo $transaction['status']; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">Date:</dt>
                                <dd class="col-sm-8"><?php echo date('d F Y', strtotime($transaction['tgl_transaksi'])); ?></dd>

                                <dt class="col-sm-4">Description:</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($transaction['nama_transaksi']); ?></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-4">Status:</dt>
                                <dd class="col-sm-8">
                                    <span class="badge <?php
                                                        if ($transaction['hapus'] == '1') echo 'bg-danger';
                                                        elseif ($transaction['post'] == '1') echo 'bg-success';
                                                        else echo 'bg-warning';
                                                        ?>">
                                        <?php echo $transaction['status']; ?>
                                    </span>
                                </dd>

                                <dt class="col-sm-4">Action:</dt>
                                <dd class="col-sm-8">
                                    <?php if ($transaction['hapus'] == '1'): ?>
                                        <!-- For deleted transactions -->
                                        <a href="index.php?page=posting&action=restore&id=<?php echo $transaction['id_transaksi']; ?>"
                                            class="btn btn-sm btn-success"
                                            onclick="return confirm('Are you sure you want to restore this transaction?')">
                                            <i class="fas fa-trash-restore me-1"></i> Restore Transaction
                                        </a>
                                    <?php else: ?>
                                        <?php if ($transaction['post'] == '0'): ?>
                                            <!-- For unposted transactions -->
                                            <div class="btn-group" role="group">
                                                <a href="index.php?page=input_transaction&id=<?php echo $transaction['id_transaksi']; ?>"
                                                    class="btn btn-sm btn-warning me-2">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                                <a href="index.php?page=posting&action=post&id=<?php echo $transaction['id_transaksi']; ?>"
                                                    class="btn btn-sm btn-success me-2"
                                                    onclick="return confirm('Are you sure you want to post this transaction? This will lock it from further edits.')">
                                                    <i class="fas fa-check-circle me-1"></i> Post
                                                </a>
                                                <a href="index.php?page=posting&action=delete&id=<?php echo $transaction['id_transaksi']; ?>"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to mark this transaction as deleted?')">
                                                    <i class="fas fa-trash me-1"></i> Delete
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <!-- For posted transactions -->
                                            <div class="btn-group" role="group">
                                                <a href="index.php?page=posting&action=unpost&id=<?php echo $transaction['id_transaksi']; ?>"
                                                    class="btn btn-sm btn-warning me-2"
                                                    onclick="return confirm('Are you sure you want to unpost this transaction? This will allow it to be edited again.')">
                                                    <i class="fas fa-undo me-1"></i> Unpost
                                                </a>
                                                <a href="index.php?page=posting&action=delete&id=<?php echo $transaction['id_transaksi']; ?>"
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Are you sure you want to mark this transaction as deleted?')">
                                                    <i class="fas fa-trash me-1"></i> Delete
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Transaction Line Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account</th>
                                    <th>Type</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($transaction_details)): ?>
                                    <?php foreach ($transaction_details as $index => $detail): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($detail['nama_akun']); ?></td>
                                            <td><?php echo $detail['debit_kredit'] == 'D' ? 'Debit' : 'Credit'; ?></td>
                                            <td class="text-end">
                                                <?php if ($detail['debit_kredit'] == 'D'): ?>
                                                    <?php echo number_format($detail['nilai'], 0, ',', '.'); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($detail['debit_kredit'] == 'K'): ?>
                                                    <?php echo number_format($detail['nilai'], 0, ',', '.'); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No transaction details found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <th colspan="3" class="text-end">Total</th>
                                    <th class="text-end"><?php echo number_format($total_debit, 0, ',', '.'); ?></th>
                                    <th class="text-end"><?php echo number_format($total_kredit, 0, ',', '.'); ?></th>
                                </tr>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <?php if ($total_debit == $total_kredit): ?>
                                            <span class="badge bg-success">Balanced</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Unbalanced (Difference: <?php echo number_format(abs($total_debit - $total_kredit), 0, ',', '.'); ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        Transaction ID: <?php echo $transaction['id_transaksi']; ?> |
                        Created: <?php echo date('d M Y', strtotime($transaction['tgl_transaksi'])); ?> |
                        Status: <?php echo $transaction['status']; ?>
                    </small>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                Transaction not found or has been removed.
            </div>
        <?php endif; ?>
    </div>
</div>