<?php
/**
 * eWAY Prestashop Payment Module
 *
 * @author    eWAY www.eway.com.au
 * @copyright (c) 2014, Web Active Corporation Pty Ltd
 * @license   http://opensource.org/licenses/MIT MIT
 * @version   3.4.5
 *
 */

class EwayrapidEupaymentModuleFrontController extends ModuleFrontController
{
    /** @var Ewayrapid $module */
    public $module;

    /**
     * EwayrapidEupaymentModuleFrontController constructor.
     *
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->ssl = Tools::usingSecureMode();
        $this->display_column_right = false;
        $this->display_column_left = false;
    }

    /**
     * @throws Adapter_Exception
     * @throws PrestaShopException
     */
    public function initContent()
    {
        if (!Module::isEnabled($this->module->name)) {
            Tools::redirect('index.php?controller=order&step=3');
        }
        $cart = $this->context->cart;
        if (!$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=3');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=3');
        }

        $this->addJS("https://secure.ewaypayments.com/scripts/eCrypt.js", false);
        parent::initContent();
        $this->setTemplate('eupayment.tpl');


        $sandbox = Configuration::get('EWAYRAPID_SANDBOX');
        $username = Configuration::get('EWAYRAPID_USERNAME');
        $password = Configuration::get('EWAYRAPID_PASSWORD');
        $paymenttype = explode(',', Configuration::get('EWAYRAPID_PAYMENTTYPE'));
        if (count($paymenttype) == 0) {
            $paymenttype = array('visa', 'mastercard');
        }

        $is_failed = Tools::getValue('ewayerror');

        /* Load objects */
        $address = new Address((int)$cart->id_address_invoice);
        $shipping_address = new Address((int)$cart->id_address_delivery);
        $customer = new Customer((int)$cart->id_customer);
        $currency = new Currency((int)$cart->id_currency);

