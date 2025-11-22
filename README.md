[2025-11-22 19:29:29] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[22-Nov-2025 19:29:29 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:29 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:29 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:29 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:29 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:29 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[22-Nov-2025 19:29:29 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:29 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:29 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:29 UTC] 🔍 [SessionBridge] Current session_id: and3e7p79pia19pl33untatvt0
[22-Nov-2025 19:29:29 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[22-Nov-2025 19:29:29 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[22-Nov-2025 19:29:29 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[22-Nov-2025 19:29:29 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:29] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[22-Nov-2025 19:29:29 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:29 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:29 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:29 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[22-Nov-2025 19:29:29 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[22-Nov-2025 19:29:29 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[22-Nov-2025 19:29:29 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:29 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:29 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:29] 🧠 REST CALL → /ppv/v1/pos/recent-scans | User=10 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:29 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:29 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:29 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:29 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[22-Nov-2025 19:29:29 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:29 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:29 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:29 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:29 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:29 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-22 19:29:29)
---------------------------------------------------
[2025-11-22 19:29:32] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:32 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:32 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:32 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:32 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:32 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:32 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:32 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:32 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:32 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:32 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:32 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:32 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:32 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:32 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:32 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:32 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:32 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:32 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:32 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:32 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:32] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:32 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:32 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:33 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:33 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:33 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:33] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Dashboard] rest_poll_points: Error found but successful scan happened after, ignoring error
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=1, Store=Erepairshop
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:33)
---------------------------------------------------
[2025-11-22 19:29:33] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[2025-11-22 19:29:33] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[22-Nov-2025 19:29:33 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[22-Nov-2025 19:29:33 UTC] 🌍 [PPV_Lang] Geo fallback → de
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET={"doing_wp_cron":"1763839772.9798340797424316406250"} | COOKIE=- | SESSION=-
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET={"doing_wp_cron":"1763839772.9798340797424316406250"} | HEADER=- | COOKIE=- | SESSION=-
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:33 UTC] 🎨 [PPV_Theme_Handler] Using default theme: light
[22-Nov-2025 19:29:33 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] Current session_id: NO SESSION
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] ppv_user_id in session: EMPTY
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] ppv_user_token cookie: MISSING
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=missing, ppv_user_id=empty
[2025-11-22 19:29:33] 🧩 SESSION STATE → {"user_id":0,"store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:33 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:33 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[22-Nov-2025 19:29:33 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:33 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] Current session_id: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[22-Nov-2025 19:29:33 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[22-Nov-2025 19:29:33 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[22-Nov-2025 19:29:33 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[22-Nov-2025 19:29:33 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:33 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[2025-11-22 19:29:33] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[22-Nov-2025 19:29:33 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:33 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized

📊 DAILY SUMMARY (2025-11-22 19:29:33)
---------------------------------------------------
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:33 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[22-Nov-2025 19:29:33 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[22-Nov-2025 19:29:33 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[22-Nov-2025 19:29:33 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:33 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:33 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:33] 🧠 REST CALL → /ppv/v1/pos/recent-scans | User=10 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[22-Nov-2025 19:29:33 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:33 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:33 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-22 19:29:33)
---------------------------------------------------
[2025-11-22 19:29:35] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[22-Nov-2025 19:29:35 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:35 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:35 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:35 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:35 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:35 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[22-Nov-2025 19:29:35 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:35 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:35 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:35 UTC] 🔍 [SessionBridge] Current session_id: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:35 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[22-Nov-2025 19:29:35 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[22-Nov-2025 19:29:35 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[22-Nov-2025 19:29:35 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:35] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[22-Nov-2025 19:29:35 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:35 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:35 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:35 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[22-Nov-2025 19:29:35 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[22-Nov-2025 19:29:35 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[22-Nov-2025 19:29:35 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:35 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:35 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[22-Nov-2025 19:29:35 UTC] 🔍 [REST_AUTH] Starting session auth check...
[22-Nov-2025 19:29:35 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/pos/campaigns
[22-Nov-2025 19:29:35 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[22-Nov-2025 19:29:35 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[22-Nov-2025 19:29:35 UTC] 🔍 [REST_AUTH] Session ID: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:35 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[22-Nov-2025 19:29:35 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[22-Nov-2025 19:29:35 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-22 19:29:35] 🧠 REST CALL → /punktepass/v1/pos/campaigns | User=10 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:35 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:35 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:35 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:35 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[22-Nov-2025 19:29:35 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:35 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:35 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:35 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:35 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-22 19:29:35)
---------------------------------------------------
[2025-11-22 19:29:37] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:37 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:37 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:37 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:37 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:37 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:37 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:37 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:37 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:37 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:37] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:37 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:37 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:37 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:37 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:37 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:37 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:37] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:37 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Dashboard] rest_poll_points: Error found but successful scan happened after, ignoring error
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=1, Store=Erepairshop
[22-Nov-2025 19:29:37 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:37)
---------------------------------------------------
[2025-11-22 19:29:37] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:37 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:37 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:37 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:37 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:37 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:37 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:37 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:37 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:37 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:37 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:37] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:37 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:37 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:37 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:37 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:37 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:37 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:37] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:37 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Dashboard] rest_poll_points: Error found but successful scan happened after, ignoring error
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=1, Store=Erepairshop
[22-Nov-2025 19:29:37 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:37 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:37)
---------------------------------------------------
[2025-11-22 19:29:42] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:42 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:42 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:42 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:42 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:42 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:42 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:42 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:42 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:42 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:42] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:42 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:42 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:42 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:42 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:42 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:42 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:42] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:42 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Dashboard] rest_poll_points: Error found but successful scan happened after, ignoring error
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=1, Store=Erepairshop
[22-Nov-2025 19:29:42 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:42)
---------------------------------------------------
[2025-11-22 19:29:42] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:42 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:42 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:42 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:42 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:42 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:42 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:42 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:42 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:42 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:42 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:42 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:42] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:43 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:43 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:43 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:43 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:43 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:43 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:43] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Dashboard] rest_poll_points: Error found but successful scan happened after, ignoring error
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=1, Store=Erepairshop
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:43)
---------------------------------------------------
[2025-11-22 19:29:43] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[22-Nov-2025 19:29:43 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:43 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:43 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:43 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:43 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:43 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[22-Nov-2025 19:29:43 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:43 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:43 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:43 UTC] 🔍 [SessionBridge] Current session_id: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:43 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[22-Nov-2025 19:29:43 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[22-Nov-2025 19:29:43 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[22-Nov-2025 19:29:43 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:43] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[22-Nov-2025 19:29:43 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:43 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:43 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:43 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[22-Nov-2025 19:29:43 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[22-Nov-2025 19:29:43 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[22-Nov-2025 19:29:43 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:43 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:43 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:43] 🧠 REST CALL → /ppv/v1/pos/recent-scans | User=10 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[22-Nov-2025 19:29:43 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:43 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:43 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-22 19:29:43)
---------------------------------------------------
[2025-11-22 19:29:46] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[22-Nov-2025 19:29:46 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:46 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:46 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:46 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:46 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:46 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[22-Nov-2025 19:29:46 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:46 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:46 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:46 UTC] 🔍 [SessionBridge] Current session_id: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:46 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[22-Nov-2025 19:29:46 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[22-Nov-2025 19:29:46 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[22-Nov-2025 19:29:46 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:46] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[22-Nov-2025 19:29:46 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:46 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:46 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:46 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[22-Nov-2025 19:29:46 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[22-Nov-2025 19:29:46 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[22-Nov-2025 19:29:46 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:46 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:46 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[22-Nov-2025 19:29:46 UTC] 🔍 [REST_AUTH] Starting session auth check...
[22-Nov-2025 19:29:46 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/pos/campaigns
[22-Nov-2025 19:29:46 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[22-Nov-2025 19:29:46 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[22-Nov-2025 19:29:46 UTC] 🔍 [REST_AUTH] Session ID: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:46 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[22-Nov-2025 19:29:46 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[22-Nov-2025 19:29:46 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-22 19:29:46] 🧠 REST CALL → /punktepass/v1/pos/campaigns | User=10 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:46 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:46 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:46 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:46 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[22-Nov-2025 19:29:46 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:46 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:46 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:46 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:46 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-22 19:29:46)
---------------------------------------------------
[2025-11-22 19:29:47] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:47 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:47 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:47 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:47 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:47 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:47 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:47 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:47 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:47 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:47] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:47 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:47 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:47 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:47 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:47 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:47 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:47] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:47 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:47 UTC] ⚠️ [PPV_Dashboard] rest_poll_points: User=3 has recent error: ⚠️ Heute bereits gescannt (type: already_scanned_today)
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none
[22-Nov-2025 19:29:47 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:47)
---------------------------------------------------
[2025-11-22 19:29:47] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:47 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:47 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:47 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:47 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:47 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:47 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:47 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:47 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:47 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:47 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:47] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:47 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:47 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:47 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:47 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:47 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:47 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:47] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:47 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:47 UTC] ⚠️ [PPV_Dashboard] rest_poll_points: User=3 has recent error: ⚠️ Heute bereits gescannt (type: already_scanned_today)
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none
[22-Nov-2025 19:29:47 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:47 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:47)
---------------------------------------------------
[2025-11-22 19:29:52] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:52 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:52 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:52 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:52 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:52 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:52 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:52 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:52 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:52 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:52 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:52 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:52 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:52 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:52 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:52 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:52] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:52 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:52 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:52 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:52 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:52 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:52 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:52] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:52 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:52 UTC] ⚠️ [PPV_Dashboard] rest_poll_points: User=3 has recent error: ⚠️ Heute bereits gescannt (type: already_scanned_today)
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none
[22-Nov-2025 19:29:52 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:52 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:52)
---------------------------------------------------
[2025-11-22 19:29:52] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:53 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:53 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:53 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:53 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:53 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:53 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:53] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:53 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:53 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[2025-11-22 19:29:53] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:53 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:53 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:53 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:53] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:53 UTC] ⚠️ [PPV_Dashboard] rest_poll_points: User=3 has recent error: ⚠️ Heute bereits gescannt (type: already_scanned_today)
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=0, Store=none
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:53)
---------------------------------------------------
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[22-Nov-2025 19:29:53 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:53 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:53 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[22-Nov-2025 19:29:53 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:53 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] Current session_id: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[22-Nov-2025 19:29:53 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[22-Nov-2025 19:29:53 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[22-Nov-2025 19:29:53 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:53] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[22-Nov-2025 19:29:53 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:53 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:53 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[22-Nov-2025 19:29:53 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[22-Nov-2025 19:29:53 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[22-Nov-2025 19:29:53 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:53 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:53 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:53] 🧠 REST CALL → /ppv/v1/pos/recent-scans | User=10 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[22-Nov-2025 19:29:53 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:53 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:53 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-22 19:29:53)
---------------------------------------------------
[2025-11-22 19:29:56] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[22-Nov-2025 19:29:57 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:57 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:57 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[22-Nov-2025 19:29:57 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:57 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] Current session_id: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[22-Nov-2025 19:29:57 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[22-Nov-2025 19:29:57 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:57] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:57 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:57 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[22-Nov-2025 19:29:57 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[22-Nov-2025 19:29:57 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[22-Nov-2025 19:29:57 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:57 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:57 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:57] 🧠 REST CALL → /punktepass/v1/pos/scan | User=10 | Params={"qr":"PPU3MzqSH4dq","store_key":"5hwJGIroEqyjQfJhJRByjo7fkmhNyYV4","points":1}
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_QR rest_process_scan] Store ID resolution: {"session_store_object":"EXISTS","session_store_id":"9","validated_store_id":"9","final_store_id":9}
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_User_Level] Added 1 lifetime points to user 3
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-22 19:29:57)
---------------------------------------------------
[2025-11-22 19:29:57] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:57 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:57 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:57 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:57 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:57 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:57] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:57 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:57 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:57 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:57 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:57] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Dashboard] rest_poll_points: Error found but successful scan happened after, ignoring error
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=1, Store=Erepairshop
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:57)
---------------------------------------------------
[2025-11-22 19:29:57] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] 🧩 [PPV_Session::get_store_by_key] Store found → ID=9
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Session] POS restored via cookie | Store=9
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:57 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:57 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: dark
[22-Nov-2025 19:29:57 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:57 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: dark
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] Current session_id: r4b24o2n3s92eenonguatcklgi
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: EMPTY
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] ppv_user_id in session: 3
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] ppv_user_token cookie: EXISTS (len=32, value=6e3c184cd8cc146406ae...)
[22-Nov-2025 19:29:57 UTC] 🔍 [SessionBridge] Skipping token restore: user_token=exists, ppv_user_id=3
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_SessionBridge] Fallback POS active | store=9
[22-Nov-2025 19:29:57 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:57] 🧩 SESSION STATE → {"user_id":"3","store_id":0,"filiale_id":null,"base_store":0,"pos":false}
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:57 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:57 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:57 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:57 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:57 UTC] 🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id=3)
[2025-11-22 19:29:57] 🧠 REST CALL → /ppv/v1/user/points-poll | User=3 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Dashboard] rest_poll_points: Error found but successful scan happened after, ignoring error
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Dashboard] rest_poll_points: User=3, Points=1, Store=Erepairshop
[22-Nov-2025 19:29:57 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:57 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=3

📊 DAILY SUMMARY (2025-11-22 19:29:57)
---------------------------------------------------
[2025-11-22 19:29:58] 🟢 PPV Auto Debug v5.0 aktiv (PHP + JS + REST + AJAX + SESSION + TRACE)
---------------------------------------------------
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_POS_REST] hooks() aktiv
[22-Nov-2025 19:29:58 UTC] ✅ PPV_Admin_Vendors initialized via plugins_loaded (Hostinger stable)
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Session] store sync ok | ID=9
[22-Nov-2025 19:29:58 UTC] 🌍 [PPV_Lang] Using cookie → de
[22-Nov-2025 19:29:58 UTC] 🧠 [PPV_Lang::FINAL] Active=de | GET=[] | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:58 UTC] 🧠 [PPV_Lang] Loaded 950 keys for 'de' from /home/u660905446/domains/punktepass.de/public_html/wp-content/plugins/punktepass/includes/lang/ppv-lang-de.php
[22-Nov-2025 19:29:58 UTC] 🧠 [PPV_Lang REST/GET Sync] lang=de | GET=[] | HEADER=- | COOKIE=de | SESSION=de
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Signup] Hooks registered successfully
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Signup] Initialized
[22-Nov-2025 19:29:58 UTC] 🎨 [PPV_Theme_Handler] Theme from Cookie: light
[22-Nov-2025 19:29:58 UTC] 🍪 [PPV_Theme_Handler] Cookie set on domains: ["","punktepass.de",".punktepass.de"]
[22-Nov-2025 19:29:58 UTC] 🎨 [PPV_Theme_Handler::init] Theme initialized: light
[22-Nov-2025 19:29:58 UTC] 🔍 [SessionBridge] restore_from_token() called
[22-Nov-2025 19:29:58 UTC] 🔍 [SessionBridge] Current session_id: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:58 UTC] 🔍 [SessionBridge] ppv_vendor_store_id in session: 9
[22-Nov-2025 19:29:58 UTC] 🔍 [SessionBridge] ppv_user_id in session: 10
[22-Nov-2025 19:29:58 UTC] 🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=10
[22-Nov-2025 19:29:58 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[2025-11-22 19:29:58] 🧩 SESSION STATE → {"user_id":"10","store_id":9,"filiale_id":null,"base_store":"9","pos":true}
---------------------------------------------------
[22-Nov-2025 19:29:58 UTC] ✅ PPV_Stripe (Webhook aktiv, linked to ppv_stores)
[22-Nov-2025 19:29:58 UTC] ✅ PPV_Stripe_Checkout::hooks() initialized
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Stats] ALL REST routes OK
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Dashboard] REST routes registered (with points-detailed)
[22-Nov-2025 19:29:58 UTC] 🧠 [PPV_MyPoints_REST] register_rest_route aktiválva
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Analytics] REST endpoints registered
[22-Nov-2025 19:29:58 UTC] 🧩 [PPV_POS_Admin] register_rest_route aktiválva
[22-Nov-2025 19:29:58 UTC] 🧩 [PPV_POS_REST] register_routes aktiválva
[22-Nov-2025 19:29:58 UTC] 🧩 [PPV_POS_REST] /pos/login route regisztrálva
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_POS_REST] alle /ppv/v1 POS routes erfolgreich registriert
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_POS_DOCK] register_routes fired
[22-Nov-2025 19:29:58 UTC] ✅ Stripe REST route registered via rest_api_init
[22-Nov-2025 19:29:58 UTC] ✅ Stripe Checkout REST route registered
[22-Nov-2025 19:29:58 UTC] 🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store=9)
[22-Nov-2025 19:29:58 UTC] 🔍 [REST_AUTH] Starting session auth check...
[22-Nov-2025 19:29:58 UTC] 🔍 [REST_AUTH] URI: /wp-json/punktepass/v1/pos/logs
[22-Nov-2025 19:29:58 UTC] 🔍 [REST_AUTH] ppv_user_token cookie: EXISTS (len=32)
[22-Nov-2025 19:29:58 UTC] 🔍 [REST_AUTH] Session status before: 2 (1=disabled, 2=active, 3=none)
[22-Nov-2025 19:29:58 UTC] 🔍 [REST_AUTH] Session ID: 1u99bo4eeqskbo6f9e4455mtp8
[22-Nov-2025 19:29:58 UTC] 🔍 [REST_AUTH] ppv_user_id in session BEFORE restore: 10
[22-Nov-2025 19:29:58 UTC] 🔍 [REST_AUTH] PPV_SessionBridge class exists: YES
[22-Nov-2025 19:29:58 UTC] ✅ [REST_AUTH] Session user authenticated: user_id=10
[2025-11-22 19:29:58] 🧠 REST CALL → /punktepass/v1/pos/logs | User=10 | Params=null
---------------------------------------------------
[22-Nov-2025 19:29:58 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:58 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:58 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:58 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS
[22-Nov-2025 19:29:58 UTC] 🔍 [PPV_Permissions] check_handler() called
[22-Nov-2025 19:29:58 UTC] 🔍 [PPV_Permissions] check_authenticated() called
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Permissions] Auth via SESSION: user_id=10
[22-Nov-2025 19:29:58 UTC] 🔍 [PPV_Permissions] check_handler() user_type from SESSION: store
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Permissions] check_handler() user_type=store is in handler_types
[22-Nov-2025 19:29:58 UTC] 🔍 [PPV_Permissions] Checking subscription expiry for store_id=9
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Permissions] Subscription is VALID
[22-Nov-2025 19:29:58 UTC] ✅ [PPV_Permissions] check_handler() SUCCESS

📊 DAILY SUMMARY (2025-11-22 19:29:58)
---------------------------------------------------
