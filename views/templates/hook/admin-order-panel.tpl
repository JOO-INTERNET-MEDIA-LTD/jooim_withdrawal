<div class="card mt-2"><div class="card-header"><strong>{l s='JOOIM Withdrawal' d='Modules.Jooimwithdrawal.Admin'}</strong></div><div class="card-body">
{if empty($withdrawals)}
  <p>{l s='No withdrawal submissions linked to this order.' d='Modules.Jooimwithdrawal.Admin'}</p>
{else}
  <ul>
    {foreach from=$withdrawals item=w}
      <li><a href="{$withdrawal_admin_link|escape:'htmlall':'UTF-8'}&id_jooim_withdrawal={$w.id_jooim_withdrawal|intval}&viewjooim_withdrawal=1">{$w.reference|escape:'htmlall':'UTF-8'}</a> — <span class="label label-{$w.status_badge_class|escape:'htmlall':'UTF-8'}">{$w.status_label|escape:'htmlall':'UTF-8'}</span> — {$w.submitted_at_utc|escape:'htmlall':'UTF-8'}</li>
    {/foreach}
  </ul>
{/if}
</div></div>
