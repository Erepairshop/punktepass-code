[2025-11-23 11:57:02] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[23-Nov-2025 11:57:02 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[23-Nov-2025 11:57:02 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:02 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:02 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:02 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:02 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:02 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:02 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:02 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:02 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:02 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:02 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:02 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:02 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:02] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:02 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:02 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:02 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:02 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[23-Nov-2025 11:57:02 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[23-Nov-2025 11:57:02 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[23-Nov-2025 11:57:02 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:02 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:02 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:02 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:02 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/pos/campaigns
[23-Nov-2025 11:57:02 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:02 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:02 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:02 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:02 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:02 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:02] 🧠 REST CALL → /punktepass/v1/pos/campaigns | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:02 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:02 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:02 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:02 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:02 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:02)
---------------------------------------------------
[2025-11-23 11:57:28] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[23-Nov-2025 11:57:28 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[23-Nov-2025 11:57:28 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:28 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:28 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:28 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:28 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:28 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:28 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:28 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:28 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:28 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:28 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:28 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:28 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:28] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:28 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:28 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:28 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:28 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[23-Nov-2025 11:57:28 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[23-Nov-2025 11:57:28 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[23-Nov-2025 11:57:28 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:28 UTC] ✅ Stripe Checkout REST route registered
[2025-11-23 11:57:28] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:28 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:28 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:28 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/pos/campaigns
[23-Nov-2025 11:57:28 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:28 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:28 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:28 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:28 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:28 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:28] 🧠 REST CALL → /punktepass/v1/pos/campaigns | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:28 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:28 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:28 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:28 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:28 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:28)
---------------------------------------------------
[23-Nov-2025 11:57:29 UTC] 🌍 [PPV_Lang] Geo fallback → de
[23-Nov-2025 11:57:29 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"doing_wp_cron":"1763899048.6211171150207519531250"} | COOKIE=- | SESSION=-
[23-Nov-2025 11:57:29 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:29 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"doing_wp_cron":"1763899048.6211171150207519531250"} | HEADER=- | COOKIE=- | SESSION=-
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:29 UTC] 🎨 [PPV_Theme_Handler] Using default theme: light
[23-Nov-2025 11:57:29 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] Current session_id: NO SESSION
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] ppv_user_id in session: EMPTY
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] ppv_user_token cookie: MISSING
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=missing, ppv_user_id=empty
[2025-11-23 11:57:29] 🧩 SESSION STATE → {"user_id":0,"store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:29 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:29 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized

📊 DAILY SUMMARY (2025-11-23 11:57:29)
---------------------------------------------------
[2025-11-23 11:57:29] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:29 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:29 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:29 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:29 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:29 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:29 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:29 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:29 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:29 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:29 UTC] 🔄 [AutoMode] HANDLER PAGE → type=store, POS ON (store=9)
[2025-11-23 11:57:29] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:29 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:29 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Session] Token priority active → Store=9
[23-Nov-2025 11:57:29 UTC] 📈 [Stats] enqueue_assets() called
[23-Nov-2025 11:57:29 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:29 UTC] ✅ [Stats] Store from GLOBALS: 9
[23-Nov-2025 11:57:29 UTC] 🌐 [Stats] Translations loaded from PPV_Lang: 953 strings
[23-Nov-2025 11:57:29 UTC] 📊 [Stats] JS Data: store_id=9, lang=de, translations=953
[23-Nov-2025 11:57:29 UTC] ⏸️ [PPV_Redeem_Admin] JS übersprungen – Seite ohne [ppv_rewards]
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points::enqueue_assets] ========== START ==========
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points] Current URL: /qr-center/
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points] User Agent: Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points] Session already active
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points] Lang from GET: EMPTY
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points] Lang from COOKIE: de
[23-Nov-2025 11:57:29 UTC] 🌍 [PPV_My_Points] Active language: de
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points] Global data prepared:
[23-Nov-2025 11:57:29 UTC]     - ajaxurl: https://punktepass.de/wp-admin/admin-ajax.php
[23-Nov-2025 11:57:29 UTC]     - api_url: https://punktepass.de/wp-json/ppv/v1/mypoints
[23-Nov-2025 11:57:29 UTC]     - lang: de
[23-Nov-2025 11:57:29 UTC]     - nonce: acccdef544...
[23-Nov-2025 11:57:29 UTC] ⚠️ [PPV_My_Points] Lang file not found: /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/languages/lang-de-MY-POINTS-ONLY.php
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points] Language strings loaded: 0 keys
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_My_Points] Inline scripts added, lang=de, strings=0
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_My_Points::enqueue_assets] ========== END ==========
[23-Nov-2025 11:57:29 UTC] 🌍 [PPV_Belohnungen] Active language: de
[23-Nov-2025 11:57:29 UTC] 🌍 [PPV_User_Settings] Active language: de
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_BRIDGE] Meglévő token betöltve user=10
[23-Nov-2025 11:57:29 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_Header] === USER TYPE DETECTION START === User ID: 10
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Header] User type from SESSION: 'store'
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_Header] === DETECTION RESULT ===
[23-Nov-2025 11:57:29 UTC]    User ID: 10
[23-Nov-2025 11:57:29 UTC]    Raw type: 'store'
[23-Nov-2025 11:57:29 UTC]    Clean type: 'store'
[23-Nov-2025 11:57:29 UTC]    Source: session
[23-Nov-2025 11:57:29 UTC]    Is Handler: YES ✅
[23-Nov-2025 11:57:29 UTC]    Valid handler types: store, handler, vendor, admin, scanner
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Header] WILL RENDER: User Dashboard + Scanner buttons
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_Header] === DETECTION END ===
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_Header] === USER TYPE DETECTION START === User ID: 10
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Header] User type from SESSION: 'store'
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_Header] === DETECTION RESULT ===
[23-Nov-2025 11:57:29 UTC]    User ID: 10
[23-Nov-2025 11:57:29 UTC]    Raw type: 'store'
[23-Nov-2025 11:57:29 UTC]    Clean type: 'store'
[23-Nov-2025 11:57:29 UTC]    Source: session
[23-Nov-2025 11:57:29 UTC]    Is Handler: YES ✅
[23-Nov-2025 11:57:29 UTC]    Valid handler types: store, handler, vendor, admin, scanner
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Header] WILL RENDER: User Dashboard + Scanner buttons
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_Header] === DETECTION END ===
[23-Nov-2025 11:57:29 UTC] 🔍 [QR_CENTER] SESSION CHECK: {"ppv_user_id":"10","ppv_user_type":"store","ppv_store_id":9,"ppv_vendor_store_id":"9","ppv_current_filiale_id":"NOT_SET"}
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:29 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:29 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:29 UTC] ✅ [QR_CENTER] check_handler() PASSED
[23-Nov-2025 11:57:29 UTC] 🏪 [PPV_QR] render_filiale_switcher: current_filiale_id=9

