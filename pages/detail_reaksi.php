<?php

// Check if id is provided
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "Reaction ID is required!";
    $_SESSION['message_type'] = "danger";
    echo '<script>window.location.replace("index.php?page=reaksi");</script>';
    exit;
}

$reaksi_id = intval($_GET['id']);

// Get reaction info
$sql_reaksi = "SELECT id_reaksi, nama_reaksi FROM reaksi WHERE id_reaksi = ?";
$stmt_reaksi = $conn->prepare($sql_reaksi);
$stmt_reaksi->bind_param("i", $reaksi_id);
$stmt_reaksi->execute();
$result_reaksi = $stmt_reaksi->get_result();

if ($result_reaksi->num_rows == 0) {
    $_SESSION['message'] = "Reaction template not found!";
    $_SESSION['message_type'] = "danger";
    echo '<script>window.location.replace("index.php?page=reaksi");</script>';
    exit;
}

$reaksi = $result_reaksi->fetch_assoc();
$stmt_reaksi->close();

// Handle detail deletion
if (isset($_GET['delete_detail'])) {
    $detail_id = intval($_GET['delete_detail']);

    $delete_sql = "DELETE FROM detail_reaksi WHERE id_detail_reaksi = ? AND id_reaksi = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("ii", $detail_id, $reaksi_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Detail deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting detail: " . $stmt->error;
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();

    echo '<script>window.location.replace("index.php?page=reaksi_detail&id=". $reaksi_id);</script>';
    exit;
}

// Handle detail addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_detail'])) {
    $akun_id = intval($_POST['id_akun']);
    $debit_kredit = $_POST['debit_kredit'];

    // Validate debit_kredit is either 'D' or 'K'
    if ($debit_kredit != 'D' && $debit_kredit != 'K') {
        $_SESSION['message'] = "Invalid debit/kredit value!";
        $_SESSION['message_type'] = "danger";
    } else {
        $sql = "INSERT INTO detail_reaksi (id_reaksi, id_akun, debit_kredit) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $reaksi_id, $akun_id, $debit_kredit);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Detail added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding detail: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }

    echo '<script>window.location.replace("index.php?page=reaksi_detail&id=". $reaksi_id);</script>';
    exit;
}

// Fetch reaction details
$sql_details = "SELECT dr.id_detail_reaksi, dr.id_akun, dr.debit_kredit, a.nama_akun 
                FROM detail_reaksi dr
                JOIN akun a ON dr.id_akun = a.id_akun
                WHERE dr.id_reaksi = ?
                ORDER BY dr.id_detail_reaksi";
$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $reaksi_id);
$stmt_details->execute();
$result_details = $stmt_details->get_result();
$details = [];
while ($row = $result_details->fetch_assoc()) {
    $details[] = $row;
}
$stmt_details->close();

// Fetch all accounts for the dropdown
$sql_accounts = "SELECT id_akun, nama_akun FROM akun ORDER BY id_akun";
$result_accounts = $conn->query($sql_accounts);
$accounts = [];
while ($row = $result_accounts->fetch_assoc()) {
    $accounts[] = $row;
}
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Reaction Template Details</h1>

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

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                Template Information
            </div>
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($reaksi['nama_reaksi']); ?></h5>
                <p class="card-text">ID: <?php echo htmlspecialchars($reaksi['id_reaksi']); ?></p>
                <a href="index.php?page=reaksi" class="btn btn-secondary">Back to Templates</a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                Add New Detail
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=reaksi_detail&id=<?php echo $reaksi_id; ?>" class="row g-3">
                    <div class="col-md-6">
                        <label for="id_akun" class="form-label">Account</label>
                        <select class="form-select" id="id_akun" name="id_akun" required>
                            <option value="">Select Account</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo $account['id_akun']; ?>">
                                    <?php echo htmlspecialchars($account['id_akun'] . ' - ' . $account['nama_akun']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="debit_kredit" class="form-label">Type</label>
                        <select class="form-select" id="debit_kredit" name="debit_kredit" required>
                            <option value="">Select Type</option>
                            <option value="D">Debit</option>
                            <option value="K">Credit</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_detail" class="btn btn-success">Add Detail</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-info text-white">
                Template Details
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Account</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (count($details) > 0) {
                                foreach ($details as $detail) {
                                    $type = $detail['debit_kredit'] == 'D' ? 'Debit' : 'Credit';
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($detail['id_detail_reaksi']) . "</td>";
                                    echo "<td>" . htmlspecialchars($detail['id_akun'] . ' - ' . $detail['nama_akun']) . "</td>";
                                    echo "<td>" . htmlspecialchars($type) . "</td>";
                                    echo "<td>
                                            <a href='index.php?page=reaksi_detail&id=" . $reaksi_id . "&delete_detail=" . $detail['id_detail_reaksi'] . "' 
                                               class='btn btn-sm btn-danger' 
                                               onclick='return confirm(\"Are you sure you want to delete this detail? This cannot be undone.\")'>Delete</a>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center'>No details found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>