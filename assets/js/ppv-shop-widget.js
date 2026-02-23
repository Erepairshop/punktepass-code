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
            back: 'Zur\u00fcck', next: 'Weiter', analyze: 'Analysieren',
            // Catalog mode
            cat_fab: 'Preisliste', cat_title: 'Unsere Leistungen', cat_subtitle: 'Preise & \u00d6ffnungszeiten',
            cat_search: 'Service suchen...', cat_hours: '\u00d6ffnungszeiten', cat_open: 'Ge\u00f6ffnet', cat_closed: 'Geschlossen',
            cat_contact: 'Kontakt & Anfahrt', cat_cta: 'Reparatur anfragen', cat_no_results: 'Keine Ergebnisse',
            cat_no_results_hint: 'Kein passender Service gefunden? Kein Problem! F\u00fcllen Sie das Formular aus und wir melden uns bei Ihnen.',
            cat_general: 'Allgemein', cat_from: 'ab', cat_map: 'Route planen', cat_call: 'Anrufen',
            cat_days: ['Mo','Di','Mi','Do','Fr','Sa','So'], cat_services: 'Leistungen', cat_time: 'Dauer',
            cat_confirm_title: 'Reparatur gew\u00fcnscht?',
            cat_confirm_text: 'M\u00f6chten Sie eine Reparatur f\u00fcr diesen Service anfragen?',
            cat_confirm_yes: 'Ja, Reparatur anfragen',
            cat_confirm_no: 'Zur\u00fcck',
            cat_transition_title: 'Formular ausf\u00fcllen',
            cat_transition_text: 'Bitte f\u00fcllen Sie das folgende Formular aus \u2013 wir melden uns mit einem Termin, wann wir es erledigen k\u00f6nnen.',
            cat_transition_btn: 'Formular \u00f6ffnen',
            cat_price_label: 'Preis', cat_time_label: 'Dauer'
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
            back: 'Back', next: 'Next', analyze: 'Analyze',
            cat_fab: 'Price list', cat_title: 'Our Services', cat_subtitle: 'Prices & Opening Hours',
            cat_search: 'Search service...', cat_hours: 'Opening Hours', cat_open: 'Open', cat_closed: 'Closed',
            cat_contact: 'Contact & Directions', cat_cta: 'Request repair', cat_no_results: 'No results',
            cat_no_results_hint: 'Can\'t find the right service? No problem! Fill out the form and we\'ll get back to you.',
            cat_general: 'General', cat_from: 'from', cat_map: 'Get directions', cat_call: 'Call',
            cat_days: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], cat_services: 'Services', cat_time: 'Duration',
            cat_confirm_title: 'Request repair?',
            cat_confirm_text: 'Would you like to request a repair for this service?',
            cat_confirm_yes: 'Yes, request repair',
            cat_confirm_no: 'Back',
            cat_transition_title: 'Fill out the form',
            cat_transition_text: 'Please fill out the following form \u2013 we will get back to you with an appointment.',
            cat_transition_btn: 'Open form',
            cat_price_label: 'Price', cat_time_label: 'Duration'
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
            back: 'Vissza', next: 'Tov\u00e1bb', analyze: 'Elemz\u00e9s',
            cat_fab: '\u00c1rlista', cat_title: 'Szolg\u00e1ltat\u00e1saink', cat_subtitle: '\u00c1rak & Nyitvatart\u00e1s',
            cat_search: 'Keres\u00e9s...', cat_hours: 'Nyitvatart\u00e1s', cat_open: 'Nyitva', cat_closed: 'Z\u00e1rva',
            cat_contact: 'Kapcsolat & Megk\u00f6zel\u00edt\u00e9s', cat_cta: 'Jav\u00edt\u00e1s k\u00e9r\u00e9se', cat_no_results: 'Nincs tal\u00e1lat',
            cat_no_results_hint: 'Nem tal\u00e1lja a megfelel\u0151 szolg\u00e1ltat\u00e1st? Semmi gond! T\u00f6ltse ki az \u0171rlapot \u00e9s felvessz\u00fck \u00d6nnel a kapcsolatot.',
            cat_general: '\u00c1ltal\u00e1nos', cat_from: '-t\u00f3l', cat_map: '\u00datvonaltervez\u00e9s', cat_call: 'H\u00edv\u00e1s',
            cat_days: ['H\u00e9','Ke','Sze','Cs','P\u00e9','Szo','Vas'], cat_services: 'Szolg\u00e1ltat\u00e1sok', cat_time: 'Id\u0151tartam',
            cat_confirm_title: 'Jav\u00edt\u00e1st szeretn\u00e9?',
            cat_confirm_text: 'Szeretn\u00e9 megrendelni ezt a jav\u00edt\u00e1st?',
            cat_confirm_yes: 'Igen, jav\u00edt\u00e1st k\u00e9rek',
            cat_confirm_no: 'Vissza',
            cat_transition_title: '\u0170rlap kit\u00f6lt\u00e9se',
            cat_transition_text: 'K\u00e9rj\u00fck t\u00f6ltse ki a k\u00f6vetkez\u0151 \u0171rlapot \u2013 jelentkez\u00fcnk egy id\u0151ponttal, amikor el tudjuk v\u00e9gezni.',
            cat_transition_btn: '\u0170rlap megnyit\u00e1sa',
            cat_price_label: '\u00c1r', cat_time_label: 'Id\u0151tartam'
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
            back: '\u00cenapoi', next: 'Urm\u0103torul', analyze: 'Analizeaz\u0103',
            cat_fab: 'List\u0103 pre\u021buri', cat_title: 'Serviciile noastre', cat_subtitle: 'Pre\u021buri & Program',
            cat_search: 'C\u0103utare serviciu...', cat_hours: 'Program', cat_open: 'Deschis', cat_closed: '\u00cenchis',
            cat_contact: 'Contact & Direc\u021bii', cat_cta: 'Solicit\u0103 repara\u021bie', cat_no_results: 'Niciun rezultat',
            cat_no_results_hint: 'Nu g\u0103si\u021bi serviciul potrivit? Nicio problem\u0103! Completa\u021bi formularul \u0219i v\u0103 vom contacta.',
            cat_general: 'General', cat_from: 'de la', cat_map: 'Planific\u0103 ruta', cat_call: 'Sun\u0103',
            cat_days: ['Lu','Ma','Mi','Jo','Vi','S\u00e2','Du'], cat_services: 'Servicii', cat_time: 'Durat\u0103',
            cat_confirm_title: 'Dori\u021bi repara\u021bie?',
            cat_confirm_text: 'Dori\u021bi s\u0103 solicita\u021bi o repara\u021bie pentru acest serviciu?',
            cat_confirm_yes: 'Da, solicit repara\u021bie',
            cat_confirm_no: '\u00cenapoi',
            cat_transition_title: 'Completa\u021bi formularul',
            cat_transition_text: 'V\u0103 rug\u0103m completa\u021bi formularul \u2013 v\u0103 vom contacta cu o programare.',
            cat_transition_btn: 'Deschide formularul',
            cat_price_label: 'Pre\u021b', cat_time_label: 'Durat\u0103'
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
            back: 'Indietro', next: 'Avanti', analyze: 'Analizza',
            cat_fab: 'Listino prezzi', cat_title: 'I nostri servizi', cat_subtitle: 'Prezzi & Orari',
            cat_search: 'Cerca servizio...', cat_hours: 'Orari di apertura', cat_open: 'Aperto', cat_closed: 'Chiuso',
            cat_contact: 'Contatti & Indicazioni', cat_cta: 'Richiedi riparazione', cat_no_results: 'Nessun risultato',
            cat_no_results_hint: 'Non trovi il servizio giusto? Nessun problema! Compila il modulo e ti contatteremo.',
            cat_general: 'Generale', cat_from: 'da', cat_map: 'Indicazioni', cat_call: 'Chiama',
            cat_days: ['Lun','Mar','Mer','Gio','Ven','Sab','Dom'], cat_services: 'Servizi', cat_time: 'Durata',
            cat_confirm_title: 'Richiedi riparazione?',
            cat_confirm_text: 'Vorresti richiedere una riparazione per questo servizio?',
            cat_confirm_yes: 'S\u00ec, richiedi riparazione',
            cat_confirm_no: 'Indietro',
            cat_transition_title: 'Compila il modulo',
            cat_transition_text: 'Compila il seguente modulo \u2013 ti contatteremo con un appuntamento.',
            cat_transition_btn: 'Apri modulo',
            cat_price_label: 'Prezzo', cat_time_label: 'Durata'
        }
    };
    var lang = t[config.lang] || t.de;
    var fabText = config.text || (config.mode === 'ai' ? lang.ai_fab : config.mode === 'catalog' ? lang.cat_fab : lang.fab);

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
        '}' +

        /* ─── CATALOG MODE STYLES ──────────────────────────── */
        '#' + W + '-cat-body{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;background:#f8fafc}' +
        '#' + W + '-cat-body *{box-sizing:border-box}' +

        /* Search */
        '#' + W + '-cat-search-wrap{padding:12px 16px;background:#fff;border-bottom:1px solid #f1f5f9;position:sticky;top:0;z-index:2}' +
        '#' + W + '-cat-search{width:100%;padding:10px 14px 10px 38px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:inherit;outline:none;background:#f8fafc url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2394a3b8\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'8\'/%3E%3Cline x1=\'21\' y1=\'21\' x2=\'16.65\' y2=\'16.65\'/%3E%3C/svg%3E") 12px center no-repeat;transition:border-color .2s,background-color .2s;color:#0f172a}' +
        '#' + W + '-cat-search:focus{border-color:' + config.color + ';background-color:#fff}' +
        '#' + W + '-cat-search::placeholder{color:#94a3b8}' +

        /* Service count badge in header */
        '#' + W + '-cat-count{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:11px;background:rgba(255,255,255,.25);font-size:11px;font-weight:700;margin-left:8px}' +

        /* Category sections */
        '.' + W + '-cat-section{border-bottom:1px solid #f1f5f9}' +
        '.' + W + '-cat-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;cursor:pointer;background:#fff;transition:background .15s;-webkit-tap-highlight-color:transparent;touch-action:manipulation;user-select:none}' +
        '.' + W + '-cat-hdr:hover{background:#f8fafc}' +
        '.' + W + '-cat-hdr:active{background:#f1f5f9}' +
        '.' + W + '-cat-hdr-left{display:flex;align-items:center;gap:10px;min-width:0}' +
        '.' + W + '-cat-hdr-icon{width:32px;height:32px;border-radius:8px;background:' + config.color + '12;color:' + config.color + ';display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}' +
        '.' + W + '-cat-hdr-name{font-size:14px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
        '.' + W + '-cat-hdr-right{display:flex;align-items:center;gap:8px;flex-shrink:0}' +
        '.' + W + '-cat-hdr-cnt{font-size:11px;font-weight:600;color:#94a3b8;background:#f1f5f9;padding:2px 8px;border-radius:10px}' +
        '.' + W + '-cat-hdr-arrow{width:20px;height:20px;color:#94a3b8;transition:transform .25s ease;flex-shrink:0}' +
        '.' + W + '-cat-section.open .' + W + '-cat-hdr-arrow{transform:rotate(180deg)}' +

        /* Category items container */
        '.' + W + '-cat-items{max-height:0;overflow:hidden;transition:max-height .3s ease;background:#fafbfc}' +
        '.' + W + '-cat-section.open .' + W + '-cat-items{max-height:9999px}' +

        /* Service rows – clickable cards */
        '.' + W + '-cat-row{display:flex;align-items:center;padding:12px 16px 12px 58px;border-top:1px solid #f1f5f9;gap:10px;min-height:52px;cursor:pointer;transition:all .15s ease;-webkit-tap-highlight-color:transparent;touch-action:manipulation;position:relative}' +
        '.' + W + '-cat-row:first-child{border-top:none}' +
        '.' + W + '-cat-row:hover{background:#f0f7ff}' +
        '.' + W + '-cat-row:active{background:#e0efff;transform:scale(.995)}' +
        '.' + W + '-cat-name{flex:1;min-width:0;font-size:14px;font-weight:500;color:#1e293b;line-height:1.3;overflow:hidden;text-overflow:ellipsis}' +
        '.' + W + '-cat-meta{display:flex;align-items:center;gap:8px;flex-shrink:0}' +
        '.' + W + '-cat-time{font-size:11px;color:#64748b;white-space:nowrap;background:#f1f5f9;padding:2px 8px;border-radius:4px}' +
        '.' + W + '-cat-price{font-size:14px;font-weight:800;color:' + config.color + ';white-space:nowrap;background:' + config.color + '12;padding:4px 12px;border-radius:8px}' +
        '.' + W + '-cat-arrow{width:18px;height:18px;color:#94a3b8;flex-shrink:0;transition:transform .2s,color .2s}' +
        '.' + W + '-cat-row:hover .' + W + '-cat-arrow{color:' + config.color + ';transform:translateX(2px)}' +

        /* Hours section */
        '#' + W + '-cat-hours{padding:16px;background:#fff;border-bottom:1px solid #f1f5f9}' +
        '#' + W + '-cat-hours-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#1e293b;margin:0 0 10px}' +
        '.' + W + '-cat-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}' +
        '.' + W + '-cat-badge-open{background:#dcfce7;color:#166534}' +
        '.' + W + '-cat-badge-closed{background:#fee2e2;color:#991b1b}' +
        '.' + W + '-cat-badge-dot{width:6px;height:6px;border-radius:50%;display:inline-block}' +
        '.' + W + '-cat-badge-open .' + W + '-cat-badge-dot{background:#22c55e}' +
        '.' + W + '-cat-badge-closed .' + W + '-cat-badge-dot{background:#ef4444}' +
        '.' + W + '-cat-day{display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:#475569}' +
        '.' + W + '-cat-day-name{font-weight:600;color:#64748b;min-width:40px}' +
        '.' + W + '-cat-day-time{color:#334155}' +
        '.' + W + '-cat-day.today{font-weight:700;color:' + config.color + '}' +
        '.' + W + '-cat-day.today .' + W + '-cat-day-name{color:' + config.color + '}' +

        /* Contact section */
        '#' + W + '-cat-contact{padding:16px;background:#fff;border-bottom:1px solid #f1f5f9}' +
        '#' + W + '-cat-contact-title{font-size:14px;font-weight:700;color:#1e293b;margin:0 0 10px}' +
        '.' + W + '-cat-contact-row{display:flex;align-items:center;gap:10px;padding:8px 0;font-size:13px;color:#475569;text-decoration:none}' +
        'a.' + W + '-cat-contact-row:hover{color:' + config.color + '}' +
        '.' + W + '-cat-contact-icon{width:32px;height:32px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}' +

        /* Confirm overlay */
        '#' + W + '-cat-confirm{display:none;position:absolute;top:0;left:0;right:0;bottom:0;z-index:5;background:#fff;flex-direction:column;align-items:center;justify-content:center;padding:32px 24px;text-align:center}' +
        '#' + W + '-cat-confirm.active{display:flex}' +
        '#' + W + '-cat-confirm-icon{width:72px;height:72px;border-radius:50%;background:' + config.color + '12;display:flex;align-items:center;justify-content:center;margin-bottom:20px}' +
        '#' + W + '-cat-confirm-icon svg{width:36px;height:36px;color:' + config.color + '}' +
        '#' + W + '-cat-confirm-svc{font-size:17px;font-weight:700;color:#0f172a;margin:0 0 4px}' +
        '#' + W + '-cat-confirm-meta{display:flex;align-items:center;justify-content:center;gap:12px;margin:8px 0 20px;font-size:13px;color:#64748b}' +
        '#' + W + '-cat-confirm-meta span{display:flex;align-items:center;gap:4px}' +
        '#' + W + '-cat-confirm-q{font-size:15px;color:#475569;margin:0 0 24px;line-height:1.5}' +
        '#' + W + '-cat-confirm-yes{display:block;width:100%;max-width:320px;padding:14px;border:none;border-radius:12px;background:' + grad + ';color:#fff;font-size:15px;font-weight:700;font-family:inherit;cursor:pointer;transition:all .2s;box-shadow:0 4px 16px ' + config.color + '30;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '#' + W + '-cat-confirm-yes:hover{opacity:.9;transform:translateY(-1px)}' +
        '#' + W + '-cat-confirm-back{display:block;width:100%;max-width:320px;padding:12px;border:2px solid #e2e8f0;border-radius:12px;background:#fff;color:#64748b;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;transition:all .2s;margin-top:10px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '#' + W + '-cat-confirm-back:hover{border-color:#cbd5e1;background:#f8fafc}' +

        /* Transition screen */
        '#' + W + '-cat-transition{display:none;position:absolute;top:0;left:0;right:0;bottom:0;z-index:6;background:#fff;flex-direction:column;align-items:center;justify-content:center;padding:32px 24px;text-align:center}' +
        '#' + W + '-cat-transition.active{display:flex}' +
        '#' + W + '-cat-transition-icon{width:80px;height:80px;border-radius:50%;background:' + config.color + '12;display:flex;align-items:center;justify-content:center;margin-bottom:20px}' +
        '#' + W + '-cat-transition-icon svg{width:40px;height:40px;color:' + config.color + '}' +
        '#' + W + '-cat-transition h3{font-size:20px;font-weight:700;color:#0f172a;margin:0 0 12px}' +
        '#' + W + '-cat-transition p{font-size:15px;color:#475569;margin:0 0 28px;line-height:1.6;max-width:360px}' +
        '#' + W + '-cat-transition-btn{display:block;width:100%;max-width:320px;padding:16px;border:none;border-radius:12px;background:' + grad + ';color:#fff;font-size:16px;font-weight:700;font-family:inherit;cursor:pointer;transition:all .2s;box-shadow:0 4px 16px ' + config.color + '30;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '#' + W + '-cat-transition-btn:hover{opacity:.9;transform:translateY(-1px)}' +

        /* Embedded form iframe */
        '#' + W + '-cat-iframe-wrap{display:none;position:absolute;top:0;left:0;right:0;bottom:0;z-index:7;background:#fff;flex-direction:column}' +
        '#' + W + '-cat-iframe-wrap.active{display:flex}' +
        '#' + W + '-cat-iframe{flex:1;border:none;width:100%;height:100%;background:#f8fafc}' +
        '#' + W + '-cat-back-bar{display:flex;align-items:center;gap:8px;padding:10px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-shrink:0;cursor:pointer;min-height:44px;-webkit-tap-highlight-color:transparent;touch-action:manipulation}' +
        '#' + W + '-cat-back-bar:hover{background:#f1f5f9}' +
        '#' + W + '-cat-back-bar svg{width:18px;height:18px;color:#64748b}' +
        '#' + W + '-cat-back-bar span{font-size:14px;font-weight:600;color:#64748b}' +

        /* No results */
        '#' + W + '-cat-empty{padding:40px 20px;text-align:center;color:#94a3b8;font-size:14px;display:none}' +
        '#' + W + '-cat-empty svg{width:48px;height:48px;margin:0 auto 12px;color:#cbd5e1}' +

        /* Loading */
        '#' + W + '-cat-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;gap:16px}' +
        '#' + W + '-cat-loading .' + W + '-spinner{width:40px;height:40px}' +

        /* Desktop panel for catalog – right side sheet */
        '@media(min-width:769px){' +
            '#' + W + '-panel.catalog-panel{top:0;left:auto;right:0;bottom:0;width:420px;max-width:100%;border-radius:0;box-shadow:-4px 0 24px rgba(0,0,0,.12);transform:translateX(100%)}' +
            '#' + W + '-panel.catalog-panel.open{transform:translateX(0)}' +
        '}' +
        '@media(max-width:768px){' +
            '#' + W + '-cat-row{padding-left:16px}' +
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

    // ─── CATALOG MODE ────────────────────────────────────────
    if (config.mode === 'catalog') {
        var listSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
        var chevronSVG = '<svg class="' + W + '-cat-hdr-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';

        // Create FAB
        var catFab = document.createElement('button');
        catFab.id = W + '-fab';
        catFab.innerHTML = listSVG + '<span>' + fabText + '</span>';
        document.body.appendChild(catFab);

        // Create Panel (fullscreen mobile, side-sheet desktop)
        var catPanel = document.createElement('div');
        catPanel.id = W + '-panel';
        catPanel.className = 'catalog-panel';
        var arrowBackSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>';
        var clipboardSVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>';

        catPanel.innerHTML =
            '<div id="' + W + '-hdr">' +
                '<div><div id="' + W + '-hdr-t">' + lang.cat_title + '<span id="' + W + '-cat-count"></span></div>' +
                '<div id="' + W + '-hdr-s">' + lang.cat_subtitle + '</div></div>' +
                '<button id="' + W + '-cls" aria-label="' + lang.close + '">&times;</button>' +
            '</div>' +
            '<div style="position:relative;flex:1;display:flex;flex-direction:column;overflow:hidden">' +
                '<div id="' + W + '-cat-search-wrap">' +
                    '<input type="text" id="' + W + '-cat-search" placeholder="' + lang.cat_search + '" autocomplete="off">' +
                '</div>' +
                '<div id="' + W + '-cat-body">' +
                    '<div id="' + W + '-cat-loading"><div class="' + W + '-spinner"></div><div style="font-size:14px;color:#64748b">' + lang.cat_title + '...</div></div>' +
                '</div>' +
                /* Confirm overlay */
                '<div id="' + W + '-cat-confirm">' +
                    '<div id="' + W + '-cat-confirm-icon">' + wrenchSVG + '</div>' +
                    '<div id="' + W + '-cat-confirm-svc"></div>' +
                    '<div id="' + W + '-cat-confirm-meta"></div>' +
                    '<p id="' + W + '-cat-confirm-q">' + lang.cat_confirm_text + '</p>' +
                    '<button type="button" id="' + W + '-cat-confirm-yes">' + lang.cat_confirm_yes + ' \u2192</button>' +
                    '<button type="button" id="' + W + '-cat-confirm-back">' + lang.cat_confirm_no + '</button>' +
                '</div>' +
                /* Transition screen */
                '<div id="' + W + '-cat-transition">' +
                    '<div id="' + W + '-cat-transition-icon">' + clipboardSVG + '</div>' +
                    '<h3>' + lang.cat_transition_title + '</h3>' +
                    '<p>' + lang.cat_transition_text + '</p>' +
                    '<button type="button" id="' + W + '-cat-transition-btn">' + lang.cat_transition_btn + ' \u2192</button>' +
                '</div>' +
                /* Embedded form iframe */
                '<div id="' + W + '-cat-iframe-wrap">' +
                    '<div id="' + W + '-cat-back-bar">' +
                        arrowBackSVG + '<span>' + lang.cat_confirm_no + '</span>' +
                    '</div>' +
                    '<iframe id="' + W + '-cat-iframe" src="about:blank" loading="lazy"></iframe>' +
                '</div>' +
            '</div>' +
            '<div id="' + W + '-ftr">' + buildFooter() + '</div>';
        document.body.appendChild(catPanel);

        var catOpen = false;
        var catLoaded = false;
        var catScrollY = 0;
        var allServices = [];
        var catBody = catPanel.querySelector('#' + W + '-cat-body');
        var searchInput = catPanel.querySelector('#' + W + '-cat-search');

        function catLock() {
            catScrollY = window.pageYOffset;
            document.body.classList.add(W + '-noscroll');
            document.body.style.top = '-' + catScrollY + 'px';
        }
        function catUnlock() {
            document.body.classList.remove(W + '-noscroll');
            document.body.style.top = '';
            window.scrollTo(0, catScrollY);
        }

        catFab.addEventListener('click', function(e) {
            e.preventDefault();
            catOpen = !catOpen;
            catPanel.classList.toggle('open', catOpen);
            if (catOpen) {
                catLock();
                if (!catLoaded) { catLoaded = true; loadCatalog(); }
            } else { catUnlock(); }
        });

        catPanel.querySelector('#' + W + '-cls').addEventListener('click', function() {
            catOpen = false;
            catPanel.classList.remove('open');
            catUnlock();
        });

        // Category icon mapping
        var catIcons = {
            'display': '\uD83D\uDCF1', 'akku': '\uD83D\uDD0B', 'batterie': '\uD83D\uDD0B', 'battery': '\uD83D\uDD0B',
            'kamera': '\uD83D\uDCF7', 'camera': '\uD83D\uDCF7', 'wasser': '\uD83D\uDCA7', 'water': '\uD83D\uDCA7',
            'ladebuchse': '\uD83D\uDD0C', 'charging': '\uD83D\uDD0C', 'lautsprecher': '\uD83D\uDD0A', 'speaker': '\uD83D\uDD0A',
            'software': '\uD83D\uDCBB', 'glas': '\uD83D\uDD0D', 'backcover': '\uD83D\uDEE1', 'r\u00fcckseite': '\uD83D\uDEE1',
            'konsole': '\uD83C\uDFAE', 'console': '\uD83C\uDFAE', 'laptop': '\uD83D\uDCBB', 'tablet': '\uD83D\uDCF2',
            'daten': '\uD83D\uDCBE', 'data': '\uD83D\uDCBE', 'platine': '\u2699\uFE0F', 'board': '\u2699\uFE0F',
            'default': '\uD83D\uDD27'
        };

        function getCatIcon(catName) {
            var lower = (catName || '').toLowerCase();
            for (var key in catIcons) {
                if (key !== 'default' && lower.indexOf(key) !== -1) return catIcons[key];
            }
            return catIcons['default'];
        }

        function escH(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        // Check if store is currently open
        function isStoreOpen(hours) {
            if (!hours) return null;
            var now = new Date();
            var dayMap = ['so','mo','di','mi','do','fr','sa'];
            var todayKey = dayMap[now.getDay()];
            var day = hours[todayKey];
            if (!day || day.closed) return false;
            var currentMin = now.getHours() * 60 + now.getMinutes();
            var fromParts = (day.von || '').split(':');
            var toParts = (day.bis || '').split(':');
            if (fromParts.length < 2 || toParts.length < 2) return null;
            var fromMin = parseInt(fromParts[0]) * 60 + parseInt(fromParts[1]);
            var toMin = parseInt(toParts[0]) * 60 + parseInt(toParts[1]);
            return currentMin >= fromMin && currentMin <= toMin;
        }

        // Build opening hours HTML
        function buildHoursHTML(hours) {
            if (!hours) return '';
            var dayKeys = ['mo','di','mi','do','fr','sa','so'];
            var dayNames = lang.cat_days || ['Mo','Di','Mi','Do','Fr','Sa','So'];
            var now = new Date();
            var todayIdx = (now.getDay() + 6) % 7; // Monday=0
            var html = '';
            for (var i = 0; i < dayKeys.length; i++) {
                var d = hours[dayKeys[i]];
                var isClosed = !d || d.closed || (!d.von && !d.bis);
                var timeStr = isClosed ? lang.cat_closed : (d.von + ' \u2013 ' + d.bis);
                var cls = W + '-cat-day' + (i === todayIdx ? ' today' : '');
                html += '<div class="' + cls + '">' +
                    '<span class="' + W + '-cat-day-name">' + dayNames[i] + '</span>' +
                    '<span class="' + W + '-cat-day-time">' + (isClosed ? '<span style="color:#94a3b8">' + timeStr + '</span>' : timeStr) + '</span>' +
                '</div>';
            }
            return html;
        }

        // Build contact HTML
        function buildContactHTML(data) {
            var html = '';
            if (data.store_address) {
                var mapUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(data.store_address);
                if (data.store_lat && data.store_lng) {
                    mapUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + data.store_lat + ',' + data.store_lng;
                }
                html += '<a class="' + W + '-cat-contact-row" href="' + mapUrl + '" target="_blank" rel="noopener">' +
                    '<span class="' + W + '-cat-contact-icon">\uD83D\uDCCD</span>' +
                    '<span>' + escH(data.store_address) + '</span></a>';
            }
            if (data.store_phone) {
                html += '<a class="' + W + '-cat-contact-row" href="tel:' + escH(data.store_phone) + '">' +
                    '<span class="' + W + '-cat-contact-icon">\uD83D\uDCDE</span>' +
                    '<span>' + escH(data.store_phone) + '</span></a>';
            }
            if (data.store_whatsapp) {
                var waNum = data.store_whatsapp.replace(/[^0-9]/g, '');
                html += '<a class="' + W + '-cat-contact-row" href="https://wa.me/' + waNum + '" target="_blank" rel="noopener">' +
                    '<span class="' + W + '-cat-contact-icon">\uD83D\uDCAC</span>' +
                    '<span>WhatsApp</span></a>';
            }
            if (data.store_website) {
                html += '<a class="' + W + '-cat-contact-row" href="' + escH(data.store_website) + '" target="_blank" rel="noopener">' +
                    '<span class="' + W + '-cat-contact-icon">\uD83C\uDF10</span>' +
                    '<span>' + escH(data.store_website.replace(/^https?:\/\//, '')) + '</span></a>';
            }
            return html;
        }

        // Group services by category
        function groupServices(services) {
            var groups = {};
            var order = [];
            for (var i = 0; i < services.length; i++) {
                var svc = services[i];
                var cat = svc.category || lang.cat_general;
                if (!groups[cat]) { groups[cat] = []; order.push(cat); }
                groups[cat].push(svc);
            }
            return { groups: groups, order: order };
        }

        // Render catalog content
        function renderCatalog(data) {
            allServices = data.services || [];
            var grouped = groupServices(allServices);
            var totalCount = allServices.length;

            // Update header count
            var countBadge = catPanel.querySelector('#' + W + '-cat-count');
            if (countBadge) countBadge.textContent = totalCount;

            // Update header with store name if available
            if (data.store_name) {
                var hdrTitle = catPanel.querySelector('#' + W + '-hdr-t');
                if (hdrTitle) hdrTitle.innerHTML = escH(data.store_name) + '<span id="' + W + '-cat-count">' + totalCount + '</span>';
            }

            var html = '';

            // Service categories
            html += '<div id="' + W + '-cat-sections">';
            for (var ci = 0; ci < grouped.order.length; ci++) {
                var catName = grouped.order[ci];
                var items = grouped.groups[catName];
                var isFirst = ci === 0;
                html += '<div class="' + W + '-cat-section' + (isFirst ? ' open' : '') + '" data-cat="' + escH(catName) + '">' +
                    '<div class="' + W + '-cat-hdr">' +
                        '<div class="' + W + '-cat-hdr-left">' +
                            '<span class="' + W + '-cat-hdr-icon">' + getCatIcon(catName) + '</span>' +
                            '<span class="' + W + '-cat-hdr-name">' + escH(catName) + '</span>' +
                        '</div>' +
                        '<div class="' + W + '-cat-hdr-right">' +
                            '<span class="' + W + '-cat-hdr-cnt">' + items.length + '</span>' +
                            chevronSVG +
                        '</div>' +
                    '</div>' +
                    '<div class="' + W + '-cat-items">';
                for (var si = 0; si < items.length; si++) {
                    var svc = items[si];
                    // Find original index in allServices
                    var origIdx = allServices.indexOf(svc);
                    html += '<div class="' + W + '-cat-row" data-svc-idx="' + origIdx + '" data-search="' + escH((svc.name || '').toLowerCase() + ' ' + (catName).toLowerCase()) + '">' +
                        '<span class="' + W + '-cat-name">' + escH(svc.name || '') + '</span>' +
                        '<span class="' + W + '-cat-meta">' +
                            (svc.time ? '<span class="' + W + '-cat-time">\u23F1 ' + escH(svc.time) + '</span>' : '') +
                            (svc.price ? '<span class="' + W + '-cat-price">' + escH(svc.price) + '</span>' : '') +
                        '</span>' +
                        '<svg class="' + W + '-cat-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>' +
                    '</div>';
                }
                html += '</div></div>';
            }
            html += '</div>';

            // No results placeholder with CTA
            html += '<div id="' + W + '-cat-empty">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>' +
                '<div style="font-weight:600;margin-bottom:6px">' + lang.cat_no_results + '</div>' +
                '<div style="font-size:13px;color:#64748b;line-height:1.5;margin-bottom:14px">' + lang.cat_no_results_hint + '</div>' +
                '<button id="' + W + '-cat-empty-cta" style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:' + config.color + ';color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px ' + config.color + '40;transition:transform .15s,box-shadow .15s;font-family:inherit">' +
                    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>' +
                    lang.cat_cta +
                '</button>' +
            '</div>';

            // Opening hours
            if (data.store_hours) {
                var openStatus = isStoreOpen(data.store_hours);
                var badgeCls = openStatus === true ? W + '-cat-badge-open' : W + '-cat-badge-closed';
                var badgeText = openStatus === true ? lang.cat_open : lang.cat_closed;
                html += '<div id="' + W + '-cat-hours">' +
                    '<div id="' + W + '-cat-hours-title">' +
                        '\uD83D\uDD52 ' + lang.cat_hours +
                        (openStatus !== null ? ' <span class="' + W + '-cat-badge ' + badgeCls + '"><span class="' + W + '-cat-badge-dot"></span> ' + badgeText + '</span>' : '') +
                    '</div>' +
                    buildHoursHTML(data.store_hours) +
                '</div>';
            }

            // Contact
            var contactHTML = buildContactHTML(data);
            if (contactHTML) {
                html += '<div id="' + W + '-cat-contact">' +
                    '<div id="' + W + '-cat-contact-title">\uD83D\uDCCD ' + lang.cat_contact + '</div>' +
                    contactHTML +
                '</div>';
            }

            catBody.innerHTML = html;

            // Bind accordion toggles
            var sections = catBody.querySelectorAll('.' + W + '-cat-hdr');
            for (var hi = 0; hi < sections.length; hi++) {
                sections[hi].addEventListener('click', function() {
                    this.parentElement.classList.toggle('open');
                });
            }

            // Bind service row clicks → confirm flow
            var svcRows = catBody.querySelectorAll('.' + W + '-cat-row');
            for (var ri = 0; ri < svcRows.length; ri++) {
                svcRows[ri].addEventListener('click', function() {
                    var svcIdx = parseInt(this.getAttribute('data-svc-idx'));
                    if (!isNaN(svcIdx) && allServices[svcIdx]) {
                        showConfirm(allServices[svcIdx]);
                    }
                });
            }

            // No-results CTA → open form directly (no service pre-selected)
            var emptyCta = catBody.querySelector('#' + W + '-cat-empty-cta');
            if (emptyCta) {
                emptyCta.addEventListener('click', function() {
                    selectedSvc = null;
                    showForm();
                });
            }
        }

        // ── Confirm / Transition / Form flow ──
        var confirmEl = catPanel.querySelector('#' + W + '-cat-confirm');
        var confirmSvc = catPanel.querySelector('#' + W + '-cat-confirm-svc');
        var confirmMeta = catPanel.querySelector('#' + W + '-cat-confirm-meta');
        var confirmYes = catPanel.querySelector('#' + W + '-cat-confirm-yes');
        var confirmBack = catPanel.querySelector('#' + W + '-cat-confirm-back');
        var transitionEl = catPanel.querySelector('#' + W + '-cat-transition');
        var transitionBtn = catPanel.querySelector('#' + W + '-cat-transition-btn');
        var iframeWrapEl = catPanel.querySelector('#' + W + '-cat-iframe-wrap');
        var iframeEl = catPanel.querySelector('#' + W + '-cat-iframe');
        var backBarEl = catPanel.querySelector('#' + W + '-cat-back-bar');
        var selectedSvc = null;

        function showConfirm(svc) {
            selectedSvc = svc;
            confirmSvc.textContent = svc.name || '';
            var metaHTML = '';
            if (svc.price) metaHTML += '<span>\uD83D\uDCB0 ' + escH(svc.price) + '</span>';
            if (svc.time) metaHTML += '<span>\u23F1 ' + escH(svc.time) + '</span>';
            confirmMeta.innerHTML = metaHTML;
            confirmEl.classList.add('active');
        }

        function hideConfirm() {
            confirmEl.classList.remove('active');
            selectedSvc = null;
        }

        function showTransition() {
            confirmEl.classList.remove('active');
            transitionEl.classList.add('active');
        }

        function hideTransition() {
            transitionEl.classList.remove('active');
        }

        function showForm() {
            transitionEl.classList.remove('active');
            var url = FORM_URL + '?embed=1';
            if (selectedSvc) {
                if (selectedSvc.name) url += '&problem=' + encodeURIComponent(selectedSvc.name);
                if (selectedSvc.category) url += '&category=' + encodeURIComponent(selectedSvc.category);
            }
            iframeEl.src = url;
            iframeWrapEl.classList.add('active');
        }

        function hideForm() {
            iframeWrapEl.classList.remove('active');
            iframeEl.src = 'about:blank';
        }

        function resetFlow() {
            hideConfirm();
            hideTransition();
            hideForm();
        }

        confirmYes.addEventListener('click', showTransition);
        confirmBack.addEventListener('click', hideConfirm);
        transitionBtn.addEventListener('click', showForm);
        backBarEl.addEventListener('click', function() {
            resetFlow();
        });

        // Search/filter
        var searchTimer = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            var val = this.value.trim().toLowerCase();
            searchTimer = setTimeout(function() {
                var sectionsEl = catBody.querySelectorAll('.' + W + '-cat-section');
                var emptyEl = catBody.querySelector('#' + W + '-cat-empty');
                var hoursEl = catBody.querySelector('#' + W + '-cat-hours');
                var contactEl = catBody.querySelector('#' + W + '-cat-contact');
                var anyVisible = false;

                for (var si = 0; si < sectionsEl.length; si++) {
                    var sec = sectionsEl[si];
                    var rows = sec.querySelectorAll('.' + W + '-cat-row');
                    var visibleInSection = 0;
                    for (var ri = 0; ri < rows.length; ri++) {
                        var searchData = rows[ri].getAttribute('data-search') || '';
                        var match = !val || searchData.indexOf(val) !== -1;
                        rows[ri].style.display = match ? '' : 'none';
                        if (match) visibleInSection++;
                    }
                    sec.style.display = visibleInSection > 0 ? '' : 'none';
                    if (visibleInSection > 0) {
                        anyVisible = true;
                        // Update count badge
                        var cnt = sec.querySelector('.' + W + '-cat-hdr-cnt');
                        if (cnt) cnt.textContent = visibleInSection;
                        // Auto-expand on search
                        if (val) sec.classList.add('open');
                    }
                }

                if (emptyEl) emptyEl.style.display = anyVisible ? 'none' : 'block';
                // Hide hours/contact during search
                if (hoursEl) hoursEl.style.display = val ? 'none' : '';
                if (contactEl) contactEl.style.display = val ? 'none' : '';
            }, 150);
        });

        // Load catalog data from server
        function loadCatalog() {
            var fd = new FormData();
            fd.append('action', 'ppv_shop_widget_config');
            fd.append('store_slug', config.store);
            fetch(AJAX_URL, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    if (resp.success && resp.data) {
                        renderCatalog(resp.data);
                    } else {
                        catBody.innerHTML = '<div style="padding:40px 20px;text-align:center;color:#94a3b8">' +
                            '<div style="font-size:32px;margin-bottom:8px">\u26A0\uFE0F</div>' +
                            '<div>Store not found</div></div>';
                    }
                })
                .catch(function() {
                    catBody.innerHTML = '<div style="padding:40px 20px;text-align:center;color:#94a3b8">' +
                        '<div style="font-size:32px;margin-bottom:8px">\u26A0\uFE0F</div>' +
                        '<div>Connection error</div></div>';
                });
        }
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
