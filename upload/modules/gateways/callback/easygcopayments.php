<?php
/*
 * EasyGCO Payments Module for WHMCS
 *
 * Accept payments via EasyGCO Payments in WHMCS
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://docs.easygco.com/
 *
 * @copyright Copyright (c) EasyGCO.com 2022
 * @author EasyGCO https://easygco.com/
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';


$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$apiKey = $gatewayParams['apiKey'];

$apiSecret = $gatewayParams['apiSecret'];

if(empty($_REQUEST) || empty($_REQUEST['ps_response_data']) || !is_array($_REQUEST['ps_response_data'])) {
    http_response_code(403);
    exit('Error: Access is denied');
}

$apiResponseData = array_map('urldecode',$_REQUEST['ps_response_data']);

if(!isset($apiResponseData['payment_uid'])) {
    http_response_code(403);
    exit('Error: Invalid PS-Data, no payment UID identified, Access is denied');
}

if(empty($apiResponseData['externalid'])) {
    http_response_code(403);
    exit('Success: IPN Received, payment transaction reference empty or not provided');
}

$paymentUID = $apiResponseData['payment_uid'];
$invoiceID = $apiResponseData['externalid'];

$invoiceID = checkCbInvoiceID($invoiceID, $gatewayParams['name']);

require_once(__DIR__ . '/../easygcopayments/EasyGCO-Payments/vendor/autoload.php');

$ePaymentsClient = new EasyGCO\EasyGCOPayments\API($apiKey,$apiSecret);

$apiPath = 'payment/get';

$inputData = [
    'uid' => trim(urldecode($paymentUID)),
];

$apiResponse = $ePaymentsClient->doRequest($apiPath, $inputData);

if(!$ePaymentsClient->isSuccess($apiResponse)) {
    http_response_code(200);
    logTransaction($gatewayParams['name'], $_REQUEST, 'Failure');
    exit('Failed: IPN Received, No action taken, cannot verify payment UID');
}


$responseData = $ePaymentsClient->getData($apiResponse);

if(!isset($responseData['success']) || intval($responseData['success']) !== 1) {
    http_response_code(200);
    logTransaction($gatewayParams['name'], $responseData, 'Failure');
    exit('Failed: IPN Received, No action taken, payment is unsuccessful');
}

$paidAmount = number_format($responseData['input_amounts']['paid'], 8, '.', '');
$paidAmount = number_format($paidAmount, 8, '.', '');
$transactionID = $paymentUID;

checkCbTransID($transactionID);

$transactionStatus = 'Success';

logTransaction($gatewayParams['name'], $responseData, 'Success');

addInvoicePayment(
    $invoiceID,
    $transactionID,
    $paidAmount,
    0,
    $gatewayModuleName
);
http_response_code(200);
exit('SUCCESS: IPN Received, payment has been processed');