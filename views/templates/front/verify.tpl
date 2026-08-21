{extends file=$jooim_theme_page_template}

{block name='page_title'}{l s='Secure access to withdrawal' d='Modules.Jooimwithdrawal.Shop'}{/block}

{block name='page_content'}
  <section class="jooim-withdrawal">
    {if $form_error}<div class="alert alert-danger" role="alert">{$form_error|escape:'htmlall':'UTF-8'}</div>{/if}
    {if $token_valid}
      <p>{l s='The access link is valid. Continue to the order to choose the withdrawal scope.' d='Modules.Jooimwithdrawal.Shop'}</p>
      <form method="post" class="jooim-withdrawal-actions">
        <input type="hidden" name="t" value="{$token|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="csrf_token" value="{$csrf_token|escape:'htmlall':'UTF-8'}">
        <button class="btn btn-primary" type="submit" name="submitVerify" value="1">{l s='Continue to order' d='Modules.Jooimwithdrawal.Shop'}</button>
      </form>
    {else}
      <div class="alert alert-warning" role="alert">{l s='This access link is invalid, expired or already used. Request a new link from the withdrawal page.' d='Modules.Jooimwithdrawal.Shop'}</div>
      <a class="btn btn-primary" href="{$jooim_identify_url|escape:'htmlall':'UTF-8'}">{l s='Request a new access link' d='Modules.Jooimwithdrawal.Shop'}</a>
    {/if}
  </section>
{/block}
