/**
 * Temporizador de compra online (holds).
 * Expone window.TeatroCompraTimer
 */
(function (global) {
  'use strict';

  const TIMER_KEY = 'teatro_online_hold_expires';

  function parseExpira(expiraEn) {
    if (expiraEn == null || expiraEn === '') return null;
    if (typeof expiraEn === 'number' && Number.isFinite(expiraEn)) {
      return expiraEn < 1e12 ? expiraEn * 1000 : expiraEn;
    }
    const raw = String(expiraEn).trim();
    if (/^\d{4}-\d{2}-\d{2}T/.test(raw) || /Z$|[+-]\d{2}:?\d{2}$/.test(raw)) {
      const d = new Date(raw);
      return Number.isNaN(d.getTime()) ? null : d.getTime();
    }
    const s = raw.replace(' ', 'T');
    const d = new Date(s);
    if (Number.isNaN(d.getTime())) return null;
    return d.getTime();
  }

  function formatMmSs(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const m = Math.floor(total / 60);
    const s = total % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
  }

  const TeatroCompraTimer = {
    _expiresAt: null,
    _interval: null,
    _onTick: null,
    _onExpire: null,
    _expired: false,
    _armed: false,

    getExpiresAt() {
      if (this._expiresAt) return this._expiresAt;
      const raw = sessionStorage.getItem(TIMER_KEY);
      return raw ? parseInt(raw, 10) : null;
    },

    setExpiresAt(expiraEnOrMs) {
      let ms = typeof expiraEnOrMs === 'number' ? expiraEnOrMs : parseExpira(expiraEnOrMs);
      if (!ms) return false;
      if (ms <= Date.now()) {
        ms = Date.now() + 1000;
      }
      this._expiresAt = ms;
      sessionStorage.setItem(TIMER_KEY, String(ms));
      this._expired = false;
      this._armed = true;
      this._paint();
      return true;
    },

    startFromNow(seconds) {
      const sec = Math.max(1, parseInt(seconds, 10) || 300);
      return this.setExpiresAt(Date.now() + sec * 1000);
    },

    clear() {
      this._expiresAt = null;
      this._expired = false;
      this._armed = false;
      sessionStorage.removeItem(TIMER_KEY);
      if (this._interval) {
        clearInterval(this._interval);
        this._interval = null;
      }
      this._paint();
    },

    remainingMs() {
      if (!this._armed) return null;
      const exp = this.getExpiresAt();
      if (!exp) return null;
      return exp - Date.now();
    },

    start(opts) {
      this._onTick = opts && opts.onTick ? opts.onTick : null;
      this._onExpire = opts && opts.onExpire ? opts.onExpire : null;
      if (opts && opts.arm === false) {
        this._armed = false;
      } else if (opts && opts.arm === true) {
        this._armed = !!this.getExpiresAt();
      } else if (this.getExpiresAt()) {
        this._armed = true;
      }
      if (this._interval) clearInterval(this._interval);
      this._interval = setInterval(() => this._paint(), 250);
      this._paint();
    },

    _paint() {
      const rem = this.remainingMs();
      const el = document.getElementById('compraTimer');
      const wrap = document.getElementById('compraTimerWrap');

      if (rem === null || !this._armed) {
        if (wrap) {
          wrap.style.display = 'none';
          wrap.classList.remove('visible');
        }
        if (this._onTick) this._onTick(null, '');
        return;
      }

      if (wrap) {
        wrap.style.display = '';
        wrap.classList.add('visible');
      }
      const text = formatMmSs(rem);
      if (el) {
        el.textContent = text;
        el.classList.toggle('timer-warn', rem <= 60000);
        el.classList.toggle('timer-danger', rem <= 30000);
      }
      if (this._onTick) this._onTick(rem, text);

      if (rem <= 0 && !this._expired) {
        this._expired = true;
        if (this._interval) {
          clearInterval(this._interval);
          this._interval = null;
        }
        if (this._onExpire) this._onExpire();
      }
    },
  };

  global.TeatroCompraTimer = TeatroCompraTimer;
})(window);
