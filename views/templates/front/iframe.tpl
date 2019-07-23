{*
*
*  @author     eWAY www.eway.com.au
*  @copyright  2015, Web Active Corporation Pty Ltd
*  @license    http://opensource.org/licenses/MIT MIT
*}

<div class="row">
	{if $isFailed == 1}
	<p class="alert error alert-danger">
		{if !empty($smarty.get.message)}
			{l s='Sorry, your payment failed: ' mod='ewayrapid'}
			{$smarty.get.message|escape:'htmlall':'UTF-8'}
		{else}
			{l s='Error, please verify the card information' mod='ewayrapid'}
		{/if}
	</p>
	{/if}
        
	<a class="eway-iframe btn btn-info" data-style="slide-left" name="eway" id="processPayment" title="{l s='Pay with credit or debit card' mod='ewayrapid'}" href="#">
            <i class="material-icons">&#xE870;</i> <span class="eway-call-to-action">{l s='Pay with credit or debit card' mod='ewayrapid'}</span>
        </a>

<script src="https://secure.ewaypayments.com/scripts/eCrypt.js"></script>
<script language="JavaScript" type="text/javascript" >
//<!--
{literal}
document.addEventListener("DOMContentLoaded", function(event) { 
    /**
    * eWAY Rapid IFrame config object.
    */
    var eWAYConfig = {
        sharedPaymentUrl: "{/literal}{$SharedPageUrl|escape:'htmlall':'UTF-8'}{literal}"
    };
    
    var ewayPaid = false;
    
    /**
     * eWAY Rapid IFrame callback
     */
    function resultCallback(result, transactionID, errors) {
        if (result == "Complete") {
            var ewayPaid = true;
            jQuery('#processPayment').html('<img src="img/loader.gif"> {/literal}{l s='Processing, please wait...' mod='ewayrapid'}{literal}');
            window.location.href = "{/literal}{$callback|escape:'htmlall':'UTF-8'}{literal}";
        } else if (result == "Error") {
            jQuery('#processPayment').html('{/literal}{l s='Pay with credit or debit card' mod='ewayrapid'}{literal}');
            alert("There was a problem completing the payment: " + errors);
        } else {
            jQuery('#processPayment').html('{/literal}{l s='Pay with credit or debit card' mod='ewayrapid'}{literal}');
        }
    }
    
    jQuery('#processPayment').on('click', function(e){
        if (jQuery('#conditions_to_approve\\[terms-and-conditions\\]').is(':checked')) {
            jQuery('#processPayment').html('<img src="img/loader.gif"> {/literal}{l s='Opening the secure payment window' mod='ewayrapid'}{literal}');
            if (!ewayPaid) {
                eCrypt.showModalPayment(eWAYConfig, resultCallback);
            }
            return false;
        } else {
            alert('{/literal}{l s='Please agree to the terms of service to proceed' mod='ewayrapid'}{literal}');
        }
    });
});
{/literal}
//-->
</script>
</div>