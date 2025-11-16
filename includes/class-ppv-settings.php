<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'PPV_Settings' ) ) {

class PPV_Settings {

    public static function hooks() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    public static function register_settings() {
        // Beispiel: einfache Option
        register_setting( 'punktepass_options', 'punktepass_option_api_key' );

        add_settings_section(
            'punktepass_main_section',
            __( 'PunktePass Einstellungen', 'punktepass' ),
            function() {
                echo '<p>' . __( 'Grundlegende Einstellungen für PunktePass.', 'punktepass' ) . '</p>';
            },
            'punktepass'
        );

        add_settings_field(
            'punktepass_option_api_key',
            __( 'API Key', 'punktepass' ),
            [ __CLASS__, 'render_api_key_field' ],
            'punktepass',
            'punktepass_main_section'
        );
    }

    public static function render_api_key_field() {
        $value = get_option( 'punktepass_option_api_key', '' );
        echo '<input type="text" name="punktepass_option_api_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
    }
}

}