📊 DAILY SUMMARY (2025-11-23 11:57:29)
---------------------------------------------------
[2025-11-23 11:57:29] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:30] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[23-Nov-2025 11:57:30 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[2025-11-23 11:57:30] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:30] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:30] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:30] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:30 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:30 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:30 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:30 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:30 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:30 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:30 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:30 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:30 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:30 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:30 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:30 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:30 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:30] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:30 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:30 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:30 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:30 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:30 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:30 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:30] 🧠 REST CALL → /ppv/v1/vip/settings | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:30 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:30 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:30 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:30 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:30)
---------------------------------------------------
[2025-11-23 11:57:30] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[23-Nov-2025 11:57:30 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[23-Nov-2025 11:57:30 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:30 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:30 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:30 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:30 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:30 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:30 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:30 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:30 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:30 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:30 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:30 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:30 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:30] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:30 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:30 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:30 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:30 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[23-Nov-2025 11:57:30 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[23-Nov-2025 11:57:30 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[23-Nov-2025 11:57:30 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:30 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:30 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:30 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:30 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/pos/campaigns
[23-Nov-2025 11:57:30 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:30 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:30 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:30 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:30 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:30 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:30] 🧠 REST CALL → /punktepass/v1/pos/campaigns | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:30 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:30 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:30 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:30 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:30 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:31)
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"_":"1763899048718"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"_":"1763899048718"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:31 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:31 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:31] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:31 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:31 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/stats/conversion?_=1763899048718
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:31 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:31] 🧠 REST CALL → /punktepass/v1/stats/conversion | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Stats Perm] OK - store=9
[23-Nov-2025 11:57:31 UTC] 📊 [Conversion] Start
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Conversion] Complete
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Stats Perm] OK - store=9

