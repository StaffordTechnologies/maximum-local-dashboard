(function () {
  'use strict';

  var cfg = window.mldTracking || window.mlTracking || {};
  var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
  var STORAGE_KEY = 'mld_utm';

  function qs(name) {
    try {
      return new URLSearchParams(window.location.search).get(name);
    } catch (e) {
      return null;
    }
  }

  function readStoredUtms() {
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY) || getCookie(STORAGE_KEY) || getCookie('ml_utm');
      if (!raw) return {};
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (e) {
      return {};
    }
  }

  function persistUtms(utms) {
    try {
      var json = JSON.stringify(utms);
      sessionStorage.setItem(STORAGE_KEY, json);
      setCookie(STORAGE_KEY, json, cfg.cookieDays || 30);
    } catch (e) {
      /* ignore */
    }
  }

  function captureUtmsFromUrl() {
    var found = false;
    var utms = readStoredUtms();
    UTM_KEYS.forEach(function (k) {
      var v = qs(k);
      if (v) {
        utms[k] = v;
        found = true;
      }
    });
    if (found) persistUtms(utms);
    return utms;
  }

  function setCookie(name, value, days) {
    var maxAge = (days || 30) * 86400;
    var secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie =
      name +
      '=' +
      encodeURIComponent(value) +
      '; path=/; max-age=' +
      maxAge +
      '; SameSite=Lax' +
      secure;
  }

  function getCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }

  function gtagReady() {
    return typeof window.gtag === 'function';
  }

  function sendEvent(name, params) {
    if (!cfg.enabled) return;
    if (!gtagReady()) return;

    var payload = Object.assign(
      {
        page_location: window.location.href,
        page_path: window.location.pathname,
        send_to: cfg.measurementId,
      },
      params || {}
    );

    var utms = readStoredUtms();
    if (utms.utm_campaign) payload.campaign = utms.utm_campaign;
    if (utms.utm_source) payload.source = utms.utm_source;
    if (utms.utm_medium) payload.medium = utms.utm_medium;
    if (utms.utm_content) payload.content = utms.utm_content;
    if (utms.utm_term) payload.term = utms.utm_term;

    if (cfg.debugMode) {
      payload.debug_mode = true;
    }

    window.gtag('event', name, payload);
  }

  function hostnameOf(href) {
    try {
      return new URL(href, window.location.href).hostname;
    } catch (e) {
      return '';
    }
  }

  function pathOf(href) {
    try {
      return new URL(href, window.location.href).pathname;
    } catch (e) {
      return '';
    }
  }

  function isDownload(href) {
    var path = pathOf(href).toLowerCase();
    var ext = (cfg.downloadExt || []).join('|');
    if (!ext) return false;
    return new RegExp('\\.(' + ext + ')(?:$|[?#])', 'i').test(path);
  }

  function matchesAny(el, selectors) {
    if (!el || !el.closest) return null;
    for (var i = 0; i < selectors.length; i++) {
      try {
        var hit = el.closest(selectors[i]);
        if (hit) return hit;
      } catch (e) {
        /* invalid selector */
      }
    }
    return null;
  }

  function onClick(e) {
    var a = e.target && e.target.closest ? e.target.closest('a') : null;
    if (!a || !a.href) return;

    var href = a.href;
    var lower = href.toLowerCase();

    if (lower.indexOf('tel:') === 0) {
      sendEvent('phone_click', {
        link_url: href,
        link_text: (a.textContent || '').trim().slice(0, 120),
      });
      return;
    }

    if (lower.indexOf('mailto:') === 0) {
      sendEvent('email_click', {
        link_url: href,
        link_text: (a.textContent || '').trim().slice(0, 120),
      });
      return;
    }

    var cta = matchesAny(a, cfg.ctaSelectors || []);
    if (cta) {
      sendEvent('cta_click', {
        link_url: href,
        link_text: (a.textContent || '').trim().slice(0, 120),
        cta_label: (cta.getAttribute('data-mld-cta') || cta.getAttribute('data-ml-cta') || a.textContent || '').trim().slice(0, 120),
      });
      return;
    }

    if (isDownload(href)) {
      sendEvent('file_download', {
        link_url: href,
        file_name: pathOf(href).split('/').pop(),
      });
      return;
    }

    var host = hostnameOf(href);
    if (host && host !== window.location.hostname) {
      sendEvent('outbound_click', {
        link_url: href,
        link_domain: host,
      });
    }
  }

  function onSubmit(e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM') return;

    var selectors = cfg.formSelectors || [];
    if (selectors.length) {
      var hit = matchesAny(form, selectors);
      if (!hit) return;
    }

    var id = form.getAttribute('id') || form.getAttribute('name') || '';
    sendEvent('form_submit', {
      form_id: id.slice(0, 120),
      form_action: (form.getAttribute('action') || '').slice(0, 200),
    });
  }

  captureUtmsFromUrl();

  if (!cfg.enabled) {
    return;
  }

  document.addEventListener('click', onClick, true);
  document.addEventListener('submit', onSubmit, true);
})();
