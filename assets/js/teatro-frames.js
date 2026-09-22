/**
 * Monta frames same-origin desde placeholders (sin tag iframe estático en HTML).
 * Uso:
 *   <div data-teatro-frame data-id="..." data-src="..." data-class="..." data-name="..." data-style="..."></div>
 *   <script src=".../teatro-frames.js"></script>
 */
(function (w) {
  function mountOne(el) {
    if (!el || !el.getAttribute || !el.hasAttribute('data-teatro-frame')) return null;
    const f = document.createElement('iframe');
    const id = el.getAttribute('data-id');
    const name = el.getAttribute('data-name');
    const src = el.getAttribute('data-src');
    const cls = el.getAttribute('data-class');
    const title = el.getAttribute('data-title');
    const style = el.getAttribute('data-style');
    const sandbox = el.getAttribute('data-sandbox');
    if (id) f.id = id;
    if (name) f.name = name;
    if (src) f.src = src;
    if (cls) f.className = cls;
    if (title) f.title = title;
    if (style) f.setAttribute('style', style);
    if (sandbox) f.setAttribute('sandbox', sandbox);
    if (el.hasAttribute('data-allowfullscreen')) f.allowFullscreen = true;
    el.replaceWith(f);
    return f;
  }

  function mountAll(root) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('[data-teatro-frame]').forEach(mountOne);
  }

  mountAll(document);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      mountAll(document);
    });
  }

  w.teatroMountFrames = mountAll;
})(window);
