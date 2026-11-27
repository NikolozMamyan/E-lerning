import { Controller } from '@hotwired/stimulus';

const CONSENT_KEY = 'lc_consent_v1';
const CONSENT_LIFETIME = 180 * 24 * 60 * 60 * 1000;

export default class extends Controller {
  static targets = ['panel', 'preferences', 'statistics', 'advertising'];
  static values = { config: Object, page: String, purchase: Object };

  connect() {
    const config = this.configValue;
    this.active = location.hostname === config.host || location.hostname === `www.${config.host}`;
    if (!this.active) return;
    this.measurementId = /^G-[A-Z0-9]+$/.test(config.measurement_id) ? config.measurement_id : '';
    this.adsId = /^AW-\d+$/.test(config.ads_id) ? config.ads_id : '';
    if (!this.measurementId && !this.adsId) { this.active = false; return; }

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.lcGoogle = window.lcGoogle || { configured: new Set(), loaded: false };
    if (!window.lcGoogle.defaults) {
      window.gtag('consent', 'default', {
        analytics_storage: 'denied', ad_storage: 'denied',
        ad_user_data: 'denied', ad_personalization: 'denied'
      });
      window.gtag('set', 'ads_data_redaction', true);
      window.gtag('set', 'url_passthrough', false);
      window.lcGoogle.defaults = true;
    }

    this.consent = this.readConsent();
    this.preferencesTarget.hidden = false;
    if (this.consent) this.applyConsent();
    if (!this.consent || new URL(location.href).searchParams.has('cookie_preferences')) this.open();
  }

  readConsent() {
    try {
      const value = JSON.parse(localStorage.getItem(CONSENT_KEY));
      if (value && typeof value.statistics === 'boolean' && typeof value.advertising === 'boolean'
          && value.expires > Date.now() && value.expires <= Date.now() + CONSENT_LIFETIME) return value;
    } catch (_) {}
    return null;
  }

  open() {
    if (!this.active) return;
    this.statisticsTarget.checked = this.consent?.statistics === true;
    this.advertisingTarget.checked = this.consent?.advertising === true;
    this.panelTarget.hidden = false;
    this.panelTarget.querySelector('button').focus();
  }

  reject() { this.storeConsent(true, true); }
  accept() { this.storeConsent(true, true); }
  save() { this.storeConsent(this.statisticsTarget.checked, this.advertisingTarget.checked); }

  storeConsent(statistics, advertising) {
    const revoked = (this.consent?.statistics && !statistics) || (this.consent?.advertising && !advertising);
    this.consent = { statistics, advertising, expires: Date.now() + CONSENT_LIFETIME };
    try { localStorage.setItem(CONSENT_KEY, JSON.stringify(this.consent)); } catch (_) {}
    this.panelTarget.hidden = true;
    if (revoked) {
      this.updateConsent();
      this.clearCookies();
      location.reload();
      return;
    }
    this.applyConsent();
    this.preferencesTarget.focus();
  }

  updateConsent() {
    window.gtag('consent', 'update', {
      analytics_storage: this.consent.statistics ? 'granted' : 'denied',
      ad_storage: this.consent.advertising ? 'granted' : 'denied',
      ad_user_data: this.consent.advertising ? 'granted' : 'denied',
      ad_personalization: 'denied'
    });
    if (this.measurementId) window[`ga-disable-${this.measurementId}`] = !this.consent.statistics;
  }

  applyConsent() {
    this.updateConsent();
    const destinations = [];
    if (this.consent.statistics && this.measurementId) destinations.push(this.measurementId);
    if (this.consent.advertising && this.adsId) destinations.push(this.adsId);
    if (!destinations.length) return;

    window.gtag('set', this.pageParameters());
    if (!window.lcGoogle.loaded) {
      window.gtag('js', new Date());
      const script = document.createElement('script');
      script.async = true;
      script.src = `https://www.googletagmanager.com/gtag/js?id=${destinations[0]}`;
      document.head.appendChild(script);
      window.lcGoogle.loaded = true;
    }
    for (const id of destinations) {
      if (window.lcGoogle.configured.has(id)) continue;
      window.gtag('config', id, {
        ...this.pageParameters(), send_page_view: false,
        allow_google_signals: false, allow_ad_personalization_signals: false
      });
      window.lcGoogle.configured.add(id);
    }
    if (!this.pageSent && this.consent.statistics && this.measurementId) {
      this.event('page_view');
      this.pageSent = true;
    }
    this.sendConversions();
  }

