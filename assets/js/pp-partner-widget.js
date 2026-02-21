/**
 * PunktePass Partner Widget
 * Embed on partner websites to promote repair management
 *
 * Usage:
 * <script src="https://punktepass.de/formular/widget.js" data-partner="PP-XXXXXX"></script>
 *
 * Options (data attributes):
 *   data-partner   - Partner code (required)
 *   data-lang      - Language: de|en (default: de)
 *   data-position  - Float position: bottom-right|bottom-left (default: bottom-right)
 *   data-color     - Primary color (default: #667eea)
 *   data-mode      - Display mode: float|inline (default: float)
 *   data-target    - CSS selector for inline mode container
 */
(function() {
    'use strict';

    // Find our script tag and read config
    var scripts = document.getElementsByTagName('script');
    var currentScript = scripts[scripts.length - 1];
    // Try to find the correct script by src
    for (var i = 0; i < scripts.length; i++) {
        if (scripts[i].src && scripts[i].src.indexOf('partner-widget') !== -1 || scripts[i].src && scripts[i].src.indexOf('widget.js') !== -1) {
            currentScript = scripts[i];
        }
    }

    var config = {
        partner: currentScript.getAttribute('data-partner') || '',
        lang: currentScript.getAttribute('data-lang') || 'de',
        position: currentScript.getAttribute('data-position') || 'bottom-right',
        color: currentScript.getAttribute('data-color') || '#667eea',
        mode: currentScript.getAttribute('data-mode') || 'float',
        target: currentScript.getAttribute('data-target') || ''
    };

    if (!config.partner) return;

    var BASE_URL = 'https://punktepass.de';
    var REF_URL = BASE_URL + '/formular?ref=' + encodeURIComponent(config.partner);
    var PARTNER_URL = BASE_URL + '/formular/partner';

    // Translations
    var t = {
        de: {
            badge: 'Reparatur-Service',
            title: 'Digitale Reparaturverwaltung',
            subtitle: 'Reparaturen digital erfassen, Rechnungen erstellen, Kunden informieren &ndash; alles in einem System.',
            feat1: 'Online-Formular &amp; Tablet',
            feat2: 'Rechnungen &amp; Angebote',
            feat3: 'Kundenverwaltung',
            feat4: 'DATEV-Export',
            cta: 'Kostenlos starten',
            info: 'Mehr erfahren',
            free: 'Kostenlos',
            powered: 'Powered by'
        },
        en: {
            badge: 'Repair Service',
            title: 'Digital Repair Management',
            subtitle: 'Record repairs digitally, create invoices, keep customers informed &ndash; all in one system.',
            feat1: 'Online Form &amp; Tablet',
            feat2: 'Invoices &amp; Quotes',
            feat3: 'Customer Management',
            feat4: 'DATEV Export',
            cta: 'Start for free',
            info: 'Learn more',
            free: 'Free',
            powered: 'Powered by'
        }
    };
    var lang = t[config.lang] || t.de;

    // Inject styles
    var WIDGET_ID = 'pp-partner-widget';
    var isLeft = config.position === 'bottom-left';
    var grad = 'linear-gradient(135deg,' + config.color + ',#4338ca)';
    var style = document.createElement('style');
    style.textContent = '#' + WIDGET_ID + '-fab{' +
        'position:fixed;' + (isLeft ? 'left:20px' : 'right:20px') + ';bottom:20px;z-index:999990;' +
        'display:flex;align-items:center;gap:8px;' +
        'background:' + grad + ';' +
        'color:#fff;border:none;padding:12px 20px;border-radius:50px;cursor:pointer;' +
        'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:14px;font-weight:600;' +
        'box-shadow:0 4px 24px rgba(0,0,0,0.18);transition:all .3s cubic-bezier(.4,0,.2,1);' +
        'text-decoration:none;line-height:1;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
    '#' + WIDGET_ID + '-fab:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,0.25)}' +
    '#' + WIDGET_ID + '-fab svg{width:20px;height:20px;flex-shrink:0}' +

    /* Panel – FULLSCREEN on all devices */
    '#' + WIDGET_ID + '-panel{' +
        'position:fixed;top:0;left:0;right:0;bottom:0;z-index:999991;' +
        'width:100%;height:100%;background:#fff;border-radius:0;' +
        'box-shadow:none;' +
        'transform:translateY(100%);opacity:0;pointer-events:none;' +
        'transition:transform .35s cubic-bezier(.4,0,.2,1),opacity .25s ease;' +
        'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;' +
        'overflow:hidden;display:flex;flex-direction:column}' +
    '#' + WIDGET_ID + '-panel.pp-open{transform:translateY(0);opacity:1;pointer-events:auto}' +

    /* Body scroll lock */
    'body.' + WIDGET_ID + '-noscroll{overflow:hidden!important;position:fixed!important;width:100%!important;height:100%!important}' +

    '#' + WIDGET_ID + '-panel .pp-w-header{' +
        'background:' + grad + ';padding:24px;position:relative;overflow:hidden;flex-shrink:0;' +
        'padding-top:max(24px,env(safe-area-inset-top,0px))}' +
    '#' + WIDGET_ID + '-panel .pp-w-header::after{' +
        'content:"";position:absolute;top:-30px;right:-30px;width:120px;height:120px;' +
        'background:rgba(255,255,255,0.08);border-radius:50%}' +
    '#' + WIDGET_ID + '-panel .pp-w-header::before{' +
        'content:"";position:absolute;bottom:-40px;left:-20px;width:100px;height:100px;' +
        'background:rgba(255,255,255,0.05);border-radius:50%}' +
    '#' + WIDGET_ID + '-panel .pp-w-close{' +
        'position:absolute;top:16px;right:16px;background:rgba(255,255,255,0.18);border:none;' +
        'color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:20px;' +
        'display:flex;align-items:center;justify-content:center;transition:background .2s;z-index:1;' +
        'top:max(16px,env(safe-area-inset-top,0px));' +
        '-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
    '#' + WIDGET_ID + '-panel .pp-w-close:hover{background:rgba(255,255,255,0.3)}' +
    '#' + WIDGET_ID + '-panel .pp-w-title{color:#fff;font-size:22px;font-weight:700;margin:0 0 6px 0;position:relative;z-index:1}' +
    '#' + WIDGET_ID + '-panel .pp-w-subtitle{color:rgba(255,255,255,0.85);font-size:14px;line-height:1.5;margin:0;position:relative;z-index:1}' +
    '#' + WIDGET_ID + '-panel .pp-w-free{' +
        'display:inline-block;background:rgba(255,255,255,0.2);color:#fff;padding:4px 12px;' +
        'border-radius:20px;font-size:12px;font-weight:700;margin-top:12px;position:relative;z-index:1;' +
        'letter-spacing:0.5px;text-transform:uppercase}' +

    '#' + WIDGET_ID + '-panel .pp-w-body{padding:24px;flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;' +
        'display:flex;flex-direction:column;align-items:center}' +
    '#' + WIDGET_ID + '-panel .pp-w-body-inner{max-width:480px;width:100%}' +

    '#' + WIDGET_ID + '-panel .pp-w-features{' +
        'display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px}' +
    '#' + WIDGET_ID + '-panel .pp-w-feat{' +
        'display:flex;align-items:center;gap:10px;font-size:14px;color:#374151;font-weight:500}' +
    '#' + WIDGET_ID + '-panel .pp-w-feat-icon{' +
        'width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;' +
        'font-size:18px;flex-shrink:0;background:' + config.color + '12}' +

    '#' + WIDGET_ID + '-panel .pp-w-cta{' +
        'display:block;width:100%;padding:16px;border:none;border-radius:12px;cursor:pointer;' +
        'font-size:16px;font-weight:700;font-family:inherit;text-align:center;text-decoration:none;' +
        'background:' + grad + ';color:#fff;' +
        'transition:all .2s;box-shadow:0 4px 12px ' + config.color + '40;' +
        'min-height:52px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
    '#' + WIDGET_ID + '-panel .pp-w-cta:hover{transform:translateY(-1px);box-shadow:0 6px 20px ' + config.color + '50}' +

    '#' + WIDGET_ID + '-panel .pp-w-info{' +
        'display:block;text-align:center;margin-top:12px;font-size:14px;color:#64748b;' +
        'text-decoration:none;font-weight:500;min-height:44px;display:flex;align-items:center;justify-content:center}' +
    '#' + WIDGET_ID + '-panel .pp-w-info:hover{color:' + config.color + '}' +

    '#' + WIDGET_ID + '-panel .pp-w-footer{' +
        'padding:12px 24px;border-top:1px solid #f1f5f9;text-align:center;' +
        'font-size:11px;color:#94a3b8;flex-shrink:0;' +
        'padding-bottom:max(12px,env(safe-area-inset-bottom,0px))}' +
    '#' + WIDGET_ID + '-panel .pp-w-footer a{color:#64748b;text-decoration:none;font-weight:600}' +
    '#' + WIDGET_ID + '-panel .pp-w-footer a:hover{color:' + config.color + '}' +

    /* Inline banner mode */
    '#' + WIDGET_ID + '-inline{' +
        'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;' +
        'background:#fff;border-radius:16px;overflow:hidden;' +
        'box-shadow:0 1px 3px rgba(0,0,0,0.08),0 0 0 1px rgba(0,0,0,0.04);max-width:480px}' +
    '#' + WIDGET_ID + '-inline .pp-w-header{' +
        'background:' + grad + ';padding:20px 24px;position:relative;overflow:hidden}' +
    '#' + WIDGET_ID + '-inline .pp-w-header::after{' +
        'content:"";position:absolute;top:-30px;right:-30px;width:100px;height:100px;' +
        'background:rgba(255,255,255,0.08);border-radius:50%}' +
    '#' + WIDGET_ID + '-inline .pp-w-title{color:#fff;font-size:18px;font-weight:700;margin:0 0 4px 0;position:relative;z-index:1}' +
    '#' + WIDGET_ID + '-inline .pp-w-subtitle{color:rgba(255,255,255,0.85);font-size:13px;line-height:1.5;margin:0;position:relative;z-index:1}' +
    '#' + WIDGET_ID + '-inline .pp-w-body{padding:16px 24px 20px}' +
    '#' + WIDGET_ID + '-inline .pp-w-features{' +
        'display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px}' +
    '#' + WIDGET_ID + '-inline .pp-w-feat{' +
        'display:flex;align-items:center;gap:6px;font-size:12px;color:#374151;font-weight:500}' +
    '#' + WIDGET_ID + '-inline .pp-w-feat-icon{' +
        'width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;' +
        'font-size:14px;flex-shrink:0;background:' + config.color + '12}' +
    '#' + WIDGET_ID + '-inline .pp-w-cta{' +
        'display:block;width:100%;padding:12px;border:none;border-radius:10px;cursor:pointer;' +
        'font-size:14px;font-weight:700;font-family:inherit;text-align:center;text-decoration:none;' +
        'background:' + grad + ';color:#fff;' +
        'transition:all .2s}' +
    '#' + WIDGET_ID + '-inline .pp-w-cta:hover{opacity:0.9}' +
    '#' + WIDGET_ID + '-inline .pp-w-footer{' +
        'padding:10px 24px;border-top:1px solid #f1f5f9;text-align:center;font-size:11px;color:#94a3b8}' +
    '#' + WIDGET_ID + '-inline .pp-w-footer a{color:#64748b;text-decoration:none;font-weight:600}' +

    /* Mobile optimizations */
    '@media(max-width:480px){' +
        '#' + WIDGET_ID + '-fab{' + (isLeft ? 'left:12px' : 'right:12px') + ';bottom:12px;padding:10px 16px;font-size:13px}' +
        '#' + WIDGET_ID + '-panel .pp-w-header{padding:20px 16px}' +
        '#' + WIDGET_ID + '-panel .pp-w-title{font-size:20px}' +
        '#' + WIDGET_ID + '-panel .pp-w-subtitle{font-size:13px}' +
        '#' + WIDGET_ID + '-panel .pp-w-body{padding:20px 16px}' +
        '#' + WIDGET_ID + '-panel .pp-w-features{gap:10px}' +
        '#' + WIDGET_ID + '-panel .pp-w-feat{font-size:13px}' +
    '}';
    document.head.appendChild(style);

    // Feature icons (simple Unicode)
    var featIcons = ['\uD83D\uDCF1', '\uD83D\uDCCE', '\uD83D\uDC65', '\uD83D\uDCCA'];
    var featLabels = [lang.feat1, lang.feat2, lang.feat3, lang.feat4];

    function buildFeatures() {
        var html = '';
        for (var i = 0; i < 4; i++) {
            html += '<div class="pp-w-feat"><span class="pp-w-feat-icon">' + featIcons[i] + '</span>' + featLabels[i] + '</div>';
        }
        return html;
    }

    function buildFooter() {
        return '<div class="pp-w-footer">' + lang.powered + ' <a href="' + PARTNER_URL + '" target="_blank" rel="noopener">PunktePass</a></div>';
    }

    // ─── FLOAT MODE ─────────────────────────────────────────
    if (config.mode === 'float') {
        // FAB button
        var fab = document.createElement('button');
        fab.id = WIDGET_ID + '-fab';
        fab.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>' +
            '<span>' + lang.badge + '</span>';
        document.body.appendChild(fab);

        // Panel
        var panel = document.createElement('div');
        panel.id = WIDGET_ID + '-panel';
        panel.innerHTML =
            '<div class="pp-w-header">' +
                '<button class="pp-w-close" aria-label="Close">&times;</button>' +
                '<div class="pp-w-title">' + lang.title + '</div>' +
                '<div class="pp-w-subtitle">' + lang.subtitle + '</div>' +
                '<span class="pp-w-free">\u2713 ' + lang.free + '</span>' +
            '</div>' +
            '<div class="pp-w-body"><div class="pp-w-body-inner">' +
                '<div class="pp-w-features">' + buildFeatures() + '</div>' +
                '<a class="pp-w-cta" href="' + REF_URL + '" target="_blank" rel="noopener">' + lang.cta + ' \u2192</a>' +
                '<a class="pp-w-info" href="' + PARTNER_URL + '" target="_blank" rel="noopener">' + lang.info + '</a>' +
            '</div></div>' +
            buildFooter();
        document.body.appendChild(panel);

        var isOpen = false;
        var scrollY = 0;
        function lockBody() {
            scrollY = window.pageYOffset;
            document.body.classList.add(WIDGET_ID + '-noscroll');
            document.body.style.top = '-' + scrollY + 'px';
        }
        function unlockBody() {
            document.body.classList.remove(WIDGET_ID + '-noscroll');
            document.body.style.top = '';
            window.scrollTo(0, scrollY);
        }

        fab.addEventListener('click', function(e) {
            e.preventDefault();
            isOpen = !isOpen;
            panel.classList.toggle('pp-open', isOpen);
            if (isOpen) {
                lockBody();
            } else {
                unlockBody();
            }
        });

        panel.querySelector('.pp-w-close').addEventListener('click', function() {
            isOpen = false;
            panel.classList.remove('pp-open');
            unlockBody();
        });
    }

    // ─── INLINE MODE ────────────────────────────────────────
    if (config.mode === 'inline' && config.target) {
        var container = document.querySelector(config.target);
        if (!container) return;

        var inline = document.createElement('div');
        inline.id = WIDGET_ID + '-inline';
        inline.innerHTML =
            '<div class="pp-w-header">' +
                '<div class="pp-w-title">' + lang.title + '</div>' +
                '<div class="pp-w-subtitle">' + lang.subtitle + '</div>' +
            '</div>' +
            '<div class="pp-w-body">' +
                '<div class="pp-w-features">' + buildFeatures() + '</div>' +
                '<a class="pp-w-cta" href="' + REF_URL + '" target="_blank" rel="noopener">' + lang.cta + ' \u2192</a>' +
            '</div>' +
            buildFooter();
        container.appendChild(inline);
    }
})();
