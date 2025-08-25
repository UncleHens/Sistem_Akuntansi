<?php

// Handle account deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Also check for usage in detail_reaksi
    $check_transaksi_sql = "SELECT COUNT(*) as count FROM detail_transaksi WHERE id_akun = ?";
    $check_reaksi_sql = "SELECT COUNT(*) as count FROM detail_reaksi WHERE id_akun = ?";

    // Check detail_transaksi
    $check_stmt = $conn->prepare($check_transaksi_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $transaction_count = $check_result->fetch_assoc()['count'];
    $check_stmt->close();

    // Check detail_reaksi
    $check_reaksi_stmt = $conn->prepare($check_reaksi_sql);
    $check_reaksi_stmt->bind_param("i", $id);
    $check_reaksi_stmt->execute();
    $check_reaksi_result = $check_reaksi_stmt->get_result();
    $reaksi_count = $check_reaksi_result->fetch_assoc()['count'];
    $check_reaksi_stmt->close();

    if ($transaction_count > 0 || $reaksi_count > 0) {
        $_SESSION['message'] = "Cannot delete account. It is used in existing transactions or templates.";
        $_SESSION['message_type'] = "danger";
    } else {
        $delete_sql = "DELETE FROM akun WHERE id_akun = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Account deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting account: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }

    echo '<script>window.location.replace("index.php?page=accounts");</script>';
    exit;
}

// Fetch accounts
$sql = "SELECT id_akun, nama_akun, aktiva_pasiva FROM akun ORDER BY id_akun";
$result = $conn->query($sql);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Accounts Management</h1>

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
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                Account List
                <a href="index.php?page=accounts_form" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> Add New Account
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Account ID</th>
                                <th>Account Name</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $account_type = $row['aktiva_pasiva'] == 'A' ? 'Assets' : ($row['aktiva_pasiva'] == 'P' ? 'Liabilities/Equity' : 'Unknown');
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['id_akun']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['nama_akun']) . "</td>";
                                    echo "<td>" . htmlspecialchars($account_type) . "</td>";
                                    echo "<td>
                                            <div class='btn-group' role='group'>
                                                <a href='index.php?page=accounts_form&id=" . $row['id_akun'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                                <a href='index.php?page=accounts&delete=" . $row['id_akun'] . "' 
                                                   class='btn btn-sm btn-danger' 
                                                   onclick='return confirm(\"Are you sure you want to delete this account? This cannot be undone.\")'>Delete</a>
                                            </div>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center'>No accounts found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>