  pageParameters() {
    const current = new URL(location.href);
    const commercialPath = /^\/(?:courses\/\d+-[a-z0-9-]+|catalog(?:\/payment\/success)?|contact|login|register)?\/?$/;
    const safe = new URL(commercialPath.test(current.pathname) ? current.pathname : `/_pages/${this.pageValue}`, current.origin);
    if (current.searchParams.get('collection') === 'aml-2026') safe.searchParams.set('collection', 'aml-2026');
    for (const key of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_content', 'utm_term', 'gclid', 'gbraid', 'wbraid']) {
      const value = current.searchParams.get(key);
      if (value && /^[a-zA-Z0-9_.~ -]{1,256}$/.test(value)) safe.searchParams.set(key, value);
    }
    let referrer = '';
    try { referrer = new URL(document.referrer).origin; } catch (_) {}
    return { page_location: safe.href, page_referrer: referrer, page_title: this.pageValue };
  }

  event(name, parameters = {}) {
    if (!this.active || !this.consent?.statistics || !this.measurementId) return;
    window.gtag('event', name, {
      ...this.pageParameters(), ...parameters, send_to: this.measurementId, transport_type: 'beacon'
    });
  }

  once(key, callback) {
    try { if (sessionStorage.getItem(key)) return; } catch (_) {}
    callback();
    try { sessionStorage.setItem(key, '1'); } catch (_) {}
  }

  sendConversions() {
    const purchase = this.purchaseValue;
    if (purchase?.transaction_id) {
      if (this.consent.statistics && this.measurementId) {
        this.once(`lc_ga_purchase_${purchase.transaction_id}`, () => this.event('purchase', purchase));
      }
      this.adsConversion('purchase', purchase.transaction_id, { value: purchase.value, currency: purchase.currency });
    }
  }

  adsConversion(type, transactionId, parameters = {}) {
    const label = this.configValue[`${type}_label`];
    if (!this.consent?.advertising || !this.adsId || !/^[a-zA-Z0-9_-]+$/.test(label || '')) return;
    this.once(`lc_ads_${type}_${transactionId}`, () => window.gtag('event', 'conversion', {
      ...this.pageParameters(), ...parameters,
      send_to: `${this.adsId}/${label}`, transaction_id: transactionId
    }));
  }

  trackClick(event) {
    if (!this.active) return;
    if (event.target.closest('[data-analytics-preferences]')) {
      event.preventDefault();
      this.open();
      return;
    }
  }

  trackCheckout(event) {
    if (event.defaultPrevented) return;
    const form = event.target.closest('form[data-analytics-checkout]');
    if (!form) return;
    this.event('begin_checkout', { purchase_type: form.dataset.analyticsCheckout });
  }

  clearCookies() {
    for (const cookie of document.cookie.split(';')) {
      const name = cookie.split('=')[0].trim();
      const statistics = /^_ga(?:_|$)|^_gid$|^_gat/.test(name);
      const advertising = /^_gcl_/.test(name);
      if ((!statistics || this.consent.statistics) && (!advertising || this.consent.advertising)) continue;
      const expired = `${name}=; Max-Age=0; path=/`;
      document.cookie = expired;
      const parts = location.hostname.split('.');
      for (let index = 0; index < parts.length - 1; index++) {
        document.cookie = `${expired}; domain=.${parts.slice(index).join('.')}`;
      }
    }
  }

  beforeCache() { this.panelTarget.hidden = true; }
}
