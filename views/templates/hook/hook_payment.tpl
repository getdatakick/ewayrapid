{*
*
*  @author     eWAY www.eway.com.au
*  @copyright  2015, Web Active Corporation Pty Ltd
*  @license    http://opensource.org/licenses/MIT MIT
*}
<div class="row">
	<div class="col-xs-12 col-md-6">
            <div id="eway_payment_module" class="payment_module">

                <form method='post' name='ewaypay' action='{$gateway_url|escape:'quotes':'UTF-8'}' class='eway_payment_form' onsubmit="return eWAYpayment_submit();" style="margin: 0; padding: 0;">

                        <a name="eway"></a>
                        {if $isFailed == 1}
                        <div class="alert alert-danger"  id="eway_error">
                                {if !empty($smarty.get.message)}
                                        {l s='Sorry, your payment failed: ' mod='ewayrapid'}
                                        {$smarty.get.message|escape:'htmlall':'UTF-8'}
                                {else}
                                        {l s='Error, please verify the card information' mod='ewayrapid'}
                                {/if}
                        </div>
                        {/if}

                        <input type='hidden' name='EWAY_ACCESSCODE' value='{$AccessCode|escape:'quotes':'UTF-8'}' />

                        <div class="block-left">
                        {if $payment_type|@count == 1}
                                <input type='hidden' name='EWAY_PAYMENTTYPE' value='$payment_type[0]' />
                        {else}
                            {if (in_array('visa', $payment_type) || in_array('mastercard', $payment_type) || in_array('diners', $payment_type) || in_array('jcb', $payment_type) || in_array('amex', $payment_type))}
                                <label><input type='radio' name='EWAY_PAYMENTTYPE' id='eway_radio_cc' value='creditcard' checked='checked' onchange='javascript:select_eWAYPaymentOption("creditcard")' />
                                {if (in_array('visa', $payment_type))}
                                <img src='{$module_dir|escape:'quotes':'UTF-8'}views/img/eway_creditcard_visa.png' height='30' alt='Visa' />
                                {/if}
                                {if (in_array('mastercard', $payment_type))}
                                <img src='{$module_dir|escape:'quotes':'UTF-8'}views/img/eway_creditcard_master.png' height='30' alt='MasterCard' />
                                {/if}
                                {if (in_array('diners', $payment_type))}
                                <img src='{$module_dir|escape:'quotes':'UTF-8'}views/img/eway_creditcard_diners.png' height='30' alt='Diners Club' />
                                {/if}
                                {if (in_array('jcb', $payment_type))}
                                <img src='{$module_dir|escape:'quotes':'UTF-8'}views/img/eway_creditcard_jcb.png' height='30' alt='JCB' />
                                {/if}
                                {if (in_array('amex', $payment_type))}
                                <img src='{$module_dir|escape:'quotes':'UTF-8'}views/img/eway_creditcard_amex.png' height='30' alt='AMEX' />
                                {/if}
                                </label>
                            {/if}
                            {if in_array('paypal', $payment_type)}
                                <label><input type='radio' name='EWAY_PAYMENTTYPE' value='paypal' onchange='javascript:select_eWAYPaymentOption("paypal")' /> <img src='{$module_dir|escape:'quotes':'UTF-8'}views/img/eway_paypal.png' height='30' alt='PayPal' /></label>
                            {/if}
                            {if in_array('masterpass', $payment_type)}
                                <label><input type='radio' name='EWAY_PAYMENTTYPE' value='masterpass' onchange='javascript:select_eWAYPaymentOption("masterpass")' /> <img src='{$module_dir|escape:'quotes':'UTF-8'}views/img/eway_masterpass.png' height='30' alt='MasterPass by MasterCard' /></label>
                            {/if}
                                    {*
                            {if in_array('vme', $payment_type)}
                                <label><input type='radio' name='EWAY_PAYMENTTYPE' value='vme' onchange='javascript:select_eWAYPaymentOption("vme")' /> <img src='{$module_dir|escape:'quotes':'UTF-8'}views/img/eway_vme.png' height='30' /></label>
                            {/if}
                                    *}
                        {/if}

                        </div>

                        {if in_array('paypal', $payment_type)}
                                <p id="tip_paypal" style="display:none;">{l s='After you click "Process Payment" you will be redirected to PayPal to complete your payment.' mod='ewayrapid'}</p>
                        {/if}
                        {if in_array('masterpass', $payment_type)}
                                <p id="tip_masterpass" style="display:none;">{l s='After you click "Process Payment" you will be redirected to MasterPass by MasterCard to complete your payment.' mod='ewayrapid'}</p>
                        {/if}
                        {*
                        {if in_array('vme', $payment_type)}
                                <p id="tip_vme" style="display:none;">{l s='After you click "Process Payment" you will be redirected to V.Me by Visa to complete your payment.' mod='ewayrapid'}</p>
                        {/if}
                        *}

                        {if (in_array('visa', $payment_type) || in_array('mastercard', $payment_type) || in_array('diners', $payment_type) || in_array('jcb', $payment_type) || in_array('amex', $payment_type))}
                        <br />
                        <div class="block-left">

                                <label for="EWAY_CARDNAME">{l s='Card Holder Name' mod='ewayrapid'}</label><br />
                                <input type="text" class="text" name="EWAY_CARDNAME" id='EWAY_CARDNAME' size="30" autocomplete="cc-name" />
                                <span id="ewaycard_error"></span>
                        </div>
                        <br />
                        <div class="block-left">
                                <label for="EWAY_CARDNUMBER">{l s='Card Number' mod='ewayrapid'}</label><br />
                                <input type="text" class="text" name="EWAY_CARDNUMBER" id='EWAY_CARDNUMBER' autocomplete="cc-number" size="30" maxlength="19" pattern="\d*" />
                                <span id="ewaynumber_error"></span>
                        </div>
                        <br />
                        <div class="block-left">
                                <label for="EWAY_CARDEXPIRYMONTH">{l s='Card Expiry' mod='ewayrapid'}</label><br />

                                <select id="EWAY_CARDEXPIRYMONTH" name="EWAY_CARDEXPIRYMONTH">
                                {section name=date_m start=01 loop=13}
                                    <option value="{$smarty.section.date_m.index|string_format:"%02d"|escape:'htmlall':'UTF-8'}">{$smarty.section.date_m.index|string_format:"%02d"|escape:'htmlall':'UTF-8'}</option>
                                {/section}
                                </select>
                                <span> / </span>
                                <select id="EWAY_CARDEXPIRYYEAR" name="EWAY_CARDEXPIRYYEAR">
                                        {section name=date_y start=17 loop=27}
                                                <option value="{$smarty.section.date_y.index|escape:'htmlall':'UTF-8'}">{$smarty.section.date_y.index|escape:'htmlall':'UTF-8'}</option>
                                        {/section}
                                </select>
                                <div id="expiry_error"></div>
                        </div>
                        <br />
                        <div class="block-left">
                            <label for="EWAY_CARDCVN">{l s='Security Code (CVV/CVC)' mod='ewayrapid'}</label><br />
                            <input type="text" class="text" name="EWAY_CARDCVN" id="EWAY_CARDCVN" size="4" maxlength="4" autocomplete="cc-csc" pattern="\d*" />
                            <span id="ewaycvn_error"></span><br />
                            <span id="cvn_details" class="help-block" style="float: left; clear: both;">
                                    {l s='For Mastercard or Visa, this is the last three digits in the signature area on the back of your card.' mod='ewayrapid'}
                                    {if (in_array('amex', $payment_type))}
                                            <br>{l s='For American Express, it\'s the four digits on the front of the card.' mod='ewayrapid'}
                                    {/if}
                        </div>
                
                        {/if}
                        <br>
                        <input type="submit" class="button" name="processPayment" id="processPayment" value="{l s='Process Payment' mod='ewayrapid'} &raquo;" />
                        <br class="clear" />
                </form>
            </div>
        </div>
