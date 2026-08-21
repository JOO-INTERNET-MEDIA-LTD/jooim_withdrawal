{extends file=$jooim_theme_page_template}

{block name='page_title'}{l s='Withdraw from contract here' d='Modules.Jooimwithdrawal.Shop'}{/block}

{block name='page_content'}
  <section class="jooim-withdrawal">
    <p>{l s='Use your order reference to securely display the order and choose all or selected items.' d='Modules.Jooimwithdrawal.Shop'}</p>

    {if $sent}
      <div class="alert alert-success" role="status">
        {l s='If we found an order with the reference provided, we sent a continuation link to the email address associated with that order.' d='Modules.Jooimwithdrawal.Shop'}
      </div>
    {/if}
    {if $form_error}<div class="alert alert-danger" role="alert">{$form_error|escape:'htmlall':'UTF-8'}</div>{/if}

    <form method="post" action="{$jooim_identify_url|escape:'htmlall':'UTF-8'}" class="jooim-withdrawal-form">
      <input type="hidden" name="csrf_token" value="{$csrf_token|escape:'htmlall':'UTF-8'}">
      <div class="form-group">
        <label for="jooim-order-reference">{l s='Order number or reference' d='Modules.Jooimwithdrawal.Shop'}</label>
        <input id="jooim-order-reference" class="form-control" type="text" name="order_reference" maxlength="64" autocomplete="off" required>
        <small class="form-text text-muted">{l s='You can find the order reference in your order confirmation email or invoice.' d='Modules.Jooimwithdrawal.Shop'}</small>
      </div>
      <button class="btn btn-primary" type="submit" name="submitIdentify" value="1">{l s='Continue securely' d='Modules.Jooimwithdrawal.Shop'}</button>
    </form>

    {if $direct_function_enabled}
      <hr>
      <h2 class="h3">{l s='No access to the original email?' d='Modules.Jooimwithdrawal.Shop'}</h2>
      <p>{l s='You can still send an unequivocal online withdrawal statement without displaying any order data.' d='Modules.Jooimwithdrawal.Shop'}</p>
      <a class="btn btn-outline-primary" href="{$jooim_direct_url|escape:'htmlall':'UTF-8'}">{l s='Withdraw from contract here' d='Modules.Jooimwithdrawal.Shop'}</a>
    {/if}

    <div class="jooim-privacy">
      <p>{$jooim_privacy_text|escape:'htmlall':'UTF-8'} {if $jooim_privacy_url}<a href="{$jooim_privacy_url|escape:'htmlall':'UTF-8'}" rel="privacy-policy">{l s='Privacy policy' d='Modules.Jooimwithdrawal.Shop'}</a>{/if}</p>
    </div>
  </section>
{/block}
