<?php

$edit_mode = false;
$account_id = '';
$account_name = '';
$account_type = '';

// Check if editing an existing account
if (isset($_GET['id'])) {
    $edit_mode = true;
    $id = intval($_GET['id']);

    $result = mysqli_query($conn, "SELECT id_akun, nama_akun, aktiva_pasiva FROM akun WHERE id_akun = '$id'");

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $account_id = $row['id_akun'];
        $account_name = $row['nama_akun'];
        $account_type = $row['aktiva_pasiva'];
    } else {
        $_SESSION['message'] = "Account not found.";
        $_SESSION['message_type'] = "danger";
        echo '<script>window.location.replace("index.php?page=accounts");</script>';
        exit;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account_id = $_POST['account_id'];
    $account_name = trim($_POST['account_name']);
    $account_type = $_POST['account_type'];

    // Validate inputs
    $errors = [];
    if (empty($account_id)) {
        $errors[] = "Account ID is required.";
    }
    if (empty($account_name)) {
        $errors[] = "Account Name is required.";
    }
    if (empty($account_type)) {
        $errors[] = "Account Type is required.";
    }

    // Simple check if account ID already exists (only for new accounts)
    if (!$edit_mode) {
        $check = mysqli_query($conn, "SELECT id_akun FROM akun WHERE id_akun = '$account_id'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "Account ID already exists. Please choose another ID.";
        }
    }

    if (empty($errors)) {
        if ($edit_mode) {
            // Update existing account
            mysqli_query($conn, "UPDATE akun SET nama_akun = '$account_name', aktiva_pasiva = '$account_type' WHERE id_akun = '$account_id'");
        } else {
            // Insert new account
            mysqli_query($conn, "INSERT INTO akun (id_akun, nama_akun, aktiva_pasiva) VALUES ('$account_id', '$account_name', '$account_type')");
        }

        if (mysqli_affected_rows($conn) > 0) {
            $_SESSION['message'] = $edit_mode ? "Account updated successfully!" : "Account created successfully!";
            $_SESSION['message_type'] = "success";
            echo '<script>window.location.replace("index.php?page=accounts");</script>';
            exit;
        } else {
            $_SESSION['message'] = "Error: " . mysqli_error($conn);
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "danger";
    }
}
?>

<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <?php echo $edit_mode ? 'Edit Account' : 'Add New Account'; ?>
            </div>
            <div class="card-body">
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

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="account_id" class="form-label">Account ID</label>
                        <input type="number" class="form-control" id="account_id" name="account_id"
                            value="<?php echo htmlspecialchars($account_id); ?>"
                            <?php echo $edit_mode ? 'readonly' : ''; ?> required>
                    </div>
                    <div class="mb-3">
                        <label for="account_name" class="form-label">Account Name</label>
                        <input type="text" class="form-control" id="account_name" name="account_name"
                            value="<?php echo htmlspecialchars($account_name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="account_type" class="form-label">Account Type</label>
                        <select class="form-select" id="account_type" name="account_type" required>
                            <option value="">Select Account Type</option>
                            <option value="A" <?php echo $account_type == 'A' ? 'selected' : ''; ?>>Assets</option>
                            <option value="P" <?php echo $account_type == 'P' ? 'selected' : ''; ?>>Liabilities/Equity</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_mode ? 'Update Account' : 'Create Account'; ?>
                    </button>
                    <a href="index.php?page=accounts" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>