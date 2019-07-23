<?php
/**
 * A PHP eWAY Rapid API library implementation.
 *
 * Requires PHP 5.2 or greater with the cURL extension
 *
 * @see       https://eway.io/api-v3/
 * @version   1.0.5
 * @package   eWAY
 * @author    eWAY www.eway.com.au
 * @copyright (c) 2015, Web Active Corporation Pty Ltd
 * @license   http://opensource.org/licenses/MIT MIT
 */

/**
 * eWAY Rapid 3.1 Library
 *
 * @package eWAY
 */
class EwayRapidAPI
{

    private $url;
    private $sandbox;
    private $apiKey;
    private $password;

    /**
     * RapidAPI constructor
     *
     * @param string $apiKey your eWAY API Key
     * @param string $password your eWAY API Password
     * @param string $params set options for connecting to eWAY
     *      $params['sandbox'] to true to use the sandbox for testing
     */
    public function __construct($apiKey, $password, $params = array())
    {
        if (Tools::strlen($apiKey) === 0 || Tools::strlen($password) === 0) {
            Logger::addLog('eWAY Username & Password not configured', 4, null);
            die('Username and Password are required');
        }

        $this->apiKey = $apiKey;
        $this->password = $password;

        if (count($params) && isset($params['sandbox']) && $params['sandbox']) {
            $this->url = 'https://api.sandbox.ewaypayments.com/';
            $this->sandbox = true;
        } else {
            $this->url = 'https://api.ewaypayments.com/';
            $this->sandbox = false;
        }
    }

    /**
     * Create a request for a Transparent Redirect Access Code
     *
     * @see https://eway.io/api-v3/#transparent-redirect
     * @param eWAY\CreateAccessCodeRequest $request
     * @return object decoded response from eWAY
     */
    public function createAccessCode($request)
    {
        $request = $this->fixObjtoJSON($request);
        $response = $this->postToRapidAPI('AccessCodes', $request);
        return $response;
    }

    /**
     * Get the result from an AccessCode after a customer has completed
     * a transaction with either Responsive Shared or Transparent Redirect.
     *
     * @param string $request the eWAY access code.
     * @return object decoded response from eWAY
     */
    public function getAccessCodeResult($accesscode)
    {
        $response = $this->postToRapidAPI('AccessCode/'.$accesscode, '', false);
        return $response;
    }

    /**
     * Create an AccessCode & Redirect URL for a Responsive Shared Page payment
     *
     * @see https://eway.io/api-v3/#responsive-shared-page
     * @param eWAY\CreateAccessCodesSharedRequest $request
     * @return object decoded response from eWAY
     */
    public function createAccessCodesShared($request)
    {
        $request = $this->fixObjtoJSON($request);
        $response = $this->postToRapidAPI('AccessCodesShared', $request);
        return $response;
    }

    /**
     * Perform a Direct Payment
     *
     * Note: Before being able to send credit card data via the direct API, eWAY
     * must enable it on the account. To be enabled on a live account eWAY must
     * receive proof that the environment is PCI-DSS compliant or use Client
     * Side Encryption
     *
     * @see https://eway.io/api-v3/#direct-connection
     * @param eWAY\CreateDirectPaymentRequest $request
     * @return object decoded response from eWAY
     */
    public function directPayment($request)
    {
        $request = $this->fixObjtoJSON($request);
        $response = $this->postToRapidAPI('Transaction', $request);
        return $response;
    }

    /**
     * Performs a refund
     * Note: Before accessing the direct refund API you must add the Refund
     *  ability to your API user role.
     *
     * @see https://eway.io/api-v3/#refunds
     * @param eWAY\CreateRefundRequest $request
     * @return object decoded response from eWAY
     */
    public function refund($request)
    {
        $transaction_id = $request->Refund->TransactionID;
        $request = $this->fixObjtoJSON($request);
        $response = $this->postToRapidAPI("Transaction/$transaction_id/Refund", $request);
        return $response;
    }