</div>
                        
<script language="JavaScript" type="text/javascript" >
//<!--

function select_eWAYPaymentOption(v) {
    if (document.getElementById("creditcard_info"))
        document.getElementById("creditcard_info").style.display = "none";
    if (document.getElementById("tip_paypal"))
        document.getElementById("tip_paypal").style.display = "none";
    if (document.getElementById("tip_masterpass"))
        document.getElementById("tip_masterpass").style.display = "none";
    if (document.getElementById("tip_vme"))
        document.getElementById("tip_vme").style.display = "none";
    if (v == 'creditcard') {
        document.getElementById("creditcard_info").style.display = "block";
    } else {
        document.getElementById("tip_" + v).style.display = "block";
    }
}

function eWAYpayment_submit() {
{literal}
	if ($('#eway_radio_cc').is(':checked')) {
		var eway_error = false;
		if ($('#EWAY_CARDNAME').val().length < 1) {
			eway_error = true;
			$('#ewaycard_error').html('<span style="color:red;">Card Holder\'s Name must be entered</span>');
		} else {
			$('#ewaycard_error').empty();
		}

		var ccnum_regex = new RegExp("^[0-9]{13,19}$");
		if (!ccnum_regex.test($('#EWAY_CARDNUMBER').val().replace(/ /g, '')) || !luhn10($('#EWAY_CARDNUMBER').val())) {
			eway_error = true;
			$('#ewaynumber_error').html('<span style="color:red;">Card Number appears invalid</span>');
		} else {
			$('#ewaynumber_error').empty();
		}

		var cc_year = parseInt($('#EWAY_CARDEXPIRYYEAR').val(),10) + 2000;
		var cc_month = parseInt($('#EWAY_CARDEXPIRYMONTH').val(),10);

		var cc_expiry = new Date(cc_year, cc_month, 1);
		var cc_expired = new Date(cc_expiry - 1);
		var today = new Date();

		if (today.getTime() > cc_expired.getTime()) {
			eway_error = true;
			$('#expiry_error').html('<span style="color:red;">This expiry date has passed</span>');
		} else {
			$('#expiry_error').empty();
		}

		var ccv_regex = new RegExp("^[0-9]{3,4}$");
		if (!ccv_regex.test($('#EWAY_CARDCVN').val().replace(/ /g, ''))) {
			eway_error = true;
			$('#ewaycvn_error').html('<span style="color:red;">Security Code appears invalid</span>');
		} else {
			$('#ewaycvn_error').empty();
		}

		if (eway_error) {
			return false;
		}
	}

	$("#processPayment").prop('disabled', true);
	return true;
}

var luhn10 = function(a,b,c,d,e) {
	for(d = +a[b = a.length-1], e=0; b--;)
		c = +a[b], d += ++e % 2 ? 2 * c % 10 + (c > 4) : c;
	return !(d%10)
};

{/literal}
//-->
</script>