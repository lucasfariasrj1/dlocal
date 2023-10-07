<?php

// require_once __DIR__ . '/../../../init.php';
// require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
// require_once __DIR__ . '/../../../includes/invoicefunctions.php';


// $gatewayModuleName = basename(__FILE__, '.php');

// $gatewayParams = getGatewayVariables($gatewayModuleName);


// $url = '';
// if ($gatewayParams['testMode'] == 'on') {
//     $url = 'https://sandbox.dlocal.com/';
// } else {
//     $url = 'https://api.dlocal.com/';
// }

// $dt = new DateTime();
// $dt->setTimeZone(new DateTimeZone('UTC'));
// $date = $dt->format('Y-m-d\TH:i:s.\0\0\0\Z');


// $ch = curl_init();
// $sha = $gatewayParams['x-Login'] . $date;
// $shaParser = hash_hmac('SHA256', $sha, $gatewayParams['secretKey']);

// curl_setopt($ch, CURLOPT_HTTPHEADER, [
//     'X-Login: ' . $gatewayParams['x-Login'],
//     'X-Trans-Key: ' . $gatewayParams['X-Trans-Key'],
//     'X-Date: ' . $date,
//     'Content-Type: application/json',
//     'Authorization: V2-HMAC-SHA256, Signature:' . $shaParser
// ]);
// curl_setopt($ch, CURLOPT_URL, $url . 'payments/' . $_POST['payment_id']);

// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// $response = curl_exec($ch);
// curl_close($ch);

// $data = json_decode($response, true);

// $order_id = explode('_', $data['order_id']);

// var_dump($data);


// if ($data['status'] == 'PAID') {
//     addInvoicePayment(
//         (int) $order_id[1],
//         $data['id'],
//         $data['amount'],
//         0.00,
//         'dlocal'
//     );
// }

// header('Location: https://' . $_SERVER['SERVER_NAME'] . '/viewinvoice.php?id=' . $order_id[1]);

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

$url = '';
if ($gatewayParams['testMode'] == 'on') {
    $url = 'https://sandbox.dlocal.com/';
} else {
    $url = 'https://api.dlocal.com/';
}

$dt = new DateTime();
$dt->setTimeZone(new DateTimeZone('UTC'));
$date = $dt->format('Y-m-d\TH:i:s.\0\0\0\Z');

$ch = curl_init();
$sha = $gatewayParams['x-Login'] . $date;
$shaParser = hash_hmac('SHA256', $sha, $gatewayParams['secretKey']);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Login: ' . $gatewayParams['x-Login'],
    'X-Trans-Key: ' . $gatewayParams['X-Trans-Key'],
    'X-Date: ' . $date,
    'Content-Type: application/json',
    'Authorization: V2-HMAC-SHA256, Signature:' . $shaParser
]);
$jsonData = file_get_contents('php://input'); // Obtém os dados JSON brutos
$jsonDataArray = json_decode($jsonData, true); // Decodifica o JSON para um array associativo

$payment_id = $jsonDataArray['id']; // Acessa o campo 'id'
//curl_setopt($ch, CURLOPT_URL, $url . 'payments/' . $_POST['payment_id']);
curl_setopt($ch, CURLOPT_URL, $url . 'payments/' . $payment_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$order_id = explode('_', $data['order_id']);

$jsonData = file_get_contents('php://input');
$jsonDataArray = json_decode($jsonData, true); // Pass `true` to get an associative array

// Prepare the log data
$logData = "Request Date: " . date('Y-m-d H:i:s') . "\n";
$logData .= "Server Data: " . print_r($_SERVER, true) . "\n";
$logData .= "Request Data: " . print_r($_REQUEST, true) . "\n\n";
$logData .= "JSON Data: " . print_r($jsonDataArray, true) . "\n\n";
// Append log data to the log file
$logFilePath = __DIR__ . '/request_log.txt';
$logFile = fopen($logFilePath, 'a');
fwrite($logFile, $logData);
fclose($logFile);

if ($data['status'] == 'PAID') {
    addInvoicePayment(
        (int) $order_id[1],
        $data['id'],
        $data['amount'],
        0.00,
        'dlocal'
    );
}

header('Location: https://' . rtrim(\App::getSystemUrl(),"/") . '/viewinvoice.php?id=' . $order_id[1]);

?>
