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

        /* Panel – FULLSCREEN on all devices */
        '#' + W + '-panel{' +
            'position:fixed;top:0;left:0;right:0;bottom:0;z-index:999991;' +
            'width:100%;height:100%;' +
            'background:#fff;border-radius:0;overflow:hidden;' +
            'box-shadow:none;' +
            'transform:translateY(100%);opacity:0;pointer-events:none;' +
            'transition:transform .35s cubic-bezier(.4,0,.2,1),opacity .25s ease;' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;' +
            'display:flex;flex-direction:column}' +
        '#' + W + '-panel.open{transform:translateY(0);opacity:1;pointer-events:auto}' +

        /* Body scroll lock when panel is open */
        'body.' + W + '-noscroll{overflow:hidden!important;position:fixed!important;width:100%!important;height:100%!important}' +

        /* Panel header */
        '#' + W + '-hdr{' +
            'display:flex;align-items:center;justify-content:space-between;' +
            'padding:16px 20px;background:' + grad + ';color:#fff;flex-shrink:0;' +
            'padding-top:max(16px,env(safe-area-inset-top,0px))}' +
        '#' + W + '-hdr-t{font-size:17px;font-weight:700}' +
        '#' + W + '-hdr-s{font-size:12px;opacity:.85;margin-top:2px}' +
        '#' + W + '-cls{background:rgba(255,255,255,.18);border:none;color:#fff;width:36px;height:36px;' +
            'border-radius:50%;cursor:pointer;font-size:20px;display:flex;align-items:center;justify-content:center;transition:background .2s;flex-shrink:0;' +
            '-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '#' + W + '-cls:hover{background:rgba(255,255,255,.3)}' +

        /* Iframe – fills remaining space */
        '#' + W + '-iframe{flex:1;border:none;width:100%;height:100%;background:#f8fafc}' +

        /* Footer */
        '#' + W + '-ftr{padding:8px 16px;border-top:1px solid #f1f5f9;text-align:center;font-size:11px;color:#94a3b8;flex-shrink:0;' +
            'padding-bottom:max(8px,env(safe-area-inset-bottom,0px))}' +
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
        '#' + W + '-ai-body{flex:1;overflow-y:auto;padding:24px;display:flex;flex-direction:column;' +
            '-webkit-overflow-scrolling:touch}' +
        '#' + W + '-ai-body *{box-sizing:border-box}' +

        /* Steps container */
        '.' + W + '-step{display:none;flex-direction:column;gap:16px;flex:1;max-width:600px;width:100%;margin:0 auto}' +
        '.' + W + '-step.active{display:flex}' +
        '.' + W + '-step-title{font-size:16px;font-weight:700;color:#0f172a;margin:0}' +

        /* Progress bar */
        '#' + W + '-progress{display:flex;gap:4px;padding:0 20px;flex-shrink:0}' +
        '.' + W + '-prog-dot{flex:1;height:3px;border-radius:2px;background:#e2e8f0;transition:background .3s}' +
        '.' + W + '-prog-dot.done{background:' + config.color + '}' +
        '.' + W + '-prog-dot.current{background:' + config.color + ';opacity:.5}' +

        /* Brand grid */
        '.' + W + '-brands{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}' +
        '.' + W + '-brand{padding:14px 10px;border:2px solid #e2e8f0;border-radius:12px;background:#fff;cursor:pointer;text-align:center;' +
            'font-size:13px;font-weight:600;color:#475569;transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:6px;' +
            'min-height:44px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '.' + W + '-brand:hover{border-color:#cbd5e1;background:#f8fafc}' +
        '.' + W + '-brand.sel{border-color:' + config.color + ';background:' + config.color + '10;color:' + config.color + '}' +
        '.' + W + '-brand-icon{font-size:26px}' +

        /* Model input */
        '.' + W + '-input{width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:16px;' +
            'font-family:inherit;outline:none;transition:border-color .2s;background:#fff;color:#0f172a}' +
        '.' + W + '-input:focus{border-color:' + config.color + '}' +
        '.' + W + '-input::placeholder{color:#94a3b8}' +

        /* Textarea */
        '.' + W + '-textarea{width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:16px;' +
            'font-family:inherit;outline:none;resize:none;min-height:100px;transition:border-color .2s;background:#fff;color:#0f172a}' +
        '.' + W + '-textarea:focus{border-color:' + config.color + '}' +
        '.' + W + '-textarea::placeholder{color:#94a3b8}' +

        /* Problem chips */
        '.' + W + '-chips{display:flex;flex-wrap:wrap;gap:8px}' +
        '.' + W + '-chip{padding:10px 16px;border:1.5px solid #e2e8f0;border-radius:24px;background:#fff;cursor:pointer;' +
            'font-size:14px;font-weight:500;color:#64748b;transition:all .2s;white-space:nowrap;' +
            'min-height:44px;display:flex;align-items:center;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '.' + W + '-chip:hover{border-color:#cbd5e1}' +
        '.' + W + '-chip.sel{border-color:' + config.color + ';background:' + config.color + '10;color:' + config.color + '}' +

        /* Buttons */
        '.' + W + '-btns{display:flex;gap:10px;margin-top:auto;padding-top:16px}' +
        '.' + W + '-btn-back{flex:1;padding:14px;border:2px solid #e2e8f0;border-radius:12px;background:#fff;cursor:pointer;' +
            'font-size:15px;font-weight:600;font-family:inherit;color:#64748b;transition:all .2s;' +
            'min-height:48px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '.' + W + '-btn-back:hover{border-color:#cbd5e1;background:#f8fafc}' +
        '.' + W + '-btn-next{flex:2;padding:14px;border:none;border-radius:12px;background:' + grad + ';cursor:pointer;' +
            'font-size:15px;font-weight:700;font-family:inherit;color:#fff;transition:all .2s;box-shadow:0 2px 8px ' + config.color + '30;' +
            'min-height:48px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '.' + W + '-btn-next:hover{opacity:.9;box-shadow:0 4px 16px ' + config.color + '40}' +
        '.' + W + '-btn-next:disabled{opacity:.4;cursor:not-allowed;box-shadow:none}' +

        /* Loading animation */
        '.' + W + '-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;gap:20px;text-align:center}' +
        '.' + W + '-spinner{width:56px;height:56px;border:4px solid #e2e8f0;border-top-color:' + config.color + ';border-radius:50%;animation:' + W + '-spin 1s linear infinite}' +
        '@keyframes ' + W + '-spin{to{transform:rotate(360deg)}}' +
        '.' + W + '-loading-text{font-size:15px;color:#64748b;font-weight:500}' +

        /* Result card */
        '.' + W + '-result{display:flex;flex-direction:column;gap:16px;flex:1;overflow-y:auto;max-width:600px;width:100%;margin:0 auto;' +
            '-webkit-overflow-scrolling:touch}' +
        '.' + W + '-result-section{background:#f8fafc;border-radius:12px;padding:16px}' +
        '.' + W + '-result-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin:0 0 6px}' +
        '.' + W + '-result-text{font-size:14px;color:#334155;line-height:1.6;margin:0;white-space:pre-line}' +
        '.' + W + '-result-price{background:' + config.color + '10;border:1.5px solid ' + config.color + '30;border-radius:12px;padding:16px;text-align:center}' +
        '.' + W + '-result-price-val{font-size:24px;font-weight:800;color:' + config.color + ';margin:4px 0}' +
        '.' + W + '-result-price-hint{font-size:11px;color:#94a3b8}' +
        '.' + W + '-result-cta{display:block;width:100%;padding:16px;border:none;border-radius:12px;' +
            'background:' + grad + ';color:#fff;font-size:16px;font-weight:700;font-family:inherit;cursor:pointer;' +
            'text-align:center;text-decoration:none;transition:all .2s;box-shadow:0 4px 16px ' + config.color + '30;margin-top:auto;' +
            'min-height:52px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '.' + W + '-result-cta:hover{opacity:.9;transform:translateY(-1px)}' +

        /* Quality tiers */
        '.' + W + '-tiers{display:flex;gap:10px;margin:0}' +
        '.' + W + '-tier{flex:1;border:2px solid #e2e8f0;border-radius:12px;padding:14px;text-align:center;cursor:pointer;transition:all .2s;background:#fff}' +
        '.' + W + '-tier:hover{border-color:#cbd5e1}' +
        '.' + W + '-tier.sel{border-color:' + config.color + ';background:' + config.color + '08}' +
        '.' + W + '-tier-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;margin-bottom:8px}' +
        '.' + W + '-tier-price{font-size:20px;font-weight:800;color:#0f172a;margin:4px 0}' +
        '.' + W + '-tier-time{font-size:12px;color:#64748b}' +
        '.' + W + '-tier-desc{font-size:11px;color:#94a3b8;margin-top:4px}' +

        /* Custom sections */
        '.' + W + '-custom-section{background:#f8fafc;border-radius:12px;padding:16px}' +
        '.' + W + '-cs-title{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:#334155;margin:0 0 10px}' +
        '.' + W + '-cs-title i{font-size:16px;color:' + config.color + '}' +
        '.' + W + '-cs-list{margin:0;padding:0 0 0 18px;font-size:13px;color:#475569;line-height:1.8}' +
        '.' + W + '-cs-steps{display:flex;flex-direction:column;gap:8px;margin:0;padding:0;list-style:none}' +
        '.' + W + '-cs-step{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#475569}' +
        '.' + W + '-cs-step-num{width:24px;height:24px;border-radius:50%;background:' + grad + ';color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}' +
        '.' + W + '-cs-highlight{background:' + config.color + '10;border:1.5px solid ' + config.color + '25;border-radius:12px;padding:14px;text-align:center}' +
        '.' + W + '-cs-highlight-badge{display:inline-block;padding:2px 10px;border-radius:20px;background:' + grad + ';color:#fff;font-size:11px;font-weight:700;margin-bottom:6px}' +
        '.' + W + '-cs-highlight-text{font-size:14px;font-weight:600;color:#334155;margin:0}' +
        '.' + W + '-cs-info{font-size:13px;color:#475569;line-height:1.7;margin:0}' +
        '.' + W + '-cs-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}' +
        '.' + W + '-cs-grid-item{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:10px;font-size:12px;color:#475569;text-align:center}' +
        '.' + W + '-cs-faq{display:flex;flex-direction:column;gap:6px}' +
        '.' + W + '-cs-faq-q{font-size:13px;font-weight:600;color:#334155;cursor:pointer;padding:8px 10px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;transition:all .2s}' +
        '.' + W + '-cs-faq-q:hover{background:#f1f5f9}' +
        '.' + W + '-cs-faq-a{font-size:13px;color:#475569;padding:4px 10px 10px;display:none;line-height:1.6}' +
        '.' + W + '-cs-faq-a.open{display:block}' +

        /* AI mode iframe (embedded form) – fullscreen inside panel */
        '#' + W + '-ai-iframe-wrap{display:none;flex:1;flex-direction:column;overflow:hidden}' +
        '#' + W + '-ai-iframe-wrap.active{display:flex}' +
        '#' + W + '-ai-iframe{flex:1;border:none;width:100%;height:100%;background:#f8fafc}' +
        '#' + W + '-ai-back-bar{display:flex;align-items:center;gap:8px;padding:10px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-shrink:0;cursor:pointer;' +
            'min-height:44px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '#' + W + '-ai-back-bar:hover{background:#f1f5f9}' +
        '#' + W + '-ai-back-bar svg{width:18px;height:18px;color:#64748b}' +
        '#' + W + '-ai-back-bar span{font-size:14px;font-weight:600;color:#64748b}' +

        /* Mobile optimizations */
        '@media(max-width:480px){' +
            '#' + W + '-fab{' + (isLeft ? 'left:12px' : 'right:12px') + ';bottom:12px;padding:12px 18px;font-size:13px}' +
            '#' + W + '-hdr{padding:12px 16px;padding-top:max(12px,env(safe-area-inset-top,0px))}' +
            '#' + W + '-hdr-t{font-size:15px}' +
            '#' + W + '-hdr-s{font-size:11px}' +
            '#' + W + '-ai-body{padding:16px}' +
            '.' + W + '-step-title{font-size:15px}' +
            '.' + W + '-brands{grid-template-columns:repeat(3,1fr);gap:8px}' +
            '.' + W + '-brand{padding:12px 8px;font-size:12px}' +
            '.' + W + '-brand-icon{font-size:22px}' +
        '}' +
        /* Tablet / medium screens – content centered */
        '@media(min-width:768px){' +
            '#' + W + '-ai-body{padding:32px 40px}' +
            '.' + W + '-brands{grid-template-columns:repeat(3,1fr);gap:12px}' +
            '.' + W + '-brand{padding:16px 12px;font-size:14px}' +
            '.' + W + '-brand-icon{font-size:28px}' +
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
        var scrollY = 0;

        function lockBody() {
            scrollY = window.pageYOffset;
            document.body.classList.add(W + '-noscroll');
            document.body.style.top = '-' + scrollY + 'px';
        }
        function unlockBody() {
            document.body.classList.remove(W + '-noscroll');
            document.body.style.top = '';
            window.scrollTo(0, scrollY);
        }

        fab.addEventListener('click', function(e) {
            e.preventDefault();
            isOpen = !isOpen;
            panel.classList.toggle('open', isOpen);
            if (isOpen) {
                lockBody();
                if (!iframeLoaded) {
                    panel.querySelector('#' + W + '-iframe').src = FORM_URL + '?embed=1';
                    iframeLoaded = true;
                }
            } else {
                unlockBody();
            }
        });

        panel.querySelector('#' + W + '-cls').addEventListener('click', function() {
            isOpen = false;
            panel.classList.remove('open');
            unlockBody();
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
        var defaultBrands = [
            { id: 'Apple',   icon: '\uD83C\uDF4E', label: 'Apple' },
            { id: 'Samsung', icon: '\uD83D\uDCF1', label: 'Samsung' },
            { id: 'Huawei',  icon: '\uD83D\uDCF2', label: 'Huawei' },
            { id: 'Xiaomi',  icon: '\u2B50', label: 'Xiaomi' },
            { id: 'Google',  icon: 'G', label: 'Google' },
            { id: 'Other',   icon: '\u2699', label: lang.step1_model }
        ];
        var defaultChips = lang.step2_chips || [];
        var brands = defaultBrands;
        var storeConfig = null;
        var brandModelsMap = {};

        var state = { step: 1, brand: '', model: '', problem: '', result: null };

        // FAB
        var aiFab = document.createElement('button');
        aiFab.id = W + '-fab';
        aiFab.innerHTML = sparkSVG + '<span>' + fabText + '</span>';
        document.body.appendChild(aiFab);

        // Panel
        var aiPanel = document.createElement('div');
        aiPanel.id = W + '-panel';

        // Load custom config from server (async, non-blocking)
        (function loadStoreConfig() {
            var fd = new FormData();
            fd.append('action', 'ppv_shop_widget_config');
            fd.append('store_slug', config.store);
            fetch(AJAX_URL, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.data) {
                        storeConfig = data.data;
                        // Override brands if custom ones provided
                        if (storeConfig.brands && storeConfig.brands.length > 0) {
                            var brandIcons = {Apple:'\uD83C\uDF4E',Samsung:'\uD83D\uDCF1',Huawei:'\uD83D\uDCF2',Xiaomi:'\u2B50',Google:'G',Sony:'\uD83C\uDFAE',OnePlus:'1+',LG:'\uD83D\uDCFA',Nokia:'N'};
                            brandModelsMap = {};
                            var newBrands = [];
                            for (var i = 0; i < storeConfig.brands.length; i++) {
                                var b = storeConfig.brands[i];
                                var bid = typeof b === 'string' ? b : (b.id || b.label || b);
                                var blabel = typeof b === 'string' ? b : (b.label || b.id || b);
                                var bicon = (typeof b === 'object' && b.icon) ? b.icon : (brandIcons[bid] || '\u2699');
                                var bmodels = (typeof b === 'object' && b.models) ? b.models : [];
                                newBrands.push({ id: bid, icon: bicon, label: blabel });
                                if (bmodels.length > 0) brandModelsMap[bid] = bmodels;
                            }
                            // Always add "Other" at the end
                            newBrands.push({ id: 'Other', icon: '\u2699', label: lang.step1_model });
                            // Re-render brand grid
                            var brandContainer = aiPanel.querySelector('.' + W + '-brands');
                            if (brandContainer) {
                                var html = '';
                                for (var j = 0; j < newBrands.length; j++) {
                                    html += '<button type="button" class="' + W + '-brand" data-brand="' + newBrands[j].id + '">' +
                                        '<span class="' + W + '-brand-icon">' + newBrands[j].icon + '</span>' + newBrands[j].label + '</button>';
                                }
                                brandContainer.innerHTML = html;
                                // Re-bind click events with model suggestions
                                var newBrandBtns = brandContainer.querySelectorAll('.' + W + '-brand');
                                for (var k = 0; k < newBrandBtns.length; k++) {
                                    newBrandBtns[k].addEventListener('click', function() {
                                        state.brand = this.getAttribute('data-brand');
                                        var all = brandContainer.querySelectorAll('.' + W + '-brand');
                                        for (var x = 0; x < all.length; x++) all[x].classList.remove('sel');
                                        this.classList.add('sel');
                                        var n1 = aiPanel.querySelector('#' + W + '-next1');
                                        if (n1) n1.disabled = false;
                                        // Show model suggestions
                                        showModelSuggestions(state.brand);
                                    });
                                }
                            }
                        }
                        // Override chips if custom ones provided
                        if (storeConfig.chips && storeConfig.chips.length > 0) {
                            var chipsContainer = aiPanel.querySelector('.' + W + '-chips');
                            if (chipsContainer) {
                                var chipHtml = '';
                                for (var ci = 0; ci < storeConfig.chips.length; ci++) {
                                    var c = storeConfig.chips[ci];
                                    chipHtml += '<button type="button" class="' + W + '-chip" data-chip="' + c + '">' + c + '</button>';
                                }
                                chipsContainer.innerHTML = chipHtml;
                                // Re-bind chip events
                                var newChips = chipsContainer.querySelectorAll('.' + W + '-chip');
                                var pTA = aiPanel.querySelector('#' + W + '-problem');
                                for (var cl = 0; cl < newChips.length; cl++) {
                                    newChips[cl].addEventListener('click', function() {
                                        this.classList.toggle('sel');
                                        var selected = chipsContainer.querySelectorAll('.' + W + '-chip.sel');
                                        var parts = [];
                                        for (var p = 0; p < selected.length; p++) parts.push(selected[p].getAttribute('data-chip'));
                                        if (pTA && (pTA.value.trim().length < 5 || pTA.value === state._lastChipText)) {
                                            pTA.value = parts.join(', ');
                                            state._lastChipText = pTA.value;
                                        }
                                        var aBtn = aiPanel.querySelector('#' + W + '-analyze');
                                        if (aBtn) aBtn.disabled = (pTA ? pTA.value.trim().length < 5 : true);
                                    });
                                }
                            }
                        }
                    }
                })
                .catch(function() { /* silent fail – use defaults */ });
        })();

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
                showModelSuggestions(state.brand);
            });
        }

        modelInput.addEventListener('input', function() {
            state.model = this.value.trim();
        });

        // Model suggestion chips (shown after brand selection)
        function showModelSuggestions(brand) {
            var existingDiv = aiPanel.querySelector('#' + W + '-model-suggestions');
            if (existingDiv) existingDiv.remove();
            if (!brand || brand === 'Other' || !brandModelsMap || !brandModelsMap[brand]) return;
            var models = brandModelsMap[brand];
            if (!models.length) return;
            var div = document.createElement('div');
            div.id = W + '-model-suggestions';
            div.style.cssText = 'display:flex;flex-wrap:wrap;gap:5px;margin:6px 0 2px;';
            for (var mi = 0; mi < models.length; mi++) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.textContent = models[mi];
                chip.setAttribute('data-model', models[mi]);
                chip.style.cssText = 'padding:4px 10px;border:1.5px solid ' + config.color + '33;background:#fff;border-radius:16px;font-size:11px;color:#334155;cursor:pointer;transition:all .2s;';
                chip.addEventListener('click', function() {
                    modelInput.value = this.getAttribute('data-model');
                    state.model = modelInput.value;
                    // highlight selected
                    var allMC = div.querySelectorAll('button');
                    for (var mx = 0; mx < allMC.length; mx++) {
                        allMC[mx].style.background = '#fff';
                        allMC[mx].style.color = '#334155';
                        allMC[mx].style.borderColor = config.color + '33';
                    }
                    this.style.background = config.color;
                    this.style.color = '#fff';
                    this.style.borderColor = config.color;
                });
                div.appendChild(chip);
            }
            modelInput.parentNode.insertBefore(div, modelInput.nextSibling);
        }

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

            // Quality tiers + tiered pricing
            var qTiers = (storeConfig && storeConfig.quality_tiers) ? storeConfig.quality_tiers : [];
            var tServices = (storeConfig && storeConfig.tiered_services) ? storeConfig.tiered_services : [];
            var matchedTierService = null;
            if (qTiers.length > 0 && tServices.length > 0 && state.brand) {
                // Find best matching tiered service for this device+problem
                // Score each service by how many of its name words appear in the search terms
                var searchTerms = ((state.brand || '') + ' ' + (state.model || '') + ' ' + (state.problem || '')).toLowerCase();
                var bestScore = 0;
                for (var ts = 0; ts < tServices.length; ts++) {
                    var svcName = (tServices[ts].name || '').toLowerCase();
                    if (!svcName) continue;
                    var words = svcName.split(/\s+/);
                    var score = 0;
                    for (var wi = 0; wi < words.length; wi++) {
                        if (words[wi] && searchTerms.indexOf(words[wi]) >= 0) score++;
                    }
                    if (score > bestScore) {
                        bestScore = score;
                        matchedTierService = tServices[ts];
                    }
                }
                // If no specific match, show first tiered service as example
                if (!matchedTierService && tServices.length > 0) matchedTierService = tServices[0];
            }

            if (qTiers.length > 0 && matchedTierService && matchedTierService.tiers) {
                // Show quality tier cards
                html += '<div class="' + W + '-result-section">' +
                    '<p class="' + W + '-result-label">' + lang.step4_price + '</p>' +
                    '<div class="' + W + '-tiers">';
                for (var ti = 0; ti < qTiers.length; ti++) {
                    var tier = qTiers[ti];
                    var tierData = matchedTierService.tiers[tier.id] || {};
                    var badgeColor = tier.badge_color || config.color;
                    html += '<div class="' + W + '-tier" data-tier="' + (tier.id || '') + '">' +
                        '<span class="' + W + '-tier-badge" style="background:' + badgeColor + '">' + escHTML(tier.label || tier.id) + '</span>' +
                        '<div class="' + W + '-tier-price">' + escHTML(tierData.price || '–') + '</div>' +
                        (tierData.time ? '<div class="' + W + '-tier-time">' + escHTML(tierData.time) + '</div>' : '') +
                        (tier.description ? '<div class="' + W + '-tier-desc">' + escHTML(tier.description) + '</div>' : '') +
                        '</div>';
                }
                html += '</div>' +
                    '<p class="' + W + '-result-price-hint" style="text-align:center;margin-top:8px">' + lang.step4_hint + '</p></div>';
            } else {
                // Standard single price display
                var price = data.price_range || sections.price || '';
                if (price) {
                    html += '<div class="' + W + '-result-price">' +
                        '<p class="' + W + '-result-label">' + lang.step4_price + '</p>' +
                        '<p class="' + W + '-result-price-val">' + escHTML(price) + '</p>' +
                        '<p class="' + W + '-result-price-hint">' + lang.step4_hint + '</p></div>';
                }
            }

            // Custom sections from store config
            var cSections = (storeConfig && storeConfig.custom_sections) ? storeConfig.custom_sections : [];
            for (var si = 0; si < cSections.length; si++) {
                html += renderCustomSection(cSections[si]);
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

            // Wire tier selection (visual only – highlights selected tier)
            var tierBtns = resultDiv.querySelectorAll('.' + W + '-tier');
            for (var tb = 0; tb < tierBtns.length; tb++) {
                tierBtns[tb].addEventListener('click', function() {
                    var allT = resultDiv.querySelectorAll('.' + W + '-tier');
                    for (var at = 0; at < allT.length; at++) allT[at].classList.remove('sel');
                    this.classList.add('sel');
                });
            }

            // Wire FAQ toggles
            var faqQs = resultDiv.querySelectorAll('.' + W + '-cs-faq-q');
            for (var fq = 0; fq < faqQs.length; fq++) {
                faqQs[fq].addEventListener('click', function() {
                    var ans = this.nextElementSibling;
                    if (ans) ans.classList.toggle('open');
                });
            }

            // Wire CTA to show embedded form
            var openFormBtn = resultDiv.querySelector('#' + W + '-open-form');
            if (openFormBtn) {
                openFormBtn.addEventListener('click', function() {
                    showEmbeddedForm(state._formEmbedUrl);
                });
            }
        }

        function renderCustomSection(sec) {
            var type = sec.type || 'info';
            var iconHtml = sec.icon ? '<i class="' + sec.icon + '"></i>' : '';
            var h = '<div class="' + W + '-custom-section">';
            if (sec.title) {
                h += '<div class="' + W + '-cs-title">' + iconHtml + ' ' + escHTML(sec.title) + '</div>';
            }
            if (type === 'list' && sec.items) {
                h += '<ul class="' + W + '-cs-list">';
                for (var i = 0; i < sec.items.length; i++) h += '<li>' + escHTML(sec.items[i]) + '</li>';
                h += '</ul>';
            } else if (type === 'steps' && sec.items) {
                h += '<div class="' + W + '-cs-steps">';
                for (var i = 0; i < sec.items.length; i++) {
                    h += '<div class="' + W + '-cs-step"><span class="' + W + '-cs-step-num">' + (i + 1) + '</span><span>' + escHTML(sec.items[i]) + '</span></div>';
                }
                h += '</div>';
            } else if (type === 'highlight') {
                if (sec.badge) h += '<span class="' + W + '-cs-highlight-badge">' + escHTML(sec.badge) + '</span>';
                h += '<p class="' + W + '-cs-highlight-text">' + escHTML(sec.text || '') + '</p>';
                h = '<div class="' + W + '-cs-highlight">' + (sec.title ? '<div class="' + W + '-cs-title" style="justify-content:center">' + iconHtml + ' ' + escHTML(sec.title) + '</div>' : '') +
                    (sec.badge ? '<span class="' + W + '-cs-highlight-badge">' + escHTML(sec.badge) + '</span>' : '') +
                    '<p class="' + W + '-cs-highlight-text">' + escHTML(sec.text || '') + '</p></div>';
                return h;
            } else if (type === 'grid' && sec.items) {
                h += '<div class="' + W + '-cs-grid">';
                for (var i = 0; i < sec.items.length; i++) h += '<div class="' + W + '-cs-grid-item">' + escHTML(sec.items[i]) + '</div>';
                h += '</div>';
            } else if (type === 'faq' && sec.items) {
                h += '<div class="' + W + '-cs-faq">';
                for (var i = 0; i < sec.items.length; i++) {
                    var item = sec.items[i];
                    h += '<div class="' + W + '-cs-faq-q">' + escHTML(item.q || '') + '</div>' +
                         '<div class="' + W + '-cs-faq-a">' + escHTML(item.a || '') + '</div>';
                }
                h += '</div>';
            } else {
                // info type (default)
                h += '<p class="' + W + '-cs-info">' + escHTML(sec.text || '') + '</p>';
            }
            h += '</div>';
            return h;
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

        // Open/close with body scroll lock
        var aiScrollY = 0;
        function aiLockBody() {
            aiScrollY = window.pageYOffset;
            document.body.classList.add(W + '-noscroll');
            document.body.style.top = '-' + aiScrollY + 'px';
        }
        function aiUnlockBody() {
            document.body.classList.remove(W + '-noscroll');
            document.body.style.top = '';
            window.scrollTo(0, aiScrollY);
        }

        aiFab.addEventListener('click', function(e) {
            e.preventDefault();
            aiOpen = !aiOpen;
            aiPanel.classList.toggle('open', aiOpen);
            if (aiOpen) {
                aiLockBody();
            } else {
                aiUnlockBody();
            }
        });

        aiPanel.querySelector('#' + W + '-cls').addEventListener('click', function() {
            aiOpen = false;
            aiPanel.classList.remove('open');
            aiUnlockBody();
        });
    }
})();
