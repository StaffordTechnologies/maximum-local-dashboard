(function () {
  'use strict';

  var cfg = window.mldDashboard || {};
  var state = {
    tab: 'overview',
    campaign: 'all',
    start: '',
    end: '',
    sortKey: null,
    sortDir: 'desc',
  };

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function $all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function todayISO() {
    var d = new Date();
    return d.toISOString().slice(0, 10);
  }

  function daysAgoISO(n) {
    var d = new Date();
    d.setUTCDate(d.getUTCDate() - n);
    return d.toISOString().slice(0, 10);
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatNum(n) {
    var x = Number(n);
    if (isNaN(x)) return '—';
    return x.toLocaleString();
  }

  function formatPct(n) {
    var x = Number(n);
    if (isNaN(x)) return '—';
    return (x * 100).toFixed(1) + '%';
  }

  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', cfg.nonce);
    Object.keys(data || {}).forEach(function (k) {
      body.append(k, data[k]);
    });
    return fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    }).then(function (r) {
      return r.json();
    });
  }

  function setBusy(busy) {
    var panel = $('#ml-panel');
    if (!panel) return;
    panel.classList.toggle('is-busy', !!busy);
  }

  function showMessage(html, kind) {
    var panel = $('#ml-panel');
    if (!panel) return;
    panel.innerHTML = '<div class="ml-notice ml-notice--' + (kind || 'info') + '">' + html + '</div>';
  }

  function metricCards(items) {
    return (
      '<div class="ml-cards">' +
      items
        .map(function (it) {
          return (
            '<div class="ml-card"><div class="ml-card__label">' +
            escapeHtml(it.label) +
            '</div><div class="ml-card__value">' +
            escapeHtml(it.value) +
            '</div>' +
            (it.sub ? '<div class="ml-card__sub">' + escapeHtml(it.sub) + '</div>' : '') +
            '</div>'
          );
        })
        .join('') +
      '</div>'
    );
  }

  function sortableTable(columns, rows, opts) {
    opts = opts || {};
    var head = columns
      .map(function (c) {
        var cls = c.sortable !== false ? ' is-sortable' : '';
        var aria = state.sortKey === c.key ? ' aria-sort="' + (state.sortDir === 'asc' ? 'ascending' : 'descending') + '"' : '';
        return (
          '<th class="' +
          cls +
          '" data-sort="' +
          escapeHtml(c.key) +
          '"' +
          aria +
          '>' +
          escapeHtml(c.label) +
          '</th>'
        );
      })
      .join('');

    var sorted = rows.slice();
    if (state.sortKey) {
      var key = state.sortKey;
      var dir = state.sortDir === 'asc' ? 1 : -1;
      sorted.sort(function (a, b) {
        var av = a[key];
        var bv = b[key];
        if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
        return String(av == null ? '' : av).localeCompare(String(bv == null ? '' : bv)) * dir;
      });
    }

    var body = sorted
      .map(function (row) {
        return (
          '<tr>' +
          columns
            .map(function (c) {
              var v = row[c.key];
              var display = c.format ? c.format(v, row) : escapeHtml(v == null ? '—' : v);
              return '<td>' + display + '</td>';
            })
            .join('') +
          '</tr>'
        );
      })
      .join('');

    return (
      '<div class="ml-table-wrap"><table class="ml-table"><thead><tr>' +
      head +
      '</tr></thead><tbody>' +
      (body || '<tr><td colspan="' + columns.length + '">No rows for this range.</td></tr>') +
      '</tbody></table></div>'
    );
  }

  function gaRows(report) {
    var dims = (report.dimensionHeaders || []).map(function (h) {
      return h.name;
    });
    var mets = (report.metricHeaders || []).map(function (h) {
      return h.name;
    });
    return (report.rows || []).map(function (r) {
      var out = {};
      (r.dimensionValues || []).forEach(function (dv, i) {
        out[dims[i]] = dv.value;
      });
      (r.metricValues || []).forEach(function (mv, i) {
        var n = Number(mv.value);
        out[mets[i]] = isNaN(n) ? mv.value : n;
      });
      return out;
    });
  }

  function renderOverview(payload) {
    if (!payload.configured) {
      showMessage(escapeHtml(payload.message || cfg.i18n.notConfigured), 'warn');
      return;
    }
    var report = payload.data || {};
    var rows = report.rows || [];
    var current = rows[0] || { metricValues: [] };
    var previous = rows[1] || { metricValues: [] };
    var names = (report.metricHeaders || []).map(function (h) {
      return h.name;
    });

    function val(row, name) {
      var i = names.indexOf(name);
      if (i < 0 || !row.metricValues || !row.metricValues[i]) return null;
      return Number(row.metricValues[i].value);
    }

    function delta(name) {
      var a = val(current, name);
      var b = val(previous, name);
      if (a == null || b == null || b === 0) return '';
      var pct = ((a - b) / b) * 100;
      var sign = pct > 0 ? '+' : '';
      return sign + pct.toFixed(1) + '% vs prior period';
    }

    var panel = $('#ml-panel');
    panel.innerHTML =
      metricCards([
        { label: 'Sessions', value: formatNum(val(current, 'sessions')), sub: delta('sessions') },
        { label: 'Users', value: formatNum(val(current, 'totalUsers')), sub: delta('totalUsers') },
        { label: 'Conversions', value: formatNum(val(current, 'conversions')), sub: delta('conversions') },
        { label: 'Events', value: formatNum(val(current, 'eventCount')), sub: delta('eventCount') },
      ]) +
      '<p class="ml-muted">Range ' +
      escapeHtml(payload.start) +
      ' → ' +
      escapeHtml(payload.end) +
      '. Spend and cost-per-conversion appear once Facebook Ads is connected.</p>';
  }

  function renderCampaigns(payload) {
    if (!payload.configured) {
      showMessage(escapeHtml(payload.message || cfg.i18n.notConfigured), 'warn');
      return;
    }
    var rows = gaRows(payload.data || {}).map(function (r) {
      return {
        campaign: r.sessionCampaignName || '(not set)',
        source: r.sessionSource || '',
        medium: r.sessionMedium || '',
        sessions: r.sessions || 0,
        users: r.totalUsers || 0,
        conversions: r.conversions || 0,
        bounceRate: r.bounceRate || 0,
      };
    });

    // Populate campaign filter from unique names.
    var select = $('#ml-campaign-filter');
    if (select) {
      var current = select.value;
      var names = {};
      rows.forEach(function (r) {
        if (r.campaign && r.campaign !== '(not set)') names[r.campaign] = true;
      });
      var opts = ['<option value="all">All campaigns</option>'];
      Object.keys(names)
        .sort()
        .forEach(function (n) {
          opts.push('<option value="' + escapeHtml(n) + '"' + (current === n ? ' selected' : '') + '>' + escapeHtml(n) + '</option>');
        });
      select.innerHTML = opts.join('');
    }

    var panel = $('#ml-panel');
    panel.innerHTML = sortableTable(
      [
        { key: 'campaign', label: 'Campaign' },
        { key: 'source', label: 'Source' },
        { key: 'medium', label: 'Medium' },
        { key: 'sessions', label: 'Sessions', format: function (v) { return formatNum(v); } },
        { key: 'users', label: 'Users', format: function (v) { return formatNum(v); } },
        { key: 'conversions', label: 'Conversions', format: function (v) { return formatNum(v); } },
        { key: 'bounceRate', label: 'Bounce rate', format: function (v) { return formatPct(v); } },
      ],
      rows
    );
    bindSort();
  }

  function renderConversions(payload) {
    if (!payload.configured) {
      showMessage(escapeHtml(payload.message || cfg.i18n.notConfigured), 'warn');
      return;
    }
    var rows = gaRows(payload.data || {}).map(function (r) {
      return {
        event: r.eventName || '',
        page: r.pagePath || '',
        campaign: r.sessionCampaignName || '(not set)',
        count: r.eventCount || 0,
      };
    });
    $('#ml-panel').innerHTML = sortableTable(
      [
        { key: 'event', label: 'Event' },
        { key: 'page', label: 'Page' },
        { key: 'campaign', label: 'Campaign' },
        { key: 'count', label: 'Count', format: function (v) { return formatNum(v); } },
      ],
      rows
    );
    bindSort();
  }

  function renderTraffic(payload) {
    if (!payload.configured) {
      showMessage(escapeHtml(payload.message || cfg.i18n.notConfigured), 'warn');
      return;
    }
    var data = payload.data || {};
    var sources = gaRows(data.sources || {}).map(function (r) {
      return {
        source: r.sessionSource || '',
        medium: r.sessionMedium || '',
        sessions: r.sessions || 0,
        users: r.totalUsers || 0,
        conversions: r.conversions || 0,
      };
    });
    var devices = gaRows(data.devices || {}).map(function (r) {
      return {
        device: r.deviceCategory || '',
        sessions: r.sessions || 0,
        users: r.totalUsers || 0,
      };
    });
    var pages = gaRows(data.pages || {}).map(function (r) {
      return {
        page: r.pagePath || '',
        sessions: r.sessions || 0,
        views: r.screenPageViews || 0,
        conversions: r.conversions || 0,
      };
    });

    var html = '';
    html += '<h2 class="ml-section-title">Sources</h2>';
    html += sortableTable(
      [
        { key: 'source', label: 'Source' },
        { key: 'medium', label: 'Medium' },
        { key: 'sessions', label: 'Sessions', format: function (v) { return formatNum(v); } },
        { key: 'users', label: 'Users', format: function (v) { return formatNum(v); } },
        { key: 'conversions', label: 'Conversions', format: function (v) { return formatNum(v); } },
      ],
      sources
    );
    html += '<h2 class="ml-section-title">Devices</h2>';
    html += sortableTable(
      [
        { key: 'device', label: 'Device' },
        { key: 'sessions', label: 'Sessions', format: function (v) { return formatNum(v); } },
        { key: 'users', label: 'Users', format: function (v) { return formatNum(v); } },
      ],
      devices
    );
    html += '<h2 class="ml-section-title">Top pages</h2>';
    html += sortableTable(
      [
        { key: 'page', label: 'Page' },
        { key: 'sessions', label: 'Sessions', format: function (v) { return formatNum(v); } },
        { key: 'views', label: 'Views', format: function (v) { return formatNum(v); } },
        { key: 'conversions', label: 'Conversions', format: function (v) { return formatNum(v); } },
      ],
      pages
    );
    $('#ml-panel').innerHTML = html;
    bindSort();
  }

  function renderSearch(payload) {
    if (!payload.configured) {
      showMessage(escapeHtml(payload.message || cfg.i18n.notConfigured), 'warn');
      return;
    }
    var rows = ((payload.data && payload.data.rows) || []).map(function (r) {
      return {
        query: (r.keys && r.keys[0]) || '',
        clicks: r.clicks || 0,
        impressions: r.impressions || 0,
        ctr: r.ctr || 0,
        position: r.position || 0,
      };
    });
    $('#ml-panel').innerHTML = sortableTable(
      [
        { key: 'query', label: 'Query' },
        { key: 'clicks', label: 'Clicks', format: function (v) { return formatNum(v); } },
        { key: 'impressions', label: 'Impressions', format: function (v) { return formatNum(v); } },
        { key: 'ctr', label: 'CTR', format: function (v) { return formatPct(v); } },
        { key: 'position', label: 'Avg position', format: function (v) { return Number(v).toFixed(1); } },
      ],
      rows
    );
    bindSort();
  }

  function renderPlaceholder(tab) {
    showMessage('<strong>' + escapeHtml((cfg.i18n && cfg.i18n.notConnected) || 'Not connected') + '</strong><p>This tab will light up in a later phase.</p>', 'info');
  }

  function renderSettings() {
    var s = cfg.settings || {};
    var sa = cfg.sa || {};
    var html = '';
    html += '<div class="ml-settings">';
    html += '<section class="ml-settings__block"><h2>Connections</h2>';
    html += '<ul class="ml-status-list">';
    html +=
      '<li><span class="ml-dot ' +
      (sa.configured ? 'is-on' : 'is-off') +
      '"></span> Google service account: ' +
      (sa.configured
        ? escapeHtml(sa.email) + (sa.project ? ' · project ' + escapeHtml(sa.project) : '')
        : 'Not configured') +
      '</li>';
    html += '<li><span class="ml-dot is-off"></span> Facebook Ads: Not connected</li>';
    html += '<li><span class="ml-dot is-off"></span> Microsoft Clarity: Not connected</li>';
    html += '</ul></section>';

    html += '<section class="ml-settings__block"><h2>Service account JSON</h2>';
    html +=
      '<p class="ml-muted">Paste the full JSON key. It is stored in WordPress options on the server only — never sent to the browser after save, and never returned by the API. Grant the service account Viewer on your GA4 property and access to your Search Console property.</p>';
    html += '<textarea id="ml-sa-json" rows="8" placeholder="{ &quot;type&quot;: &quot;service_account&quot;, ... }"></textarea>';
    html += '<div class="ml-actions"><button type="button" class="button button-primary" id="ml-save-sa">Save service account</button>';
    html += '<button type="button" class="button" id="ml-clear-sa">Clear</button></div>';
    html += '<p id="ml-sa-msg" class="ml-msg" hidden></p></section>';

    html += '<section class="ml-settings__block"><h2>Property settings</h2>';
    html += '<div class="ml-grid-form">';
    html += '<label>GA4 property ID<input type="text" id="ml-ga4-property" value="' + escapeHtml(s.ga4_property_id || '') + '" /></label>';
    html += '<label>GA4 measurement ID<input type="text" id="ml-ga4-mid" value="' + escapeHtml(s.ga4_measurement_id || '') + '" /></label>';
    html += '<label>Search Console site URL<input type="url" id="ml-gsc-site" value="' + escapeHtml(s.search_console_site || '') + '" /></label>';
    html += '<label>Production host<input type="text" id="ml-prod-host" value="' + escapeHtml(s.production_host || '') + '" /></label>';
    html += '<label>Default date range (days)<input type="number" min="1" max="90" id="ml-days" value="' + escapeHtml(s.date_range_days || 28) + '" /></label>';
    html += '</div></section>';
    html += '<section class="ml-settings__block"><h2>Conversion tracking selectors</h2>';
    html += '<p class="ml-muted">One CSS selector per line. Used by the front-end tracker for CTA clicks, form submits, and download extensions. Leave blank to restore the plugin defaults on next save of an empty field is not applied — clear a line to remove it.</p>';
    html += '<div class="ml-grid-form ml-grid-form--full">';
    html += '<label>CTA selectors<textarea id="ml-cta-selectors" rows="5">' + escapeHtml((s.cta_selectors || []).join('\n')) + '</textarea></label>';
    html += '<label>Form selectors<textarea id="ml-form-selectors" rows="5">' + escapeHtml((s.form_selectors || []).join('\n')) + '</textarea></label>';
    html += '<label>Download extensions (comma or newline)<textarea id="ml-download-ext" rows="3">' + escapeHtml((s.download_ext || []).join(', ')) + '</textarea></label>';
    html += '</div>';
    html += '<div class="ml-actions"><button type="button" class="button button-primary" id="ml-save-settings">Save settings</button></div>';
    html += '<p id="ml-settings-msg" class="ml-msg" hidden></p></section>';
    html += '</div>';

    $('#ml-panel').innerHTML = html;

    $('#ml-save-sa').addEventListener('click', function () {
      var json = $('#ml-sa-json').value;
      post('mld_save_service_account', { service_account_json: json }).then(function (res) {
        var msg = $('#ml-sa-msg');
        msg.hidden = false;
        if (res.success) {
          msg.textContent = res.data.message || cfg.i18n.saved;
          msg.className = 'ml-msg is-ok';
          cfg.sa = res.data.service_account || cfg.sa;
          $('#ml-sa-json').value = '';
        } else {
          msg.textContent = (res.data && res.data.message) || 'Save failed.';
          msg.className = 'ml-msg is-err';
        }
      });
    });

    $('#ml-clear-sa').addEventListener('click', function () {
      if (!window.confirm('Clear the stored service account key?')) return;
      post('mld_save_service_account', { service_account_json: '' }).then(function (res) {
        var msg = $('#ml-sa-msg');
        msg.hidden = false;
        msg.textContent = (res.data && res.data.message) || 'Cleared.';
        msg.className = res.success ? 'ml-msg is-ok' : 'ml-msg is-err';
        if (res.success) cfg.sa = res.data.service_account || { configured: false };
      });
    });

    $('#ml-save-settings').addEventListener('click', function () {
      var settings = {
        ga4_property_id: $('#ml-ga4-property').value,
        ga4_measurement_id: $('#ml-ga4-mid').value,
        search_console_site: $('#ml-gsc-site').value,
        production_host: $('#ml-prod-host').value,
        date_range_days: $('#ml-days').value,
        cta_selectors: $('#ml-cta-selectors').value,
        form_selectors: $('#ml-form-selectors').value,
        download_ext: $('#ml-download-ext').value,
      };
      post('mld_save_settings', { settings: JSON.stringify(settings) }).then(function (res) {
        var msg = $('#ml-settings-msg');
        msg.hidden = false;
        if (res.success) {
          msg.textContent = res.data.message || cfg.i18n.saved;
          msg.className = 'ml-msg is-ok';
          cfg.settings = res.data.settings || settings;
        } else {
          msg.textContent = (res.data && res.data.message) || 'Save failed.';
          msg.className = 'ml-msg is-err';
        }
      });
    });
  }

  function bindSort() {
    $all('.ml-table th.is-sortable').forEach(function (th) {
      th.addEventListener('click', function () {
        var key = th.getAttribute('data-sort');
        if (state.sortKey === key) {
          state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          state.sortKey = key;
          state.sortDir = 'desc';
        }
        loadTab(state.tab);
      });
    });
  }

  function loadTab(tab) {
    state.tab = tab;
    $all('.ml-tab').forEach(function (btn) {
      var on = btn.getAttribute('data-tab') === tab;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });

    if (tab === 'settings') {
      renderSettings();
      return;
    }
    if (tab === 'facebook' || tab === 'clarity') {
      renderPlaceholder(tab);
      return;
    }

    setBusy(true);
    showMessage(escapeHtml(cfg.i18n.loading || 'Loading…'), 'info');

    post('mld_dashboard_data', {
      tab: tab,
      campaign: state.campaign,
      start: state.start,
      end: state.end,
    })
      .then(function (res) {
        setBusy(false);
        if (!res || !res.success) {
          showMessage(escapeHtml((res && res.data && res.data.message) || cfg.i18n.error), 'err');
          return;
        }
        var payload = res.data;
        switch (tab) {
          case 'overview':
            renderOverview(payload);
            break;
          case 'campaigns':
            renderCampaigns(payload);
            break;
          case 'conversions':
            renderConversions(payload);
            break;
          case 'traffic':
            renderTraffic(payload);
            break;
          case 'search':
            renderSearch(payload);
            break;
          default:
            showMessage(escapeHtml(cfg.i18n.error), 'err');
        }
      })
      .catch(function () {
        setBusy(false);
        showMessage(escapeHtml(cfg.i18n.error), 'err');
      });
  }

  function init() {
    var days = (cfg.settings && cfg.settings.date_range_days) || 28;
    state.end = todayISO();
    state.start = daysAgoISO(days);
    var startEl = $('#ml-start');
    var endEl = $('#ml-end');
    if (startEl) startEl.value = state.start;
    if (endEl) endEl.value = state.end;

    $all('.ml-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.sortKey = null;
        loadTab(btn.getAttribute('data-tab'));
      });
    });

    var filter = $('#ml-campaign-filter');
    if (filter) {
      filter.addEventListener('change', function () {
        state.campaign = filter.value || 'all';
        loadTab(state.tab);
      });
    }

    var refresh = $('#ml-refresh');
    if (refresh) {
      refresh.addEventListener('click', function () {
        state.start = ($('#ml-start') && $('#ml-start').value) || state.start;
        state.end = ($('#ml-end') && $('#ml-end').value) || state.end;
        loadTab(state.tab);
      });
    }

    loadTab('overview');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
