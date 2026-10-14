{* Shared Joobox attribution. Keep exactly one Joobox marketing attribution even when several JOOIM modules are installed. *}
<div class="jooim-joobox-attribution jooim-withdrawal-attribution" data-jooim-attribution="jooim_withdrawal" style="font-size:12px;line-height:1.4;text-align:center;margin:10px 0;opacity:.75;">
  <span>{l s='PrestaShop module by' d='Modules.Jooimwithdrawal.Shop'}</span>
  <a href="{$joobox_home_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener" style="text-decoration:none;">joobox.eu</a>
</div>
<script>
(function () {
  function deduplicateJooboxAttribution() {
    var candidates = document.querySelectorAll('[data-jooim-attribution], .jooim-joobox-attribution');
    var keeper = null;

    for (var i = 0; i < candidates.length; i++) {
      var candidate = candidates[i];
      if (!candidate.querySelector || !candidate.querySelector('a[href*="joobox.eu"]')) {
        continue;
      }

      if (keeper === null) {
        keeper = candidate;
        keeper.style.display = '';
        continue;
      }

      if (candidate.parentNode) {
        candidate.parentNode.removeChild(candidate);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', deduplicateJooboxAttribution);
  } else {
    deduplicateJooboxAttribution();
  }
})();
</script>
