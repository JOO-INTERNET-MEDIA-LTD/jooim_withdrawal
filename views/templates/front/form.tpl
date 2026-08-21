{extends file=$jooim_theme_page_template}

{block name='page_title'}{l s='Online contract withdrawal' d='Modules.Jooimwithdrawal.Shop'}{/block}

{block name='page_content'}
  <section class="jooim-withdrawal">
    <div class="jooim-summary alert alert-info" role="status">
      <p><strong>{l s='Order:' d='Modules.Jooimwithdrawal.Shop'}</strong> {$order->reference|escape:'htmlall':'UTF-8'}<br>
      <strong>{l s='Customer:' d='Modules.Jooimwithdrawal.Shop'}</strong> {$identity.firstname|escape:'htmlall':'UTF-8'} {$identity.lastname|escape:'htmlall':'UTF-8'}<br>
      <strong>{l s='Confirmation email:' d='Modules.Jooimwithdrawal.Shop'}</strong> {$identity.email|escape:'htmlall':'UTF-8'}</p>
    </div>
    <form method="post" action="{$review_url|escape:'htmlall':'UTF-8'}" class="jooim-withdrawal-form" data-jooim-withdrawal-form>
      <input type="hidden" name="csrf_token" value="{$csrf_token|escape:'htmlall':'UTF-8'}">
      <input type="hidden" name="id_order" value="{$order->id|intval}">
      <fieldset class="jooim-withdrawal-fieldset">
        <legend>{l s='Withdrawal scope' d='Modules.Jooimwithdrawal.Shop'}</legend>
        <div class="form-check"><label class="form-check-label"><input class="form-check-input" type="radio" name="scope" value="full" checked data-jooim-scope> {l s='I withdraw from the entire contract/order' d='Modules.Jooimwithdrawal.Shop'}</label></div>
        <div class="form-check"><label class="form-check-label"><input class="form-check-input" type="radio" name="scope" value="partial" data-jooim-scope> {l s='I withdraw only from selected items' d='Modules.Jooimwithdrawal.Shop'}</label></div>
      </fieldset>

      <div class="table-responsive jooim-withdrawal-items" data-jooim-items>
        <table class="table table-striped">
          <thead><tr><th>{l s='Item' d='Modules.Jooimwithdrawal.Shop'}</th><th>{l s='Reference' d='Modules.Jooimwithdrawal.Shop'}</th><th>{l s='Ordered' d='Modules.Jooimwithdrawal.Shop'}</th><th>{l s='Available' d='Modules.Jooimwithdrawal.Shop'}</th><th>{l s='Quantity to withdraw' d='Modules.Jooimwithdrawal.Shop'}</th></tr></thead>
          <tbody>
          {foreach from=$items item=item}
            <tr>
              <td>{$item.product_name|escape:'htmlall':'UTF-8'}</td>
              <td>{$item.product_reference|escape:'htmlall':'UTF-8'}</td>
              <td>{$item.ordered_quantity|intval}</td>
              <td>{$item.available_quantity|intval}</td>
              <td><input class="form-control jooim-quantity-input" type="number" min="0" max="{$item.available_quantity|intval}" name="quantity[{$item.id_order_detail|intval}]" value="0" data-jooim-qty></td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>

      <div class="form-group">
        <label for="jooim-reason">{l s='Reason (optional)' d='Modules.Jooimwithdrawal.Shop'}</label>
        <select id="jooim-reason" name="reason" class="form-control"><option value="">—</option>{foreach from=$reason_options key=code item=label}<option value="{$code|escape:'htmlall':'UTF-8'}">{$label|escape:'htmlall':'UTF-8'}</option>{/foreach}</select>
      </div>
      <div class="form-group"><label for="jooim-phone">{l s='Telephone (optional)' d='Modules.Jooimwithdrawal.Shop'}</label><input id="jooim-phone" class="form-control" name="phone" maxlength="64" autocomplete="tel"></div>
      <div class="form-group"><label for="jooim-note">{l s='Note (optional)' d='Modules.Jooimwithdrawal.Shop'}</label><textarea id="jooim-note" class="form-control" name="note" maxlength="{$note_max_length|intval}" rows="4"></textarea></div>
      <div class="jooim-statement"><strong>{l s='Your statement:' d='Modules.Jooimwithdrawal.Shop'}</strong> {$jooim_statement|escape:'htmlall':'UTF-8'}</div>
      <p class="jooim-privacy">{$jooim_privacy_text|escape:'htmlall':'UTF-8'} {if $jooim_privacy_url}<a href="{$jooim_privacy_url|escape:'htmlall':'UTF-8'}" rel="privacy-policy">{l s='Privacy policy' d='Modules.Jooimwithdrawal.Shop'}</a>{/if}</p>
      <button class="btn btn-primary" type="submit">{l s='Continue to review' d='Modules.Jooimwithdrawal.Shop'}</button>
    </form>
  </section>
{/block}