    /* alias */

    /**
     * Fetches the message associated with a Response Code
     *
     * @param string $code
     * @return string
     */
    public function getMessage($code)
    {
        return EwayResponseCode::getMessage($code);
    }

    /**
     * Formats the request into JSON
     *
     * @param object $request
     * @return string JSON encoded string
     */
    private function fixObjtoJSON($request)
    {
        if (isset($request->Options) && count($request->Options->Option)) {
            $i = 0;
            $temp_class = new stdClass();
            foreach ($request->Options->Option as $option) {
                $temp_class->Options[$i] = $option;
                $i++;
            }
            $request->Options = $temp_class->Options;
        }
        if (isset($request->Items) && count($request->Items->LineItem)) {
            $i = 0;
            $temp_class = new stdClass();
            foreach ($request->Items->LineItem as $line_item) {
                // must be strings
                if (isset($line_item->Quantity))
                    $line_item->Quantity = (string)$line_item->Quantity;
                if (isset($line_item->UnitCost))
                    $line_item->UnitCost = (string)$line_item->UnitCost;
                if (isset($line_item->Tax))
                    $line_item->Tax = (string)$line_item->Tax;
                if (isset($line_item->Total))
                    $line_item->Total = (string)$line_item->Total;
                $temp_class->Items[$i] = $line_item;
                $i++;
            }
            $request->Items = $temp_class->Items;
        }

        // fix blank issue
        if (isset($request->RedirectUrl))
            $request->RedirectUrl = str_replace(' ', '%20', $request->RedirectUrl);
        if (isset($request->CancelUrl))
            $request->CancelUrl = str_replace(' ', '%20', $request->CancelUrl);

        return Tools::jsonEncode($request);
    }

    /**
     * A Function for doing a cURL GET/POST
     *
     * @param string  $path the path for this request
     * @param Request $request JSON encoded request body
     * @param boolean $is_post set to false to perform a GET
     * @return string
     */
    private function postToRapidAPI($url, $request, $is_post = true)
    {
        $url = $this->url.$url;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, Array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiKey.':'.$this->password);
        curl_setopt($ch, CURLOPT_USERAGENT,'prestashop '._PS_VERSION_.' transparent official');
        if ($is_post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        } else
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        $response = curl_exec($ch);

        if (curl_errno($ch) != CURLE_OK) {
            $response = new stdClass();
            $response->Errors = 'POST Error: '.curl_error($ch)." URL: $url";
        } else {
            $info = curl_getinfo($ch);
            if ($info['http_code'] != 200) {
                $response = new stdClass();
                if ($info['http_code'] == 401 || $info['http_code'] == 404 || $info['http_code'] == 403) {
                    $endpoint = $this->sandbox ? ' (Sandbox)' : ' (Live)';
                    $response->Errors = "Please check the API Key and Password $endpoint";
                } else
                    $response->Errors = 'Error connecting to eWAY: '.$info['http_code'];
            } else
                $response = Tools::jsonDecode($response);
        }

        curl_close($ch);

        if ($response === null) {
            $response = new stdClass();
            $response->Errors = 'Decode Error: Invalid response';
        }

        return $response;
    }

}

/**
 * Class containing translations of Response Codes
 */
class EwayResponseCode
{

