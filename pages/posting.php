<?php

// Handle transaction actions (post, unpost, delete, restore)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);

    // Determine which field to update based on the action
    $field = '';
    $value = '';
    $success_message = '';

    switch ($action) {
        case 'post':
            $field = 'post';
            $value = '1';
            $success_message = "Transaction #$id has been posted successfully!";
            break;
        case 'unpost':
            $field = 'post';
            $value = '0';
            $success_message = "Transaction #$id has been unposted successfully!";
            break;
        case 'delete':
            $field = 'hapus';
            $value = '1';
            $success_message = "Transaction #$id has been marked as deleted!";
            break;
        case 'restore':
            $field = 'hapus';
            $value = '0';
            $success_message = "Transaction #$id has been restored successfully!";
            break;
        default:
            $_SESSION['message'] = "Invalid action requested!";
            $_SESSION['message_type'] = "danger";
            echo '<script>window.location.replace("index.php?page=posting");</script>';
            exit;
    }

    // Prepare and execute the update query
    $update_sql = "UPDATE transaksi SET $field = ? WHERE id_transaksi = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $value, $id);

    if ($stmt->execute()) {
        $_SESSION['message'] = $success_message;
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating transaction: " . $stmt->error;
        $_SESSION['message_type'] = "danger";
    }

    $stmt->close();
    echo '<script>window.location.replace("index.php?page=posting");</script>';
    exit;
}

// Set default filter values
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'active_unposted';  // Changed default to 'active_unposted'
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the SQL query based on filters
$sql = "SELECT id_transaksi, tgl_transaksi, nama_transaksi, post, hapus FROM transaksi WHERE 1=1";

// Apply filters
switch ($filter_type) {
    case 'active_unposted':  // New filter for front page (active and unposted only)
        $sql .= " AND post = '0' AND hapus = '0'";
        break;
    case 'posted':
        $sql .= " AND post = '1' AND hapus = '0'";
        break;
    case 'unposted':
        $sql .= " AND post = '0' AND hapus = '0'";
        break;
    case 'deleted':
        $sql .= " AND hapus = '1'";
        break;
    case 'active':
        $sql .= " AND hapus = '0'";
        break;
        // 'all' shows everything, so no additional filter needed
}

// Apply search if provided
if (!empty($search_query)) {
    $search_term = "%" . $search_query . "%";
    $sql .= " AND (nama_transaksi LIKE ? OR CAST(id_transaksi AS CHAR) LIKE ?)";
}

// Add sorting
$sql .= " ORDER BY tgl_transaksi DESC, id_transaksi DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

