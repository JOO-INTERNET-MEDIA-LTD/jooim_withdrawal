<div class="panel jooim-withdrawal-admin">
  <h3>{l s='Contract withdrawals' d='Modules.Jooimwithdrawal.Admin'}</h3>
  <p><a class="btn btn-default" href="{$export_url|escape:'htmlall':'UTF-8'}"><i class="icon-download"></i> {l s='Export CSV' d='Modules.Jooimwithdrawal.Admin'}</a></p>
  <form method="get" class="form-inline">
    <input type="hidden" name="controller" value="AdminJooimWithdrawal">
    <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
    <input class="form-control" name="reference" placeholder="{l s='Withdrawal reference' d='Modules.Jooimwithdrawal.Admin'}" value="{$filters.reference|escape:'htmlall':'UTF-8'}">
    <input class="form-control" name="order_reference" placeholder="{l s='Order reference' d='Modules.Jooimwithdrawal.Admin'}" value="{$filters.order_reference|escape:'htmlall':'UTF-8'}">
    <input class="form-control" name="email" placeholder="{l s='Customer email' d='Modules.Jooimwithdrawal.Admin'}" value="{$filters.email|escape:'htmlall':'UTF-8'}">
    <select class="form-control" name="status">
      <option value="">{l s='All statuses' d='Modules.Jooimwithdrawal.Admin'}</option>
      {foreach from=$status_options item=status}
        <option value="{$status.code|escape:'htmlall':'UTF-8'}" {if $filters.status == $status.code}selected{/if}>{$status.label|escape:'htmlall':'UTF-8'}</option>
      {/foreach}
    </select>
    <input class="form-control" type="date" name="date_from" value="{$filters.date_from|escape:'htmlall':'UTF-8'}">
    <input class="form-control" type="date" name="date_to" value="{$filters.date_to|escape:'htmlall':'UTF-8'}">
    <button class="btn btn-default">{l s='Filter' d='Modules.Jooimwithdrawal.Admin'}</button>
  </form>
  <div class="table-responsive">
    <table class="table">
      <thead><tr><th>{l s='Reference' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Order' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Customer' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Email' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Submitted UTC' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Scope' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Items' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Status' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Period' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Shop' d='Modules.Jooimwithdrawal.Admin'}</th></tr></thead>
      <tbody>
        {foreach from=$withdrawals item=w}
          <tr>
            <td><a href="{$current_url|escape:'htmlall':'UTF-8'}&id_jooim_withdrawal={$w.id_jooim_withdrawal|intval}&viewjooim_withdrawal=1">{$w.reference|escape:'htmlall':'UTF-8'}</a></td>
            <td>{$w.order_reference|escape:'htmlall':'UTF-8'}</td>
            <td>{$w.customer_firstname|escape:'htmlall':'UTF-8'} {$w.customer_lastname|escape:'htmlall':'UTF-8'}</td>
            <td>{$w.customer_email|escape:'htmlall':'UTF-8'}</td>
            <td>{$w.submitted_at_utc|escape:'htmlall':'UTF-8'}</td>
            <td>{$w.scope|escape:'htmlall':'UTF-8'}</td>
            <td>{$w.item_count|intval}</td>
            <td><span class="label label-{$w.status_badge_class|escape:'htmlall':'UTF-8'}">{$w.status_label|escape:'htmlall':'UTF-8'}</span></td>
            <td>{$w.period_assessment|escape:'htmlall':'UTF-8'}</td>
            <td>{$w.id_shop|intval}</td>
          </tr>
        {foreachelse}
          <tr><td colspan="10">{l s='No withdrawals found.' d='Modules.Jooimwithdrawal.Admin'}</td></tr>
        {/foreach}
      </tbody>
    </table>
  </div>
  <p>{l s='Total:' d='Modules.Jooimwithdrawal.Admin'} {$total|intval} · {l s='Page' d='Modules.Jooimwithdrawal.Admin'} {$page|intval}/{$pages|intval}</p>
  {if $page > 1}<a class="btn btn-default" href="{$current_url|escape:'htmlall':'UTF-8'}&page={$prev_page|intval}">{l s='Previous' d='Modules.Jooimwithdrawal.Admin'}</a>{/if}
  {if $page < $pages}<a class="btn btn-default" href="{$current_url|escape:'htmlall':'UTF-8'}&page={$next_page|intval}">{l s='Next' d='Modules.Jooimwithdrawal.Admin'}</a>{/if}
</div>
<div class="panel">
  <h3>{l s='Create manual withdrawal record' d='Modules.Jooimwithdrawal.Admin'}</h3>
  <p>{l s='For withdrawals received by email, post, in person or another channel. This creates the same immutable submission record and sends an acknowledgement to the entered email.' d='Modules.Jooimwithdrawal.Admin'}</p>
  <form method="post">
    <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
    <div class="row">
      <div class="col-md-3 form-group"><label>{l s='First name' d='Modules.Jooimwithdrawal.Admin'}</label><input class="form-control" name="manual_firstname" required></div>
      <div class="col-md-3 form-group"><label>{l s='Last name' d='Modules.Jooimwithdrawal.Admin'}</label><input class="form-control" name="manual_lastname" required></div>
      <div class="col-md-3 form-group"><label>{l s='Email' d='Modules.Jooimwithdrawal.Admin'}</label><input class="form-control" type="email" name="manual_email" required></div>
      <div class="col-md-3 form-group"><label>{l s='Order reference' d='Modules.Jooimwithdrawal.Admin'}</label><input class="form-control" name="manual_order_reference" required></div>
    </div>
    <div class="row">
      <div class="col-md-3 form-group"><label>{l s='Received method' d='Modules.Jooimwithdrawal.Admin'}</label><select class="form-control" name="manual_received_method"><option>email</option><option>post</option><option>in_person</option><option>telephone_followed_by_written_confirmation</option></select></div>
      <div class="col-md-3 form-group"><label>{l s='Scope' d='Modules.Jooimwithdrawal.Admin'}</label><select class="form-control" name="manual_scope"><option value="full">full</option><option value="partial">partial</option></select></div>
      <div class="col-md-6 form-group"><label>{l s='Selected part (for partial)' d='Modules.Jooimwithdrawal.Admin'}</label><input class="form-control" name="manual_items_description"></div>
    </div>
    <div class="form-group"><label>{l s='Original content / internal transcription' d='Modules.Jooimwithdrawal.Admin'}</label><textarea class="form-control" rows="4" name="manual_original_content" required></textarea></div>
    <button class="btn btn-primary" name="createManualWithdrawal" value="1">{l s='Create manual withdrawal' d='Modules.Jooimwithdrawal.Admin'}</button>
  </form>
</div>