        $total_amount = number_format($cart->getOrderTotal(), 2, '.', '') * 100;
        $redirect_url = (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http')
            .'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/'.$this->module->name.'/eway.php';

        include_once(_PS_MODULE_DIR_.'/ewayrapid/lib/eWAY/RapidAPI.php');

        // Create Responsive Shared Page Request Object
        $request = new EwayCreateAccessCodeRequest();

        $country_obj = new Country((int)$address->id_country, Configuration::get('PS_LANG_DEFAULT'));
        $state = '';
        if ($address->id_state) {
            $state = new State((int)$address->id_state);
            $state = $state->iso_code;
        }

        $request->Customer->FirstName = (string)Tools::substr($address->firstname, 0, 50);
        $request->Customer->LastName = (string)Tools::substr($address->lastname, 0, 50);
        $request->Customer->CompanyName = '';
        $request->Customer->JobDescription = '';
        $request->Customer->Street1 = (string)Tools::substr($address->address1, 0, 50);
        $request->Customer->Street2 = (string)Tools::substr($address->address2, 0, 50);
        $request->Customer->City = (string)Tools::substr($address->city, 0, 50);
        $request->Customer->State = (string)Tools::substr($state, 0, 50);
        $request->Customer->PostalCode = (string)Tools::substr($address->postcode, 0, 30);
        $request->Customer->Country = Tools::strtolower((string)$country_obj->iso_code);
        $request->Customer->Email = (string)Tools::substr($customer->email, 0, 50);
        $request->Customer->Phone = (string)Tools::substr($address->phone, 0, 32);
        $request->Customer->Mobile = (string)Tools::substr($address->phone_mobile, 0, 32);

        // require field
        $country_obj = new Country(
            (int)$shipping_address->id_country,
            Configuration::get('PS_LANG_DEFAULT')
        );
        $state = '';
        if ($address->id_state) {
            $state = new State((int)$shipping_address->id_state);
            $state = $state->iso_code;
        }
        $request->ShippingAddress->FirstName = (string)Tools::substr($shipping_address->firstname, 0, 50);
        $request->ShippingAddress->LastName = (string)Tools::substr($shipping_address->lastname, 0, 50);
        $request->ShippingAddress->Street1 = (string)Tools::substr($shipping_address->address1, 0, 50);
        $request->ShippingAddress->Street2 = (string)Tools::substr($shipping_address->address2, 0, 50);
        $request->ShippingAddress->City = (string)Tools::substr($shipping_address->city, 0, 50);
        $request->ShippingAddress->State = (string)Tools::substr($state, 0, 50);
        $request->ShippingAddress->PostalCode = (string)Tools::substr($shipping_address->postcode, 0, 30);
        $request->ShippingAddress->Country = Tools::strtolower((string)$country_obj->iso_code);
        $request->ShippingAddress->Email = (string)Tools::substr($customer->email, 0, 50);
        $request->ShippingAddress->Phone = (string)Tools::substr($shipping_address->phone, 0, 32);
        $request->ShippingAddress->ShippingMethod = 'Unknown';

        $total = 0;
        $invoice_desc = '';
        $products = $cart->getProducts();
        foreach ($products as $product) {
            $item = new EwayLineItem();
            $item->SKU = (string)Tools::substr($product['id_product'], 0, 12);
            $item->Description = (string)Tools::substr($product['name'], 0, 26);
            $item->Quantity = $product['cart_quantity'];
            $item->UnitCost = number_format($product['price_wt'], 2, '.', '') * 100;
            if (isset($product['ecotax'])) {
                $item->Tax = number_format($product['ecotax'], 2, '.', '') * 100;
            }
            $item->Total = number_format($product['total_wt'], 2, '.', '') * 100;
            $request->Items->LineItem[] = $item;
            $invoice_desc .= $product['name'].', ';
            $total = $total + $item->Total;
        }
        $invoice_desc = (string)Tools::substr($invoice_desc, 0, -2);
        if (Tools::strlen($invoice_desc) > 64) {
            $invoice_desc = (string)Tools::substr($invoice_desc, 0, 61).'...';
        }

        // If totals don't match add shipping item
        if ($total != $total_amount) {
            $carrier = new Carrier($cart->id_carrier);
            if (version_compare(_PS_VERSION_, '1.5', '<')) {
                $shipping_cost_wt = $cart->getOrderShippingCost();
                $id = $cart->id_carrier;
            } else {
                $shipping_cost_wt = $cart->getTotalShippingCost();
                $id = $carrier->id_reference;
            }
            $item = new EwayLineItem();
            $item->SKU = $id;
            $item->Description = (string)Tools::substr($carrier->name, 0, 26);
            $item->Quantity = 1;
            $item->UnitCost = number_format($shipping_cost_wt, 2, '.', '') * 100;
            $item->Total = number_format($shipping_cost_wt, 2, '.', '') * 100;
            $request->Items->LineItem[] = $item;
        }

        $opt1 = new EwayOption();
        $opt1->Value = (int)$cart->id.'_'.date('YmdHis').'_'.$cart->secure_key;
        $request->Options->Option[0] = $opt1;

        $request->Payment->TotalAmount = $total_amount;
        $request->Payment->InvoiceNumber = (int)$cart->id;
        $request->Payment->InvoiceDescription = $invoice_desc;
        $request->Payment->InvoiceReference = (int)$cart->id;
        $request->Payment->CurrencyCode = $currency->iso_code;

        $request->RedirectUrl = $redirect_url;
        $request->Method = 'ProcessPayment';
        $request->TransactionType = 'Purchase';
        $request->DeviceID = 'prestashop-'._PS_VERSION_.' transparent-'.$this->module->version;
        $request->CustomerIP = Tools::getRemoteAddr();
        $request->PartnerID = '3c397a07266f41cab3282d9fe9248481';

        // Call RapidAPI
        $eway_params = array();
        if ($sandbox) {
            $eway_params['sandbox'] = true;
        }
        $service = new EwayRapidAPI($username, $password, $eway_params);

        $smarty = $this->context->smarty;

        $request->CancelUrl = 'http://www.example.org';
        $request->CustomerReadOnly = true;
        $result = $service->createAccessCodesShared($request);

        $smarty->assign(array(
            'callback' => $redirect_url.'?AccessCode='.$result->AccessCode,
            'SharedPageUrl' => $result->SharedPaymentUrl,
        ));

        $smarty->assign(array(
            'AccessCode' => $result->AccessCode,
            'payment_type' => $paymenttype,
            'isFailed' => $is_failed,
            'module_dir' => $this->module->getPathUri()
        ));

        // Check if any error returns
        if (isset($result->Errors)) {
            // Get Error Messages from Error Code
            $error_array = explode(',', $result->Errors);
            $lbl_error = '';
            foreach ($error_array as $error) {
                $error = $service->getMessage($error);
                $lbl_error .= $error.'<br />';
            }


            $msg = 'eWAY error (get access code): '.$lbl_error;
            $smarty->assign('error', $msg);
            Logger::addLog($msg, 4, null, null, (int)$cart->id);
            return '<p style="color: red;">'.$lbl_error.'</p>';
        }

    }
}
