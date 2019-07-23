{*
*
*  @author     eWAY www.eway.com.au
*  @copyright  2015, Web Active Corporation Pty Ltd
*  @license    http://opensource.org/licenses/MIT MIT
*}

<div>{l s='Please wait, initializing payment...' mod='ewayrapid'}</div>

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

<script language="JavaScript" type="text/javascript">
{literal}

function initPayment() {
    window.eCrypt.showModalPayment({
        sharedPaymentUrl: "{/literal}{$SharedPageUrl|escape:'quotes':'UTF-8'}{literal}"
    }, function(result, transactionID, errors) {
        if (result === "Complete") {
            window.location.href = "{/literal}{$callback|escape:'quotes':'UTF-8'}{literal}";
        } else {
            window.location.href = "index.php?controller=order&step=3";
        }
    });
}

function bootstrapPayment() {
   if (! window.eCrypt.showModalPayment)  {
       setTimeout(bootstrapPayment, 100);
   } else {
       initPayment();
   }
}

docReady(bootstrapPayment);
{/literal}
</script>