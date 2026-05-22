(function () {
    var nav = document.querySelector('.site-nav');
    var toggle = document.querySelector('.nav-toggle');
    var links = document.querySelectorAll('.nav-links a[href^="#"]');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        links.forEach(function (a) {
            a.addEventListener('click', function () {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    var header = document.querySelector('.site-header');
    if (header) {
        function updateHeaderScroll() {
            header.classList.toggle('is-scrolled', window.scrollY > 12);
        }
        updateHeaderScroll();
        window.addEventListener('scroll', updateHeaderScroll, { passive: true });
    }

    links.forEach(function (link) {
        link.addEventListener('click', function (e) {
            var id = link.getAttribute('href');
            if (!id || id === '#') return;
            var el = document.querySelector(id);
            if (!el) return;
            e.preventDefault();
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
