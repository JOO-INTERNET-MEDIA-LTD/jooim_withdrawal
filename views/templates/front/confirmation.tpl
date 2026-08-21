{extends file=$jooim_theme_page_template}

{block name='page_title'}{l s='Your withdrawal statement has been received' d='Modules.Jooimwithdrawal.Shop'}{/block}

{block name='page_content'}
  <section class="jooim-withdrawal" aria-labelledby="jooim-withdrawal-confirmation-title">
    <h2 id="jooim-withdrawal-confirmation-title" class="sr-only visually-hidden">{l s='Your withdrawal statement has been received' d='Modules.Jooimwithdrawal.Shop'}</h2>
    <div class="alert alert-success" role="status">
      <p><strong>{l s='Withdrawal reference:' d='Modules.Jooimwithdrawal.Shop'}</strong> {$withdrawal.reference|escape:'htmlall':'UTF-8'}</p>
      <p><strong>{l s='Received at (UTC):' d='Modules.Jooimwithdrawal.Shop'}</strong> {$withdrawal.submitted_at_utc|escape:'htmlall':'UTF-8'}</p>
    </div>
    <p>{l s='We have sent an acknowledgement to the electronic address specified for this withdrawal. The submission remains recorded even if email delivery fails.' d='Modules.Jooimwithdrawal.Shop'}</p>
    {if $can_download}
      <a class="btn btn-secondary" href="{$document_url|escape:'htmlall':'UTF-8'}">{l s='Download PDF acknowledgement' d='Modules.Jooimwithdrawal.Shop'}</a>
    {/if}
  </section>
{/block}
