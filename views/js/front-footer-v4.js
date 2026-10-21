document.addEventListener('DOMContentLoaded', function () {
  var scope = document.querySelectorAll('[data-jooim-scope]');
  var items = document.querySelector('[data-jooim-items]');
  function syncOrderScope() {
    var checked = document.querySelector('[data-jooim-scope]:checked');
    if (!checked || !items) return;
    var partial = checked.value === 'partial';
    items.querySelectorAll('[data-jooim-qty]').forEach(function (input) {
      input.disabled = !partial;
      if (!partial) input.value = '0';
    });
    items.style.opacity = partial ? '1' : '.65';
  }
  scope.forEach(function (el) { el.addEventListener('change', syncOrderScope); });
  syncOrderScope();

  var directScope = document.querySelectorAll('[data-jooim-direct-scope]');
  var manual = document.querySelector('[data-jooim-manual-items]');
  function syncDirect() {
    var checked = document.querySelector('[data-jooim-direct-scope]:checked');
    if (!checked || !manual) return;
    var partial = checked.value === 'partial';
    manual.hidden = !partial;
    var textarea = manual.querySelector('textarea');
    if (textarea) textarea.required = partial;
  }
  directScope.forEach(function (el) { el.addEventListener('change', syncDirect); });
  syncDirect();
});


