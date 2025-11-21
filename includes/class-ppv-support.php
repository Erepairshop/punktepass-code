<?php
/**
 * PPV Support Page
 * Händler support ticket submission page
 *
 * @package PunktePass
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class PPV_Support {

    public static function hooks() {
        add_shortcode('ppv_support', [__CLASS__, 'render_support_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Enqueue assets for support page
     */
    public static function enqueue_assets() {
        if (!is_page() || strpos($_SERVER['REQUEST_URI'] ?? '', 'support') === false) {
            return;
        }

        wp_enqueue_style('ppv-support', PPV_PLUGIN_URL . 'assets/css/ppv-theme-light.css', [], time());
    }

    /**
     * Translation helper
     */
    private static function t($key, $default = '') {
        if (class_exists('PPV_Lang')) {
            return PPV_Lang::t($key, $default);
        }
        return $default;
    }

    /**
     * Check if user is authorized
     */
    private static function check_auth() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // Check various session variables
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return ['valid' => true, 'store_id' => intval($_SESSION['ppv_vendor_store_id'])];
        }
        if (!empty($_SESSION['ppv_store_id'])) {
            return ['valid' => true, 'store_id' => intval($_SESSION['ppv_store_id'])];
        }
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return ['valid' => true, 'store_id' => intval($_SESSION['ppv_current_filiale_id'])];
        }
        if (!empty($_SESSION['ppv_user_id'])) {
            return ['valid' => true, 'user_id' => intval($_SESSION['ppv_user_id'])];
        }

        return ['valid' => false];
    }

    /**
     * Render support page
     */
    public static function render_support_page() {
        $auth = self::check_auth();

        if (!$auth['valid']) {
            return '<div class="ppv-error" style="padding: 20px; text-align: center; color: #ff5252;">
                <i class="ri-error-warning-line" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                ' . self::t('login_required', 'Bitte melden Sie sich an.') . '
            </div>';
        }

        ob_start();
        ?>
        <div class="ppv-support-wrapper glass-card" style="max-width: 600px; margin: 0 auto; padding: 24px;">
            <div class="ppv-support-header" style="text-align: center; margin-bottom: 24px;">
                <i class="ri-customer-service-2-line" style="font-size: 48px; color: var(--pp-accent, #667eea); display: block; margin-bottom: 12px;"></i>
                <h2 style="margin: 0 0 8px 0;"><?php echo self::t('support_ticket_title', 'Support anfragen'); ?></h2>
                <p style="color: var(--pp-text-secondary, #666); margin: 0;">
                    <?php echo self::t('support_ticket_desc', 'Beschreiben Sie Ihr Problem. Wir melden uns schnellstmöglich bei Ihnen.'); ?>
                </p>
            </div>

            <form id="ppv-support-form" class="ppv-support-form">
                <!-- Problem Description -->
                <div class="ppv-form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--pp-text-primary, #333);">
                        <?php echo self::t('problem_description', 'Problembeschreibung'); ?> <span style="color: #ff5252;">*</span>
                    </label>
                    <textarea
                        id="ppv-support-description"
                        name="description"
                        class="ppv-input"
                        placeholder="<?php echo esc_attr(self::t('problem_placeholder', 'Bitte beschreiben Sie Ihr Problem...')); ?>"
                        rows="5"
                        required
                        style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--pp-border, #e0e0e0); background: var(--pp-bg-secondary, #f8f9fa); resize: vertical;"
                    ></textarea>
                </div>

                <!-- Priority -->
                <div class="ppv-form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--pp-text-primary, #333);">
                        <?php echo self::t('priority', 'Priorität'); ?>
                    </label>
                    <select
                        id="ppv-support-priority"
                        name="priority"
                        class="ppv-input"
                        style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--pp-border, #e0e0e0); background: var(--pp-bg-secondary, #f8f9fa);"
                    >
                        <option value="normal"><?php echo self::t('priority_normal', 'Normal'); ?></option>
                        <option value="urgent"><?php echo self::t('priority_urgent', 'Dringend'); ?></option>
                        <option value="low"><?php echo self::t('priority_low', 'Niedrig'); ?></option>
                    </select>
                </div>

                <!-- Contact Preference -->
                <div class="ppv-form-group" style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: var(--pp-text-primary, #333);">
                        <?php echo self::t('contact_preference', 'Bevorzugter Kontakt'); ?>
                    </label>
                    <select
                        id="ppv-support-contact"
                        name="contact_method"
                        class="ppv-input"
                        style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--pp-border, #e0e0e0); background: var(--pp-bg-secondary, #f8f9fa);"
                    >
                        <option value="email">📧 <?php echo self::t('contact_email', 'E-Mail'); ?></option>
                        <option value="phone">📞 <?php echo self::t('contact_phone', 'Telefon'); ?></option>
                        <option value="whatsapp">💬 <?php echo self::t('contact_whatsapp', 'WhatsApp'); ?></option>
                    </select>
                </div>

                <!-- Messages -->
                <div id="ppv-support-error" style="display: none; padding: 12px; background: rgba(255, 82, 82, 0.1); border-radius: 8px; color: #ff5252; margin-bottom: 16px;"></div>
                <div id="ppv-support-success" style="display: none; padding: 12px; background: rgba(76, 175, 80, 0.1); border-radius: 8px; color: #4caf50; margin-bottom: 16px;"></div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    id="ppv-support-submit"
                    class="ppv-btn"
                    style="width: 100%; padding: 14px; font-size: 16px; font-weight: 600; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; cursor: pointer;"
                >
                    <i class="ri-send-plane-line"></i> <?php echo self::t('send_ticket', 'Ticket senden'); ?>
                </button>
            </form>

            <!-- Previous Tickets Link -->
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: var(--pp-text-secondary, #666); font-size: 14px;">
                    <?php echo self::t('support_response_time', 'Wir antworten in der Regel innerhalb von 24 Stunden.'); ?>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#ppv-support-form').on('submit', function(e) {
                e.preventDefault();

                const $form = $(this);
                const $btn = $('#ppv-support-submit');
                const $error = $('#ppv-support-error');
                const $success = $('#ppv-support-success');

                const description = $('#ppv-support-description').val().trim();
                const priority = $('#ppv-support-priority').val();
                const contact = $('#ppv-support-contact').val();

                if (!description) {
                    $error.text('<?php echo esc_js(self::t('error_description_required', 'Bitte beschreiben Sie Ihr Problem.')); ?>').show();
                    return;
                }

                $error.hide();
                $success.hide();
                $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> <?php echo esc_js(self::t('sending', 'Senden...')); ?>');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppv_submit_support_ticket',
                        description: description,
                        priority: priority,
                        contact_method: contact,
                        nonce: '<?php echo wp_create_nonce('ppv_support_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $success.html('<i class="ri-checkbox-circle-line"></i> ' + (response.data?.message || '<?php echo esc_js(self::t('ticket_sent_success', 'Ticket erfolgreich gesendet!')); ?>')).show();
                            $form[0].reset();
                        } else {
                            $error.text(response.data?.message || '<?php echo esc_js(self::t('error_general', 'Ein Fehler ist aufgetreten.')); ?>').show();
                        }
                    },
                    error: function() {
                        $error.text('<?php echo esc_js(self::t('error_network', 'Netzwerkfehler. Bitte versuchen Sie es später erneut.')); ?>').show();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<i class="ri-send-plane-line"></i> <?php echo esc_js(self::t('send_ticket', 'Ticket senden')); ?>');
                    }
                });
            });
        });
        </script>

        <?php
        if (class_exists('PPV_Bottom_Nav')) {
            echo PPV_Bottom_Nav::render_nav();
        }

        return ob_get_clean();
    }
}

PPV_Support::hooks();
