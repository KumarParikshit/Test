<?php
header('Content-Type: application/x-www-form-urlencoded');
include("../serive/samparka.php");

// Function to log errors
function logError($message) {
    file_put_contents('log.txt', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

// ✅ Log received webhook data for debugging
logError("Received data: " . print_r($_POST, true));

// ✅ Load configuration
$config = require 'config.php';
$secretKey = $config['secret_key'];

if (empty($secretKey)) {
    logError("Error: Missing secret key in config.");
    die("fail(secret key missing)");
}

// ✅ Extract incoming POST data
$data = $_POST;
$resSign = $data['sign'] ?? null;

if (!$resSign) {
    logError("Error: Signature not found in callback.");
    die("fail(sign not exists)");
}

// ✅ Prepare parameters for signature verification
$paramArray = [
    'order_sn'  => $data['order_sn'] ?? '',
    'money'     => $data['money'] ?? '',
    'status'    => $data['status'] ?? '',
    'pay_time'  => $data['pay_time'] ?? '',
    'msg'       => $data['msg'] ?? '',
    'remark'    => $data['remark'] ?? '',
];

// ✅ Remove empty values
$filteredParams = array_filter($paramArray, function ($value) {
    return $value !== null && $value !== '';
});

// ✅ Sort parameters alphabetically
ksort($filteredParams);

// ✅ Generate signature string
$signatureString = "";
foreach ($filteredParams as $key => $value) {
    $signatureString .= "$key=$value&";
}
$signatureString .= "key=$secretKey"; // Append secret key

$calculatedSign = strtoupper(md5($signatureString));

// ✅ Validate the signature
if ($resSign !== $calculatedSign) {
    logError("Error: Signature mismatch. Expected: $calculatedSign, Received: $resSign");
    die("fail(verify fail)");
}

// ✅ Check if the order exists and is not already processed (status = 0)
$order_sn = mysqli_real_escape_string($conn, $data['order_sn']);
$query = "SELECT motta, balakedara FROM thevani WHERE dharavahi = '$order_sn' AND sthiti = '0'";
$result = mysqli_query($conn, $query);

if (!$result) {
    logError("Database query error: " . mysqli_error($conn));
    die("fail(database error)");
}

if (mysqli_num_rows($result) >= 1) {
    $row = mysqli_fetch_assoc($result);
    $motta = $row['motta'];
    $shonuid = $row['balakedara'];

    // ✅ Update user balance
    $updateBalance = "UPDATE shonu_kaichila SET motta = ROUND(motta + '$motta', 2) WHERE balakedara = '$shonuid'";
    
    if (!mysqli_query($conn, $updateBalance)) {
        logError("Error updating balance: " . mysqli_error($conn));
        die("fail(update balance error)");
    }

    // ✅ Update order status to processed (sthiti = 1)
    $updateOrder = "UPDATE thevani SET sthiti = '1' WHERE dharavahi = '$order_sn'";
    
    if (!mysqli_query($conn, $updateOrder)) {
        logError("Error updating order: " . mysqli_error($conn));
        die("fail(update order error)");
    }

    logError("Order $order_sn successfully processed.");
} else {
    logError("Order not found or already processed: $order_sn");
}

// ✅ Respond to LG System (to stop retries)
echo "ok";
exit;
?>
