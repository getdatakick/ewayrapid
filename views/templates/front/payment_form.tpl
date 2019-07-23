{*
*
*  @author     eWAY www.eway.com.au
*  @copyright  2015, Web Active Corporation Pty Ltd
*  @license    http://opensource.org/licenses/MIT MIT
*}

<form action="{$gateway_url|escape:'htmlall':'UTF-8'}" id="payment-form" method='post'>

  <div class="form-group row">
    <label for="EWAY_CARDNAME" class="col-md-3 form-control-label required">{l s='Card Holder Name' mod='ewayrapid'}</label>
    <div class="col-md-6">
        <input type="text" class="form-control" name="EWAY_CARDNAME" id='EWAY_CARDNAME' size="30" autocomplete="cc-name" />
    </div>
  </div>

  <div class="form-group row">
    <label for="EWAY_CARDNUMBER" class="col-md-3 form-control-label required">{l s='Card Number' mod='ewayrapid'}</label>
    <div class="col-md-6">
        <input type="text" class="form-control" name="EWAY_CARDNUMBER" id='EWAY_CARDNUMBER' autocomplete="cc-number" size="30" maxlength="19" pattern="\d*" />
    </div>
  </div>

  <div class="form-group row">
    <label for="EWAY_CARDEXPIRYMONTH" class="col-md-3 form-control-label required">{l s='Card Expiry' mod='ewayrapid'}</label>
    <div class="col-md-2">
        <select class="form-control form-control-select" id="EWAY_CARDEXPIRYMONTH" name="EWAY_CARDEXPIRYMONTH">
            {section name=date_m start=01 loop=13}
                <option value="{$smarty.section.date_m.index|string_format:"%02d"|escape:'htmlall':'UTF-8'}">{$smarty.section.date_m.index|string_format:"%02d"|escape:'htmlall':'UTF-8'}</option>
            {/section}
        </select> 
    </div>
    <div class="col-md-1">/</div>
    <div class="col-md-2"> <select class="form-control form-control-select" id="EWAY_CARDEXPIRYYEAR" name="EWAY_CARDEXPIRYYEAR">
            {section name=date_y start=17 loop=27}
                    <option value="{$smarty.section.date_y.index|escape:'htmlall':'UTF-8'}">{$smarty.section.date_y.index|escape:'htmlall':'UTF-8'}</option>
            {/section}
        </select>
    </div>
  </div>

  <div class="form-group row">
    <label for="EWAY_CARDCVN" class="col-md-3 form-control-label required">{l s='Security Code (CVV/CVC)' mod='ewayrapid'}</label>
    <div class="col-md-6">
        <input class="form-control" type="text" name="EWAY_CARDCVN" id="EWAY_CARDCVN" size="4" maxlength="4" autocomplete="cc-csc" pattern="\d*" />
    </div>
  </div>
  
  <input type='hidden' name='EWAY_ACCESSCODE' value='{$AccessCode|escape:'htmlall':'UTF-8'}' />
  <input type='hidden' name='EWAY_PAYMENTTYPE' value='creditcard' />
</form>