    /**
     * @see https://eway.io/api-v3/#response-amp-error-codes
     * @var array response codes
     */
    private static $codes = array(
        'F7000' => 'Undefined Fraud Error',
        'V5000' => 'Undefined System',
        'A0000' => 'Undefined Approved',
        'A2000' => 'Transaction Approved',
        'A2008' => 'Honour With Identification',
        'A2010' => 'Approved For Partial Amount',
        'A2011' => 'Approved VIP',
        'A2016' => 'Approved Update Track 3',
        'V6000' => 'Undefined Validation Error',
        'V6001' => 'Invalid Customer IP',
        'V6002' => 'Invalid DeviceID',
        'V6011' => 'Invalid Amount',
        'V6012' => 'Invalid Invoice Description',
        'V6013' => 'Invalid Invoice Number',
        'V6014' => 'Invalid Invoice Reference',
        'V6015' => 'Invalid Currency Code',
        'V6016' => 'Payment Required',
        'V6017' => 'Payment Currency Code Required',
        'V6018' => 'Unknown Payment Currency Code',
        'V6021' => 'Cardholder Name Required',
        'V6022' => 'Card Number Required',
        'V6023' => 'CVN Required',
        'V6031' => 'Invalid Card Number',
        'V6032' => 'Invalid CVN',
        'V6033' => 'Invalid Expiry Date',
        'V6034' => 'Invalid Issue Number',
        'V6035' => 'Invalid Start Date',
        'V6036' => 'Invalid Month',
        'V6037' => 'Invalid Year',
        'V6040' => 'Invalid Token Customer Id',
        'V6041' => 'Customer Required',
        'V6042' => 'Customer First Name Required',
        'V6043' => 'Customer Last Name Required',
        'V6044' => 'Customer Country Code Required',
        'V6045' => 'Customer Title Required',
        'V6046' => 'Token Customer ID Required',
        'V6047' => 'RedirectURL Required',
        'V6051' => 'Invalid Customer First Name',
        'V6052' => 'Invalid Customer Last Name',
        'V6053' => 'Invalid Customer Country Code',
        'V6054' => 'Invalid Customer Email',
        'V6055' => 'Invalid Customer Phone',
        'V6056' => 'Invalid Customer Mobile',
        'V6057' => 'Invalid Customer Fax',
        'V6058' => 'Invalid Customer Title',
        'V6059' => 'Redirect URL Invalid',
        'V6060' => 'Redirect URL Invalid',
        'V6061' => 'Invalid Customer Reference',
        'V6062' => 'Invalid Customer Company Name',
        'V6063' => 'Invalid Customer Job Description',
        'V6064' => 'Invalid Customer Street1',
        'V6065' => 'Invalid Customer Street2',
        'V6066' => 'Invalid Customer City',
        'V6067' => 'Invalid Customer State',
        'V6068' => 'Invalid Customer Postalcode',
        'V6069' => 'Invalid Customer Email',
        'V6070' => 'Invalid Customer Phone',
        'V6071' => 'Invalid Customer Mobile',
        'V6072' => 'Invalid Customer Comments',
        'V6073' => 'Invalid Customer Fax',
        'V6074' => 'Invalid Customer Url',
        'V6075' => 'Invalid ShippingAddress First Name',
        'V6076' => 'Invalid ShippingAddress Last Name',
        'V6077' => 'Invalid ShippingAddress Street1',
        'V6078' => 'Invalid ShippingAddress Street2',
        'V6079' => 'Invalid ShippingAddress City',
        'V6080' => 'Invalid ShippingAddress State',
        'V6081' => 'Invalid ShippingAddress PostalCode',
        'V6082' => 'Invalid ShippingAddress Email',
        'V6083' => 'Invalid ShippingAddress Phone',
        'V6084' => 'Invalid ShippingAddress Country',
        'V6091' => 'Unknown Country Code',
        'V6100' => 'Invalid Card Name',
        'V6101' => 'Invalid Card Expiry Month',
        'V6102' => 'Invalid Card Expiry Year',
        'V6103' => 'Invalid Card Start Month',
        'V6104' => 'Invalid Card Start Year',
        'V6105' => 'Invalid Card Issue Number',
        'V6106' => 'Invalid Card CVN',
        'V6107' => 'Invalid AccessCode',
        'V6108' => 'Invalid CustomerHostAddress',
        'V6109' => 'Invalid UserAgent',
        'V6110' => 'Invalid Card Number',
        'V6111' => 'Unauthorised API Access, Account Not PCI Certified',
        'V6112' => 'Redundant card details other than expiry year and month',
        'V6113' => 'Invalid transaction for refund',
        'V6114' => 'Gateway validation error',
        'V6115' => 'Invalid DirectRefundRequest, Transaction ID',
        'V6116' => 'Invalid card data on original TransactionID',
        'V6117' => 'Invalid CreateAccessCodeSharedRequest, FooterText',
        'V6118' => 'Invalid CreateAccessCodeSharedRequest, HeaderText',
        'V6119' => 'Invalid CreateAccessCodeSharedRequest, Language',
        'V6120' => 'Invalid CreateAccessCodeSharedRequest, LogoUrl',
        'V6121' => 'Invalid TransactionSearch, Filter Match Type',
        'V6122' => 'Invalid TransactionSearch, Non numeric Transaction ID',
        'V6123' => 'Invalid TransactionSearch,no TransactionID or AccessCode specified',
        'V6124' => 'Invalid Line Items. The line items have been provided however the totals do not match the TotalAmount field',
        'V6125' => 'Selected Payment Type not enabled',
        'V6126' => 'Invalid encrypted card number, decryption failed',
        'V6127' => 'Invalid encrypted cvn, decryption failed',
        'V6128' => 'Invalid Method for Payment Type',
        'V6129' => 'Transaction has not been authorised for Capture/Cancellation',
        'V6130' => 'Generic customer information error',
        'V6131' => 'Generic shipping information error',
        'V6132' => 'Transaction has already been completed or voided, operation not permitted',
        'V6133' => 'Checkout not available for Payment Type',
        'V6134' => 'Invalid Auth Transaction ID for Capture/Void',
        'V6135' => 'PayPal Error Processing Refund',
        'V6140' => 'Merchant account is suspended',
        'V6141' => 'Invalid PayPal account details or API signature',
        'V6142' => 'Authorise not available for Bank/Branch',
        'V6150' => 'Invalid Refund Amount',
        'V6151' => 'Refund amount greater than original transaction',
        'V6152' => 'Original transaction already refunded for total amount',
        'V6153' => 'Card type not support by merchant',
        'V6151' => 'Refund amount greater than original transaction',
        'D4401' => 'Refer to Issuer',
        'D4402' => 'Refer to Issuer, special',
        'D4403' => 'No Merchant',
        'D4404' => 'Pick Up Card',
        'D4405' => 'Do Not Honour',
        'D4406' => 'Error',
        'D4407' => 'Pick Up Card, Special',
        'D4409' => 'Request In Progress',
        'D4412' => 'Invalid Transaction',
        'D4413' => 'Invalid Amount',
        'D4414' => 'Invalid Card Number',
        'D4415' => 'No Issuer',
        'D4419' => 'Re-enter Last Transaction',
        'D4421' => 'No Method Taken',
        'D4422' => 'Suspected Malfunction',
        'D4423' => 'Unacceptable Transaction Fee',
        'D4425' => 'Unable to Locate Record On File',
        'D4430' => 'Format Error',
        'D4431' => 'Bank Not Supported By Switch',
        'D4433' => 'Expired Card, Capture',
        'D4434' => 'Suspected Fraud, Retain Card',
        'D4435' => 'Card Acceptor, Contact Acquirer, Retain Card',
        'D4436' => 'Restricted Card, Retain Card',
        'D4437' => 'Contact Acquirer Security Department, Retain Card',
        'D4438' => 'PIN Tries Exceeded, Capture',
        'D4439' => 'No Credit Account',
        'D4440' => 'Function Not Supported',
        'D4441' => 'Lost Card',
        'D4442' => 'No Universal Account',
        'D4443' => 'Stolen Card',
        'D4444' => 'No Investment Account',
        'D4451' => 'Insufficient Funds',
        'D4452' => 'No Cheque Account',
        'D4453' => 'No Savings Account',
        'D4454' => 'Expired Card',
        'D4455' => 'Incorrect PIN',
        'D4456' => 'No Card Record',
        'D4457' => 'Function Not Permitted to Cardholder',
        'D4458' => 'Function Not Permitted to Terminal',
        'D4460' => 'Acceptor Contact Acquirer',
        'D4461' => 'Exceeds Withdrawal Limit',
        'D4462' => 'Restricted Card',
        'D4463' => 'Security Violation',
        'D4464' => 'Original Amount Incorrect',
        'D4466' => 'Acceptor Contact Acquirer, Security',
        'D4467' => 'Capture Card',
        'D4475' => 'PIN Tries Exceeded',
        'D4482' => 'CVV Validation Error',
        'D4490' => 'Cutoff In Progress',
        'D4491' => 'Card Issuer Unavailable',
        'D4492' => 'Unable To Route Transaction',
        'D4493' => 'Cannot Complete, Violation Of The Law',
        'D4494' => 'Duplicate Transaction',
        'D4496' => 'System Error',
        'D4497' => 'MasterPass Error Failed',
        'D4498' => 'PayPal Create Transaction Error Failed',
        'D4499' => 'Invalid Transaction for Auth/Void',
        'F7000' => 'Undefined Fraud Error',
        'F7001' => 'Challenged Fraud',
        'F7002' => 'Country Match Fraud',
        'F7003' => 'High Risk Country Fraud',
        'F7004' => 'Anonymous Proxy Fraud',
        'F7005' => 'Transparent Proxy Fraud',
        'F7006' => 'Free Email Fraud',
        'F7007' => 'International Transaction Fraud',
        'F7008' => 'Risk Score Fraud',
        'F7009' => 'Denied Fraud',
        'F7010' => 'Denied by PayPal Fraud Rules',
        'F9010' => 'High Risk Billing Country',
        'F9011' => 'High Risk Credit Card Country',
        'F9012' => 'High Risk Customer IP Address',
        'F9013' => 'High Risk Email Address',
        'F9014' => 'High Risk Shipping Country',
        'F9015' => 'Multiple card numbers for single email address',
        'F9016' => 'Multiple card numbers for single location',
        'F9017' => 'Multiple email addresses for single card number',
        'F9018' => 'Multiple email addresses for single location',
        'F9019' => 'Multiple locations for single card number',
        'F9020' => 'Multiple locations for single email address',
        'F9021' => 'Suspicious Customer First Name',
        'F9022' => 'Suspicious Customer Last Name',
        'F9023' => 'Transaction Declined',
        'F9024' => 'Multiple transactions for same address with known credit card',
        'F9025' => 'Multiple transactions for same address with new credit card',
        'F9026' => 'Multiple transactions for same email with new credit card',
        'F9027' => 'Multiple transactions for same email with known credit card',
        'F9028' => 'Multiple transactions for new credit card',
        'F9029' => 'Multiple transactions for known credit card',
        'F9030' => 'Multiple transactions for same email address',
        'F9031' => 'Multiple transactions for same credit card',
        'F9032' => 'Invalid Customer Last Name',
        'F9033' => 'Invalid Billing Street',
        'F9034' => 'Invalid Shipping Street',
        'F9037' => 'Suspicious Customer Email Address'
    );

