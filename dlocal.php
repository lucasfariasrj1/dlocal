<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function dlocal_MetaData()
{
    return array(
        'DisplayName' => 'Dlocal Payment Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function dlocal_config()
{
    $configarray = array(
        "FriendlyName" => array(
            "Type" => "System",
            "Value" => "Dlocal"
        ),
        'X-Trans-Key' => array(
            'FriendlyName' => 'Trans Key',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Enter Trans key here',
        ),
        'x-Login' => array(
            'FriendlyName' => 'Login Key',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Enter Login key here',
        ),
        'secretKey' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'text',
            'Default' => '',
            'Description' => 'Enter secret key here',
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),
    );
    return $configarray;
}

function dlocal_link($params)
{
    $htmlOutput = '';
    $check = Capsule::table('mod_dlocal_invoices')->where('invoice_id', $params['invoiceid'])->first();

    if (isset($check->id)) {
        $htmlOutput .= '<form method="GET" action="' . $check->redirect_url . '">';
        $htmlOutput .= '<input type="submit" class="btn btn-success" value="Pay" />';
        $htmlOutput .= '</form>';

        return $htmlOutput;
    }

    if (!isset($check->id)) {
        $url = '';
        if ($params['testMode'] == 'on') {
            $url = 'https://sandbox.dlocal.com/';
        } else {
            $url = 'https://api.dlocal.com/';
        }

        $dt = new DateTime();
        $dt->setTimeZone(new DateTimeZone('UTC'));
        $date = $dt->format('Y-m-d\TH:i:s.\0\0\0\Z');

        $postfields = [
            'order_id' => rand(12345678, 87654321) . '_' . $params['invoiceid'],
            'amount' => $params['amount'],
            'currency' => $params['currency'],
            'country' =>  $params['clientdetails']['country'],
            "payment_method_flow" => "REDIRECT",
            "payer" => [
                "name" => $params['clientdetails']['firstname'],
                "email" => $params['clientdetails']['email'],
                "document" => $params['clientdetails']['customfields2'],
                "user_reference" => $params['clientdetails']['client_id'],
                "address" =>  [
                    "city" => $params['clientdetails']['city'],
                    "street" => $params['clientdetails']['address1'],
                    "state" => $params['clientdetails']['state'],
                    "zip_code" => $params['clientdetails']['postcode'],
                    "number" => $params['clientdetails']['address2']
                ],
                "phone" => $params['clientdetails']['phonenumber']
            ],
            //"ip" => $params['cart']['client']['ip'],
            "notification_url" => "https://" . $_SERVER['SERVER_NAME'] . "/modules/gateways/callback/dlocal.php"
            ];

        $postdata = json_encode($postfields);

        $ch = curl_init();
        $sha = $params['x-Login'] . $date . $postdata;
        $shaParser = hash_hmac('SHA256', $sha, $params['secretKey']);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Login: ' . $params['x-Login'],
            'X-Trans-Key: ' . $params['X-Trans-Key'],
            'X-Date: ' . $date,
            'Content-Type: application/json',
            'Authorization: V2-HMAC-SHA256, Signature:' . $shaParser
        ]);

        curl_setopt($ch, CURLOPT_URL, $url . 'payments');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        logModuleCall("dlocal_module", "dlocal_link", json_encode($data), json_encode($data), json_encode($data), json_encode($data));

        if (isset($data['redirect_url'])) {
            $invoice = Capsule::table('mod_dlocal_invoices')->insert([
                'invoice_id' => $params['invoiceid'],
                'dlocal_id' => $data['id'],
                'redirect_url' => $data['redirect_url']
            ]);

            $htmlOutput .= '<form method="GET" action="' . $data['redirect_url'] . '">';
            $htmlOutput .= '<input type="submit" class="btn btn-success" value="Pay" />';
            $htmlOutput .= '</form>';
        }

        logModuleCall("dlocal_module", "dlocal_link", json_encode($params), json_encode($params), json_encode($params), json_encode($data));

        return $htmlOutput;
    }
}

function dlocal_refund($params)
{

    $url = '';
    if ($params['testMode'] == 'on') {
        $url = 'https://sandbox.dlocal.com/';
    } else {
        $url = 'https://api.dlocal.com/';
    }


    $dt = new DateTime();
    $dt->setTimeZone(new DateTimeZone('UTC'));
    $date = $dt->format('Y-m-d\TH:i:s.\0\0\0\Z');

    $postfields = [
        'payment_id' => $params['transid'],
        'amount' => $params['amount'],
        'currency' => $params['currency'],
        "notification_url" => "https://" . $_SERVER['SERVER_NAME'] . "/modules/gateways/callback/dlocal.php"
    ];

    $postdata = json_encode($postfields);

    $ch = curl_init();
    $sha = $params['x-Login'] . $date . $postdata;
    $shaParser = hash_hmac('SHA256', $sha, $params['secretKey']);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Login: ' . $params['x-Login'],
        'X-Trans-Key: ' . $params['X-Trans-Key'],
        'X-Date: ' . $date,
        'Content-Type: application/json',
        'Authorization: V2-HMAC-SHA256, Signature:' . $shaParser
    ]);
    curl_setopt($ch, CURLOPT_URL, $url . 'refunds');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['redirect_url'])) {
        //$mod_invoices = Capsule::table('mod_dlocal_invoices')->insert([
        //    'invoice_id' => $params['invoiceid'],
        //    'dlocal_id' => $data['id']
        //]);
    }

    return array(
        'status' => 'success',
        'rawdata' => $data,
        'transid' => "Refund-" . $params['transid'],
    );
}
