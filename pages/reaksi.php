<?php

// Handle reaction deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Check if the reaction is used in detail_reaksi
    $check_sql = "SELECT COUNT(*) as count FROM detail_reaksi WHERE id_reaksi = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $detail_count = $check_result->fetch_assoc()['count'];
    $check_stmt->close();

    if ($detail_count > 0) {
        $_SESSION['message'] = "Cannot delete reaction template. It is used in existing details.";
        $_SESSION['message_type'] = "danger";
    } else {
        $delete_sql = "DELETE FROM reaksi WHERE id_reaksi = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Reaction template deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting reaction template: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        $stmt->close();
    }

    echo '<script>window.location.replace("index.php?page=reaksi");</script>';
    exit;
}

// Fetch reactions
$sql = "SELECT id_reaksi, nama_reaksi FROM reaksi ORDER BY id_reaksi";
$result = $conn->query($sql);
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Transaction Templates Management</h1>

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
                Reaction Templates List
                <a href="index.php?page=reaksi_form" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> Add New Template
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Template Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['id_reaksi']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['nama_reaksi']) . "</td>";
                                    echo "<td>
                                            <div class='btn-group' role='group'>
                                                <a href='index.php?page=reaksi_form&id=" . $row['id_reaksi'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                                <a href='index.php?page=reaksi&delete=" . $row['id_reaksi'] . "' 
                                                   class='btn btn-sm btn-danger' 
                                                   onclick='return confirm(\"Are you sure you want to delete this template? This cannot be undone.\")'>Delete</a>
                                                <a href='index.php?page=reaksi_detail&id=" . $row['id_reaksi'] . "' class='btn btn-sm btn-info'>View Details</a>
                                            </div>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='3' class='text-center'>No reaction templates found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>