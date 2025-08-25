<?php

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_reaksi = $_POST['nama_reaksi'];
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;

    if ($id) {
        // Update existing reaction
        $sql = "UPDATE reaksi SET nama_reaksi = ? WHERE id_reaksi = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nama_reaksi, $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Reaction template updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating reaction template: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
    } else {
        // Get the next available id_reaksi
        $sql = "SELECT MAX(id_reaksi) + 1 as next_id FROM reaksi";
        $result = $conn->query($sql);
        $next_id = 1; // Default if table is empty

        if ($result && $row = $result->fetch_assoc()) {
            $next_id = $row['next_id'] ? $row['next_id'] : 1;
        }

        // Insert new reaction with the next ID
        $sql = "INSERT INTO reaksi (id_reaksi, nama_reaksi) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $next_id, $nama_reaksi);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Reaction template added successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding reaction template: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
    }

    $stmt->close();
    echo '<script>window.location.replace("index.php?page=reaksi");</script>';
    exit;
}

// Check if we're editing
$editing = false;
$reaction = [
    'id_reaksi' => '',
    'nama_reaksi' => ''
];

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT id_reaksi, nama_reaksi FROM reaksi WHERE id_reaksi = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $reaction = $result->fetch_assoc();
        $editing = true;
    } else {
        $_SESSION['message'] = "Reaction template not found!";
        $_SESSION['message_type'] = "danger";
        echo '<script>window.location.replace("index.php?page=reaksi");</script>';
        exit;
    }
    $stmt->close();
}
?>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <h1 class="mb-4"><?php echo $editing ? 'Edit' : 'Add New'; ?> Reaction Template</h1>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <?php echo $editing ? 'Edit Reaction Template' : 'New Reaction Template'; ?>
            </div>
            <div class="card-body">
                <form method="post" action="index.php?page=reaksi_form">
                    <?php if ($editing): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($reaction['id_reaksi']); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nama_reaksi" class="form-label">Template Name</label>
                        <input type="text" class="form-control" id="nama_reaksi" name="nama_reaksi"
                            value="<?php echo htmlspecialchars($reaction['nama_reaksi']); ?>" required>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php?page=reaksi" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <?php echo $editing ? 'Update' : 'Add'; ?> Reaction Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>