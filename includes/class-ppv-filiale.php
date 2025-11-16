<?php
if (!defined('ABSPATH')) exit;

class PPV_Filiale {

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** ============================================================
     * 🔗 REST Route regisztrálása
     * ============================================================ */
    public static function register_routes() {
        register_rest_route('ppv/v1', '/store/clone', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'clone_store'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
    }

    /** ============================================================
     * 🔐 Engedélyezés – csak POS token vagy admin
     * ============================================================ */
    public static function check_permission() {
        return (
            (isset($_COOKIE['ppv_pos_token']) && !empty($_COOKIE['ppv_pos_token']))
            || current_user_can('manage_options')
        );
    }

    /** ============================================================
     * 🏪 Filiale létrehozása meglévő store alapján
     * ============================================================ */
    public static function clone_store(WP_REST_Request $request) {
        global $wpdb;

        $params = $request->get_json_params();
        if (empty($params)) $params = $request->get_params();

        $source_id = intval($params['source_id'] ?? 0);
        $new_name  = sanitize_text_field($params['name'] ?? '');
        $new_city  = sanitize_text_field($params['city'] ?? '');
        $new_plz   = sanitize_text_field($params['plz'] ?? '');
        $zeiten    = !empty($params['zeiten']) ? wp_json_encode($params['zeiten']) : null;

        if (!$source_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Fehlende Quell-ID'], 400);
        }

        $source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d", $source_id));
        if (!$source) {
            return new WP_REST_Response(['success' => false, 'message' => 'Quelle nicht gefunden'], 404);
        }

        // 🔒 Saját bolt ellenőrzése
        $current_user = get_current_user_id();
        if ($current_user && $source->user_id != $current_user && !current_user_can('manage_options')) {
            return new WP_REST_Response(['success' => false, 'message' => 'Keine Berechtigung für diesen Store'], 403);
        }

        // 🔹 Másolat adatainak előkészítése
        $data = [
            'user_id'        => $source->user_id,
            'parent_id'      => $source->id,
            'name'           => $new_name ?: ($source->name . ' Filiale'),
            'slogan'         => $source->slogan,
            'category'       => $source->category,
            'address'        => $source->address,
            'plz'            => $new_plz ?: $source->plz,
            'city'           => $new_city ?: $source->city,
            'country'        => $source->country,
            'phone'          => $source->phone,
            'email'          => $source->email,
            'website'        => $source->website,
            'description'    => $source->description,
            'company_name'   => $source->company_name,
            'contact_person' => $source->contact_person,
            'logo'           => $source->logo,
            'cover'          => $source->cover,
            'gallery'        => $source->gallery,
            'facebook'       => $source->facebook,
            'instagram'      => $source->instagram,
            'tiktok'         => $source->tiktok,
            'whatsapp'       => $source->whatsapp,
            'zeiten'         => $zeiten ?: $source->zeiten,
            'latitude'       => $source->latitude,
            'longitude'      => $source->longitude,
            'active'         => 1,
            'visible'        => 0,
            'store_key'      => sanitize_title($new_name ?: ($source->name . '-filiale-' . rand(1000,9999))),
            'last_updated'   => current_time('mysql'),
        ];

        // 🔹 Extra mezők, ha léteznek
        $columns = $wpdb->get_col("DESC {$wpdb->prefix}ppv_stores", 0);
        if (in_array('is_pos_filiale', $columns)) {
            $data['is_pos_filiale'] = 1;
        }

        // 🔹 Új Filiale mentése
        $insert = $wpdb->insert("{$wpdb->prefix}ppv_stores", $data);

        if ($insert === false) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Fehler beim Erstellen der Filiale'
            ], 500);
        }

        $new_id = $wpdb->insert_id;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Neue Filiale erfolgreich erstellt',
            'new_store_id' => $new_id,
            'name' => $data['name']
        ], 200);
    }
}

PPV_Filiale::hooks();
