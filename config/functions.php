<?php
function getPrepaidBalance($conn, $account_id, $as_of_date)
{
  $query = "SELECT 
                SUM(CASE WHEN dt.debit_kredit = 'D' THEN dt.nilai ELSE 0 END) as total_debit,
                SUM(CASE WHEN dt.debit_kredit = 'K' THEN dt.nilai ELSE 0 END) as total_kredit
              FROM 
                detail_transaksi dt
              JOIN 
                transaksi t ON dt.id_transaksi = t.id_transaksi
              WHERE 
                dt.id_akun = ? AND 
                t.tgl_transaksi <= ? AND
                t.hapus = '0'";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("is", $account_id, $as_of_date);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  return $row['total_debit'] - $row['total_kredit'];
}

function getAccountName($conn, $account_id)
{
  $query = "SELECT nama_akun FROM akun WHERE id_akun = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $account_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  return $row ? $row['nama_akun'] : 'Unknown Account';
}

function getOriginalTransactionAmount($conn, $transaction_id, $account_id)
{
  $query = "SELECT nilai FROM detail_transaksi 
              WHERE id_transaksi = ? AND id_akun = ? AND debit_kredit = 'D'";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $transaction_id, $account_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  return $row ? $row['nilai'] : 0;
}

function recordPrepaidAdjustment($conn, $account_id, $transaction_id, $original_amount, $adjusted_amount, $adjustment_date, $user_id)
{
  $remaining_amount = $original_amount - $adjusted_amount;

  // Begin transaction for atomic operations
  $conn->begin_transaction();

  try {
    // First, get the corresponding expense account from templates
    $expense_account_id = 0;
    $query = "SELECT expense_account_id FROM prepaid_adjustment_templates WHERE prepaid_account_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
      $expense_account_id = $row['expense_account_id'];
    }
    $stmt->close();

    if ($expense_account_id == 0) {
      throw new Exception("No matching expense account found for prepaid account ID: $account_id");
    }

    // Get transaction name for description
    $transaction_name = '';
    $query = "SELECT nama_transaksi FROM transaksi WHERE id_transaksi = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
      $transaction_name = $row['nama_transaksi'];
    }
    $stmt->close();

    // Record in prepaid_balances
    $query = "INSERT INTO prepaid_balances 
                  (account_id, expense_account_id, original_transaction_id, 
                  original_amount, adjusted_amount, remaining_amount, 
                  adjustment_date, processed_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
      "iiidddsi",
      $account_id,
      $expense_account_id,
      $transaction_id,
      $original_amount,
      $adjusted_amount,
      $remaining_amount,
      $adjustment_date,
      $user_id
    );
    $stmt->execute();
    $stmt->close();

    // Record in jurnal_penyesuaian
    $keterangan = "Penyesuaian prepaid untuk transaksi $transaction_id - " . htmlspecialchars($transaction_name);

    $query = "INSERT INTO jurnal_penyesuaian 
                  (tanggal, akun_debit, akun_kredit, jumlah, keterangan, 
                  referensi_jurnal, dibuat_oleh)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
      "siidisi",
      $adjustment_date,
      $expense_account_id,
      $account_id,
      $adjusted_amount,
      $keterangan,
      $transaction_id,
      $user_id
    );
    $stmt->execute();
    $stmt->close();

    // Create the actual adjustment transaction
    $nama_transaksi = "Penyesuaian untuk transaksi $transaction_id";
    $query = "INSERT INTO transaksi 
                  (tgl_transaksi, nama_transaksi, post, penyesuaian)
                  VALUES (?, ?, '1', '1')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $adjustment_date, $nama_transaksi);
    $stmt->execute();
    $adjustment_trans_id = $stmt->insert_id;
    $stmt->close();

    // Create debit entry (expense account)
    $query = "INSERT INTO detail_transaksi 
                  (id_transaksi, id_akun, debit_kredit, nilai, penyesuaian)
                  VALUES (?, ?, 'D', ?, '1')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iid", $adjustment_trans_id, $expense_account_id, $adjusted_amount);
    $stmt->execute();
    $stmt->close();

    // Create credit entry (prepaid account)
    $query = "INSERT INTO detail_transaksi 
                  (id_transaksi, id_akun, debit_kredit, nilai, penyesuaian)
                  VALUES (?, ?, 'K', ?, '1')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iid", $adjustment_trans_id, $account_id, $adjusted_amount);
    $stmt->execute();
    $stmt->close();

    // Update the journal entry with the adjustment transaction reference
    $query = "UPDATE jurnal_penyesuaian 
                  SET referensi_jurnal = ?
                  WHERE referensi_jurnal = ? AND akun_kredit = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $adjustment_trans_id, $transaction_id, $account_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    return true;
  } catch (Exception $e) {
    $conn->rollback();
    error_log("Error in recordPrepaidAdjustment: " . $e->getMessage());
    return false;
  }
}

function is_period_adjusted($conn, $month, $year)
{
  $sql = "SELECT COUNT(*) as count FROM closing 
            WHERE bulan = ? AND tahun = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $month, $year);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  return ($row['count'] > 0);
}

function get_adjustment_status($conn, $month, $year)
{
  $sql = "SELECT status FROM adjustment_status 
            WHERE bulan = ? AND tahun = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $month, $year);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  return $row ? $row['status'] : 'pending';
}

function initializeInitialCapital($conn, $date, $amount)
{
  $query = "CALL init_modal_awal(?, ?)";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("sd", $date, $amount);
  return $stmt->execute();
}

// Additional helper function to get prepaid adjustment template
function getPrepaidAdjustmentTemplate($conn, $prepaid_account_id)
{
  $query = "SELECT * FROM prepaid_adjustment_templates WHERE prepaid_account_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $prepaid_account_id);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc();
}

// Function to check if transaction exists
function transactionExists($conn, $transaction_id)
{
  $query = "SELECT COUNT(*) as count FROM transaksi WHERE id_transaksi = ? AND hapus = '0'";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $transaction_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  return ($row['count'] > 0);
}

function getMonthlyAdjustmentAmount($conn, $transaction_id)
{
  $query = "SELECT original_amount, period_covered 
              FROM prepaid_balances 
              WHERE original_transaction_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $transaction_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  if ($row) {
    return $row['original_amount'] / $row['period_covered'];
  }
  return 0;
}

function getPrepaidAdjustmentsMade($conn, $transaction_id)
{
  $query = "SELECT adjustments_made 
              FROM prepaid_balances 
              WHERE original_transaction_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $transaction_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  return $row ? $row['adjustments_made'] : 0;
}

function getTotalPrepaidAdjustmentsNeeded($conn, $transaction_id)
{
  $query = "SELECT total_adjustments_needed 
              FROM prepaid_balances 
              WHERE original_transaction_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $transaction_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  return $row ? $row['total_adjustments_needed'] : 0;
}

function isPrepaidFullyAdjusted($conn, $transaction_id)
{
  $query = "SELECT status 
              FROM prepaid_balances 
              WHERE original_transaction_id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $transaction_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();

  return $row ? ($row['status'] === 'fully_adjusted') : false;
}
