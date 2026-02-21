/**
 * PunktePass Shop Widget – Embeddable Repair Form Widget
 * Embed on any website to let customers submit repair requests
 *
 * Usage:
 * <script src="https://punktepass.de/formular/shop-widget.js" data-store="my-store-slug"></script>
 *
 * Options (data attributes):
 *   data-store    - Store slug (required) – matches /formular/{slug}
 *   data-mode     - Display: float | inline | button | ai (default: float)
 *   data-lang     - Language: de|en|hu|ro|it (default: de)
 *   data-position - Float position: bottom-right|bottom-left (default: bottom-right)
 *   data-color    - Primary color hex (default: #667eea)
 *   data-text     - Custom button text (overrides translation)
 *   data-target   - CSS selector for inline/button mode container
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
    var AJAX_URL = BASE_URL + '/wp-admin/admin-ajax.php';

    // Translations
    var t = {
        de: {
            fab: 'Reparatur anfragen', title: 'Reparatur einreichen',
            subtitle: 'F\u00fcllen Sie das Formular aus und wir melden uns bei Ihnen.',
            cta: 'Formular \u00f6ffnen', powered: 'Powered by', close: 'Schlie\u00dfen',
            // AI mode
            ai_fab: 'KI-Diagnose', ai_title: 'Reparatur-Diagnose',
            ai_subtitle: 'Lassen Sie Ihr Problem analysieren',
            step1_title: 'Ger\u00e4t ausw\u00e4hlen', step1_model: 'Modell eingeben',
            step1_model_ph: 'z.B. iPhone 14, Galaxy S24...',
            step2_title: 'Problem beschreiben', step2_ph: 'Beschreiben Sie das Problem...',
            step2_chips: ['Display kaputt', 'Akku schwach', 'L\u00e4dt nicht', 'Wasserschaden', 'Kamera defekt', 'Kein Ton'],
            step3_title: 'Analyse l\u00e4uft...', step3_wait: 'KI analysiert Ihr Problem',
            step4_title: 'Diagnose', step4_price: 'Gesch\u00e4tzte Kosten',
            step4_cta: 'Reparatur anfragen', step4_hint: '* Unverbindliche Sch\u00e4tzung',
            back: 'Zur\u00fcck', next: 'Weiter', analyze: 'Analysieren'
        },
        en: {
            fab: 'Request repair', title: 'Submit a repair',
            subtitle: 'Fill out the form and we will get back to you.',
            cta: 'Open form', powered: 'Powered by', close: 'Close',
            ai_fab: 'AI Diagnosis', ai_title: 'Repair Diagnosis',
            ai_subtitle: 'Let us analyze your problem',
            step1_title: 'Select device', step1_model: 'Enter model',
            step1_model_ph: 'e.g. iPhone 14, Galaxy S24...',
            step2_title: 'Describe problem', step2_ph: 'Describe the problem...',
            step2_chips: ['Broken screen', 'Weak battery', 'Not charging', 'Water damage', 'Camera broken', 'No sound'],
            step3_title: 'Analyzing...', step3_wait: 'AI is analyzing your problem',
            step4_title: 'Diagnosis', step4_price: 'Estimated cost',
            step4_cta: 'Request repair', step4_hint: '* Non-binding estimate',
            back: 'Back', next: 'Next', analyze: 'Analyze'
        },
        hu: {
            fab: 'Jav\u00edt\u00e1s k\u00e9r\u00e9se', title: 'Jav\u00edt\u00e1s bek\u00fcld\u00e9se',
            subtitle: 'T\u00f6ltse ki az \u0171rlapot \u00e9s hamarosan jelentkez\u00fcnk.',
            cta: '\u0170rlap megnyit\u00e1sa', powered: 'Powered by', close: 'Bez\u00e1r\u00e1s',
            ai_fab: 'AI Diagn\u00f3zis', ai_title: 'Jav\u00edt\u00e1si diagn\u00f3zis',
            ai_subtitle: 'Elemezz\u00fck a probl\u00e9m\u00e1j\u00e1t',
            step1_title: 'K\u00e9sz\u00fcl\u00e9k v\u00e1laszt\u00e1s', step1_model: 'Modell megad\u00e1sa',
            step1_model_ph: 'pl. iPhone 14, Galaxy S24...',
            step2_title: 'Probl\u00e9ma le\u00edr\u00e1sa', step2_ph: '\u00cdrja le a probl\u00e9m\u00e1t...',
            step2_chips: ['T\u00f6r\u00f6tt kijelz\u0151', 'Gyenge akku', 'Nem t\u00f6lt', 'V\u00edzk\u00e1r', 'Kamera hiba', 'Nincs hang'],
            step3_title: 'Elemz\u00e9s...', step3_wait: 'AI elemzi a probl\u00e9m\u00e1t',
            step4_title: 'Diagn\u00f3zis', step4_price: 'Becs\u00fclt k\u00f6lts\u00e9g',
            step4_cta: 'Jav\u00edt\u00e1s megrendel\u00e9se', step4_hint: '* Nem k\u00f6telez\u0151 becsl\u00e9s',
            back: 'Vissza', next: 'Tov\u00e1bb', analyze: 'Elemz\u00e9s'
        },
        ro: {
            fab: 'Solicit\u0103 repara\u021bie', title: 'Trimite repara\u021bie',
            subtitle: 'Completa\u021bi formularul \u0219i v\u0103 vom contacta.',
            cta: 'Deschide formularul', powered: 'Powered by', close: '\u00cenchide',
            ai_fab: 'Diagnostic AI', ai_title: 'Diagnostic repara\u021bie',
            ai_subtitle: 'Analiz\u0103m problema dumneavoastr\u0103',
            step1_title: 'Selecta\u021bi dispozitivul', step1_model: 'Introduce\u021bi modelul',
            step1_model_ph: 'ex. iPhone 14, Galaxy S24...',
            step2_title: 'Descriere problem\u0103', step2_ph: 'Descrieți problema...',
            step2_chips: ['Ecran spart', 'Baterie slab\u0103', 'Nu se \u00eencarc\u0103', 'Daune ap\u0103', 'Camer\u0103 defect\u0103', 'F\u0103r\u0103 sunet'],
            step3_title: 'Se analizeaz\u0103...', step3_wait: 'AI analizeaz\u0103 problema',
            step4_title: 'Diagnostic', step4_price: 'Cost estimat',
            step4_cta: 'Solicit\u0103 repara\u021bie', step4_hint: '* Estimare orientativ\u0103',
            back: '\u00cenapoi', next: 'Urm\u0103torul', analyze: 'Analizeaz\u0103'
        },
        it: {
            fab: 'Richiedi riparazione', title: 'Invia riparazione',
            subtitle: 'Compila il modulo e ti contatteremo.',
            cta: 'Apri modulo', powered: 'Powered by', close: 'Chiudi',
            ai_fab: 'Diagnosi AI', ai_title: 'Diagnosi riparazione',
            ai_subtitle: 'Analizziamo il tuo problema',
            step1_title: 'Seleziona dispositivo', step1_model: 'Inserisci modello',
            step1_model_ph: 'es. iPhone 14, Galaxy S24...',
            step2_title: 'Descrivi problema', step2_ph: 'Descrivi il problema...',
            step2_chips: ['Schermo rotto', 'Batteria debole', 'Non si carica', 'Danni da acqua', 'Fotocamera rotta', 'Nessun suono'],
            step3_title: 'Analisi in corso...', step3_wait: 'AI sta analizzando il problema',
            step4_title: 'Diagnosi', step4_price: 'Costo stimato',
            step4_cta: 'Richiedi riparazione', step4_hint: '* Stima non vincolante',
            back: 'Indietro', next: 'Avanti', analyze: 'Analizza'
        }
    };
    var lang = t[config.lang] || t.de;
    var fabText = config.text || (config.mode === 'ai' ? lang.ai_fab : lang.fab);

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
    var grad = 'linear-gradient(135deg,' + config.color + ',' + color2 + ')';

    // ─── Inject styles ─────────────────────────────────────
    var style = document.createElement('style');
    style.textContent =
        /* FAB button */
        '#' + W + '-fab{' +
            'position:fixed;' + posCSS + ';bottom:20px;z-index:999990;' +
            'display:flex;align-items:center;gap:10px;' +
            'background:' + grad + ';' +
            'color:#fff;border:none;padding:14px 22px;border-radius:50px;cursor:pointer;' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;font-weight:600;' +
            'box-shadow:0 4px 24px rgba(0,0,0,.18);transition:all .3s cubic-bezier(.4,0,.2,1);' +
            'line-height:1;text-decoration:none}' +
        '#' + W + '-fab:hover{transform:translateY(-2px);box-shadow:0 8px 32px rgba(0,0,0,.28)}' +
        '#' + W + '-fab svg{width:20px;height:20px;flex-shrink:0}' +

        /* Panel */
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
            'padding:14px 16px;background:' + grad + ';color:#fff;flex-shrink:0}' +
        '#' + W + '-hdr-t{font-size:15px;font-weight:700}' +
        '#' + W + '-hdr-s{font-size:11px;opacity:.8;margin-top:2px}' +
        '#' + W + '-cls{background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;' +
            'border-radius:50%;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:background .2s;flex-shrink:0}' +
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
            'background:' + grad + ';' +
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
        '#' + W + '-inline-hdr{background:' + grad + ';padding:20px 24px;color:#fff}' +
        '#' + W + '-inline-hdr h3{font-size:18px;font-weight:700;margin:0 0 4px}' +
        '#' + W + '-inline-hdr p{font-size:13px;margin:0;opacity:.85}' +
        '#' + W + '-inline-body{padding:20px 24px}' +
        '#' + W + '-inline-cta{' +
            'display:block;width:100%;padding:14px;border:none;border-radius:12px;cursor:pointer;' +
            'font-size:15px;font-weight:700;font-family:inherit;text-align:center;text-decoration:none;' +
            'background:' + grad + ';color:#fff;' +
            'transition:all .2s;box-shadow:0 4px 12px ' + config.color + '40}' +
        '#' + W + '-inline-cta:hover{opacity:.9}' +
        '#' + W + '-inline-ftr{padding:10px 24px;border-top:1px solid #f1f5f9;text-align:center;font-size:11px;color:#94a3b8}' +
        '#' + W + '-inline-ftr a{color:#64748b;text-decoration:none;font-weight:600}' +

        /* ─── AI MODE STYLES ──────────────────────────────── */
        '#' + W + '-ai-body{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column}' +
        '#' + W + '-ai-body *{box-sizing:border-box}' +

        /* Steps container */
        '.' + W + '-step{display:none;flex-direction:column;gap:16px;flex:1}' +
        '.' + W + '-step.active{display:flex}' +
        '.' + W + '-step-title{font-size:14px;font-weight:700;color:#0f172a;margin:0}' +

        /* Progress bar */
        '#' + W + '-progress{display:flex;gap:4px;padding:0 16px 0;flex-shrink:0}' +
        '.' + W + '-prog-dot{flex:1;height:3px;border-radius:2px;background:#e2e8f0;transition:background .3s}' +
        '.' + W + '-prog-dot.done{background:' + config.color + '}' +
        '.' + W + '-prog-dot.current{background:' + config.color + ';opacity:.5}' +

        /* Brand grid */
        '.' + W + '-brands{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}' +
        '.' + W + '-brand{padding:12px 8px;border:2px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;text-align:center;' +
            'font-size:12px;font-weight:600;color:#475569;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:4px}' +
        '.' + W + '-brand:hover{border-color:#cbd5e1;background:#f8fafc}' +
        '.' + W + '-brand.sel{border-color:' + config.color + ';background:' + config.color + '10;color:' + config.color + '}' +
        '.' + W + '-brand-icon{font-size:22px}' +

        /* Model input */
        '.' + W + '-input{width:100%;padding:12px 14px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;' +
            'font-family:inherit;outline:none;transition:border-color .2s;background:#fff;color:#0f172a}' +
        '.' + W + '-input:focus{border-color:' + config.color + '}' +
        '.' + W + '-input::placeholder{color:#94a3b8}' +

        /* Textarea */
        '.' + W + '-textarea{width:100%;padding:12px 14px;border:2px solid #e2e8f0;border-radius:10px;font-size:14px;' +
            'font-family:inherit;outline:none;resize:none;min-height:80px;transition:border-color .2s;background:#fff;color:#0f172a}' +
        '.' + W + '-textarea:focus{border-color:' + config.color + '}' +
        '.' + W + '-textarea::placeholder{color:#94a3b8}' +

        /* Problem chips */
        '.' + W + '-chips{display:flex;flex-wrap:wrap;gap:6px}' +
        '.' + W + '-chip{padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:20px;background:#fff;cursor:pointer;' +
            'font-size:12px;font-weight:500;color:#64748b;transition:all .2s;white-space:nowrap}' +
        '.' + W + '-chip:hover{border-color:#cbd5e1}' +
        '.' + W + '-chip.sel{border-color:' + config.color + ';background:' + config.color + '10;color:' + config.color + '}' +

        /* Buttons */
        '.' + W + '-btns{display:flex;gap:8px;margin-top:auto;padding-top:12px}' +
        '.' + W + '-btn-back{flex:1;padding:12px;border:2px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;' +
            'font-size:14px;font-weight:600;font-family:inherit;color:#64748b;transition:all .2s}' +
        '.' + W + '-btn-back:hover{border-color:#cbd5e1;background:#f8fafc}' +
        '.' + W + '-btn-next{flex:2;padding:12px;border:none;border-radius:10px;background:' + grad + ';cursor:pointer;' +
            'font-size:14px;font-weight:700;font-family:inherit;color:#fff;transition:all .2s;box-shadow:0 2px 8px ' + config.color + '30}' +
        '.' + W + '-btn-next:hover{opacity:.9;box-shadow:0 4px 16px ' + config.color + '40}' +
        '.' + W + '-btn-next:disabled{opacity:.4;cursor:not-allowed;box-shadow:none}' +

        /* Loading animation */
        '.' + W + '-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;gap:16px;text-align:center}' +
        '.' + W + '-spinner{width:48px;height:48px;border:4px solid #e2e8f0;border-top-color:' + config.color + ';border-radius:50%;animation:' + W + '-spin 1s linear infinite}' +
        '@keyframes ' + W + '-spin{to{transform:rotate(360deg)}}' +
        '.' + W + '-loading-text{font-size:14px;color:#64748b;font-weight:500}' +

        /* Result card */
        '.' + W + '-result{display:flex;flex-direction:column;gap:14px;flex:1;overflow-y:auto}' +
        '.' + W + '-result-section{background:#f8fafc;border-radius:10px;padding:14px}' +
        '.' + W + '-result-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin:0 0 6px}' +
        '.' + W + '-result-text{font-size:13px;color:#334155;line-height:1.6;margin:0;white-space:pre-line}' +
        '.' + W + '-result-price{background:' + config.color + '10;border:1.5px solid ' + config.color + '30;border-radius:10px;padding:14px;text-align:center}' +
        '.' + W + '-result-price-val{font-size:22px;font-weight:800;color:' + config.color + ';margin:4px 0}' +
        '.' + W + '-result-price-hint{font-size:11px;color:#94a3b8}' +
        '.' + W + '-result-cta{display:block;width:100%;padding:14px;border:none;border-radius:12px;' +
            'background:' + grad + ';color:#fff;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;' +
            'text-align:center;text-decoration:none;transition:all .2s;box-shadow:0 4px 16px ' + config.color + '30;margin-top:auto}' +
        '.' + W + '-result-cta:hover{opacity:.9;transform:translateY(-1px)}' +

        /* AI mode iframe (embedded form) */
        '#' + W + '-ai-iframe-wrap{display:none;flex:1;flex-direction:column;overflow:hidden}' +
        '#' + W + '-ai-iframe-wrap.active{display:flex}' +
        '#' + W + '-ai-iframe{flex:1;border:none;width:100%;background:#f8fafc}' +
        '#' + W + '-ai-back-bar{display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-shrink:0;cursor:pointer}' +
        '#' + W + '-ai-back-bar:hover{background:#f1f5f9}' +
        '#' + W + '-ai-back-bar svg{width:16px;height:16px;color:#64748b}' +
        '#' + W + '-ai-back-bar span{font-size:13px;font-weight:600;color:#64748b}' +

        /* Mobile */
        '@media(max-width:480px){' +
            '#' + W + '-panel{width:calc(100vw - 16px);' + (isLeft ? 'left:8px' : 'right:8px') + ';bottom:72px;height:calc(100vh - 100px)}' +
            '#' + W + '-fab{' + (isLeft ? 'left:12px' : 'right:12px') + ';bottom:12px;padding:12px 18px;font-size:13px}' +
        '}';
    document.head.appendChild(style);

    // ─── SVG icons ─────────────────────────────────────────
    var wrenchSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>';
    var sparkSVG = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2z"/></svg>';

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

    // ─── AI MODE ───────────────────────────────────────────
    if (config.mode === 'ai') {
        var brands = [
            { id: 'Apple',   icon: '\uD83C\uDF4E', label: 'Apple' },
            { id: 'Samsung', icon: '\uD83D\uDCF1', label: 'Samsung' },
            { id: 'Huawei',  icon: '\uD83D\uDCF2', label: 'Huawei' },
            { id: 'Xiaomi',  icon: '\u2B50', label: 'Xiaomi' },
            { id: 'Google',  icon: 'G', label: 'Google' },
            { id: 'Other',   icon: '\u2699', label: lang.step1_model }
        ];

        var state = { step: 1, brand: '', model: '', problem: '', result: null };

        // FAB
        var aiFab = document.createElement('button');
        aiFab.id = W + '-fab';
        aiFab.innerHTML = sparkSVG + '<span>' + fabText + '</span>';
        document.body.appendChild(aiFab);

        // Panel
        var aiPanel = document.createElement('div');
        aiPanel.id = W + '-panel';

        var brandGrid = '';
        for (var bi = 0; bi < brands.length; bi++) {
            brandGrid += '<button type="button" class="' + W + '-brand" data-brand="' + brands[bi].id + '">' +
                '<span class="' + W + '-brand-icon">' + brands[bi].icon + '</span>' + brands[bi].label + '</button>';
        }

        var chipHTML = '';
        var chips = lang.step2_chips || [];
        for (var ci = 0; ci < chips.length; ci++) {
            chipHTML += '<button type="button" class="' + W + '-chip" data-chip="' + chips[ci] + '">' + chips[ci] + '</button>';
        }

        aiPanel.innerHTML =
            '<div id="' + W + '-hdr">' +
                '<div><div id="' + W + '-hdr-t">' + lang.ai_title + '</div><div id="' + W + '-hdr-s">' + lang.ai_subtitle + '</div></div>' +
                '<button id="' + W + '-cls" aria-label="' + lang.close + '">&times;</button>' +
            '</div>' +
            '<div id="' + W + '-progress">' +
                '<div class="' + W + '-prog-dot done" data-s="1"></div>' +
                '<div class="' + W + '-prog-dot" data-s="2"></div>' +
                '<div class="' + W + '-prog-dot" data-s="3"></div>' +
                '<div class="' + W + '-prog-dot" data-s="4"></div>' +
            '</div>' +
            '<div id="' + W + '-ai-body">' +

                /* Step 1: Device */
                '<div class="' + W + '-step active" data-step="1">' +
                    '<p class="' + W + '-step-title">' + lang.step1_title + '</p>' +
                    '<div class="' + W + '-brands">' + brandGrid + '</div>' +
                    '<input type="text" class="' + W + '-input" id="' + W + '-model" placeholder="' + lang.step1_model_ph + '">' +
                    '<div class="' + W + '-btns">' +
                        '<button type="button" class="' + W + '-btn-next" id="' + W + '-next1" disabled>' + lang.next + ' \u2192</button>' +
                    '</div>' +
                '</div>' +

                /* Step 2: Problem */
                '<div class="' + W + '-step" data-step="2">' +
                    '<p class="' + W + '-step-title">' + lang.step2_title + '</p>' +
                    '<div class="' + W + '-chips">' + chipHTML + '</div>' +
                    '<textarea class="' + W + '-textarea" id="' + W + '-problem" placeholder="' + lang.step2_ph + '"></textarea>' +
                    '<div class="' + W + '-btns">' +
                        '<button type="button" class="' + W + '-btn-back" id="' + W + '-back2">' + lang.back + '</button>' +
                        '<button type="button" class="' + W + '-btn-next" id="' + W + '-analyze" disabled>' + lang.analyze + ' \u2728</button>' +
                    '</div>' +
                '</div>' +

                /* Step 3: Loading */
                '<div class="' + W + '-step" data-step="3">' +
                    '<div class="' + W + '-loading">' +
                        '<div class="' + W + '-spinner"></div>' +
                        '<div class="' + W + '-loading-text">' + lang.step3_wait + '</div>' +
                    '</div>' +
                '</div>' +

                /* Step 4: Result */
                '<div class="' + W + '-step" data-step="4">' +
                    '<div class="' + W + '-result" id="' + W + '-result"></div>' +
                '</div>' +

            '</div>' +
            '<div id="' + W + '-ai-iframe-wrap">' +
                '<div id="' + W + '-ai-back-bar">' +
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>' +
                    '<span>' + lang.back + '</span>' +
                '</div>' +
                '<iframe id="' + W + '-ai-iframe" src="about:blank" loading="lazy"></iframe>' +
            '</div>' +
            '<div id="' + W + '-ftr">' + buildFooter() + '</div>';
        document.body.appendChild(aiPanel);

        // --- AI Mode Logic ---
        var aiOpen = false;
        var brandBtns = aiPanel.querySelectorAll('.' + W + '-brand');
        var modelInput = aiPanel.querySelector('#' + W + '-model');
        var problemTA = aiPanel.querySelector('#' + W + '-problem');
        var chipBtns = aiPanel.querySelectorAll('.' + W + '-chip');
        var next1Btn = aiPanel.querySelector('#' + W + '-next1');
        var back2Btn = aiPanel.querySelector('#' + W + '-back2');
        var analyzeBtn = aiPanel.querySelector('#' + W + '-analyze');

        function goToStep(n) {
            state.step = n;
            var steps = aiPanel.querySelectorAll('.' + W + '-step');
            for (var si = 0; si < steps.length; si++) {
                steps[si].classList.toggle('active', steps[si].getAttribute('data-step') == n);
            }
            var dots = aiPanel.querySelectorAll('.' + W + '-prog-dot');
            for (var di = 0; di < dots.length; di++) {
                var ds = parseInt(dots[di].getAttribute('data-s'));
                dots[di].className = W + '-prog-dot' + (ds < n ? ' done' : ds === n ? ' current done' : '');
            }
        }

        function updateNext1() {
            next1Btn.disabled = !state.brand;
        }

        function updateAnalyze() {
            var txt = problemTA.value.trim();
            analyzeBtn.disabled = txt.length < 5;
        }

        // Brand selection
        for (var bii = 0; bii < brandBtns.length; bii++) {
            brandBtns[bii].addEventListener('click', function() {
                state.brand = this.getAttribute('data-brand');
                for (var x = 0; x < brandBtns.length; x++) brandBtns[x].classList.remove('sel');
                this.classList.add('sel');
                updateNext1();
            });
        }

        modelInput.addEventListener('input', function() {
            state.model = this.value.trim();
        });

        // Problem chips
        for (var cii = 0; cii < chipBtns.length; cii++) {
            chipBtns[cii].addEventListener('click', function() {
                this.classList.toggle('sel');
                var selected = aiPanel.querySelectorAll('.' + W + '-chip.sel');
                var parts = [];
                for (var p = 0; p < selected.length; p++) parts.push(selected[p].getAttribute('data-chip'));
                var existing = problemTA.value.trim();
                // Only set from chips if user hasn't typed much
                if (existing.length < 5 || existing === state._lastChipText) {
                    problemTA.value = parts.join(', ');
                    state._lastChipText = problemTA.value;
                }
                updateAnalyze();
            });
        }

        problemTA.addEventListener('input', updateAnalyze);

        // Navigation
        next1Btn.addEventListener('click', function() {
            if (state.brand) goToStep(2);
        });

        back2Btn.addEventListener('click', function() {
            goToStep(1);
        });

        // Analyze
        analyzeBtn.addEventListener('click', function() {
            state.problem = problemTA.value.trim();
            if (state.problem.length < 5) return;

            goToStep(3);

            var fd = new FormData();
            fd.append('action', 'ppv_shop_widget_diagnose');
            fd.append('store_slug', config.store);
            fd.append('device_brand', state.brand === 'Other' ? '' : state.brand);
            fd.append('device_model', state.model);
            fd.append('problem', state.problem);
            fd.append('lang', config.lang);

            fetch(AJAX_URL, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        state.result = data.data;
                        renderResult(data.data);
                        goToStep(4);
                    } else {
                        renderError(data.data && data.data.message ? data.data.message : 'Error');
                        goToStep(4);
                    }
                })
                .catch(function() {
                    renderError('Connection error. Please try again.');
                    goToStep(4);
                });
        });

        function renderResult(data) {
            var resultDiv = aiPanel.querySelector('#' + W + '-result');
            var diag = data.diagnosis || '';

            // Parse structured sections from AI response
            var sections = parseDiagnosis(diag);

            var html = '';

            // Device info
            if (data.device) {
                html += '<div class="' + W + '-result-section">' +
                    '<p class="' + W + '-result-label">' + lang.step1_title + '</p>' +
                    '<p class="' + W + '-result-text">' + escHTML(data.device) + '</p></div>';
            }

            // Diagnosis text
            html += '<div class="' + W + '-result-section">' +
                '<p class="' + W + '-result-label">' + lang.step4_title + '</p>' +
                '<p class="' + W + '-result-text">' + escHTML(sections.diagnosis || diag) + '</p></div>';

            // Price
            var price = data.price_range || sections.price || '';
            if (price) {
                html += '<div class="' + W + '-result-price">' +
                    '<p class="' + W + '-result-label">' + lang.step4_price + '</p>' +
                    '<p class="' + W + '-result-price-val">' + escHTML(price) + '</p>' +
                    '<p class="' + W + '-result-price-hint">' + lang.step4_hint + '</p></div>';
            }

            // CTA – opens form inside the same panel
            var formUrl = data.form_url || FORM_URL;
            var params = '?embed=1&brand=' + encodeURIComponent(state.brand === 'Other' ? '' : state.brand) +
                '&model=' + encodeURIComponent(state.model) +
                '&problem=' + encodeURIComponent(state.problem);
            state._formEmbedUrl = formUrl + params;
            html += '<button type="button" class="' + W + '-result-cta" id="' + W + '-open-form">' +
                lang.step4_cta + ' \u2192</button>';

            resultDiv.innerHTML = html;

            // Wire CTA to show embedded form
            var openFormBtn = resultDiv.querySelector('#' + W + '-open-form');
            if (openFormBtn) {
                openFormBtn.addEventListener('click', function() {
                    showEmbeddedForm(state._formEmbedUrl);
                });
            }
        }

        function renderError(msg) {
            var resultDiv = aiPanel.querySelector('#' + W + '-result');
            resultDiv.innerHTML =
                '<div class="' + W + '-result-section" style="text-align:center;padding:30px 14px">' +
                    '<p style="font-size:32px;margin:0 0 8px">\u26A0\uFE0F</p>' +
                    '<p class="' + W + '-result-text">' + escHTML(msg) + '</p>' +
                '</div>' +
                '<div class="' + W + '-btns">' +
                    '<button type="button" class="' + W + '-btn-back">' + lang.back + '</button>' +
                    '<button type="button" class="' + W + '-result-cta" style="flex:2;border-radius:10px;padding:12px;font-size:14px">' +
                        lang.step4_cta + '</button>' +
                '</div>';
            // Wire back button
            var backBtn = resultDiv.querySelector('.' + W + '-btn-back');
            if (backBtn) backBtn.addEventListener('click', function() { goToStep(2); });
            // Wire CTA to open form in panel
            var errCta = resultDiv.querySelector('.' + W + '-result-cta');
            if (errCta) errCta.addEventListener('click', function() { showEmbeddedForm(FORM_URL + '?embed=1'); });
        }

        function parseDiagnosis(text) {
            var result = { diagnosis: '', causes: '', price: '', tip: '' };
            // Try to extract DIAGNOSIS/PRICE sections
            var diagMatch = text.match(/(?:DIAGNOSIS|DIAGNOSE|DIAGN[OÓ]ZIS|DIAGNOSTIC|DIAGNOSI):\s*(.+?)(?=\n(?:CAUSES|URSACHEN|OKOK|CAUZE|CAUSE)|$)/is);
            if (diagMatch) result.diagnosis = diagMatch[1].trim();

            var priceMatch = text.match(/(?:PRICE|PREIS|ÁR|PREȚ|PREZZO):\s*(.+)/i);
            if (priceMatch) result.price = priceMatch[1].trim();

            // If no structured format, use full text as diagnosis
            if (!result.diagnosis) result.diagnosis = text;

            return result;
        }

        function escHTML(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        // ─── Embedded form in panel ─────────────────────────
        var aiBody = aiPanel.querySelector('#' + W + '-ai-body');
        var progressBar = aiPanel.querySelector('#' + W + '-progress');
        var iframeWrap = aiPanel.querySelector('#' + W + '-ai-iframe-wrap');
        var aiIframe = aiPanel.querySelector('#' + W + '-ai-iframe');
        var aiBackBar = aiPanel.querySelector('#' + W + '-ai-back-bar');

        function showEmbeddedForm(url) {
            aiBody.style.display = 'none';
            progressBar.style.display = 'none';
            iframeWrap.classList.add('active');
            aiIframe.src = url;
        }

        function hideEmbeddedForm() {
            iframeWrap.classList.remove('active');
            aiBody.style.display = '';
            progressBar.style.display = '';
        }

        aiBackBar.addEventListener('click', function() {
            hideEmbeddedForm();
        });

        // Open/close
        aiFab.addEventListener('click', function(e) {
            e.preventDefault();
            aiOpen = !aiOpen;
            aiPanel.classList.toggle('open', aiOpen);
        });

        aiPanel.querySelector('#' + W + '-cls').addEventListener('click', function() {
            aiOpen = false;
            aiPanel.classList.remove('open');
        });

        document.addEventListener('click', function(e) {
            if (aiOpen && !aiPanel.contains(e.target) && !aiFab.contains(e.target)) {
                aiOpen = false;
                aiPanel.classList.remove('open');
            }
        });
    }
})();