📊 DAILY SUMMARY (2025-11-23 11:57:31)
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"_":"1763899048717"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"_":"1763899048717"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:31 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:31 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[2025-11-23 11:57:31] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:31] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:31 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:31 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/stats/spending?_=1763899048717
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:31 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:31] 🧠 REST CALL → /punktepass/v1/stats/spending | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Stats Perm] OK - store=9
[23-Nov-2025 11:57:31 UTC] 💰 [Spending] Start
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Spending] Complete
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Stats Perm] OK - store=9

📊 DAILY SUMMARY (2025-11-23 11:57:31)
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"range":"week","_":"1763899048715"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"range":"week","_":"1763899048715"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:31 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:31 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:31] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:31 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:31 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/stats?range=week&_=1763899048715
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:31 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:31] 🧠 REST CALL → /punktepass/v1/stats | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Stats Perm] OK - store=9
[23-Nov-2025 11:57:31 UTC] 📊 [REST] stats() called
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [REST] stats() complete
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Stats Perm] OK - store=9

📊 DAILY SUMMARY (2025-11-23 11:57:31)
---------------------------------------------------
[2025-11-23 11:57:31] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🌍 [PPV_Lang] Geo fallback → de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"doing_wp_cron":"1763899051.0743539333343505859375"} | COOKIE=- | SESSION=-
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"doing_wp_cron":"1763899051.0743539333343505859375"} | HEADER=- | COOKIE=- | SESSION=-
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler] Using default theme: light
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:31 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:31 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] Current session_id: NO SESSION
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_user_id in session: EMPTY
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_user_token cookie: MISSING
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=missing, ppv_user_id=empty
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:31 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[2025-11-23 11:57:31] 🧩 SESSION STATE → {"user_id":0,"store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:31] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:31 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[23-Nov-2025 11:57:31 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[23-Nov-2025 11:57:31 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[23-Nov-2025 11:57:31 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:31 UTC] ✅ Stripe Checkout REST route registered

📊 DAILY SUMMARY (2025-11-23 11:57:31)
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/pos/logs
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:31 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:31] 🧠 REST CALL → /punktepass/v1/pos/logs | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:31 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:31 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:31 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:31)
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"_":"1763899048716"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"_":"1763899048716"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:31 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:31 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:31] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:31 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:31 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/stats/trend?_=1763899048716
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:31 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:31 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:31] 🧠 REST CALL → /punktepass/v1/stats/trend | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Stats Perm] OK - store=9
[23-Nov-2025 11:57:31 UTC] 📈 [Trend] Start
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Trend] Complete
[23-Nov-2025 11:57:31 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:31 UTC] ✅ [Stats] Store from SESSION (store): 9
[23-Nov-2025 11:57:31 UTC] ✅ [Stats Perm] OK - store=9

📊 DAILY SUMMARY (2025-11-23 11:57:31)
---------------------------------------------------
[2025-11-23 11:57:31] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:31] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] 🔁 [PPV_Session] switched store → 9
[23-Nov-2025 11:57:31 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:31 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:31 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:31 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:31 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:31 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:31] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:31 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:31 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:31 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:31 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:31 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:32 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:32] 🧠 REST CALL → /ppv/v1/vip/settings | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:32 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:32 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:32 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:32 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:32)
---------------------------------------------------
[23-Nov-2025 11:57:32 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:32 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"store_id":"9"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:32 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:32 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"store_id":"9"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:32 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:32 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:32 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:32 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:32 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:32] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:32 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:32 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:32 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:32 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:32 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:32 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:32] 🧠 REST CALL → /ppv/v1/rewards/list | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:32 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:32 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:32 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:32 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:32)
---------------------------------------------------
[23-Nov-2025 11:57:32 UTC] 🌍 [PPV_Lang] Geo fallback → de
[23-Nov-2025 11:57:32 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"doing_wp_cron":"1763899051.6663780212402343750000"} | COOKIE=- | SESSION=-
[23-Nov-2025 11:57:32 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:32 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"doing_wp_cron":"1763899051.6663780212402343750000"} | HEADER=- | COOKIE=- | SESSION=-
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:32 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:32 UTC] 🎨 [PPV_Theme_Handler] Using default theme: light
[23-Nov-2025 11:57:32 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] Current session_id: NO SESSION
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] ppv_user_id in session: EMPTY
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] ppv_user_token cookie: MISSING
[23-Nov-2025 11:57:32 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=missing, ppv_user_id=empty
[2025-11-23 11:57:32] 🧩 SESSION STATE → {"user_id":0,"store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:32 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:32 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized

📊 DAILY SUMMARY (2025-11-23 11:57:32)
---------------------------------------------------
[2025-11-23 11:57:33] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:33 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[2025-11-23 11:57:33] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:33 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:33 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:33 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:33 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:33 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:33 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:33 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:33 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:33] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:33 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:33 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:33 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:33 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:33 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:33 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:33] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:33)
---------------------------------------------------
[23-Nov-2025 11:57:33 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:33 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:33 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:33 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:33 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:33 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:33 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:33 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:33 UTC] ⚠️ [PPV_SessionBridge] Token not found
[23-Nov-2025 11:57:33 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:33] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:33 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:33 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:33 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:33 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:33 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:33 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:33] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:33 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:33)
---------------------------------------------------
[2025-11-23 11:57:38] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:38 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[23-Nov-2025 11:57:38 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:38 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:38 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:38 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:38 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:38 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:38 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:38 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:38] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:38 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:38 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:38 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:38 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:38 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:38 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:38] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:38)
---------------------------------------------------
[2025-11-23 11:57:38] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:38 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[23-Nov-2025 11:57:38 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:38 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:38 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:38 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:38 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:38 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:38 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:38 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:38 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:38] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:38 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:38 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:38 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:38 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:38 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:38 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:38] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:38 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:38)
---------------------------------------------------
[2025-11-23 11:57:39] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:39 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[23-Nov-2025 11:57:39 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:39 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:39 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:39 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:39 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:39 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:39 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:39 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:39 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:39 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:39 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:39] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:39 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:39 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:39 UTC] ✅ [PPV_Session] Token priority active → Store=9

