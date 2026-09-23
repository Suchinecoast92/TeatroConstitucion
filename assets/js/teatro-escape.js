/**
 * Escape + DOM helpers (XSS / endurecimiento SAST).
 *
 * Preferir:
 *   teatroSetHtml(el, htmlYaEscapado)
 *   teatroClear(el)
 *   teatroAppendHtml(el, htmlYaEscapado)
 *
 * Parsea HTML con Range.createContextualFragment usando el elemento destino
 * como contexto (imprescindible para <tr>/<td>/<option>).
 */
(function (w) {
  function escapeHtml(text) {
    if (text == null) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttr(text) {
    return escapeHtml(text).replace(/`/g, '&#96;');
  }

  function decodeHtmlEntities(s) {
    let t = String(s ?? '');
    const named = {
      amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00A0'
    };
    for (let i = 0; i < 3; i++) {
      const next = t.replace(/&(#x[\da-fA-F]+|#\d+|\w+);/g, (m, ent) => {
        if (ent[0] === '#') {
          const code = ent[1].toLowerCase() === 'x'
            ? parseInt(ent.slice(2), 16)
            : parseInt(ent.slice(1), 10);
          return Number.isFinite(code) ? String.fromCodePoint(code) : m;
        }
        return Object.prototype.hasOwnProperty.call(named, ent) ? named[ent] : m;
      });
      if (next === t) break;
      t = next;
    }
    return t;
  }

  function sanitizeFragment(root) {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll('script').forEach((n) => n.remove());
    root.querySelectorAll('*').forEach((el) => {
      Array.from(el.attributes).forEach((attr) => {
        if (/^on/i.test(attr.name) || (attr.name === 'href' && /^\s*javascript:/i.test(attr.value))) {
          el.removeAttribute(attr.name);
        }
      });
    });
  }

  /** Contexto vivo del destino; si no está en el documento, usa un anfitrión temporal. */
  function fragmentFor(el, html) {
    const src = html == null ? '' : String(html);
    if (!src) return document.createDocumentFragment();

    const tag = (el && el.tagName) ? el.tagName.toUpperCase() : '';
    let contextEl = el;
    let cleanup = null;

    // Elementos de tabla/listas/select necesitan estar (o aparentar estar) en un árbol válido
    if (!el || !el.isConnected) {
      const wrap = document.createElement('div');
      wrap.style.display = 'none';
      if (tag === 'TBODY' || tag === 'THEAD' || tag === 'TFOOT') {
        const table = document.createElement('table');
        contextEl = document.createElement(tag.toLowerCase());
        table.appendChild(contextEl);
        wrap.appendChild(table);
      } else if (tag === 'TR') {
        const table = document.createElement('table');
        const tbody = document.createElement('tbody');
        contextEl = document.createElement('tr');
        tbody.appendChild(contextEl);
        table.appendChild(tbody);
        wrap.appendChild(table);
      } else if (tag === 'SELECT') {
        contextEl = document.createElement('select');
        wrap.appendChild(contextEl);
      } else if (tag === 'UL' || tag === 'OL') {
        contextEl = document.createElement(tag.toLowerCase());
        wrap.appendChild(contextEl);
      } else {
        contextEl = wrap;
      }
      document.documentElement.appendChild(wrap);
      cleanup = () => wrap.remove();
    }

    try {
      const range = document.createRange();
      range.selectNodeContents(contextEl);
      const frag = range.createContextualFragment(src);
      sanitizeFragment(frag);
      return frag;
    } finally {
      if (cleanup) cleanup();
    }
  }

  function teatroClear(el) {
    if (!el) return;
    el.replaceChildren();
  }

  function teatroSetHtml(el, html) {
    if (!el) return;
    const frag = fragmentFor(el, html);
    el.replaceChildren();
    el.appendChild(frag);
  }

  function teatroAppendHtml(el, html) {
    if (!el) return;
    el.appendChild(fragmentFor(el, html));
  }

  /** Copia nodos hijos de src a dest (sin serializar HTML). */
  function teatroCopyChildren(dest, src) {
    if (!dest || !src) return;
    dest.replaceChildren(...Array.from(src.childNodes).map((n) => n.cloneNode(true)));
  }

  w.escapeHtml = w.escapeHtml || escapeHtml;
  w.escapeAttr = w.escapeAttr || escapeAttr;
  w.decodeHtmlEntities = w.decodeHtmlEntities || decodeHtmlEntities;
  w.teatroEsc = escapeHtml;
  w.teatroEscAttr = escapeAttr;
  w.teatroClear = teatroClear;
  w.teatroSetHtml = teatroSetHtml;
  w.teatroAppendHtml = teatroAppendHtml;
  w.teatroCopyChildren = teatroCopyChildren;
})(window);
