<?php
header('Content-type: application/json');
include("../serive/samparka.php");

// Load API configuration
$config = require 'config.php';
$apiUrl = $config['api_url'];
$secretKey = $config['secret_key'];
$app_id = $config['app_id']; 

if (empty($app_id) || empty($secretKey) || empty($apiUrl)) {
    die(json_encode(['status' => 0, 'msg' => 'Configuration error: Missing API details.']));
}

// Fetch and sanitize input
$ramt = isset($_GET['amount']) ? htmlspecialchars(mysqli_real_escape_string($conn, $_GET['amount'])) : 0;
$ramt = number_format((float)$ramt, 2, '.', ''); // Ensure 2 decimal places
$money = (int) ($ramt * 100); // Convert to integer for API compatibility

$date = date("Ymd");
$time = time();
$serial = $date . $time . rand(100000, 999900);

$uid = htmlspecialchars(mysqli_real_escape_string($conn, $_GET['uid']));
$urlInfo = htmlspecialchars(mysqli_real_escape_string($conn, $_GET['urlInfo']));

$notify_url = "https://91appy.in/pay/lgwebhook.php";
$return_url = "https://91appy.in/";

// ✅ **Build Parameters for Signature**
$params = [
    'app_id'     => $app_id,
    'trade_type' => 'INRUPI',
    'order_sn'   => $serial,
    'money'      => $money,
    'notify_url' => $notify_url,
    'return_url' => $return_url,
    'ip'         => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'],
    'remark'     => 'web pay',
];

// ✅ **Generate the Correct Signature**
function md5_sign($data, $key) {
    ksort($data); // Sort by ASCII values
    $string = http_build_query($data);
    $string = urldecode($string); // Decode URL encoding
    $string = trim($string) . "&key=" . $key; // Append secret key
    return strtoupper(md5($string)); // Generate MD5 hash in uppercase
}

$params['sign'] = md5_sign($params, $secretKey); // ✅ **Correctly Add the Signature**

// ✅ **Send API Request**
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
curl_close($ch);

// ✅ **Handle API Response**
$responseData = json_decode($response, true);
if ($responseData && isset($responseData['status']) && $responseData['status'] == 1 && isset($responseData['data']['pay_url'])) {
    // ✅ Insert transaction into database
    $conn->query("INSERT INTO `thevani` (`balakedara`, `motta`, `dharavahi`, `mula`, `ullekha`, `duravani`, `ekikrtapavati`, `dinankavannuracisi`, `madari`, `pavatiaidi`, `sthiti`) 
    VALUES ('$uid', '$ramt', '$serial', 'FAST-QRpay', 'Recharge', 'N/A', 'N/A', '" . date("Y-m-d H:i:s") . "', '1005', '2', '0')");

    header('Location: ' . $responseData['data']['pay_url']);
    exit;
} else {
    echo json_encode($responseData, JSON_PRETTY_PRINT);
}

// ✅ **Fetch Recharge History for the User**
if (isset($_GET['history']) && $_GET['history'] == '1') {
    $historyQuery = "SELECT motta AS amount, dharavahi AS transaction_id, dinankavannuracisi AS date, sthiti AS status 
                     FROM thevani WHERE balakedara = '$uid' ORDER BY dinankavannuracisi DESC";
    
    $historyResult = $conn->query($historyQuery);
    $history = [];

    if ($historyResult->num_rows > 0) {
        while ($row = $historyResult->fetch_assoc()) {
            $history[] = [
                'amount' => $row['amount'],
                'transaction_id' => $row['transaction_id'],
                'date' => $row['date'],
                'status' => $row['status'] == '1' ? 'Success' : 'Pending'
            ];
        }
    }

    echo json_encode(['status' => 1, 'history' => $history], JSON_PRETTY_PRINT);
}
?>
