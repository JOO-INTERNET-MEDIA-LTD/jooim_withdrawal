<div class="jooim-withdrawal-admin">
  <div class="panel">
    <h3>{$withdrawal.reference|escape:'htmlall':'UTF-8'}</h3>
    <p>
      <strong>{l s='Status:' d='Modules.Jooimwithdrawal.Admin'}</strong>
      <span class="label label-{$withdrawal.status_badge_class|escape:'htmlall':'UTF-8'}">{$withdrawal.status_label|escape:'htmlall':'UTF-8'}</span>
      · <strong>{l s='Order:' d='Modules.Jooimwithdrawal.Admin'}</strong> {$withdrawal.order_reference|escape:'htmlall':'UTF-8'}
      · <strong>{l s='Source:' d='Modules.Jooimwithdrawal.Admin'}</strong> {$withdrawal.source|escape:'htmlall':'UTF-8'}
      · <strong>{l s='Submitted UTC:' d='Modules.Jooimwithdrawal.Admin'}</strong> {$withdrawal.submitted_at_utc|escape:'htmlall':'UTF-8'}
    </p>
    {if isset($snapshot.requires_manual_review) && $snapshot.requires_manual_review}<div class="alert alert-warning">{l s='This direct submission has not authenticated the consumer against the order. Any matching order reference is only an internal association and must be verified before relying on it.' d='Modules.Jooimwithdrawal.Admin'}</div>{/if}
    {if $order_admin_url}<p><a class="btn btn-default" href="{$order_admin_url|escape:'htmlall':'UTF-8'}">{l s='Open PrestaShop order' d='Modules.Jooimwithdrawal.Admin'}</a></p>{/if}
    <p><strong>{l s='Snapshot SHA-256:' d='Modules.Jooimwithdrawal.Admin'}</strong> <code>{$withdrawal.payload_hash|escape:'htmlall':'UTF-8'}</code></p>
  </div>
  <div class="panel"><h3>{l s='Customer and statement' d='Modules.Jooimwithdrawal.Admin'}</h3><p>{$withdrawal.customer_firstname|escape:'htmlall':'UTF-8'} {$withdrawal.customer_lastname|escape:'htmlall':'UTF-8'} · {$withdrawal.customer_email|escape:'htmlall':'UTF-8'} · {$withdrawal.customer_phone|escape:'htmlall':'UTF-8'}</p><blockquote>{$withdrawal.statement_text|escape:'htmlall':'UTF-8'|nl2br}</blockquote>{if $withdrawal.customer_note}<p><strong>{l s='Note:' d='Modules.Jooimwithdrawal.Admin'}</strong> {$withdrawal.customer_note|escape:'htmlall':'UTF-8'|nl2br}</p>{/if}</div>
  <div class="panel"><h3>{l s='Items' d='Modules.Jooimwithdrawal.Admin'}</h3><table class="table"><thead><tr><th>{l s='Product' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Reference' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Ordered' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Available at submission' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Withdrawn' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Total incl. tax' d='Modules.Jooimwithdrawal.Admin'}</th></tr></thead><tbody>{foreach from=$items item=i}<tr><td>{$i.product_name|escape:'htmlall':'UTF-8'}</td><td>{$i.product_reference|escape:'htmlall':'UTF-8'}</td><td>{$i.ordered_quantity|intval}</td><td>{$i.available_quantity|intval}</td><td>{$i.withdrawal_quantity|intval}</td><td>{$i.total_price_tax_incl|escape:'htmlall':'UTF-8'} {$i.currency_iso|escape:'htmlall':'UTF-8'}</td></tr>{foreachelse}<tr><td colspan="6">{l s='No structured order items in this submission. Review the immutable snapshot/manual description.' d='Modules.Jooimwithdrawal.Admin'}</td></tr>{/foreach}</tbody></table>{if isset($snapshot.manual_items_description) && $snapshot.manual_items_description}<p><strong>{l s='Consumer-described selected part:' d='Modules.Jooimwithdrawal.Admin'}</strong> {$snapshot.manual_items_description|escape:'htmlall':'UTF-8'|nl2br}</p>{/if}</div>
  <div class="row">
    <div class="col-md-6"><div class="panel">
      <h3>{l s='Change status' d='Modules.Jooimwithdrawal.Admin'}</h3>
      <form method="post">
        <input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="id_jooim_withdrawal" value="{$withdrawal.id_jooim_withdrawal|intval}">
        <div class="form-group">
          <label>{l s='Change status' d='Modules.Jooimwithdrawal.Admin'}</label>
          <select class="form-control" name="new_status_code">
            {foreach from=$status_options item=status}
              <option value="{$status.code|escape:'htmlall':'UTF-8'}" {if $withdrawal.status_code == $status.code}selected{/if}>{$status.label|escape:'htmlall':'UTF-8'}</option>
            {/foreach}
          </select>
        </div>
        <div class="form-group"><label>{l s='Customer-facing reason/message (required for rejected)' d='Modules.Jooimwithdrawal.Admin'}</label><textarea class="form-control" name="public_message"></textarea></div>
        <div class="form-group"><label>{l s='Private note' d='Modules.Jooimwithdrawal.Admin'}</label><textarea class="form-control" name="private_note"></textarea></div>
        <button class="btn btn-primary" name="changeWithdrawalStatus" value="1">{l s='Update status' d='Modules.Jooimwithdrawal.Admin'}</button>
      </form>
    </div></div>
    <div class="col-md-6"><div class="panel"><h3>{l s='Record refund' d='Modules.Jooimwithdrawal.Admin'}</h3><form method="post"><input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}"><input type="hidden" name="id_jooim_withdrawal" value="{$withdrawal.id_jooim_withdrawal|intval}"><div class="row"><div class="col-md-6"><input class="form-control" type="number" step="0.01" min="0" name="refund_amount" placeholder="{l s='Amount' d='Modules.Jooimwithdrawal.Admin'}" required></div><div class="col-md-6"><input class="form-control" name="refund_currency" maxlength="3" placeholder="EUR" required></div></div><input class="form-control" name="refund_method" placeholder="{l s='Method' d='Modules.Jooimwithdrawal.Admin'}"><input class="form-control" name="transaction_reference" placeholder="{l s='Transaction reference' d='Modules.Jooimwithdrawal.Admin'}"><textarea class="form-control" name="refund_note" placeholder="{l s='Internal note' d='Modules.Jooimwithdrawal.Admin'}"></textarea><button class="btn btn-primary" name="recordRefund" value="1">{l s='Record refund' d='Modules.Jooimwithdrawal.Admin'}</button></form></div></div>
  </div>
  <div class="panel"><h3>{l s='Documents and acknowledgement' d='Modules.Jooimwithdrawal.Admin'}</h3><ul>{foreach from=$documents item=d}<li><a href="{$document_download_base|escape:'htmlall':'UTF-8'}{$d.id_document|intval}">{$d.document_type|escape:'htmlall':'UTF-8'}</a> · {$d.file_size|intval} bytes · SHA-256 {$d.file_hash|escape:'htmlall':'UTF-8'}</li>{foreachelse}<li>{l s='No document generated.' d='Modules.Jooimwithdrawal.Admin'}</li>{/foreach}</ul><form method="post"><input type="hidden" name="token" value="{$token|escape:'htmlall':'UTF-8'}"><input type="hidden" name="id_jooim_withdrawal" value="{$withdrawal.id_jooim_withdrawal|intval}"><button class="btn btn-default" name="resendAcknowledgement" value="1">{l s='Resend acknowledgement' d='Modules.Jooimwithdrawal.Admin'}</button></form></div>
  <div class="panel"><h3>{l s='Refunds' d='Modules.Jooimwithdrawal.Admin'}</h3><table class="table"><thead><tr><th>{l s='Date' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Amount' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Method' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Transaction' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Employee' d='Modules.Jooimwithdrawal.Admin'}</th></tr></thead><tbody>{foreach from=$refunds item=r}<tr><td>{$r.refunded_at|escape:'htmlall':'UTF-8'}</td><td>{$r.amount|escape:'htmlall':'UTF-8'} {$r.currency_iso|escape:'htmlall':'UTF-8'}</td><td>{$r.refund_method|escape:'htmlall':'UTF-8'}</td><td>{$r.transaction_reference|escape:'htmlall':'UTF-8'}</td><td>{$r.id_employee|intval}</td></tr>{foreachelse}<tr><td colspan="5">{l s='No refund recorded.' d='Modules.Jooimwithdrawal.Admin'}</td></tr>{/foreach}</tbody></table></div>
  <div class="panel">
    <h3>{l s='Audit history' d='Modules.Jooimwithdrawal.Admin'}</h3>
    <table class="table">
      <thead><tr><th>{l s='UTC' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Event' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Old' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='New' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Employee' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Public message' d='Modules.Jooimwithdrawal.Admin'}</th><th>{l s='Private note' d='Modules.Jooimwithdrawal.Admin'}</th></tr></thead>
      <tbody>
        {foreach from=$history item=h}
          <tr><td>{$h.created_at|escape:'htmlall':'UTF-8'}</td><td>{$h.event_label|escape:'htmlall':'UTF-8'}</td><td>{if $h.old_status_label}{$h.old_status_label|escape:'htmlall':'UTF-8'}{else}—{/if}</td><td>{if $h.new_status_label}{$h.new_status_label|escape:'htmlall':'UTF-8'}{else}—{/if}</td><td>{$h.id_employee|intval}</td><td>{$h.public_message|escape:'htmlall':'UTF-8'}</td><td>{$h.private_note|escape:'htmlall':'UTF-8'}</td></tr>
        {/foreach}
      </tbody>
    </table>
  </div>
  <div class="panel"><h3>{l s='Immutable snapshot' d='Modules.Jooimwithdrawal.Admin'}</h3><pre>{$withdrawal.payload_json|escape:'htmlall':'UTF-8'}</pre></div>
</div>
