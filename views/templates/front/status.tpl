{extends file=$jooim_theme_page_template}

{block name='page_title'}{l s='Withdrawal status' d='Modules.Jooimwithdrawal.Shop'}{/block}

{block name='page_content'}
  <section class="jooim-withdrawal">
    <dl class="row jooim-withdrawal-summary">
      <dt class="col-sm-4">{l s='Reference:' d='Modules.Jooimwithdrawal.Shop'}</dt><dd class="col-sm-8">{$withdrawal.reference|escape:'htmlall':'UTF-8'}</dd>
      <dt class="col-sm-4">{l s='Status:' d='Modules.Jooimwithdrawal.Shop'}</dt><dd class="col-sm-8">{$withdrawal.status_label|escape:'htmlall':'UTF-8'}</dd>
    </dl>
  </section>
{/block}
