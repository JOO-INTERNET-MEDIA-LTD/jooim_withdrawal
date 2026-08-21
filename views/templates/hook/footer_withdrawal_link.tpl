{if isset($jooim_withdrawal_footer_enabled) && $jooim_withdrawal_footer_enabled}
  {* Accessible no-JavaScript fallback. front.js removes this block after it
     inserts a normal <li> immediately after the ps_linklist stores entry. *}
  <div class="jooim-withdrawal-footer-link-wrapper"
       data-jooim-withdrawal-footer-link="1"
       data-jooim-withdrawal-footer-fallback="1"
       data-jooim-withdrawal-footer-hook="{$jooim_withdrawal_footer_hook|default:'displayFooter'|escape:'htmlall':'UTF-8'}">
    <a class="jooim-withdrawal-footer-link"
       href="{$jooim_withdrawal_footer_url|escape:'htmlall':'UTF-8'}">
      {$jooim_withdrawal_footer_label|escape:'htmlall':'UTF-8'}
    </a>
  </div>
{/if}