// Bind search parameters if needed
if (!empty($search_query)) {
    $stmt->bind_param("ss", $search_term, $search_term);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Get transaction summary counts
$summary_sql = "SELECT 
    SUM(CASE WHEN post = '1' AND hapus = '0' THEN 1 ELSE 0 END) as posted_count,
    SUM(CASE WHEN post = '0' AND hapus = '0' THEN 1 ELSE 0 END) as unposted_count,
    SUM(CASE WHEN hapus = '1' THEN 1 ELSE 0 END) as deleted_count,
    COUNT(*) as total_count
FROM transaksi";

$summary_result = $conn->query($summary_sql);
$summary = $summary_result->fetch_assoc();
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Transaction Posting Management</h1>

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

        <!-- Transaction Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Posted Transactions</h5>
                        <p class="card-text display-6"><?php echo $summary['posted_count']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Unposted Transactions</h5>
                        <p class="card-text display-6"><?php echo $summary['unposted_count']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title">Deleted Transactions</h5>
                        <p class="card-text display-6"><?php echo $summary['deleted_count']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transaction List</h5>
                    <a href="index.php?page=input_transaction" class="btn btn-light btn-sm">
                        <i class="fas fa-plus"></i> New Transaction
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter and Search Form -->
                <form method="GET" action="index.php" class="mb-4">
                    <input type="hidden" name="page" value="posting">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select name="filter" class="form-select">
                                <option value="active_unposted" <?php echo $filter_type == 'active_unposted' ? 'selected' : ''; ?>>Active Unposted (Front Page View)</option>
                                <option value="all" <?php echo $filter_type == 'all' ? 'selected' : ''; ?>>All Transactions</option>
                                <option value="posted" <?php echo $filter_type == 'posted' ? 'selected' : ''; ?>>Posted Only</option>
                                <option value="unposted" <?php echo $filter_type == 'unposted' ? 'selected' : ''; ?>>Unposted Only</option>
                                <option value="active" <?php echo $filter_type == 'active' ? 'selected' : ''; ?>>Active Only (Not Deleted)</option>
                                <option value="deleted" <?php echo $filter_type == 'deleted' ? 'selected' : ''; ?>>Deleted Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by ID or description" value="<?php echo htmlspecialchars($search_query); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <a href="index.php?page=posting" class="btn btn-secondary w-100">
                                <i class="fas fa-sync"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                $row_number = 1; // Initialize row counter
                                while ($row = $result->fetch_assoc()) {
                                    // Determine transaction status
                                    $status = '';
                                    $status_class = '';

                                    if ($row['hapus'] == '1') {
                                        $status = 'Deleted';
                                        $status_class = 'bg-danger';
                                    } elseif ($row['post'] == '1') {
                                        $status = 'Posted';
                                        $status_class = 'bg-success';
                                    } else {
                                        $status = 'Unposted';
                                        $status_class = 'bg-warning';
                                    }

                                    echo "<tr" . ($row['hapus'] == '1' ? " class='table-danger'" : "") . ">";
                                    echo "<td>" . $row_number . "</td>";
                                    echo "<td>" . htmlspecialchars($row['id_transaksi']) . "</td>";
                                    echo "<td>" . htmlspecialchars(date('d M Y', strtotime($row['tgl_transaksi']))) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['nama_transaksi']) . "</td>";
                                    echo "<td><span class='badge $status_class'>$status</span></td>";
                                    echo "<td>";

                                    // Show different action buttons based on status
                                    if ($row['hapus'] == '1') {
                                        // For deleted transactions
                                        echo "<a href='index.php?page=posting&action=restore&id=" . $row['id_transaksi'] . "' 
                                                class='btn btn-sm btn-outline-success me-1' title='Restore'>
                                                <i class='fas fa-trash-restore'></i></a>";
                                    } else {
                                        // For active transactions
                                        if ($row['post'] == '0') {
                                            // Unposted: can edit, delete, and post
                                            echo "<a href='index.php?page=edit_transaction&id=" . $row['id_transaksi'] . "' 
                                                    class='btn btn-sm btn-outline-warning me-1' title='Edit'>
                                                    <i class='fas fa-edit'></i></a>";

                                            echo "<a href='index.php?page=posting&action=delete&id=" . $row['id_transaksi'] . "' 
                                                    class='btn btn-sm btn-outline-danger me-1' 
                                                    onclick='return confirm(\"Are you sure you want to mark this transaction as deleted?\")' title='Delete'>
                                                    <i class='fas fa-trash'></i></a>";

                                            echo "<a href='index.php?page=posting&action=post&id=" . $row['id_transaksi'] . "' 
                                                    class='btn btn-sm btn-outline-success' 
                                                    onclick='return confirm(\"Are you sure you want to post this transaction? This will lock it from further edits and remove it from the front page.\")' title='Post'>
                                                    <i class='fas fa-check-circle'></i></a>";
                                        } else {
                                            // Posted: can only delete and unpost
                                            echo "<a href='index.php?page=posting&action=delete&id=" . $row['id_transaksi'] . "' 
                                                    class='btn btn-sm btn-outline-danger me-1' 
                                                    onclick='return confirm(\"Are you sure you want to mark this transaction as deleted?\")' title='Delete'>
                                                    <i class='fas fa-trash'></i></a>";

                                            echo "<a href='index.php?page=posting&action=unpost&id=" . $row['id_transaksi'] . "' 
                                                    class='btn btn-sm btn-outline-warning' 
                                                    onclick='return confirm(\"Are you sure you want to unpost this transaction? This will allow it to be edited again and displayed on the front page.\")' title='Unpost'>
                                                    <i class='fas fa-undo'></i></a>";
                                        }
                                    }

                                    // View button for all transactions
                                    echo "<a href='index.php?page=view_transaction&id=" . $row['id_transaksi'] . "' 
                                            class='btn btn-sm btn-outline-info ms-1' title='View Details'>
                                            <i class='fas fa-eye'></i></a>";

                                    echo "</td>";
                                    echo "</tr>";
                                    $row_number++;
                                }
                            } else {
                                echo "<tr><td colspan='5' class='text-center'>No transactions found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted">
                <small>
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Posted</strong> transactions are locked from editing and hidden from the front page.
                    <strong>Deleted</strong> transactions are hidden from the front page but can be restored.
                    Only <strong>active unposted</strong> transactions appear on the front page.
                </small>
            </div>
        </div>
    </div>
</div>