    /**
     * Fetches the message associated with a Response Code
     *
     * @param string $code
     * @return string
     * @static
     */
    public static function getMessage($code)
    {
        if (isset(EwayResponseCode::$codes[$code]))
            return EwayResponseCode::$codes[$code];
        else
            return $code;
    }

}

class EwayCreateAccessCodeRequest
{

    public $Customer;
    public $ShippingAddress;
    public $Items;
    public $Options;
    public $Payment;
    public $RedirectUrl;
    public $Method;
    public $TransactionType;
    public $CustomerIP;
    public $DeviceID;
    public $PartnerID;

    public function __construct()
    {
        $this->Customer = new EwayCustomer();
        $this->ShippingAddress = new EwayShippingAddress();
        $this->Payment = new EwayPayment();
    }

}

class EwayCreateAccessCodesSharedRequest extends EwayCreateAccessCodeRequest
{

    public $CancelUrl;
    public $LogoUrl;
    public $HeaderText;

    /**
     * @var bool When set to false, cardholders will be able to edit the
     * information on the shared page even if it’s sent through in the server
     * side reques
     */
    public $CustomerReadOnly;

    /**
     * @var string Language code determines the language that the shared page will be
     * displayed in. One of EN or ES
     */
    public $Language;

    /**
     *
     * @var string Set the theme of the Responsive Shared Page from 12
     * predetermined themes (default is Default)
     * One of: Default, Bootstrap, BootstrapAmelia, BootstrapCerulean, BootstrapCosmo,
     * BootstrapCyborg, BootstrapFlatly, BootstrapJournal, BootstrapReadable,
     * BootstrapSimplex, BootstrapSlate, BootstrapSpacelab, BootstrapUnited
     */
    public $CustomView;