/* JOOIM_WITHDRAWAL_FOOTER_NORMALIZER_V4 */
(function () {
  'use strict';

  var normalizing = false;

  function config() {
    return window.jooimWithdrawalFooter || {};
  }

  function actualFooter() {
    // Do not use a combined selector such as "footer#footer, footer" here:
    // querySelector returns the first match in document order and commercial
    // themes often contain an earlier <footer class="page-footer"> inside the
    // page body. We need the real shop footer explicitly.
    return document.querySelector('footer#footer') ||
      document.getElementById('footer') ||
      document.querySelector('[role="contentinfo"]');
  }

  function normalizedUrl(value) {
    if (!value) {
      return '';
    }

    try {
      var parsed = new URL(value, document.baseURI || window.location.href);
      var path = parsed.pathname.replace(/\/+$/, '') || '/';
      return parsed.protocol.toLowerCase() + '//' + parsed.host.toLowerCase() + path;
    } catch (e) {
      return String(value).replace(/[?#].*$/, '').replace(/\/+$/, '');
    }
  }

  function storesUrl(cfg) {
    if (cfg.storesUrl) {
      return cfg.storesUrl;
    }

    if (window.prestashop && window.prestashop.urls && window.prestashop.urls.pages) {
      return window.prestashop.urls.pages.stores || '';
    }

    return '';
  }

  function findStoresAnchor(footer, cfg) {
    var prefix = typeof cfg.storesLinkIdPrefix === 'string' && cfg.storesLinkIdPrefix !== ''
      ? cfg.storesLinkIdPrefix
      : 'link-static-page-stores-';
    var anchorsWithId = footer.querySelectorAll('a[id]');

    for (var i = 0; i < anchorsWithId.length; i++) {
      if (String(anchorsWithId[i].id || '').indexOf(prefix) === 0) {
        return anchorsWithId[i];
      }
    }

    var expectedUrl = normalizedUrl(storesUrl(cfg));
    if (expectedUrl !== '') {
      var linkedAnchors = footer.querySelectorAll('a[href]');
      for (var j = 0; j < linkedAnchors.length; j++) {
        if (normalizedUrl(linkedAnchors[j].href) === expectedUrl) {
          return linkedAnchors[j];
        }
      }
    }

    return null;
  }

  function closestListItem(node) {
    if (!node) {
      return null;
    }
    if (node.closest) {
      return node.closest('li');
    }
    while (node && node.nodeType === 1) {
      if (String(node.tagName).toLowerCase() === 'li') {
        return node;
      }
      node = node.parentElement;
    }
    return null;
  }

  function uniqueClasses(value, required) {
    var seen = {};
    var classes = [];
    String(value || '').split(/\s+/).concat([required]).forEach(function (name) {
      if (name && !seen[name]) {
        seen[name] = true;
        classes.push(name);
      }
    });
    return classes.join(' ');
  }

  function buildOrUpdateListItem(footer, storesAnchor, cfg) {
    var item = footer.querySelector('li[data-jooim-withdrawal-footer-item="1"]');
    if (!item) {
      item = document.createElement('li');
      item.setAttribute('data-jooim-withdrawal-footer-item', '1');
    }

    var link = item.querySelector('a');
    if (!link) {
      link = document.createElement('a');
      item.appendChild(link);
    }

    link.removeAttribute('id');
    var expectedClasses = uniqueClasses(storesAnchor.className, 'jooim-withdrawal-footer-link');
    if (link.className !== expectedClasses) {
      link.className = expectedClasses;
    }
    link.setAttribute('data-jooim-withdrawal-footer-link', '1');
    if (link.getAttribute('href') !== cfg.url) {
      link.setAttribute('href', cfg.url);
    }
    if (link.textContent !== cfg.label) {
      link.textContent = cfg.label;
    }
    if (link.title !== cfg.label) {
      link.title = cfg.label;
    }

    return item;
  }

  function insertImmediatelyAfter(referenceItem, item) {
    if (!referenceItem || !referenceItem.parentNode) {
      return false;
    }

    var parent = referenceItem.parentNode;
    var nextElement = referenceItem.nextElementSibling;
    if (nextElement !== item) {
      if (nextElement) {
        parent.insertBefore(item, nextElement);
      } else {
        parent.appendChild(item);
      }
    }

    return referenceItem.nextElementSibling === item;
  }

  function removeNode(node) {
    if (node && node.parentNode) {
      node.parentNode.removeChild(node);
    }
  }

  function removeLegacyAndDuplicates(keepItem) {
    Array.prototype.slice.call(document.querySelectorAll(
      '.jooim-withdrawal-footer-link-wrapper, [data-jooim-withdrawal-footer-fallback="1"]'
    )).forEach(removeNode);

    Array.prototype.slice.call(document.querySelectorAll(
      '[data-jooim-withdrawal-footer-item="1"]'
    )).forEach(function (item) {
      if (item !== keepItem) {
        removeNode(item);
      }
    });

    Array.prototype.slice.call(document.querySelectorAll(
      'a.jooim-withdrawal-footer-link, a[data-jooim-withdrawal-footer-link="1"]'
    )).forEach(function (link) {
      if (!keepItem.contains(link)) {
        var listItem = closestListItem(link);
        if (listItem && listItem.getAttribute('data-jooim-withdrawal-footer-item') === '1') {
          removeNode(listItem);
        } else {
          removeNode(link);
        }
      }
    });
  }

  function ensureFallback(footer, cfg) {
    var wrappers = Array.prototype.slice.call(document.querySelectorAll(
      '.jooim-withdrawal-footer-link-wrapper, [data-jooim-withdrawal-footer-fallback="1"]'
    ));
    var wrapper = wrappers.shift();

    if (!wrapper) {
      wrapper = document.createElement('div');
      wrapper.className = 'jooim-withdrawal-footer-link-wrapper';
      wrapper.setAttribute('data-jooim-withdrawal-footer-link', '1');
      wrapper.setAttribute('data-jooim-withdrawal-footer-fallback', '1');
      wrapper.setAttribute('data-jooim-withdrawal-footer-hook', 'displayFooter');
      wrapper.appendChild(document.createElement('a'));
    }

    wrappers.forEach(removeNode);

    var link = wrapper.querySelector('a');
    if (!link) {
      link = document.createElement('a');
      wrapper.appendChild(link);
    }
    if (link.className !== 'jooim-withdrawal-footer-link') {
      link.className = 'jooim-withdrawal-footer-link';
    }
    if (link.getAttribute('href') !== cfg.url) {
      link.setAttribute('href', cfg.url);
    }
    if (link.textContent !== cfg.label) {
      link.textContent = cfg.label;
    }
    if (link.title !== cfg.label) {
      link.title = cfg.label;
    }

    if (!footer.contains(wrapper)) {
      var target = footer.querySelector('.footer-container') || footer;
      target.appendChild(wrapper);
    }
  }

  function deduplicateJooboxAttribution() {
    if (window.JooboxAttribution) window.JooboxAttribution.reconcile();
  }

  function normalize() {
    if (normalizing) {
      return;
    }

    var cfg = config();
    if (!cfg.enabled || !cfg.url || !cfg.label) {
      return;
    }

    var footer = actualFooter();
    if (!footer) {
      return;
    }

    normalizing = true;
    try {
      var storesAnchor = findStoresAnchor(footer, cfg);
      var storesItem = closestListItem(storesAnchor);

      if (storesAnchor && storesItem) {
        var item = buildOrUpdateListItem(footer, storesAnchor, cfg);
        if (insertImmediatelyAfter(storesItem, item)) {
          removeLegacyAndDuplicates(item);
        } else {
          ensureFallback(footer, cfg);
        }
      } else {
        // Generic-theme fallback. On standard PrestaShop/ps_linklist layouts
        // the id/url match above succeeds and this branch is not used.
        ensureFallback(footer, cfg);
      }

      deduplicateJooboxAttribution();
    } finally {
      normalizing = false;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', normalize);
  } else {
    normalize();
  }
  window.addEventListener('load', normalize);

  if (window.MutationObserver) {
    var observer = new MutationObserver(function () {
      window.clearTimeout(observer._jooimTimer);
      observer._jooimTimer = window.setTimeout(normalize, 50);
    });
    observer.observe(document.documentElement, {childList: true, subtree: true});
  }
}());
