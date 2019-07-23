{*
*
*  @author     eWAY www.eway.com.au
*  @copyright  2015, Web Active Corporation Pty Ltd
*  @license    http://opensource.org/licenses/MIT MIT
*}
<p class="payment_module">

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
        
	<a class="eway-iframe ladda-button" data-style="slide-left" name="eway" id="processPayment" title="Pay with card" href="#">{l s='Pay with credit or debit card' mod='ewayrapid'}</a>
</p>
<script src="https://secure.ewaypayments.com/scripts/eCrypt.js"></script>
<script language="JavaScript" type="text/javascript" >
//<!--
{literal}
$(document).ready(function(){
    /**
    * eWAY Rapid IFrame config object.
    */
    var eWAYConfig = {
        sharedPaymentUrl: "{/literal}{$SharedPageUrl|escape:'quotes':'UTF-8'}{literal}"
    };
    
    var ewayPaid = false;
    
    /**
     * eWAY Rapid IFrame callback
     */
    function resultCallback(result, transactionID, errors) {
        if (result == "Complete") {
            var ewayPaid = true;
            $('#processPayment').html('<img src="img/loader.gif"> {/literal}{l s='Processing, please wait...' mod='ewayrapid'}{literal}');
            window.location.href = "{/literal}{$callback|escape:'quotes':'UTF-8'}{literal}";
        } else if (result == "Error") {
            $('#processPayment').html('{/literal}{l s='Pay with credit or debit card' mod='ewayrapid'}{literal}');
            alert("There was a problem completing the payment: " + errors);
        } else {
            $('#processPayment').html('{/literal}{l s='Pay with credit or debit card' mod='ewayrapid'}{literal}');
        }
    }
    
    $('#processPayment').on('click', function(e){
        $('#processPayment').html('<img src="img/loader.gif"> {/literal}{l s='Opening the secure payment window' mod='ewayrapid'}{literal}');
        if (!ewayPaid)
          eCrypt.showModalPayment(eWAYConfig, resultCallback);
        return false;
    });
});
{/literal}
//-->
</script>