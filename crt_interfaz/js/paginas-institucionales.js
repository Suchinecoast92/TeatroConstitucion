/**
 * Nav móvil, reveal on scroll, mapa embed y slots de imagen vacíos.
 */
(function () {
  function setNavOpen(open) {
    var mainNav = document.getElementById('mainNav');
    var hamburgerBtn = document.getElementById('hamburgerBtn');
    var navBackdrop = document.getElementById('navBackdrop');
    if (!mainNav || !hamburgerBtn) return;
    mainNav.classList.toggle('open', open);
    hamburgerBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (navBackdrop) {
      navBackdrop.classList.toggle('show', open);
      navBackdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
    var icon = hamburgerBtn.querySelector('i');
    if (icon) {
      icon.className = open ? 'bi bi-x-lg' : 'bi bi-list';
      icon.style.fontSize = '1.25rem';
    }
  }

  function mountMap() {
    var box = document.getElementById('mapaUbicacion');
    if (!box || !box.getAttribute('data-map-src')) return;
    if (box.querySelector('iframe')) return;
    var f = document.createElement('iframe');
    f.title = box.getAttribute('data-map-title') || 'Mapa';
    f.src = box.getAttribute('data-map-src');
    f.loading = 'lazy';
    f.referrerPolicy = 'no-referrer-when-downgrade';
    f.allowFullscreen = true;
    f.setAttribute('sandbox', 'allow-scripts allow-popups allow-popups-to-escape-sandbox allow-same-origin');
    box.appendChild(f);
  }

  function initSlots() {
    document.querySelectorAll('.media-slot').forEach(function (slot) {
      var img = slot.querySelector('img');
      if (!img) {
        slot.classList.add('is-empty');
        return;
      }
      if (!img.getAttribute('src')) {
        slot.classList.add('is-empty');
        return;
      }
      if (img.complete && img.naturalWidth === 0) {
        slot.classList.add('is-empty');
        img.removeAttribute('src');
      }
    });
  }

  function initReveal() {
    var nodes = document.querySelectorAll('.pg-reveal');
    if (!('IntersectionObserver' in window)) {
      nodes.forEach(function (n) { n.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('is-in');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    nodes.forEach(function (n) { io.observe(n); });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var hamburgerBtn = document.getElementById('hamburgerBtn');
    var mainNav = document.getElementById('mainNav');
    var navBackdrop = document.getElementById('navBackdrop');

    if (hamburgerBtn && mainNav) {
      hamburgerBtn.addEventListener('click', function () {
        setNavOpen(!mainNav.classList.contains('open'));
      });
    }
    if (navBackdrop) {
      navBackdrop.addEventListener('click', function () { setNavOpen(false); });
    }
    if (mainNav) {
      mainNav.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { setNavOpen(false); });
      });
    }

    mountMap();
    initSlots();
    initReveal();
  });
})();