    /**
     * @var bool Set whether the customers phone should be confirmed using Beagle Verify
     * (an SMS is sent to the customer's phone)
     */
    public $VerifyCustomerPhone;

    /**
     * @var bool Set whether the customers email should be confirmed using Beagle Verify
     */
    public $VerifyCustomerEmail;

}

class EwayCreateDirectPaymentRequest
{

    public $Customer;
    public $ShippingAddress;
    public $Items;
    public $Options;
    public $Payment;
    public $CustomerIP;
    public $DeviceID;
    public $TransactionType;
    public $PartnerID;

    public function __construct()
    {
        $this->Customer = new EwayCardCustomer();
        $this->ShippingAddress = new EwayShippingAddress();
        $this->Payment = new EwayPayment();
    }

}

class EwayCreateRefundRequest
{

    public $Refund;
    public $Customer;
    public $ShippingAddress;
    public $Items;
    public $Options;
    public $CustomerIP;
    public $DeviceID;
    public $PartnerID;

    public function __construct()
    {
        $this->Refund = new EwayPayment();
        $this->Customer = new EwayCardCustomer();
        $this->ShippingAddress = new EwayShippingAddress();
    }

}

/**
 * Description of Customer
 */
class EwayCustomer
{

    public $TokenCustomerID;
    public $Reference;
    public $Title;
    public $FirstName;
    public $LastName;
    public $CompanyName;
    public $JobDescription;
    public $Street1;
    public $Street2;
    public $City;
    public $State;
    public $PostalCode;
    public $Country;
    public $Email;
    public $Phone;
    public $Mobile;
    public $Comments;
    public $Fax;
    public $Url;

}

class EwayCardCustomer extends EwayCustomer
{

    public function __construct()
    {
        $this->CardDetails = new EwayCardDetails();
    }

}

class EwayShippingAddress
{

    public $FirstName;
    public $LastName;
    public $Street1;
    public $Street2;
    public $City;
    public $State;
    public $Country;
    public $PostalCode;
    public $Email;
    public $Phone;
    public $ShippingMethod;

}

class EwayItems
{

    public $LineItem = array();

}

class EwayLineItem
{

    public $SKU;
    public $Description;
    public $Quantity;
    public $UnitCost;
    public $Tax;
    public $Total;

}

class EwayOptions
{

    public $Option = array();

}

class EwayOption
{

    public $Value;

}

class EwayPayment
{

    public $TotalAmount;
    public $InvoiceNumber;
    public $InvoiceDescription;
    public $InvoiceReference;
    public $CurrencyCode;
    public $TransactionID;

}

class EwayGetAccessCodeResultRequest
{

    public $AccessCode;

}

class EwayCardDetails
{

    public $Name;
    public $Number;
    public $ExpiryMonth;
    public $ExpiryYear;
    public $StartMonth;
    public $StartYear;
    public $IssueNumber;
    public $CVN;

}
