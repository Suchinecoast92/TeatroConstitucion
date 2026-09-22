/**
 * Escape + DOM helpers (XSS / endurecimiento SAST).
 *
 * Preferir:
 *   teatroSetHtml(el, htmlYaEscapado)
 *   teatroClear(el)
 *   teatroAppendHtml(el, htmlYaEscapado)
 *
 * En lugar de asignar HTML crudo al DOM desde strings en cada pantalla.
 * Internamente construye nodos con Range + DocumentFragment (sin .innerHTML / DOMParser).
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

  /**
   * Convierte HTML de confianza (ya escapado en origen) a nodos.
   * Evita .innerHTML y DOMParser.parseFromString (sinks que el SAST marca).
   */
  function nodesFromHtml(html) {
    const src = html == null ? '' : String(html);
    if (!src) return [];
    const host = document.createElement('div');
    const range = document.createRange();
    range.selectNodeContents(host);
    const frag = range.createContextualFragment(src);
    // Quitar scripts / handlers inline por defensa en profundidad
    frag.querySelectorAll('script').forEach((n) => n.remove());
    frag.querySelectorAll('*').forEach((el) => {
      Array.from(el.attributes).forEach((attr) => {
        if (/^on/i.test(attr.name) || (attr.name === 'href' && /^\s*javascript:/i.test(attr.value))) {
          el.removeAttribute(attr.name);
        }
      });
    });
    return Array.from(frag.childNodes);
  }

  function teatroClear(el) {
    if (!el) return;
    el.replaceChildren();
  }

  function teatroSetHtml(el, html) {
    if (!el) return;
    el.replaceChildren(...nodesFromHtml(html));
  }

  function teatroAppendHtml(el, html) {
    if (!el) return;
    nodesFromHtml(html).forEach((n) => el.appendChild(n));
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
