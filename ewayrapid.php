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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ewayrapid extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'ewayrapid';
        $this->tab = 'payments_gateways';
        $this->version = '3.4.6';
        $this->author = 'eWAY';

        $this->module_key = "a3474eaf51d2d7459e8a0542b406be2b";
        $this->bootstrap = true;

        $this->ps_versions_compliancy = array(
            'min' => '1.5',
            'max' => _PS_VERSION_
        );

        parent::__construct();

        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('eWAY Payments');
        $this->description = $this->l('Accepts payments with eWAY - Payments made easy!');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->payment_types = array(
            'visa' => $this->l('Visa'),
            'mastercard' => $this->l('MasterCard'),
            'amex' => $this->l('American Express'),
            'jcb' => $this->l('JCB'),
            'diners' => $this->l('Diners'),
            'paypal' => $this->l('PayPal'),
            'masterpass' => $this->l('MasterPass'),
        );
    }

    public function install()
    {
        /* The cURL PHP extension must be enabled to use this module */
        if (!function_exists('curl_version')) {
            $this->_errors[] = $this->l(
                'Sorry, this module requires the cURL PHP '
                .'Extension (http://www.php.net/curl), which is not enabled '
                .'on your server. Please ask your hosting provider for '
                .'assistance.'
            );
            return false;
        }

        if (!parent::install()
                || !Configuration::updateValue('EWAYRAPID_USERNAME', '')
                || !Configuration::updateValue('EWAYRAPID_PASSWORD', '')
                || !Configuration::updateValue('EWAYRAPID_SANDBOX', 1)
                || !Configuration::updateValue('EWAYRAPID_PAYMENTTYPE', 'visa,mastercard')
                || !Configuration::updateValue('EWAYRAPID_PAYMENTMETHOD', 'iframe')
                || !$this->registerHook('payment')
                || !$this->registerHook('displayPaymentEU')
                || !$this->registerHook('paymentReturn')
                || !$this->registerHook('backOfficeHeader')
                || !$this->registerHook('displayHeader')) {
            $this->_errors[] = $this->l('There was an Error installing the module.');
            return false;
        }

        if (_PS_VERSION_ >= '1.7' && (!$this->registerHook('paymentOptions'))) {
            $this->_errors[] = $this->l('There was an Error installing the module.');
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('EWAYRAPID_USERNAME')
                || !Configuration::deleteByName('EWAYRAPID_PASSWORD')
                || !Configuration::deleteByName('EWAYRAPID_SANDBOX')
                || !Configuration::deleteByName('EWAYRAPID_PAYMENTTYPE')
                || !Configuration::deleteByName('EWAYRAPID_PAYMENTMETHOD')
                || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    public function hookBackOfficeHeader()
    {
        $this->context->controller->addCSS($this->_path.'views/css/ewayrapid.css');
    }

    public function getContent()
    {
        $this->postProcess();

        $this->context->smarty->assign(array(
            'module_dir' => $this->_path
        ));

        $html = $this->display(__FILE__, 'views/templates/admin/back_office.tpl');
        return $html.$this->displayForm();
    }

    private function displayForm()
    {

        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $lang->id;
        $helper->identifier = $this->identifier;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->submit_action = 'submitRapideWAY';
        $helper->toolbar_btn = array(
            'save' => array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                  'title' => $this->l('eWAY Settings'),
                  'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => (_PS_VERSION_ < '1.6' ? 'radio':'switch'),
                        'label' => $this->l('Sandbox mode'),
                        'name' => 'sandbox',
                        'is_bool' => true,
                        'class' => 't',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'username',
                        'label' => $this->l('API Key'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'password',
                        'name' => 'password',
                        'label' => $this->l('API Password'),
                        'required' => true,
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Payment method'),
                        'name' => 'paymentmethod',
                        'required' => true,
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 'iframe',
                                    'name' => $this->l('IFrame'),
                                ),
                                array(
                                    'id_option' => 'transparent',
                                    'name' => $this->l('Transparent Redirect'),
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'checkbox',
                        'label' => $this->l('Payment Types'),
                        'name' => 'paymenttype',
                        'required' => true,
                        'values'  => array(
                            'query' => $this->getPaymentTypeFields(),
                            'id' => 'id_option',
                            'name' => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right button',
                )
            )
        );

        $types = explode(',', Configuration::get('EWAYRAPID_PAYMENTTYPE'));
        $typeFields = array();
        foreach ($types as $id) {
            $typeFields['paymenttype_'.$id] = 'on';
        }

        $helper->tpl_vars = array(
            'fields_value' => $this->getFieldValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    private function getPaymentTypeFields()
    {
        $types = array();
        foreach ($this->payment_types as $id => $name) {
            $types[] = array(
                    'id_option' => $id,
                    'name' => $name,
            );
        }
        return $types;
    }

    private function getFieldValues()
    {
        $types = explode(',', Configuration::get('EWAYRAPID_PAYMENTTYPE'));
        $typeFields = array();
        foreach ($types as $id) {
            $typeFields['paymenttype_'.$id] = 'on';
        }
        
        return array(
                'sandbox' => Configuration::get('EWAYRAPID_SANDBOX'),
                'username' => Configuration::get('EWAYRAPID_USERNAME'),
                'password' => Configuration::get('EWAYRAPID_PASSWORD'),
                'paymentmethod' => Configuration::get('EWAYRAPID_PAYMENTMETHOD'),
            ) + $typeFields;
    }
    
    private function postProcess()
    {
        if (Tools::isSubmit('submitRapideWAY')) {
            $post_errors = array();

            if (!Tools::getValue('username')) {
                $post_errors[] = $this->l('eWAY API Key cannot be empty');
            }

            if (!Tools::getValue('password')) {
                $post_errors[] = $this->l('eWAY API Password cannot be empty');
            }

            $types = array();
            foreach (array_keys($this->payment_types) as $id) {
                if (Tools::getValue('paymenttype_'.$id)) {
                    $types[] = $id;
                }
            }

            if (empty($types)) {
                $post_errors[] = $this->l('You need to accept at least 1 payment type');
            }

            if (!Tools::getValue('paymentmethod')) {
                $post_errors[] = $this->l('Please select a payment method');
            }

            if (empty($post_errors)) {
                Configuration::updateValue('EWAYRAPID_SANDBOX', (int)Tools::getValue('sandbox'));
                Configuration::updateValue('EWAYRAPID_USERNAME', trim(Tools::getValue('username')));
                Configuration::updateValue('EWAYRAPID_PASSWORD', trim(Tools::getValue('password')));
                Configuration::updateValue('EWAYRAPID_PAYMENTMETHOD', trim(Tools::getValue('paymentmethod')));
                Configuration::updateValue('EWAYRAPID_PAYMENTTYPE', implode(',', $types));

                $this->context->smarty->assign('eWAY_save_success', true);
                Logger::addLog('eWAY configuration updated', 1, null);
            } else {
                $this->context->smarty->assign('eWAY_save_fail', true);
                $this->context->smarty->assign('eWAY_errors', $post_errors);
            }
        }
    }

    public function hookHeader()
    {
        $this->context->controller->addCSS(($this->_path).'views/css/front.css', 'all');
    }

    public function hookDisplayPaymentEU()
    {
        if (! $this->active) {
            return;
        }

        return [
            [
                'cta_text' => $this->l('Pay with debit or credit card'),
                'logo'     => Media::getMediaPath($this->local_path.'views/img/eway logo (gp) for white bkg_poster_.png'),
                'action'   => $this->context->link->getModuleLink($this->name, 'eupayment', [], true),
            ]
        ];
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }

        $sandbox = Configuration::get('EWAYRAPID_SANDBOX');
        $username = Configuration::get('EWAYRAPID_USERNAME');
        $password = Configuration::get('EWAYRAPID_PASSWORD');
        $paymentmethod = Configuration::get('EWAYRAPID_PAYMENTMETHOD');
        $paymenttype = explode(',', Configuration::get('EWAYRAPID_PAYMENTTYPE'));
        if (count($paymenttype) == 0) {
            $paymenttype = array('visa', 'mastercard');
        }

        if (empty($username) || empty($password)) {
            return;
        }

        $is_failed = Tools::getValue('ewayerror');

        /* Load objects */
        $address = new Address((int)$params['cart']->id_address_invoice);
        $shipping_address = new Address((int)$params['cart']->id_address_delivery);
        $customer = new Customer((int)$params['cart']->id_customer);
        $currency = new Currency((int)$params['cart']->id_currency);

        $total_amount = number_format($params['cart']->getOrderTotal(), 2, '.', '') * 100;
        $redirect_url = (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http')
            .'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/'.$this->name.'/eway.php';

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
        $products = $params['cart']->getProducts();
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
            $carrier = new Carrier($this->context->cart->id_carrier);
            if (version_compare(_PS_VERSION_, '1.5', '<')) {
                $shipping_cost_wt = $this->context->cart->getOrderShippingCost();
                $id = $this->context->cart->id_carrier;
            } else {
                $shipping_cost_wt = $this->context->cart->getTotalShippingCost();
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
        $opt1->Value = (int)$params['cart']->id.'_'.date('YmdHis').'_'.$params['cart']->secure_key;
        $request->Options->Option[0] = $opt1;

        $request->Payment->TotalAmount = $total_amount;
        $request->Payment->InvoiceNumber = (int)$params['cart']->id;
        $request->Payment->InvoiceDescription = $invoice_desc;
        $request->Payment->InvoiceReference = (int)$params['cart']->id;
        $request->Payment->CurrencyCode = $currency->iso_code;

        $request->RedirectUrl = $redirect_url;
        $request->Method = 'ProcessPayment';
        $request->TransactionType = 'Purchase';
        $request->DeviceID = 'prestashop-'._PS_VERSION_.' transparent-'.$this->version;
        $request->CustomerIP = Tools::getRemoteAddr();
        $request->PartnerID = '3c397a07266f41cab3282d9fe9248481';

        // Call RapidAPI
        $eway_params = array();
        if ($sandbox) {
            $eway_params['sandbox'] = true;
        }
        $service = new EwayRapidAPI($username, $password, $eway_params);

        $smarty = $this->context->smarty;

        if ($paymentmethod == 'iframe') {
            $request->CancelUrl = 'http://www.example.org';
            $request->CustomerReadOnly = true;
            $result = $service->createAccessCodesShared($request);

            $smarty->assign(array(
                'callback' => $redirect_url.'?AccessCode='.$result->AccessCode,
                'SharedPageUrl' => $result->SharedPaymentUrl,
            ));
        } else {
            $result = $service->createAccessCode($request);

            $smarty->assign(array(
                'gateway_url' => $result->FormActionURL
            ));
        }

        $smarty->assign(array(
            'AccessCode' => $result->AccessCode,
            'payment_type' => $paymenttype,
            'isFailed' => $is_failed,
            'module_dir' => $this->_path
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

            $this->response['Response Reason Text'] = $lbl_error;
            $msg = 'eWAY error (get access code): '.$lbl_error;
            Logger::addLog($msg, 4, null, null, (int)$params['cart']->id);
            return '<p style="color: red;">'.$lbl_error.'</p>';
        }

        if ($paymentmethod == 'iframe') {
            return $this->context->smarty->fetch($this->local_path.'/views/templates/hook/hook_payment_iframe.tpl');
        } else {
            return $this->context->smarty->fetch($this->local_path.'/views/templates/hook/hook_payment.tpl');
        }
    }

    public function hookPaymentOptions($params)
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return false;
        }
        if (!$this->active) {
            return array();
        }

        $sandbox = Configuration::get('EWAYRAPID_SANDBOX');
        $username = Configuration::get('EWAYRAPID_USERNAME');
        $password = Configuration::get('EWAYRAPID_PASSWORD');
        $paymentmethod = Configuration::get('EWAYRAPID_PAYMENTMETHOD');
        $paymenttype = explode(',', Configuration::get('EWAYRAPID_PAYMENTTYPE'));
        if (count($paymenttype) == 0) {
            $paymenttype = array('visa', 'mastercard');
        }

        if (empty($username) || empty($password)) {
            return;
        }

        $is_failed = Tools::getValue('ewayerror');

        /* Load objects */
        $address = new Address((int)$params['cart']->id_address_invoice);
        $shipping_address = new Address((int)$params['cart']->id_address_delivery);
        $customer = new Customer((int)$params['cart']->id_customer);
        $currency = new Currency((int)$params['cart']->id_currency);

        $total_amount = number_format($params['cart']->getOrderTotal(), 2, '.', '') * 100;
        $redirect_url = (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http')
            .'://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/'.$this->name.'/eway.php';

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
        $products = $params['cart']->getProducts();
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
            $carrier = new Carrier($this->context->cart->id_carrier);
            if (version_compare(_PS_VERSION_, '1.5', '<')) {
                $shipping_cost_wt = $this->context->cart->getOrderShippingCost();
                $id = $this->context->cart->id_carrier;
            } else {
                $shipping_cost_wt = $this->context->cart->getTotalShippingCost();
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
        $opt1->Value = (int)$params['cart']->id.'_'.date('YmdHis').'_'.$params['cart']->secure_key;
        $request->Options->Option[0] = $opt1;

        $request->Payment->TotalAmount = $total_amount;
        $request->Payment->InvoiceNumber = (int)$params['cart']->id;
        $request->Payment->InvoiceDescription = $invoice_desc;
        $request->Payment->InvoiceReference = (int)$params['cart']->id;
        $request->Payment->CurrencyCode = $currency->iso_code;

        $request->RedirectUrl = $redirect_url;
        $request->Method = 'ProcessPayment';
        $request->TransactionType = 'Purchase';
        $request->DeviceID = 'prestashop-'._PS_VERSION_.' transparent-'.$this->version;
        $request->CustomerIP = Tools::getRemoteAddr();
        $request->PartnerID = '3c397a07266f41cab3282d9fe9248481';

        // Call RapidAPI
        $eway_params = array();
        if ($sandbox) {
            $eway_params['sandbox'] = true;
        }
        $service = new EwayRapidAPI($username, $password, $eway_params);

        $smarty = $this->context->smarty;

        if ($paymentmethod == 'iframe') {
            $request->CancelUrl = 'http://www.example.org';
            $request->CustomerReadOnly = true;
            $result = $service->createAccessCodesShared($request);

            $smarty->assign(array(
                'callback' => $redirect_url.'?AccessCode='.$result->AccessCode,
                'SharedPageUrl' => $result->SharedPaymentUrl,
            ));
        } else {
            $result = $service->createAccessCode($request);

            $smarty->assign(array(
                'gateway_url' => $result->FormActionURL
            ));
        }

        $smarty->assign(array(
            'AccessCode' => $result->AccessCode,
            'payment_type' => $paymenttype,
            'isFailed' => $is_failed,
            'module_dir' => _PS_MODULE_DIR_
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

            $this->response['Response Reason Text'] = $lbl_error;
            $msg = 'eWAY error (get access code): '.$lbl_error;
            Logger::addLog($msg, 4, null, null, (int)$params['cart']->id);
            return '<p style="color: red;">'.$lbl_error.'</p>';
        }

        $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();

        if ($paymentmethod == 'iframe') {
            $paymentOption->setCallToActionText($this->l('Pay by Card with eWAY'))
                ->setAdditionalInformation(
                    $this->context->smarty->fetch('module:ewayrapid/views/templates/front/iframe.tpl')
                )
                ->setBinary(true);
            return array($paymentOption);
        } else {
            $paymentOption->setCallToActionText($this->l('Pay by Card with eWAY'))
                ->setForm($this->context->smarty->fetch('module:ewayrapid/views/templates/front/payment_form.tpl'))
                ->setAdditionalInformation(
                    $this->context->smarty->fetch('module:ewayrapid/views/templates/front/payment_infos.tpl')
                );
            return array($paymentOption);
        }
    }

    public function hookPaymentReturn()
    {
        if (!$this->active) {
            return null;
        }
        return $this->context->smarty->fetch($this->local_path.'/views/templates/hook/confirmation.tpl');
    }

    public function getAccessCodeResult()
    {
        if (!$_REQUEST['AccessCode']) {
            Tools::redirect('order.php');
            return false;
        }

        include_once(_PS_MODULE_DIR_.'/ewayrapid/lib/eWAY/RapidAPI.php');

        $sandbox = Configuration::get('EWAYRAPID_SANDBOX');
        $username = Configuration::get('EWAYRAPID_USERNAME');
        $password = Configuration::get('EWAYRAPID_PASSWORD');

        // Call RapidAPI
        $eway_params = array();
        if ($sandbox) {
            $eway_params['sandbox'] = true;
        }
        $service = new EwayRapidAPI($username, $password, $eway_params);

        $result = $service->getAccessCodeResult($_REQUEST['AccessCode']);

        $is_error = false;
        // Check if any error returns
        if (isset($result->Errors)) {
            $error_array = explode(',', $result->Errors);
            $lbl_error = '';
            $is_error = true;
            foreach ($error_array as $error) {
                $error = $service->getMessage($error);
                $lbl_error .= $error.', ';
            }
            $msg = 'eWAY error (get result): '.$lbl_error;
            Logger::addLog($msg, 4);
        }

        if (!$is_error) {
            if (!$result->TransactionStatus) {
                $error_array = explode(',', $result->ResponseMessage);
                $lbl_error = '';
                $admin_error = '';
                $is_error = true;
                foreach ($error_array as $error) {
                    $error = trim($error);
                    $error_msg = $service->getMessage($error);
                    if (stripos($error, 'F') === false) {
                        $lbl_error .= $error_msg.', ';
                    }

                    $admin_error .= "($error) $error_msg, ";
                }
                $lbl_error = Tools::substr($lbl_error, 0, -2);
                $admin_error = Tools::substr($admin_error, 0, -2);
                $msg = 'eWAY payment failed (get result): '.$admin_error;
                Logger::addLog($msg, 2);
            }
        }

        // If error, send user back to order page
        if ($is_error) {
            $checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ?
                'order-opc' : 'order';

            $url = _PS_VERSION_ >= '1.5' ?
                'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?';

            $url .= 'step=3&cgv=1&ewayerror=1&message='.$lbl_error;

            if (Configuration::get('PS_ORDER_PROCESS_TYPE') == 'order-opc') {
                $url.'#eway';
            }

            Tools::redirect($url);
            exit;
        }

        $option1 = $result->Options[0]->Value;
        $id_cart = (int)Tools::substr($option1, 0, strpos($option1, '_'));
        if (_PS_VERSION_ >= 1.5) {
            Context::getContext()->cart = new Cart((int)$id_cart);
        }
        $cart = Context::getContext()->cart;
        $secure_cart = explode('_', $option1);

        if (!Validate::isLoadedObject($cart)) {
            Logger::addLog('Cart loading failed for cart '.$secure_cart, 4);
            die('An unrecoverable error occured with the cart ');
        }

        $customer = new Customer((int)$cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Logger::addLog('Issue loading customer');
            die('An unrecoverable error occured while retrieving your data');
        }

        $extra_vars = array();
        $extra_vars['transaction_id'] = $result->TransactionID;

        // use the cart total since eWAY surcharges will cause an error
        //$order_total = $cart->getOrderTotal();
        $order_total = (float)($result->TotalAmount / 100);

        $this->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_PAYMENT'),
            $order_total,
            $this->displayName,
            $this->l('eWAY Transaction ID: ').$result->TransactionID,
            $extra_vars,
            null,
            false,
            $customer->secure_key
        );

        $confirmurl = 'index.php?controller=order-confirmation&';

        if (_PS_VERSION_ < '1.5') {
            $confirmurl = 'order-confirmation.php?';
        }
        Tools::redirect(
            $confirmurl.'id_module='.(int)$this->id.'&id_cart='.
            (int)$cart->id.'&key='.$customer->secure_key
        );
    }

}
