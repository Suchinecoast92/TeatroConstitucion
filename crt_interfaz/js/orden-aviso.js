/**
 * Aviso de orden para aclaraciones en taquilla cuando el flujo se interrumpe.
 * Uso: TeatroOrdenAviso.mostrar('ORD…', { titulo?, mensaje? })
 */
(function (global) {
  'use strict';

  const STORAGE_KEY = 'teatro_ultima_orden';
  const SHOWN_PREFIX = 'teatro_orden_aviso_shown_';

  function guardarCodigo(codigo) {
    if (!codigo) return;
    try {
      sessionStorage.setItem(STORAGE_KEY, String(codigo));
    } catch (e) {}
  }

  function leerCodigo() {
    try {
      return sessionStorage.getItem(STORAGE_KEY) || '';
    } catch (e) {
      return '';
    }
  }

  async function copiar(texto) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(texto);
      return true;
    }
    const ta = document.createElement('textarea');
    ta.value = texto;
    ta.setAttribute('readonly', '');
    ta.style.cssText = 'position:fixed;left:-9999px';
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try {
      ok = document.execCommand('copy');
    } catch (e) {}
    document.body.removeChild(ta);
    return ok;
  }

  function ensureStyles() {
    if (document.getElementById('teatro-orden-aviso-css')) return;
    const style = document.createElement('style');
    style.id = 'teatro-orden-aviso-css';
    style.textContent = `
      .toa-overlay {
        position: fixed; inset: 0; z-index: 99999;
        display: none; align-items: center; justify-content: center;
        padding: 20px 16px;
        background: rgba(0,0,0,.72);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
      }
      .toa-overlay.open { display: flex; }
      .toa-modal {
        width: 100%; max-width: 420px;
        border-radius: 18px;
        border: 1px solid rgba(255,255,255,.16);
        background: linear-gradient(160deg, rgba(40,40,44,.96), rgba(12,12,14,.98));
        color: #f4f4f5;
        box-shadow: 0 28px 64px rgba(0,0,0,.55);
        padding: 22px 20px 18px;
        text-align: center;
        font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, Roboto, Helvetica, Arial, sans-serif;
      }
      .toa-modal h2 {
        margin: 0 0 10px; font-size: 1.2rem; font-weight: 750; color: #fafafa;
      }
      .toa-modal p {
        margin: 0 0 14px; color: #a1a1aa; font-size: .95rem; line-height: 1.45;
      }
      .toa-code {
        display: inline-block; margin: 4px 0 8px;
        padding: 12px 14px; border-radius: 12px;
        border: 1px solid rgba(255,255,255,.22);
        background: rgba(0,0,0,.4);
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: clamp(1rem, 4.2vw, 1.2rem);
        font-weight: 700; letter-spacing: .06em;
        color: #fff; word-break: break-all;
        cursor: pointer; touch-action: manipulation;
        -webkit-user-select: none; user-select: none;
      }
      .toa-code.copied { border-color: rgba(134,239,172,.55); }
      .toa-hint { display:block; margin-bottom: 16px; font-size: .78rem; color: #71717a; }
      .toa-hint.ok { color: #86efac; }
      .toa-actions { display: flex; flex-direction: column; gap: 10px; }
      .toa-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 100%; padding: 13px 16px; border-radius: 999px;
        border: 1px solid rgba(255,255,255,.28);
        background: linear-gradient(160deg, rgba(255,255,255,.92), rgba(220,220,224,.88));
        color: #0a0a0a; font-weight: 750; font-size: .98rem;
        text-decoration: none; cursor: pointer;
      }
      .toa-btn-ghost {
        background: transparent; color: #e4e4e7;
        border-color: rgba(255,255,255,.18);
      }
    `;
    document.head.appendChild(style);
  }

  function ensureDom() {
    ensureStyles();
    let overlay = document.getElementById('teatroOrdenAviso');
    if (overlay) return overlay;
    overlay = document.createElement('div');
    overlay.id = 'teatroOrdenAviso';
    overlay.className = 'toa-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = `
      <div class="toa-modal" role="dialog" aria-modal="true" aria-labelledby="toaTitle">
        <h2 id="toaTitle">Guarda tu número de orden</h2>
        <p id="toaMsg">Si hubo una interrupción, presenta este número en taquilla para cualquier aclaración.</p>
        <button type="button" class="toa-code" id="toaCode" aria-label="Tocar para copiar número de orden"></button>
        <span class="toa-hint" id="toaHint">Toca el número para copiarlo</span>
        <div class="toa-actions">
          <a class="toa-btn" id="toaVer" href="#">Ver mi orden</a>
          <button type="button" class="toa-btn toa-btn-ghost" id="toaCerrar">Entendido</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const codeBtn = overlay.querySelector('#toaCode');
    const hint = overlay.querySelector('#toaHint');
    codeBtn.addEventListener('click', async () => {
      const codigo = codeBtn.dataset.code || codeBtn.textContent.trim();
      if (!codigo) return;
      try {
        const ok = await copiar(codigo);
        if (!ok) return;
        codeBtn.classList.add('copied');
        hint.textContent = 'Copiado';
        hint.classList.add('ok');
        setTimeout(() => {
          codeBtn.classList.remove('copied');
          hint.textContent = 'Toca el número para copiarlo';
          hint.classList.remove('ok');
        }, 1600);
      } catch (e) {}
    });

    overlay.querySelector('#toaCerrar').addEventListener('click', () => cerrar());
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) cerrar();
    });
    return overlay;
  }

  function cerrar() {
    const overlay = document.getElementById('teatroOrdenAviso');
    if (!overlay) return;
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
  }

  /**
   * @param {string} codigo
   * @param {{titulo?:string, mensaje?:string, once?:boolean, verOrden?:boolean}} [opts]
   */
  function mostrar(codigo, opts) {
    opts = opts || {};
    codigo = String(codigo || '').trim();
    if (!codigo) return;
    guardarCodigo(codigo);

    if (opts.once) {
      try {
        if (sessionStorage.getItem(SHOWN_PREFIX + codigo) === '1') return;
        sessionStorage.setItem(SHOWN_PREFIX + codigo, '1');
      } catch (e) {}
    }

    const overlay = ensureDom();
    const title = overlay.querySelector('#toaTitle');
    const msg = overlay.querySelector('#toaMsg');
    const codeBtn = overlay.querySelector('#toaCode');
    const ver = overlay.querySelector('#toaVer');

    title.textContent = opts.titulo || 'Guarda tu número de orden';
    msg.textContent = opts.mensaje
      || 'Si algo salió mal o la compra se interrumpió, presenta este número en taquilla para aclarar tu caso y recibir tu boleto.';
    codeBtn.textContent = codigo;
    codeBtn.dataset.code = codigo;

    if (opts.verOrden === false) {
      ver.style.display = 'none';
    } else {
      ver.style.display = '';
      ver.href = 'orden.php?codigo=' + encodeURIComponent(codigo);
    }

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
  }

  global.TeatroOrdenAviso = {
    mostrar,
    cerrar,
    guardarCodigo,
    leerCodigo,
  };
})(window);
