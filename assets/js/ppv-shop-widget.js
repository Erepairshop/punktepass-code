/**
 * PunktePass Shop Widget – Embeddable Repair Form Widget
 * Embed on any website to let customers submit repair requests
 *
 * Usage:
 * <script src="https://punktepass.de/formular/shop-widget.js" data-store="my-store-slug"></script>
 *
 * Options (data attributes):
 *   data-store    - Store slug (required) – matches /formular/{slug}
 *   data-mode     - Display: float | inline | button (default: float)
 *   data-lang     - Language: de|en|hu|ro|it (default: de)
 *   data-position - Float position: bottom-right|bottom-left (default: bottom-right)
 *   data-color    - Primary color hex (default: #667eea)
 *   data-text     - Custom button text (overrides translation)
 *   data-target   - CSS selector for inline mode container
 */
(function() {
    'use strict';

    // Find our script tag
    var scripts = document.getElementsByTagName('script');
    var currentScript = null;
    for (var i = 0; i < scripts.length; i++) {
        if (scripts[i].src && scripts[i].src.indexOf('shop-widget') !== -1) {
            currentScript = scripts[i];
        }
    }
    if (!currentScript) currentScript = scripts[scripts.length - 1];

    var config = {
        store:    currentScript.getAttribute('data-store') || '',
        mode:     currentScript.getAttribute('data-mode') || 'float',
        lang:     currentScript.getAttribute('data-lang') || 'de',
        position: currentScript.getAttribute('data-position') || 'bottom-right',
        color:    currentScript.getAttribute('data-color') || '#667eea',
        text:     currentScript.getAttribute('data-text') || '',
        target:   currentScript.getAttribute('data-target') || ''
    };

    if (!config.store) return;

    // Base URL – detect from script src
    var BASE_URL = 'https://punktepass.de';
    if (currentScript.src) {
        var m = currentScript.src.match(/^(https?:\/\/[^/]+)/);
        if (m) BASE_URL = m[1];
    }
    var FORM_URL = BASE_URL + '/formular/' + encodeURIComponent(config.store);

    // Translations
    var t = {
        de: { fab: 'Reparatur anfragen', title: 'Reparatur einreichen', subtitle: 'Füllen Sie das Formular aus und wir melden uns bei Ihnen.', cta: 'Formular öffnen', powered: 'Powered by', close: 'Schließen' },
        en: { fab: 'Request repair', title: 'Submit a repair', subtitle: 'Fill out the form and we will get back to you.', cta: 'Open form', powered: 'Powered by', close: 'Close' },
        hu: { fab: 'Javítás kérése', title: 'Javítás beküldése', subtitle: 'Töltse ki az űrlapot és hamarosan jelentkezünk.', cta: 'Űrlap megnyitása', powered: 'Powered by', close: 'Bezárás' },
        ro: { fab: 'Solicită reparație', title: 'Trimite reparație', subtitle: 'Completați formularul și vă vom contacta.', cta: 'Deschide formularul', powered: 'Powered by', close: 'Închide' },
        it: { fab: 'Richiedi riparazione', title: 'Invia riparazione', subtitle: 'Compila il modulo e ti contatteremo.', cta: 'Apri modulo', powered: 'Powered by', close: 'Chiudi' }
    };
    var lang = t[config.lang] || t.de;
    var fabText = config.text || lang.fab;

    var W = 'ppv-sw'; // CSS prefix
    var isLeft = config.position === 'bottom-left';
    var posCSS = isLeft ? 'left:20px' : 'right:20px';

    // ─── Shared color helpers ──────────────────────────────
    function darken(hex, pct) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);
        r = Math.round(r * (1 - pct)); g = Math.round(g * (1 - pct)); b = Math.round(b * (1 - pct));
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }
    var color2 = darken(config.color, 0.25);

    // ─── Inject styles ─────────────────────────────────────
    var style = document.createElement('style');
    style.textContent =
        /* FAB button */
        '#' + W + '-fab{' +
            'position:fixed;' + posCSS + ';bottom:20px;z-index:999990;' +
            'display:flex;align-items:center;gap:10px;' +
            'background:linear-gradient(135deg,' + config.color + ',' + color2 + ');' +
            'color:#fff;border:none;padding:14px 22px;border-radius:50px;cursor:pointer;' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;font-weight:600;' +
            'box-shadow:0 4px 24px rgba(0,0,0,.18);transition:all .3s cubic-bezier(.4,0,.2,1);' +
            'line-height:1;text-decoration:none}' +
        '#' + W + '-fab:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,.28)}' +
        '#' + W + '-fab svg{width:20px;height:20px;flex-shrink:0}' +

        /* Panel (iframe container) */
        '#' + W + '-panel{' +
            'position:fixed;' + posCSS + ';bottom:80px;z-index:999991;' +
            'width:400px;max-width:calc(100vw - 32px);height:600px;max-height:calc(100vh - 120px);' +
            'background:#fff;border-radius:16px;overflow:hidden;' +
            'box-shadow:0 20px 60px rgba(0,0,0,.22),0 0 0 1px rgba(0,0,0,.05);' +
            'transform:translateY(20px) scale(.95);opacity:0;pointer-events:none;' +
            'transition:all .35s cubic-bezier(.4,0,.2,1);' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;' +
            'display:flex;flex-direction:column}' +
        '#' + W + '-panel.open{transform:translateY(0) scale(1);opacity:1;pointer-events:auto}' +

        /* Panel header */
        '#' + W + '-hdr{' +
            'display:flex;align-items:center;justify-content:space-between;' +
            'padding:14px 16px;background:linear-gradient(135deg,' + config.color + ',' + color2 + ');color:#fff;flex-shrink:0}' +
        '#' + W + '-hdr-t{font-size:15px;font-weight:700}' +
        '#' + W + '-hdr-s{font-size:11px;opacity:.8;margin-top:2px}' +
        '#' + W + '-cls{background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;' +
            'border-radius:50%;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:background .2s}' +
        '#' + W + '-cls:hover{background:rgba(255,255,255,.3)}' +

        /* Iframe */
        '#' + W + '-iframe{flex:1;border:none;width:100%;background:#f8fafc}' +

        /* Footer */
        '#' + W + '-ftr{padding:8px 16px;border-top:1px solid #f1f5f9;text-align:center;font-size:11px;color:#94a3b8;flex-shrink:0}' +
        '#' + W + '-ftr a{color:#64748b;text-decoration:none;font-weight:600}' +
        '#' + W + '-ftr a:hover{color:' + config.color + '}' +

        /* Button mode */
        '#' + W + '-btn{' +
            'display:inline-flex;align-items:center;gap:10px;' +
            'background:linear-gradient(135deg,' + config.color + ',' + color2 + ');' +
            'color:#fff;border:none;padding:14px 28px;border-radius:12px;cursor:pointer;' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:15px;font-weight:700;' +
            'text-decoration:none;transition:all .2s;box-shadow:0 4px 16px ' + config.color + '40}' +
        '#' + W + '-btn:hover{transform:translateY(-1px);box-shadow:0 6px 24px ' + config.color + '50}' +
        '#' + W + '-btn svg{width:20px;height:20px}' +

        /* Inline mode */
        '#' + W + '-inline{' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;' +
            'border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08),0 0 0 1px rgba(0,0,0,.04);' +
            'background:#fff;max-width:480px}' +
        '#' + W + '-inline-hdr{background:linear-gradient(135deg,' + config.color + ',' + color2 + ');padding:20px 24px;color:#fff}' +
        '#' + W + '-inline-hdr h3{font-size:18px;font-weight:700;margin:0 0 4px}' +
        '#' + W + '-inline-hdr p{font-size:13px;margin:0;opacity:.85}' +
        '#' + W + '-inline-body{padding:20px 24px}' +
        '#' + W + '-inline-cta{' +
            'display:block;width:100%;padding:14px;border:none;border-radius:12px;cursor:pointer;' +
            'font-size:15px;font-weight:700;font-family:inherit;text-align:center;text-decoration:none;' +
            'background:linear-gradient(135deg,' + config.color + ',' + color2 + ');color:#fff;' +
            'transition:all .2s;box-shadow:0 4px 12px ' + config.color + '40}' +
        '#' + W + '-inline-cta:hover{opacity:.9}' +
        '#' + W + '-inline-ftr{padding:10px 24px;border-top:1px solid #f1f5f9;text-align:center;font-size:11px;color:#94a3b8}' +
        '#' + W + '-inline-ftr a{color:#64748b;text-decoration:none;font-weight:600}' +

        /* Mobile */
        '@media(max-width:480px){' +
            '#' + W + '-panel{width:calc(100vw - 16px);' + (isLeft ? 'left:8px' : 'right:8px') + ';bottom:72px;height:calc(100vh - 100px)}' +
            '#' + W + '-fab{' + (isLeft ? 'left:12px' : 'right:12px') + ';bottom:12px;padding:12px 18px;font-size:13px}' +
        '}';
    document.head.appendChild(style);

    // ─── SVG icon (wrench) ─────────────────────────────────
    var wrenchSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>';

    function buildFooter() {
        return lang.powered + ' <a href="' + BASE_URL + '/formular/partner" target="_blank" rel="noopener">PunktePass</a>';
    }

    // ─── FLOAT MODE ────────────────────────────────────────
    if (config.mode === 'float') {
        var fab = document.createElement('button');
        fab.id = W + '-fab';
        fab.innerHTML = wrenchSVG + '<span>' + fabText + '</span>';
        document.body.appendChild(fab);

        var panel = document.createElement('div');
        panel.id = W + '-panel';
        panel.innerHTML =
            '<div id="' + W + '-hdr">' +
                '<div><div id="' + W + '-hdr-t">' + lang.title + '</div><div id="' + W + '-hdr-s">' + lang.subtitle + '</div></div>' +
                '<button id="' + W + '-cls" aria-label="' + lang.close + '">&times;</button>' +
            '</div>' +
            '<iframe id="' + W + '-iframe" src="about:blank" loading="lazy"></iframe>' +
            '<div id="' + W + '-ftr">' + buildFooter() + '</div>';
        document.body.appendChild(panel);

        var isOpen = false;
        var iframeLoaded = false;

        fab.addEventListener('click', function(e) {
            e.preventDefault();
            isOpen = !isOpen;
            panel.classList.toggle('open', isOpen);
            // Lazy-load iframe on first open
            if (isOpen && !iframeLoaded) {
                panel.querySelector('#' + W + '-iframe').src = FORM_URL + '?embed=1';
                iframeLoaded = true;
            }
        });

        panel.querySelector('#' + W + '-cls').addEventListener('click', function() {
            isOpen = false;
            panel.classList.remove('open');
        });

        document.addEventListener('click', function(e) {
            if (isOpen && !panel.contains(e.target) && !fab.contains(e.target)) {
                isOpen = false;
                panel.classList.remove('open');
            }
        });
    }

    // ─── BUTTON MODE ───────────────────────────────────────
    if (config.mode === 'button') {
        var target = config.target ? document.querySelector(config.target) : null;
        if (!target) return;

        var btn = document.createElement('a');
        btn.id = W + '-btn';
        btn.href = FORM_URL;
        btn.target = '_blank';
        btn.rel = 'noopener';
        btn.innerHTML = wrenchSVG + '<span>' + fabText + '</span>';
        target.appendChild(btn);
    }

    // ─── INLINE MODE ───────────────────────────────────────
    if (config.mode === 'inline') {
        var container = config.target ? document.querySelector(config.target) : null;
        if (!container) return;

        var inline = document.createElement('div');
        inline.id = W + '-inline';
        inline.innerHTML =
            '<div id="' + W + '-inline-hdr"><h3>' + lang.title + '</h3><p>' + lang.subtitle + '</p></div>' +
            '<div id="' + W + '-inline-body">' +
                '<a id="' + W + '-inline-cta" href="' + FORM_URL + '" target="_blank" rel="noopener">' + lang.cta + ' \u2192</a>' +
            '</div>' +
            '<div id="' + W + '-inline-ftr">' + buildFooter() + '</div>';
        container.appendChild(inline);
    }
})();