📊 DAILY SUMMARY (2025-11-23 11:57:39)
---------------------------------------------------
[2025-11-23 11:57:39] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:39 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:39 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:39 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:39 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:39 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:39 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:39 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:39 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:39 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:39 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:39 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:39 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:39] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:40 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:40 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:40 UTC] ✅ [PPV_Session] Token priority active → Store=9
[23-Nov-2025 11:57:40 UTC] 📈 [Stats] enqueue_assets() called
[23-Nov-2025 11:57:40 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:40 UTC] ✅ [Stats] Store from GLOBALS: 9
[23-Nov-2025 11:57:40 UTC] 🌐 [Stats] Translations loaded from PPV_Lang: 953 strings
[23-Nov-2025 11:57:40 UTC] 📊 [Stats] JS Data: store_id=9, lang=de, translations=953
[23-Nov-2025 11:57:40 UTC] ⏸️ [PPV_Redeem_Admin] JS übersprungen – Seite ohne [ppv_rewards]
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points::enqueue_assets] ========== START ==========
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points] Current URL: /user_dashboard/
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points] User Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points] Session already active
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points] Lang from GET: EMPTY
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points] Lang from COOKIE: de
[23-Nov-2025 11:57:40 UTC] 🌍 [PPV_My_Points] Active language: de
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points] Global data prepared:
[23-Nov-2025 11:57:40 UTC]     - ajaxurl: https://punktepass.de/wp-admin/admin-ajax.php
[23-Nov-2025 11:57:40 UTC]     - api_url: https://punktepass.de/wp-json/ppv/v1/mypoints
[23-Nov-2025 11:57:40 UTC]     - lang: de
[23-Nov-2025 11:57:40 UTC]     - nonce: acccdef544...
[23-Nov-2025 11:57:40 UTC] ⚠️ [PPV_My_Points] Lang file not found: /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/languages/lang-de-MY-POINTS-ONLY.php
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points] Language strings loaded: 0 keys
[23-Nov-2025 11:57:40 UTC] ✅ [PPV_My_Points] Inline scripts added, lang=de, strings=0
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_My_Points::enqueue_assets] ========== END ==========
[23-Nov-2025 11:57:40 UTC] 🌍 [PPV_Belohnungen] Active language: de
[23-Nov-2025 11:57:40 UTC] 🌍 [PPV_User_Settings] Active language: de
[23-Nov-2025 11:57:40 UTC] ✅ [PPV_BRIDGE] Meglévő token betöltve user=3
[23-Nov-2025 11:57:40 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_Header] === USER TYPE DETECTION START === User ID: 3
[23-Nov-2025 11:57:40 UTC] ✅ [PPV_Header] User type from SESSION: 'user'
[23-Nov-2025 11:57:40 UTC] ⚠️ [PPV_Header] Session says 'user', double-checking DB...
[23-Nov-2025 11:57:40 UTC] ℹ️ [PPV_Header] DB confirms: user type is 'user'
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_Header] === DETECTION RESULT ===
[23-Nov-2025 11:57:40 UTC]    User ID: 3
[23-Nov-2025 11:57:40 UTC]    Raw type: 'user'
[23-Nov-2025 11:57:40 UTC]    Clean type: 'user'
[23-Nov-2025 11:57:40 UTC]    Source: session
[23-Nov-2025 11:57:40 UTC]    Is Handler: NO ❌
[23-Nov-2025 11:57:40 UTC]    Valid handler types: store, handler, vendor, admin, scanner
[23-Nov-2025 11:57:40 UTC] ℹ️ [PPV_Header] WILL RENDER: Points + Rewards stats
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_Header] === DETECTION END ===
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_Header] === USER TYPE DETECTION START === User ID: 3
[23-Nov-2025 11:57:40 UTC] ✅ [PPV_Header] User type from SESSION: 'user'
[23-Nov-2025 11:57:40 UTC] ⚠️ [PPV_Header] Session says 'user', double-checking DB...
[23-Nov-2025 11:57:40 UTC] ℹ️ [PPV_Header] DB confirms: user type is 'user'
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_Header] === DETECTION RESULT ===
[23-Nov-2025 11:57:40 UTC]    User ID: 3
[23-Nov-2025 11:57:40 UTC]    Raw type: 'user'
[23-Nov-2025 11:57:40 UTC]    Clean type: 'user'
[23-Nov-2025 11:57:40 UTC]    Source: session
[23-Nov-2025 11:57:40 UTC]    Is Handler: NO ❌
[23-Nov-2025 11:57:40 UTC]    Valid handler types: store, handler, vendor, admin, scanner
[23-Nov-2025 11:57:40 UTC] ℹ️ [PPV_Header] WILL RENDER: Points + Rewards stats
[23-Nov-2025 11:57:40 UTC] 🔍 [PPV_Header] === DETECTION END ===

