{extends file=$jooim_theme_page_template}

{block name='page_title'}{l s='Withdraw from contract here' d='Modules.Jooimwithdrawal.Shop'}{/block}

{block name='page_content'}
  <section class="jooim-withdrawal">
    {if isset($submission_error) && $submission_error}<div class="alert alert-danger" role="alert">{$submission_error|escape:'htmlall':'UTF-8'}</div>{/if}
    <p>{l s='This direct form lets you send an online withdrawal statement without signing in. It does not display any order data.' d='Modules.Jooimwithdrawal.Shop'}</p>
    <form method="post" action="{$review_url|escape:'htmlall':'UTF-8'}" class="jooim-withdrawal-form" data-jooim-direct-form>
      <input type="hidden" name="csrf_token" value="{$csrf_token|escape:'htmlall':'UTF-8'}">
      <div class="row">
        <div class="col-md-6 form-group"><label for="jooim-direct-firstname">{l s='First name' d='Modules.Jooimwithdrawal.Shop'}</label><input id="jooim-direct-firstname" class="form-control" name="firstname" maxlength="128" required autocomplete="given-name"></div>
        <div class="col-md-6 form-group"><label for="jooim-direct-lastname">{l s='Last name' d='Modules.Jooimwithdrawal.Shop'}</label><input id="jooim-direct-lastname" class="form-control" name="lastname" maxlength="128" required autocomplete="family-name"></div>
      </div>
      <div class="form-group"><label for="jooim-direct-reference">{l s='Order / contract reference' d='Modules.Jooimwithdrawal.Shop'}</label><input id="jooim-direct-reference" class="form-control" name="order_reference" maxlength="64" required autocomplete="off"></div>
      <div class="form-group"><label for="jooim-direct-email">{l s='Email for the withdrawal acknowledgement' d='Modules.Jooimwithdrawal.Shop'}</label><input id="jooim-direct-email" class="form-control" type="email" name="email" aria-describedby="jooim-direct-email-help" maxlength="254" required autocomplete="email"><small id="jooim-direct-email-help" class="form-text text-muted">{l s='The acknowledgement will be sent to this electronic address. Enter it carefully.' d='Modules.Jooimwithdrawal.Shop'}</small></div>
      <div class="form-group"><label for="jooim-direct-phone">{l s='Telephone (optional)' d='Modules.Jooimwithdrawal.Shop'}</label><input id="jooim-direct-phone" class="form-control" name="phone" maxlength="64" autocomplete="tel"></div>
      <fieldset class="jooim-withdrawal-fieldset"><legend>{l s='Withdrawal scope' d='Modules.Jooimwithdrawal.Shop'}</legend>
        <div class="form-check"><label class="form-check-label"><input class="form-check-input" type="radio" name="scope" value="full" checked data-jooim-direct-scope> {l s='Entire contract/order' d='Modules.Jooimwithdrawal.Shop'}</label></div>
        <div class="form-check"><label class="form-check-label"><input class="form-check-input" type="radio" name="scope" value="partial" data-jooim-direct-scope> {l s='Only selected goods or part of the contract' d='Modules.Jooimwithdrawal.Shop'}</label></div>
      </fieldset>
      <div class="form-group" data-jooim-manual-items><label for="jooim-direct-items">{l s='Describe the selected goods or contract part' d='Modules.Jooimwithdrawal.Shop'}</label><textarea id="jooim-direct-items" class="form-control" name="manual_items_description" maxlength="2000" rows="4"></textarea></div>
      <div class="form-group"><label for="jooim-direct-note">{l s='Note (optional)' d='Modules.Jooimwithdrawal.Shop'}</label><textarea id="jooim-direct-note" class="form-control" name="note" maxlength="{$note_max_length|intval}" rows="3"></textarea></div>
      <div class="jooim-statement"><strong>{l s='Withdrawal statement:' d='Modules.Jooimwithdrawal.Shop'}</strong> {$jooim_statement|escape:'htmlall':'UTF-8'}</div>
      <p class="jooim-privacy">{$jooim_privacy_text|escape:'htmlall':'UTF-8'} {if $jooim_privacy_url}<a href="{$jooim_privacy_url|escape:'htmlall':'UTF-8'}" rel="privacy-policy">{l s='Privacy policy' d='Modules.Jooimwithdrawal.Shop'}</a>{/if}</p>
      <button class="btn btn-primary" type="submit">{l s='Continue to review' d='Modules.Jooimwithdrawal.Shop'}</button>
    </form>
  </section>
{/block}
