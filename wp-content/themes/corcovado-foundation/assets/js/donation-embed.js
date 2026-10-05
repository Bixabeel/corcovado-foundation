(function () {
  'use strict';

  // WordPress: the public campaign ID comes from Corcovado Foundation → Settings.
  var campaignId = (window.CF_DONATION && window.CF_DONATION.campaignId) || '568425';
  var embedContainer = document.getElementById('classyEmbed');
  var loadingEl = document.getElementById('classyLoading');
  var errorEl = document.getElementById('classyError');

  if (!embedContainer) return;

  var retryCount = 0;
  var maxRetries = 2;
  var loadTimeout = null;

  function showError() {
    if (loadTimeout) window.clearTimeout(loadTimeout);
    if (loadingEl) loadingEl.style.display = 'none';
    if (errorEl) errorEl.style.display = 'block';
  }

  function startTimeout() {
    if (loadTimeout) window.clearTimeout(loadTimeout);
    loadTimeout = window.setTimeout(function () {
      if (retryCount < maxRetries) {
        retryCount += 1;
        loadSdk();
      } else {
        showError();
      }
    }, 15000);
  }

  function initEmbed() {
    if (!window.eg || typeof window.eg.init !== 'function') {
      showError();
      return;
    }

    try {
      window.eg.init({
        campaignId: campaignId,
        container: embedContainer,
        onLoad: function () {
          if (loadTimeout) window.clearTimeout(loadTimeout);
          if (loadingEl) loadingEl.style.display = 'none';
        },
        onError: function () {
          showError();
        }
      });
    } catch (error) {
      console.error('Classy initialization error:', error);
      showError();
    }
  }

  function loadSdk() {
    if (loadTimeout) window.clearTimeout(loadTimeout);

    var oldScript = document.getElementById('classy-sdk-script');
    if (oldScript) oldScript.remove();

    var script = document.createElement('script');
    script.id = 'classy-sdk-script';
    script.src = 'https://sdk.classy.org/embedded-giving.js';
    script.async = true;
    script.onload = function () {
      window.setTimeout(initEmbed, 200);
    };
    script.onerror = function () {
      if (retryCount < maxRetries) {
        retryCount += 1;
        window.setTimeout(loadSdk, 1000);
      } else {
        showError();
      }
    };
    document.body.appendChild(script);
    startTimeout();
  }

  loadSdk();
}());