📊 DAILY SUMMARY (2025-11-23 11:57:40)
---------------------------------------------------
[2025-11-23 11:57:40] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:40] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:40] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:40] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:40 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:40 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"_":"1763899059841"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:40 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:40 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"_":"1763899059841"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:40 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:40 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:40 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:40 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:40 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:40 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:40 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:40 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:40 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:40 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:40 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:40 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:40 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:40] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:41 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:41 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:41 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/stats/trend?_=1763899059841
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Session ID: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 3
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:41 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=3
[2025-11-23 11:57:41] 🧠 REST CALL → /punktepass/v1/stats/trend | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:41 UTC] ❌ [Stats] NO STORE FOUND!
[23-Nov-2025 11:57:41 UTC] 🚫 [Stats Perm] DENIED - no store
[23-Nov-2025 11:57:41 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:41 UTC] ❌ [Stats] NO STORE FOUND!
[23-Nov-2025 11:57:41 UTC] 🚫 [Stats Perm] DENIED - no store

📊 DAILY SUMMARY (2025-11-23 11:57:41)
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"_":"1763899059842"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"_":"1763899059842"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:41 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:41 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:41 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:41 UTC] ⚠️ [PPV_SessionBridge] Token not found
[23-Nov-2025 11:57:41 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:41] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:41 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:41 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:41 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/stats/spending?_=1763899059842
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Session ID: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 3
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:41 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=3
[2025-11-23 11:57:41] 🧠 REST CALL → /punktepass/v1/stats/spending | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:41 UTC] ❌ [Stats] NO STORE FOUND!
[23-Nov-2025 11:57:41 UTC] 🚫 [Stats Perm] DENIED - no store
[23-Nov-2025 11:57:41 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:41 UTC] ❌ [Stats] NO STORE FOUND!
[23-Nov-2025 11:57:41 UTC] 🚫 [Stats Perm] DENIED - no store

