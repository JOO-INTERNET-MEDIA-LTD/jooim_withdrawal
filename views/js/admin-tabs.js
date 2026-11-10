(function () {
  'use strict';
  document.querySelectorAll('[data-wd-settings]').forEach(function (root) {
    var tabs = Array.from(root.querySelectorAll('[data-wd-tab]'));
    var panels = Array.from(root.querySelectorAll('[data-wd-panel]'));
    root.querySelector('.wd-tabs').setAttribute('role', 'tablist');
    function activate(name, focus) {
      tabs.forEach(function (tab) {
        var selected = tab.dataset.wdTab === name;
        tab.setAttribute('role', 'tab'); tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
        if (selected && focus) tab.focus();
      });
      panels.forEach(function (panel) { panel.setAttribute('role', 'tabpanel'); panel.hidden = panel.dataset.wdPanel !== name; });
      root.querySelectorAll('[name=withdrawal_active_tab]').forEach(function (field) { field.value = name; });
      root.querySelector('[data-wd-settings-actions]').hidden = name === 'links' || name === 'data';
    }
    tabs.forEach(function (tab, index) {
      tab.addEventListener('click', function () { activate(tab.dataset.wdTab, false); });
      tab.addEventListener('keydown', function (event) {
        var next = index;
        if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
        else if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = tabs.length - 1;
        else return;
        event.preventDefault(); activate(tabs[next].dataset.wdTab, true);
      });
    });
    var firstInvalid = null;
    root.addEventListener('invalid', function (event) {
      if (firstInvalid) return;
      var panel = event.target.closest('[data-wd-panel]');
      if (panel) {
        firstInvalid = event.target; activate(panel.dataset.wdPanel, false);
        window.setTimeout(function () { firstInvalid = null; }, 0);
      }
    }, true);
    activate(tabs.some(function (tab) { return tab.dataset.wdTab === root.dataset.activeTab; }) ? root.dataset.activeTab : 'links', false);
  });
})();
