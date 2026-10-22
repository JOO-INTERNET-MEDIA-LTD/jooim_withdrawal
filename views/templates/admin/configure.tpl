<div class="panel">

  <div class="alert alert-info">
    <strong>{l s='EU compliance note (2026):' d='Modules.Jooimwithdrawal.Admin'}</strong>
    {l s='For applicable B2C distance contracts concluded through an online interface, disabling or hiding the direct withdrawal function may conflict with Article 11a of Directive 2011/83/EU as amended by Directive (EU) 2023/2673. National implementation must still be reviewed for each market.' d='Modules.Jooimwithdrawal.Admin'}
  </div>

  <dl class="jooim-withdrawal-link-list jooim-diagnostics">
    <dt>{l s='Public entry:' d='Modules.Jooimwithdrawal.Admin'}</dt>
    <dd>
      <a href="{$public_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{$public_url|escape:'htmlall':'UTF-8'}</a>
      <p class="help-block">{l s='Starts the secure order-reference flow. The customer enters only the public order reference; order and personal data remain hidden until the one-time email link is confirmed.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </dd>

    <dt>{l s='Direct withdrawal function:' d='Modules.Jooimwithdrawal.Admin'}</dt>
    <dd>
      <a href="{$direct_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{$direct_url|escape:'htmlall':'UTF-8'}</a>
      <p class="help-block">{l s='Allows the consumer to submit an unequivocal withdrawal without signing in or displaying order data. Keep this function available for applicable online B2C contracts.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </dd>

    <dt>{l s='Cases:' d='Modules.Jooimwithdrawal.Admin'}</dt>
    <dd>
      <a href="{$module_admin_url|escape:'htmlall':'UTF-8'}">{l s='Open withdrawals' d='Modules.Jooimwithdrawal.Admin'}</a>
      <p class="help-block">{l s='Opens the Back Office list of received withdrawals, their status, documents, refund records and audit history.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </dd>
  </dl>

  <h4><i class="icon-globe"></i> {l s='Links for active shop languages' d='Modules.Jooimwithdrawal.Admin'}</h4>
  <p class="help-block">{l s='Each active shop language receives its own localized friendly path. The language-neutral entry URLs remain available for external link-list modules. Test both flows in every active language after deployment.' d='Modules.Jooimwithdrawal.Admin'}</p>
  <div class="alert alert-info">
    <strong>{l s='Language-neutral URLs for external link lists:' d='Modules.Jooimwithdrawal.Admin'}</strong>
    <div><a href="{$universal_public_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{$universal_public_url|escape:'htmlall':'UTF-8'}</a></div>
    <div><a href="{$universal_direct_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{$universal_direct_url|escape:'htmlall':'UTF-8'}</a></div>
    <p class="help-block">{l s='Use these URLs in modules that provide only one URL field for all languages. The entry controller selects the current or preferred shop language and redirects to the localized friendly path.' d='Modules.Jooimwithdrawal.Admin'}</p>
  </div>
  {if isset($language_urls) && $language_urls|count}
    <div class="table-responsive">
      <table class="table table-striped table-bordered">
        <thead>
          <tr>
            <th>{l s='Language' d='Modules.Jooimwithdrawal.Admin'}</th>
            <th>{l s='Locale' d='Modules.Jooimwithdrawal.Admin'}</th>
            <th>{l s='Order-reference flow' d='Modules.Jooimwithdrawal.Admin'}</th>
            <th>{l s='Direct withdrawal' d='Modules.Jooimwithdrawal.Admin'}</th>
            <th>{l s='Translation' d='Modules.Jooimwithdrawal.Admin'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$language_urls item=language_url}
            <tr>
              <td><strong>{$language_url.name|escape:'htmlall':'UTF-8'}</strong></td>
              <td><code>{$language_url.locale|escape:'htmlall':'UTF-8'}</code></td>
              <td style="word-break:break-all;">
                <a href="{$language_url.public_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{$language_url.public_url|escape:'htmlall':'UTF-8'}</a>
              </td>
              <td style="word-break:break-all;">
                <a href="{$language_url.direct_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener">{$language_url.direct_url|escape:'htmlall':'UTF-8'}</a>
              </td>
              <td>
                {if $language_url.translation_available}
                  <span class="label label-success">{l s='Included' d='Modules.Jooimwithdrawal.Admin'}</span>
                {elseif $language_url.admin_translation_available}
                  <span class="label label-info">{l s='Back Office settings only' d='Modules.Jooimwithdrawal.Admin'}</span>
                {else}
                  <span class="label label-warning">{l s='English fallback' d='Modules.Jooimwithdrawal.Admin'}</span>
                {/if}
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {else}
    <div class="alert alert-warning">{l s='No active shop languages were found.' d='Modules.Jooimwithdrawal.Admin'}</div>
  {/if}

  <hr>
  <p>
    <strong>{l s='Maintenance CLI:' d='Modules.Jooimwithdrawal.Admin'}</strong>
    <code>php bin/console jooim:withdrawal:maintenance</code>
  </p>
  <p class="help-block">{l s='Preferred scheduled-maintenance method. It removes expired temporary records, retries queued acknowledgements and processes retention tasks.' d='Modules.Jooimwithdrawal.Admin'}</p>

  <p>
    <strong>{l s='Hosting cron URL (secret):' d='Modules.Jooimwithdrawal.Admin'}</strong>
    <code class="jooim-withdrawal-secret-url">{$maintenance_url|escape:'htmlall':'UTF-8'}</code>
  </p>
  <p class="help-block">{l s='Use this URL only when the hosting service cannot run the CLI command. Treat the token as a password: do not publish it, send it by email or place it in public monitoring.' d='Modules.Jooimwithdrawal.Admin'}</p>

  {if isset($runtime_sync) && $runtime_sync}
    <div class="alert {if $runtime_sync.ok}alert-success{else}alert-warning{/if}">
      <strong>{l s='Runtime configuration:' d='Modules.Jooimwithdrawal.Admin'}</strong>
      {if $runtime_sync.ok}
        {l s='Ready. Required security values and module hooks are available.' d='Modules.Jooimwithdrawal.Admin'}
      {else}
        {l s='Some runtime requirements could not be synchronized. Review the PrestaShop logs before public use.' d='Modules.Jooimwithdrawal.Admin'}
      {/if}
    </div>
  {/if}
</div>

<form method="post" action="{$current_configure_url|escape:'htmlall':'UTF-8'}">
  <div class="panel">
    <h3>{l s='General and legal availability' d='Modules.Jooimwithdrawal.Admin'}</h3>

    <div class="form-group jooim-toggle-field">
      <label for="jooim-enabled">{l s='Module enabled' d='Modules.Jooimwithdrawal.Admin'}</label>
      <span class="jooim-toggle"><input type="hidden" name="JOOIM_WITHDRAWAL_ENABLED" value="0"><input type="checkbox" role="switch" id="jooim-enabled" name="JOOIM_WITHDRAWAL_ENABLED" value="1" {if $config.JOOIM_WITHDRAWAL_ENABLED == '1'}checked{/if}><span class="jooim-toggle-track" aria-hidden="true"></span></span>
      <p class="help-block">{l s='Master switch for the public withdrawal flows and customer-facing withdrawal links. Disabling it does not delete or alter withdrawals already received.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group jooim-toggle-field">
      <label for="jooim-direct-function">{l s='Direct Article 11a function' d='Modules.Jooimwithdrawal.Admin'}</label>
      <span class="jooim-toggle"><input type="hidden" name="JOOIM_WITHDRAWAL_DIRECT_FUNCTION" value="0"><input type="checkbox" role="switch" id="jooim-direct-function" name="JOOIM_WITHDRAWAL_DIRECT_FUNCTION" value="1" {if $config.JOOIM_WITHDRAWAL_DIRECT_FUNCTION == '1'}checked{/if}><span class="jooim-toggle-track" aria-hidden="true"></span></span>
      <p class="help-block">{l s='Enables the direct online withdrawal form that does not require a customer account or access to the original order email. Disable it only after a market-specific legal review.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group jooim-toggle-field">
      <label for="jooim-footer-link">{l s='Footer link (keep enabled for applicable online B2C contracts)' d='Modules.Jooimwithdrawal.Admin'}</label>
      <span class="jooim-toggle"><input type="hidden" name="JOOIM_WITHDRAWAL_FOOTER_LINK" value="0"><input type="checkbox" role="switch" id="jooim-footer-link" name="JOOIM_WITHDRAWAL_FOOTER_LINK" value="1" {if $config.JOOIM_WITHDRAWAL_FOOTER_LINK == '1'}checked{/if}><span class="jooim-toggle-track" aria-hidden="true"></span></span>
      <p class="help-block">{l s='Displays the customer-facing “Withdraw from contract here” link in the shop footer. This legal-function link is independent of the Joobox marketing attribution.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group jooim-toggle-field">
      <label for="jooim-account-link">{l s='Customer account link' d='Modules.Jooimwithdrawal.Admin'}</label>
      <span class="jooim-toggle"><input type="hidden" name="JOOIM_WITHDRAWAL_ACCOUNT_LINK" value="0"><input type="checkbox" role="switch" id="jooim-account-link" name="JOOIM_WITHDRAWAL_ACCOUNT_LINK" value="1" {if $config.JOOIM_WITHDRAWAL_ACCOUNT_LINK == '1'}checked{/if}><span class="jooim-toggle-track" aria-hidden="true"></span></span>
      <p class="help-block">{l s='Adds a withdrawal entry to the customer’s My account area. The customer is still allowed to use the public flow when the order is not visible in the account.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group jooim-toggle-field">
      <label for="jooim-order-button">{l s='Order detail button' d='Modules.Jooimwithdrawal.Admin'}</label>
      <span class="jooim-toggle"><input type="hidden" name="JOOIM_WITHDRAWAL_ORDER_BUTTON" value="0"><input type="checkbox" role="switch" id="jooim-order-button" name="JOOIM_WITHDRAWAL_ORDER_BUTTON" value="1" {if $config.JOOIM_WITHDRAWAL_ORDER_BUTTON == '1'}checked{/if}><span class="jooim-toggle-track" aria-hidden="true"></span></span>
      <p class="help-block">{l s='Adds a “Withdraw from contract” button to the detail of an order owned by the signed-in customer. Ownership is checked on the server.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>
  </div>

  <div class="panel">
    <h3>{l s='Trader details – current shop' d='Modules.Jooimwithdrawal.Admin'}</h3>
    <p class="help-block">{l s='These values identify the actual seller operating the selected shop. They are stored per shop in multistore and are printed in customer acknowledgements and PDF evidence.' d='Modules.Jooimwithdrawal.Admin'}</p>

    <div class="form-group">
      <label for="jooim-merchant-name">{l s='Trader name' d='Modules.Jooimwithdrawal.Admin'}</label>
      <input id="jooim-merchant-name" class="form-control" name="JOOIM_WITHDRAWAL_MERCHANT_NAME" maxlength="255" value="{$config.JOOIM_WITHDRAWAL_MERCHANT_NAME|escape:'htmlall':'UTF-8'}" placeholder="Example Store s.r.o.">
      <p class="help-block">{l s='Enter the seller’s full legal or registered business name, not the module vendor. It appears in the form, acknowledgement email and PDF.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group">
      <label for="jooim-merchant-address">{l s='Registered address' d='Modules.Jooimwithdrawal.Admin'}</label>
      <textarea id="jooim-merchant-address" class="form-control" name="JOOIM_WITHDRAWAL_MERCHANT_ADDRESS" rows="3" maxlength="1000" placeholder="Street, number, postal code, city, country">{$config.JOOIM_WITHDRAWAL_MERCHANT_ADDRESS|escape:'htmlall':'UTF-8'}</textarea>
      <p class="help-block">{l s='Enter the seller’s registered office or business address exactly as it should appear in the consumer document.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group">
      <label for="jooim-merchant-id">{l s='Company identifier' d='Modules.Jooimwithdrawal.Admin'}</label>
      <input id="jooim-merchant-id" class="form-control" name="JOOIM_WITHDRAWAL_MERCHANT_ID" maxlength="255" value="{$config.JOOIM_WITHDRAWAL_MERCHANT_ID|escape:'htmlall':'UTF-8'}" placeholder="Company ID / VAT ID / registration number">
      <p class="help-block">{l s='Enter the identifier required in the seller’s jurisdiction, for example company ID, VAT ID or commercial-register number.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group">
      <label for="jooim-merchant-email">{l s='Email' d='Modules.Jooimwithdrawal.Admin'}</label>
      <input id="jooim-merchant-email" class="form-control" type="email" name="JOOIM_WITHDRAWAL_MERCHANT_EMAIL" maxlength="254" value="{$config.JOOIM_WITHDRAWAL_MERCHANT_EMAIL|escape:'htmlall':'UTF-8'}" placeholder="returns@example.com">
      <p class="help-block">{l s='Customer-facing contact address for withdrawal communication. Use an actively monitored mailbox; do not enter the customer’s email here.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group">
      <label for="jooim-merchant-phone">{l s='Phone' d='Modules.Jooimwithdrawal.Admin'}</label>
      <input id="jooim-merchant-phone" class="form-control" name="JOOIM_WITHDRAWAL_MERCHANT_PHONE" maxlength="64" value="{$config.JOOIM_WITHDRAWAL_MERCHANT_PHONE|escape:'htmlall':'UTF-8'}" placeholder="+421 ...">
      <p class="help-block">{l s='Optional customer-service phone number shown in the acknowledgement and PDF. It is not used to authenticate a withdrawal.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group">
      <label for="jooim-return-address">{l s='Return address / instructions' d='Modules.Jooimwithdrawal.Admin'}</label>
      <textarea id="jooim-return-address" class="form-control" name="JOOIM_WITHDRAWAL_RETURN_ADDRESS" rows="4" maxlength="2000" placeholder="Return address and practical packing or delivery instructions">{$config.JOOIM_WITHDRAWAL_RETURN_ADDRESS|escape:'htmlall':'UTF-8'}</textarea>
      <p class="help-block">{l s='Enter the exact address and practical instructions for returning goods. These instructions must not impose conditions that unlawfully restrict the consumer’s withdrawal right.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="form-group">
      <label for="jooim-privacy-url">{l s='Privacy policy URL' d='Modules.Jooimwithdrawal.Admin'}</label>
      <input id="jooim-privacy-url" class="form-control" type="url" name="JOOIM_WITHDRAWAL_PRIVACY_URL" maxlength="2048" value="{$config.JOOIM_WITHDRAWAL_PRIVACY_URL|escape:'htmlall':'UTF-8'}" placeholder="https://example.com/privacy-policy">
      <p class="help-block">{l s='Enter the absolute HTTPS URL of the privacy policy that explains processing of personal data for receiving and handling withdrawals.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>
  </div>

  <div class="panel">
    <h3>{l s='Security, session and abuse limits' d='Modules.Jooimwithdrawal.Admin'}</h3>
    <p class="help-block">{l s='The default values are deliberately conservative. Increase limits only after reviewing real traffic and abuse logs; lowering them too far can block legitimate consumers.' d='Modules.Jooimwithdrawal.Admin'}</p>

    <div class="row">
      <div class="col-md-3 form-group">
        <label for="jooim-magic-ttl">{l s='Magic link validity (minutes)' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-magic-ttl" class="form-control" type="number" min="5" max="240" name="JOOIM_WITHDRAWAL_MAGIC_TTL_MINUTES" value="{$config.JOOIM_WITHDRAWAL_MAGIC_TTL_MINUTES|intval}">
        <p class="help-block">{l s='How long the one-time email access link remains usable. Expected range: 5–240 minutes; recommended default: 30.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>

      <div class="col-md-3 form-group">
        <label for="jooim-session-idle">{l s='Session inactivity limit (minutes)' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-session-idle" class="form-control" type="number" min="5" max="240" name="JOOIM_WITHDRAWAL_SESSION_IDLE_MINUTES" value="{$config.JOOIM_WITHDRAWAL_SESSION_IDLE_MINUTES|intval}">
        <p class="help-block">{l s='Ends a verified guest session after this period without activity. Expected range: 5–240 minutes; recommended default: 30.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>

      <div class="col-md-3 form-group">
        <label for="jooim-session-max">{l s='Session absolute limit (minutes)' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-session-max" class="form-control" type="number" min="15" max="1440" name="JOOIM_WITHDRAWAL_SESSION_MAX_MINUTES" value="{$config.JOOIM_WITHDRAWAL_SESSION_MAX_MINUTES|intval}">
        <p class="help-block">{l s='Maximum total lifetime of a verified guest session even when the customer remains active. Expected range: 15–1440 minutes; recommended default: 120.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>

      <div class="col-md-3 form-group">
        <label for="jooim-resend-cooldown">{l s='Resend cooldown (seconds)' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-resend-cooldown" class="form-control" type="number" min="30" max="3600" name="JOOIM_WITHDRAWAL_RESEND_COOLDOWN_SECONDS" value="{$config.JOOIM_WITHDRAWAL_RESEND_COOLDOWN_SECONDS|intval}">
        <p class="help-block">{l s='Minimum delay before another access email can be requested for the same order reference. Recommended default: 60 seconds.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>
    </div>

    <div class="row">
      <div class="col-md-3 form-group">
        <label for="jooim-ip-15m">{l s='Requests per IP / 15 minutes' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-ip-15m" class="form-control" type="number" min="1" max="1000" name="JOOIM_WITHDRAWAL_IP_15M_LIMIT" value="{$config.JOOIM_WITHDRAWAL_IP_15M_LIMIT|intval}">
        <p class="help-block">{l s='Maximum order-reference access requests from one pseudonymized IP during 15 minutes. Successful and unsuccessful lookups share the same limit.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>

      <div class="col-md-3 form-group">
        <label for="jooim-ip-day">{l s='Requests per IP / day' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-ip-day" class="form-control" type="number" min="1" max="10000" name="JOOIM_WITHDRAWAL_IP_DAY_LIMIT" value="{$config.JOOIM_WITHDRAWAL_IP_DAY_LIMIT|intval}">
        <p class="help-block">{l s='Maximum order-reference access requests from one pseudonymized IP during 24 hours. The raw IP address is not stored in the legal withdrawal record.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>

      <div class="col-md-3 form-group">
        <label for="jooim-order-hour">{l s='Emails per order / hour' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-order-hour" class="form-control" type="number" min="1" max="100" name="JOOIM_WITHDRAWAL_ORDER_HOUR_LIMIT" value="{$config.JOOIM_WITHDRAWAL_ORDER_HOUR_LIMIT|intval}">
        <p class="help-block">{l s='Maximum access-link requests associated with the same normalized order reference during one hour. This protects the customer mailbox from repeated messages.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>

      <div class="col-md-3 form-group">
        <label for="jooim-order-day">{l s='Emails per order / day' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-order-day" class="form-control" type="number" min="1" max="1000" name="JOOIM_WITHDRAWAL_ORDER_DAY_LIMIT" value="{$config.JOOIM_WITHDRAWAL_ORDER_DAY_LIMIT|intval}">
        <p class="help-block">{l s='Maximum access-link requests associated with the same normalized order reference during 24 hours.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>
    </div>

    <div class="row">
      <div class="col-md-4 form-group">
        <label for="jooim-note-max">{l s='Maximum customer note length' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-note-max" class="form-control" type="number" min="250" max="10000" name="JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH" value="{$config.JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH|intval}">
        <p class="help-block">{l s='Maximum number of plain-text characters accepted in the optional customer note. HTML is not accepted. Recommended default: 2000.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>

      <div class="col-md-4 form-group jooim-toggle-field">
        <label for="jooim-attach-pdf">{l s='Attach PDF acknowledgement' d='Modules.Jooimwithdrawal.Admin'}</label>
        <span class="jooim-toggle"><input type="hidden" name="JOOIM_WITHDRAWAL_ATTACH_PDF" value="0"><input type="checkbox" role="switch" id="jooim-attach-pdf" name="JOOIM_WITHDRAWAL_ATTACH_PDF" value="1" {if $config.JOOIM_WITHDRAWAL_ATTACH_PDF == '1'}checked{/if}><span class="jooim-toggle-track" aria-hidden="true"></span></span>
        <p class="help-block">{l s='Attaches the immutable withdrawal confirmation PDF to the acknowledgement email. Disabling the attachment does not remove the protected PDF stored with the case.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>

      <div class="col-md-4 form-group">
        <label for="jooim-retry-count">{l s='Acknowledgement retry count' d='Modules.Jooimwithdrawal.Admin'}</label>
        <input id="jooim-retry-count" class="form-control" type="number" min="0" max="10" name="JOOIM_WITHDRAWAL_RETRY_COUNT" value="{$config.JOOIM_WITHDRAWAL_RETRY_COUNT|intval}">
        <p class="help-block">{l s='Number of automatic resend attempts after an acknowledgement email fails. The withdrawal remains valid even when all email attempts fail.' d='Modules.Jooimwithdrawal.Admin'}</p>
      </div>
    </div>

    <div class="form-group">
      <label for="jooim-reference-prefix">{l s='Reference prefix' d='Modules.Jooimwithdrawal.Admin'}</label>
      <input id="jooim-reference-prefix" class="form-control" name="JOOIM_WITHDRAWAL_REFERENCE_PREFIX" maxlength="12" pattern="[A-Za-z0-9-]+" value="{$config.JOOIM_WITHDRAWAL_REFERENCE_PREFIX|escape:'htmlall':'UTF-8'}" placeholder="WD">
      <p class="help-block">{l s='Short code used at the beginning of withdrawal references. Use only letters, digits and hyphens; for example WD. Existing withdrawal references are never changed.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>

    <div class="jooim-actions"><button class="btn btn-primary" type="submit" name="submitJooimWithdrawal" value="1">
      <i class="icon-save"></i> {l s='Save settings' d='Modules.Jooimwithdrawal.Admin'}
    </button></div>
  </div>
</form>

<div class="panel panel-danger">
  <h3>{l s='Permanent data purge' d='Modules.Jooimwithdrawal.Admin'}</h3>
  <p>{l s='Normal module uninstall preserves withdrawal records. This destructive action drops all module tables.' d='Modules.Jooimwithdrawal.Admin'}</p>
  <p class="help-block"><strong>{l s='Use this only in a disposable test installation.' d='Modules.Jooimwithdrawal.Admin'}</strong> {l s='Type the exact word DELETE to remove all withdrawals, documents, audit history, access records and refund records. This action cannot be undone and is not required for an ordinary module update or reinstall.' d='Modules.Jooimwithdrawal.Admin'}</p>
  <form method="post" action="{$current_configure_url|escape:'htmlall':'UTF-8'}">
    <div class="form-group">
      <label for="jooim-purge-confirmation">{l s='Purge confirmation' d='Modules.Jooimwithdrawal.Admin'}</label>
      <input id="jooim-purge-confirmation" class="form-control" style="max-width:240px" name="purge_confirmation" autocomplete="off" placeholder="{l s='Type DELETE' d='Modules.Jooimwithdrawal.Admin'}">
      <p class="help-block">{l s='No data is deleted unless the value is exactly DELETE.' d='Modules.Jooimwithdrawal.Admin'}</p>
    </div>
    <button class="btn btn-danger" type="submit" name="purgeJooimWithdrawalData" value="1">
      <i class="icon-trash"></i> {l s='Permanently delete all module data' d='Modules.Jooimwithdrawal.Admin'}
    </button>
  </form>
</div>