📊 DAILY SUMMARY (2025-11-23 11:57:41)
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"lat":"48.58410414043182","lng":"10.430688960150697","max_distance":"10"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"lat":"48.58410414043182","lng":"10.430688960150697","max_distance":"10"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:41 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:41 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:41 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:41 UTC] ⚠️ [PPV_SessionBridge] Token not found
[2025-11-23 11:57:41] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:41 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:41 UTC] ✅ Stripe Checkout REST route registered
[2025-11-23 11:57:41] 🧠 REST CALL → /ppv/v1/stores/list-optimized | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🛰️ [REST DEBUG] rest_stores_optimized START
[23-Nov-2025 11:57:41 UTC] 🛰️ [REST DEBUG] stores count: 12
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store:  (ID: 12)
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store:  (ID: 13)
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: bolt3 (ID: 8)
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: boltos (ID: 9)
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: boltos (ID: 17)
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] Day: so, Time: 12:57, Data: {"mo":{"von":"11:00","bis":"18:00","closed":0},"di":{"von":"10:00","bis":"18:00","closed":0},"mi":{"von":"10:00","bis":"18:00","closed":0},"do":{"von":"10:00","bis":"18:00","closed":0},"fr":{"von":"10:00","bis":"18:00","closed":0},"sa":{"von":"10:00","bis":"16:00","closed":0},"so":{"von":"","bis":"","closed":1}}
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] CLOSED flag set for so
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: boltos (ID: 18)
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] Day: so, Time: 12:57, Data: {"mo":{"von":"09:00","bis":"18:00","closed":0},"di":{"von":"10:00","bis":"18:00","closed":0},"mi":{"von":"10:00","bis":"18:00","closed":0},"do":{"von":"10:00","bis":"18:00","closed":0},"fr":{"von":"10:00","bis":"18:00","closed":0},"sa":{"von":"10:00","bis":"16:00","closed":0},"so":{"von":"","bis":"","closed":1}}
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] CLOSED flag set for so
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: boltosuj (ID: 10)
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: Erik (ID: 15)
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] Day: so, Time: 12:57, Data: {"mo":{"von":"05:55","bis":"05:55","closed":0},"di":{"von":"","bis":"","closed":0},"mi":{"von":"","bis":"","closed":0},"do":{"von":"","bis":"","closed":0},"fr":{"von":"","bis":"","closed":0},"sa":{"von":"","bis":"","closed":0},"so":{"von":"","bis":"","closed":0}}
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] Empty hours for so: von=, bis=
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: Erik (ID: 16)
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] Day: so, Time: 12:57, Data: {"mo":{"von":"05:55","bis":"05:55","closed":0},"di":{"von":"","bis":"","closed":0},"mi":{"von":"","bis":"","closed":0},"do":{"von":"","bis":"","closed":0},"fr":{"von":"","bis":"","closed":0},"sa":{"von":"","bis":"","closed":0},"so":{"von":"","bis":"","closed":0}}
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] Empty hours for so: von=, bis=
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: Erik Borota (ID: 14)
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: Erik Borota (ID: 19)
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] Day: so, Time: 12:57, Data: {"mo":{"von":"05:55","bis":"05:55","closed":0},"di":{"von":"","bis":"","closed":0},"mi":{"von":"","bis":"","closed":0},"do":{"von":"","bis":"","closed":0},"fr":{"von":"","bis":"","closed":0},"sa":{"von":"","bis":"","closed":0},"so":{"von":"","bis":"","closed":0}}
[23-Nov-2025 11:57:41 UTC] 🕒 [Open Check] Empty hours for so: von=, bis=
[23-Nov-2025 11:57:41 UTC] 🏪 [REST DEBUG] Store: testfirma (ID: 4)

📊 DAILY SUMMARY (2025-11-23 11:57:41)
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"range":"week","_":"1763899059840"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"range":"week","_":"1763899059840"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:41 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:41 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:41 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:41 UTC] ⚠️ [PPV_SessionBridge] Token not found
[23-Nov-2025 11:57:41 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:41] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:41 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:41 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:41 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/stats?range=week&_=1763899059840
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Session ID: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 3
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:41 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=3
[2025-11-23 11:57:41] 🧠 REST CALL → /punktepass/v1/stats | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:41 UTC] ❌ [Stats] NO STORE FOUND!
[23-Nov-2025 11:57:41 UTC] 🚫 [Stats Perm] DENIED - no store
[23-Nov-2025 11:57:41 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:41 UTC] ❌ [Stats] NO STORE FOUND!
[23-Nov-2025 11:57:41 UTC] 🚫 [Stats Perm] DENIED - no store

