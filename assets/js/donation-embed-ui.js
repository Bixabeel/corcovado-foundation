(function () {
  'use strict';

  var embed = document.getElementById('classyEmbed');
  var loading = document.getElementById('classyLoading');
  var error = document.getElementById('classyError');
  if (!embed) return;

  var settled = false;
  var timeoutId = window.setTimeout(showError, 25000);
  var observer = new MutationObserver(function () {
    if (embed.querySelector('iframe, form, [data-eg-component]')) showForm();
  });
  observer.observe(embed, { childList: true, subtree: true });

  function hideLoading() {
    if (loading) loading.style.display = 'none';
  }
  function showForm() {
    if (settled) return;
    settled = true;
    window.clearTimeout(timeoutId);
    observer.disconnect();
    hideLoading();
    if (error) error.style.display = 'none';
  }
  function showError() {
    if (settled) return;
    settled = true;
    observer.disconnect();
    hideLoading();
    if (error) error.style.display = 'block';
  }
})();
