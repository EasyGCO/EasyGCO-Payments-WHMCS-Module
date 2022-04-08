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

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function easygcopayments_MetaData()
{
    return array(
        'DisplayName' => 'EasyGCO Payments',
        'APIVersion' => '1.0', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function easygcopayments_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'EasyGCO Payments',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '64',
            'Default' => '',
            'Description' => 'Enter your Payments API Key',
        ),
        'apiSecret' => array(
            'FriendlyName' => 'API Secret',
            'Type' => 'text',
            'Size' => '64',
            'Default' => '',
            'Description' => 'Enter your Payments API Secret',
        ),
    );
}

function easygcopayments_link($params)
{
	require_once(__DIR__ . '/easygcopayments/EasyGCO-Payments/vendor/autoload.php');

    $apiKey = $params['apiKey'];

    $apiSecret = $params['apiSecret'];

    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];
    $systemUrl = $params['systemurl'];
    $returnURL = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];
	

    if(empty($_POST['pay_token']) || empty($_POST['pay_sign']) || !is_numeric($_POST['pay_token']) || !is_string($_POST['pay_sign']) || md5($_POST['pay_token'].$apiSecret) !== $_POST['pay_sign']) {
        $imgSrc = $systemUrl . '/assets/img/easygco-payments-btn.png';
        
        $payToken = number_format(rand().time(),0,'','');
        $paySign = md5($payToken.$apiSecret);
        
        $htmlOutput = '
            <form action="" method="post" id="easygcopayments_payform">
                <input type="hidden" name="pay_token" value="'.$payToken.'">
                <input type="hidden" name="pay_sign" value="'.$paySign.'">
                <a style="cursor: pointer;" onclick="document.getElementById('."'easygcopayments_payform'".').submit();">
                    <img style="min-height: 60px; width: auto;" src="'.$imgSrc.'">
                </a>
            </form>
        ';
        return $htmlOutput;
    }

	$ePaymentsClient = new EasyGCO\EasyGCOPayments\API($apiKey,$apiSecret);

	$apiPath = 'token/generate';
	
	$inputData = [
		'transaction_id' 	=> $invoiceId,
		'description' 		=> $description,
		'code' 				=> $currencyCode,
		'type' 				=> 'fiat',
		'amount' 			=> 	$amount,
		"return_url"		=>	$returnURL,
		"notify_url"		=>	$systemUrl . '/modules/gateways/callback/' . $moduleName . '.php',
	];

	$apiResponse = $ePaymentsClient->doRequest($apiPath, $inputData);

	if(!$ePaymentsClient->isSuccess($apiResponse)) return null;

	$responseData = $ePaymentsClient->getData($apiResponse);

	$ePaymentsClient->doRedirect($responseData['url']);

    return $htmlOutput;
}