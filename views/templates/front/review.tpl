{extends file=$jooim_theme_page_template}

{block name='page_title'}{l s='Review withdrawal' d='Modules.Jooimwithdrawal.Shop'}{/block}

{block name='page_content'}
  <section class="jooim-withdrawal">
    <p>{l s='Check the information before the final confirmation.' d='Modules.Jooimwithdrawal.Shop'}</p>
    <dl class="row jooim-withdrawal-summary">
      <dt class="col-sm-4">{l s='Order:' d='Modules.Jooimwithdrawal.Shop'}</dt><dd class="col-sm-8">{$draft.payload.order_reference|escape:'htmlall':'UTF-8'}</dd>
      <dt class="col-sm-4">{l s='Customer:' d='Modules.Jooimwithdrawal.Shop'}</dt><dd class="col-sm-8">{$draft.payload.customer.firstname|escape:'htmlall':'UTF-8'} {$draft.payload.customer.lastname|escape:'htmlall':'UTF-8'}</dd>
      <dt class="col-sm-4">{l s='Scope:' d='Modules.Jooimwithdrawal.Shop'}</dt><dd class="col-sm-8">{if $draft.payload.scope == 'full'}{l s='Entire contract/order' d='Modules.Jooimwithdrawal.Shop'}{else}{l s='Selected items' d='Modules.Jooimwithdrawal.Shop'}{/if}</dd>
    </dl>
    <div class="table-responsive">
      <table class="table table-striped"><thead><tr><th>{l s='Item' d='Modules.Jooimwithdrawal.Shop'}</th><th>{l s='Reference' d='Modules.Jooimwithdrawal.Shop'}</th><th>{l s='Quantity' d='Modules.Jooimwithdrawal.Shop'}</th></tr></thead><tbody>
      {foreach from=$draft.payload.items item=item}<tr><td>{$item.product_name|escape:'htmlall':'UTF-8'}</td><td>{$item.product_reference|escape:'htmlall':'UTF-8'}</td><td>{$item.withdrawal_quantity|intval}</td></tr>{/foreach}
      </tbody></table>
    </div>
    <div class="jooim-statement"><strong>{l s='Withdrawal statement:' d='Modules.Jooimwithdrawal.Shop'}</strong><br>{$draft.payload.statement_text|escape:'htmlall':'UTF-8'}</div>
    <form method="post" action="{$submit_url|escape:'htmlall':'UTF-8'}" class="jooim-withdrawal-actions">
      <input type="hidden" name="id_draft" value="{$draft.id_draft|intval}">
      <input type="hidden" name="draft_key" value="{$draft.key|escape:'htmlall':'UTF-8'}">
      <input type="hidden" name="csrf_token" value="{$csrf_token|escape:'htmlall':'UTF-8'}">
      <button class="btn btn-danger" type="submit">{l s='Confirm withdrawal' d='Modules.Jooimwithdrawal.Shop'}</button>
    </form>
  </section>
{/block}
