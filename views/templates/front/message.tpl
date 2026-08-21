{extends file=$jooim_theme_page_template}

{block name='page_title'}{if isset($message_title)}{$message_title|escape:'htmlall':'UTF-8'}{else}{l s='Online contract withdrawal' d='Modules.Jooimwithdrawal.Shop'}{/if}{/block}

{block name='page_content'}
  <section class="jooim-withdrawal">
    {if isset($message_text) && $message_text}<div class="alert alert-warning" role="alert">{$message_text|escape:'htmlall':'UTF-8'}</div>{/if}
  </section>
{/block}
