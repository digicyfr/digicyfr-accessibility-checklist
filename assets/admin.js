(function () {
  'use strict';
  window.addEventListener('load', function () {
    var focusTarget = document.querySelector('.dacl-errors, .dacl-result');
    if (focusTarget) {
      window.setTimeout(function () { focusTarget.focus(); }, 0);
    }
    var select = document.getElementById('dacl-test_key');
    if (!select) { return; }
    var update = function () {
      document.querySelectorAll('.dacl-guidance [data-check]').forEach(function (item) {
        if (item.getAttribute('data-check') === select.value) {
          item.setAttribute('aria-current', 'true');
        } else {
          item.removeAttribute('aria-current');
        }
      });
    };
    select.addEventListener('change', update);
    update();
  });
}());