📊 DAILY SUMMARY (2025-11-23 11:57:41)
---------------------------------------------------
[2025-11-23 11:57:41] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[23-Nov-2025 11:57:41 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"_":"1763899059843"} | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"_":"1763899059843"} | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:41 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:41 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:41 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:41 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:41 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:41] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:41 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:41 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:41 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:41 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:41 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:41 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/stats/conversion?_=1763899059843
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] Session ID: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 3
[23-Nov-2025 11:57:41 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:41 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=3
[2025-11-23 11:57:41] 🧠 REST CALL → /punktepass/v1/stats/conversion | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:41 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:41 UTC] ❌ [Stats] NO STORE FOUND!
[23-Nov-2025 11:57:41 UTC] 🚫 [Stats Perm] DENIED - no store
[23-Nov-2025 11:57:41 UTC] 🔍 [Stats] get_handler_store_id() START
[23-Nov-2025 11:57:41 UTC] ❌ [Stats] NO STORE FOUND!
[23-Nov-2025 11:57:41 UTC] 🚫 [Stats Perm] DENIED - no store

📊 DAILY SUMMARY (2025-11-23 11:57:41)
---------------------------------------------------
[2025-11-23 11:57:45] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:45 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[2025-11-23 11:57:45] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:45 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:45 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:45 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:45 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:45 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:45 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:45 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:45 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:45] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:45 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:45 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:45 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:45 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:45 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:45 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:45] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:45)
---------------------------------------------------
[23-Nov-2025 11:57:45 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:45 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:45 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:45 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:45 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:45 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:45 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:45 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:45 UTC] ⚠️ [PPV_SessionBridge] Token not found
[23-Nov-2025 11:57:45 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:45] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:45 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:45 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:45 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:45 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:45 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:45 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:45] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:45 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:45)
---------------------------------------------------
[2025-11-23 11:57:50] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-23 11:57:50] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[2025-11-23 11:57:50] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[23-Nov-2025 11:57:50 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[23-Nov-2025 11:57:50 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:50 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:50 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:50 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:50 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:50 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:50] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:50 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:50 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:50 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:50 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:50] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:50 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:50 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:50 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:50 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:50 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[23-Nov-2025 11:57:50 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[23-Nov-2025 11:57:50 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:50 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:50 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:50 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:50 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:50] 🧠 REST CALL → /punktepass/v1/pos/scan | User=10 | Params={"qr":"PPU35SEmtXSebxC0kwd3","store_key":"5hwJGIroEqyjQfJhJRByjo7fkmhNyYV4","points":1}
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:50 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:50 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[23-Nov-2025 11:57:50 UTC] 🔍 [PPV_QR rest_process_scan] Store ID resolution: {"session_store_object":"EXISTS","session_store_id":"9","validated_store_id":"9","final_store_id":9}
[23-Nov-2025 11:57:50 UTC] 🔍 [PPV_QR] decode_user_from_qr: QR starts with PPU, payload=35SEmtXSebxC0kwd3...
[23-Nov-2025 11:57:50 UTC] 🔍 [PPV_QR] decode_user_from_qr: Parsed uid=35, token_length=15
[23-Nov-2025 11:57:50 UTC] 🔍 [PPV_QR] decode_user_from_qr: user_check=NOT FOUND
[23-Nov-2025 11:57:50 UTC] 🔴 [PPV_QR] decode_user_from_qr: Invalid/expired token for user_id=35. Token in QR: SEmtXSeb...
[23-Nov-2025 11:57:50 UTC] 🔴 [PPV_QR] Existing tokens for user 35: []
[2025-11-23 11:57:50] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:50 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:50)
---------------------------------------------------

