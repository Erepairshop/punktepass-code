<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Händlerprofil – VOLLSTÄNDIGE VERSION
 */
class PPV_Profile_Lite {

    /**
     * Formular anzeigen
     */
    public static function render_form() {
        global $wpdb;
        $user_id = get_current_user_id();

        // Store aus DB laden
        $store = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d",
            $user_id
        ) );

        $opening_hours = ! empty( $store->opening_hours ) ? json_decode( $store->opening_hours, true ) : [];
        $gallery       = ! empty( $store->gallery ) ? json_decode( $store->gallery, true ) : [];

        $action = admin_url( 'admin-post.php' );
        ?>
        <form method="post" action="<?php echo esc_url( $action ); ?>" enctype="multipart/form-data" class="ppv-profile-form">
            <?php wp_nonce_field( 'ppv_profile_save', 'ppv_profile_nonce' ); ?>
            <input type="hidden" name="action" value="ppv_save_profile">

            <h2><?php _e( 'Allgemeine Daten', 'punktepass' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="ppv_name"><?php _e( 'Store-Name', 'punktepass' ); ?></label></th>
                    <td><input type="text" id="ppv_name" name="ppv_name" value="<?php echo esc_attr( $store->name ?? '' ); ?>" required /></td>
                </tr>
                <tr>
                    <th><label for="ppv_address"><?php _e( 'Adresse', 'punktepass' ); ?></label></th>
                    <td><input type="text" id="ppv_address" name="ppv_address" value="<?php echo esc_attr( $store->address ?? '' ); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="ppv_plz"><?php _e( 'PLZ', 'punktepass' ); ?></label></th>
                    <td><input type="text" id="ppv_plz" name="ppv_plz" value="<?php echo esc_attr( $store->plz ?? '' ); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="ppv_city"><?php _e( 'Stadt', 'punktepass' ); ?></label></th>
                    <td><input type="text" id="ppv_city" name="ppv_city" value="<?php echo esc_attr( $store->city ?? '' ); ?>" /></td>
                </tr>
            </table>

            <h2><?php _e( 'Öffnungszeiten', 'punktepass' ); ?></h2>
            <table class="form-table">
                <?php
                $days = [ 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So' ];
                foreach ( $days as $day ) :
                    $open  = $opening_hours[$day]['open'] ?? '';
                    $close = $opening_hours[$day]['close'] ?? '';
                    ?>
                    <tr>
                        <th><?php echo esc_html( $day ); ?></th>
                        <td>
                            <input type="time" name="ppv_opening_hours[<?php echo $day; ?>][open]" value="<?php echo esc_attr( $open ); ?>" /> -
                            <input type="time" name="ppv_opening_hours[<?php echo $day; ?>][close]" value="<?php echo esc_attr( $close ); ?>" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h2><?php _e( 'Kontakt', 'punktepass' ); ?></h2>
            <table class="form-table">
                <tr><th><label for="ppv_phone"><?php _e( 'Telefon', 'punktepass' ); ?></label></th>
                    <td><input type="text" id="ppv_phone" name="ppv_phone" value="<?php echo esc_attr( $store->phone ?? '' ); ?>" /></td></tr>
                <tr><th><label for="ppv_email"><?php _e( 'E-Mail', 'punktepass' ); ?></label></th>
                    <td><input type="email" id="ppv_email" name="ppv_email" value="<?php echo esc_attr( $store->email ?? '' ); ?>" /></td></tr>
                <tr><th><label for="ppv_website"><?php _e( 'Webseite', 'punktepass' ); ?></label></th>
                    <td><input type="url" id="ppv_website" name="ppv_website" value="<?php echo esc_attr( $store->website ?? '' ); ?>" /></td></tr>
            </table>

            <h2><?php _e( 'Medien', 'punktepass' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php _e( 'Logo', 'punktepass' ); ?></th>
                    <td>
                        <?php if ( ! empty( $store->logo ) ) : ?>
                            <img src="<?php echo esc_url( $store->logo ); ?>" alt="Logo" style="max-height:80px;" /><br>
                        <?php endif; ?>
                        <input type="file" name="ppv_logo" accept="image/*" />
                    </td>
                </tr>
                <tr>
                    <th><?php _e( 'Titelbild', 'punktepass' ); ?></th>
                    <td>
                        <?php if ( ! empty( $store->cover ) ) : ?>
                            <img src="<?php echo esc_url( $store->cover ); ?>" alt="Cover" style="max-height:120px;" /><br>
                        <?php endif; ?>
                        <input type="file" name="ppv_cover" accept="image/*" />
                    </td>
                </tr>
                <tr>
                    <th><?php _e( 'Galerie', 'punktepass' ); ?></th>
                    <td>
                        <?php if ( ! empty( $gallery ) ) : ?>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <?php foreach ( $gallery as $img ) : ?>
                                    <img src="<?php echo esc_url( $img ); ?>" alt="Gallery" style="max-height:80px;" />
                                <?php endforeach; ?>
                            </div>
                            <br>
                        <?php endif; ?>
                        <input type="file" name="ppv_gallery[]" accept="image/*" multiple />
                    </td>
                </tr>
            </table>

            <h2><?php _e( 'Social Media', 'punktepass' ); ?></h2>
            <table class="form-table">
                <tr><th>Facebook</th><td><input type="url" name="ppv_facebook" value="<?php echo esc_attr( $store->facebook ?? '' ); ?>" /></td></tr>
                <tr><th>Instagram</th><td><input type="url" name="ppv_instagram" value="<?php echo esc_attr( $store->instagram ?? '' ); ?>" /></td></tr>
                <tr><th>TikTok</th><td><input type="url" name="ppv_tiktok" value="<?php echo esc_attr( $store->tiktok ?? '' ); ?>" /></td></tr>
                <tr><th>WhatsApp</th><td><input type="text" name="ppv_whatsapp" value="<?php echo esc_attr( $store->whatsapp ?? '' ); ?>" /></td></tr>
            </table>

            <?php submit_button( __( 'Speichern', 'punktepass' ) ); ?>
        </form>
        <?php
    }

    /**
     * Formular speichern
     */
    public static function save_form() {
        global $wpdb;
        $user_id = get_current_user_id();

        $data = [
            'user_id'       => $user_id,
            'name'          => sanitize_text_field( $_POST['ppv_name'] ?? '' ),
            'slug'          => sanitize_title( $_POST['ppv_name'] ?? '' ),
            'address'       => sanitize_text_field( $_POST['ppv_address'] ?? '' ),
            'plz'           => sanitize_text_field( $_POST['ppv_plz'] ?? '' ),
            'city'          => sanitize_text_field( $_POST['ppv_city'] ?? '' ),
            'phone'         => sanitize_text_field( $_POST['ppv_phone'] ?? '' ),
            'email'         => sanitize_email( $_POST['ppv_email'] ?? '' ),
            'website'       => esc_url_raw( $_POST['ppv_website'] ?? '' ),
            'facebook'      => esc_url_raw( $_POST['ppv_facebook'] ?? '' ),
            'instagram'     => esc_url_raw( $_POST['ppv_instagram'] ?? '' ),
            'tiktok'        => esc_url_raw( $_POST['ppv_tiktok'] ?? '' ),
            'whatsapp'      => sanitize_text_field( $_POST['ppv_whatsapp'] ?? '' ),
            'updated_at'    => current_time( 'mysql' ),
        ];

        // Öffnungszeiten JSON speichern
        $opening_hours = $_POST['ppv_opening_hours'] ?? [];
        $data['opening_hours'] = wp_json_encode( $opening_hours );

        // Logo Upload
        if ( ! empty( $_FILES['ppv_logo']['name'] ) ) {
            $upload = wp_handle_upload( $_FILES['ppv_logo'], [ 'test_form' => false ] );
            if ( empty( $upload['error'] ) ) {
                $data['logo'] = esc_url_raw( $upload['url'] );
            }
        }

        // Cover Upload
        if ( ! empty( $_FILES['ppv_cover']['name'] ) ) {
            $upload = wp_handle_upload( $_FILES['ppv_cover'], [ 'test_form' => false ] );
            if ( empty( $upload['error'] ) ) {
                $data['cover'] = esc_url_raw( $upload['url'] );
            }
        }

        // Galerie Upload (multiple)
        if ( ! empty( $_FILES['ppv_gallery']['name'][0] ) ) {
            $files = $_FILES['ppv_gallery'];
            $gallery_urls = [];
            for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
                if ( $files['error'][$i] == 0 ) {
                    $file = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                    $upload = wp_handle_upload( $file, [ 'test_form' => false ] );
                    if ( empty( $upload['error'] ) ) {
                        $gallery_urls[] = esc_url_raw( $upload['url'] );
                    }
                }
            }
            if ( ! empty( $gallery_urls ) ) {
                $data['gallery'] = wp_json_encode( $gallery_urls );
            }
        }

        // Google Maps Geocoding
        $api_key = get_option( 'ppv_google_maps_api_key', '' );
        if ( $api_key && ! empty( $data['address'] ) && ! empty( $data['city'] ) ) {
            $address_str = urlencode( $data['address'] . ', ' . $data['plz'] . ' ' . $data['city'] );
            $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address_str}&key={$api_key}";
            $response = wp_remote_get( $url );
            if ( is_array( $response ) && ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $body['results'][0]['geometry']['location'] ) ) {
                    $data['lat'] = $body['results'][0]['geometry']['location']['lat'];
                    $data['lng'] = $body['results'][0]['geometry']['location']['lng'];
                }
            }
        }

        // Existiert Store?
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d",
            $user_id
        ) );

        if ( $exists ) {
            $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                $data,
                [ 'user_id' => $user_id ]
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ppv_stores',
                $data
            );
        }
    }
}