📊 DAILY SUMMARY (2025-11-23 11:57:50)
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:50 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:50 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:50 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:50 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:50 UTC] ⚠️ [PPV_SessionBridge] Token not found
[23-Nov-2025 11:57:50 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:50] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:50 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:50 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:50 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:50 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:50 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:50] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:50 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:50)
---------------------------------------------------
[2025-11-23 11:57:55] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:55 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[2025-11-23 11:57:55] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:55 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:55 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:55 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:55 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:55 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:55 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:55 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:57:55 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:55] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:55 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:55 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:55 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:55 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:55 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:55 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:55] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:55)
---------------------------------------------------
[23-Nov-2025 11:57:55 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:55 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:55 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:55 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:55 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:55 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:55 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:57:55 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:57:55 UTC] ⚠️ [PPV_SessionBridge] Token not found
[23-Nov-2025 11:57:55 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:55] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:57:55 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:55 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:55 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:55 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:55 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:55 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:57:55] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:55 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:57:55)
---------------------------------------------------
[2025-11-23 11:57:58] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[23-Nov-2025 11:57:58 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[23-Nov-2025 11:57:58 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:57:58 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:58 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:57:58 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:57:58 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:57:58 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:57:58 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:57:58 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:57:58 UTC] 🔍 [SessionBridge] Current session_id: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:58 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[23-Nov-2025 11:57:58 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[23-Nov-2025 11:57:58 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[23-Nov-2025 11:57:58 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-23 11:57:58] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[23-Nov-2025 11:57:58 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:57:58 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:57:58 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:57:58 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[23-Nov-2025 11:57:58 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[23-Nov-2025 11:57:58 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[23-Nov-2025 11:57:58 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:57:58 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:57:58 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[23-Nov-2025 11:57:58 UTC] 🔍 [REST_AUTH] Starting session auth check...
[23-Nov-2025 11:57:58 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/pos/campaigns
[23-Nov-2025 11:57:58 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[23-Nov-2025 11:57:58 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[23-Nov-2025 11:57:58 UTC] 🔍 [REST_AUTH] Session ID: ir5m8jjeu2nteqse2oucarup3e
[23-Nov-2025 11:57:58 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[23-Nov-2025 11:57:58 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[23-Nov-2025 11:57:58 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-23 11:57:58] 🧠 REST CALL → /punktepass/v1/pos/campaigns | User=10 | Params=null
---------------------------------------------------
[23-Nov-2025 11:57:58 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:58 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[23-Nov-2025 11:57:58 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[23-Nov-2025 11:57:58 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Permissions] Subscription is VALID
[23-Nov-2025 11:57:58 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-23 11:57:58)
---------------------------------------------------
[2025-11-23 11:58:00] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:58:00 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[2025-11-23 11:58:00] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:58:00 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:58:00 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:58:00 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:58:00 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:58:00 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:58:00 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:58:00 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:58:00 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:58:00] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:58:00 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:58:00 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:58:00 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:58:00 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:58:00 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:58:00 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:58:00] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:58:00)
---------------------------------------------------
[23-Nov-2025 11:58:00 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:58:00 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:58:00 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:58:00 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:58:00 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:58:00 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:58:00 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:58:00 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:58:00 UTC] ⚠️ [PPV_SessionBridge] Token not found
[23-Nov-2025 11:58:00 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:58:00] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:58:00 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:58:00 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:58:00 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:58:00 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:58:00 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:58:00 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:58:00] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:58:00 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:58:00)
---------------------------------------------------
[2025-11-23 11:58:05] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:58:05 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[2025-11-23 11:58:05] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[23-Nov-2025 11:58:05 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:58:05 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:58:05 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:58:05 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:58:05 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:58:05 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:58:05 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[23-Nov-2025 11:58:05 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:58:05] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:58:05 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:58:05 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:58:05 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:58:05 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:58:05 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:58:05 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:58:05] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:58:05)
---------------------------------------------------
[23-Nov-2025 11:58:05 UTC] 🌍 [PPV_Lang] Using cookie → de
[23-Nov-2025 11:58:05 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[23-Nov-2025 11:58:05 UTC] 🧠 [PPV_Lang] Loaded 953 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[23-Nov-2025 11:58:05 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Signup] Hooks registered successfully
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Signup] Initialized
[23-Nov-2025 11:58:05 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[23-Nov-2025 11:58:05 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[23-Nov-2025 11:58:05 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] restore_from_token() called
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] Current session_id: beqsls8gclrld6dcmt4mpnp6eu
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[23-Nov-2025 11:58:05 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[23-Nov-2025 11:58:05 UTC] ⚠️ [PPV_SessionBridge] Token not found
[23-Nov-2025 11:58:05 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:58:05] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[23-Nov-2025 11:58:05 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[23-Nov-2025 11:58:05 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Stats] ALL REST routes OK
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[23-Nov-2025 11:58:05 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Analytics] REST endpoints registered
[23-Nov-2025 11:58:05 UTC] ✅ Stripe REST route registered via rest_api_init
[23-Nov-2025 11:58:05 UTC] ✅ Stripe Checkout REST route registered
[23-Nov-2025 11:58:05 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-23 11:58:05] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[23-Nov-2025 11:58:05 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none

📊 DAILY SUMMARY (2025-11-23 11:58:05)
---------------------------------------------------
