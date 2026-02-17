<?php
/**
 * PunktePass AI Engine - Claude API Integration
 * Provides AI-powered features: repair analysis, smart suggestions
 *
 * Requires: ANTHROPIC_API_KEY defined in wp-config.php
 */

if (!defined('ABSPATH')) exit;

class PPV_AI_Engine {

    private static $api_url = 'https://api.anthropic.com/v1/messages';
    private static $model = 'claude-haiku-4-5-20251001';
    private static $max_tokens = 300;

    /**
     * Check if AI features are available (API key configured)
     */
    public static function is_available() {
        return defined('ANTHROPIC_API_KEY') && !empty(ANTHROPIC_API_KEY);
    }

    /**
     * Send a single message to Claude API
     *
     * @param string $system  System prompt
     * @param string $user    User message
     * @param string $lang    Response language (de/hu/ro/en/it)
     * @return array|WP_Error ['text' => string] or WP_Error
     */
    public static function chat($system, $user, $lang = 'de') {
        return self::chat_with_history($system, [
            ['role' => 'user', 'content' => $user]
        ]);
    }

    /**
     * Send messages with conversation history to Claude API
     *
     * @param string $system    System prompt
     * @param array  $messages  Array of ['role' => 'user'|'assistant', 'content' => string]
     * @return array|WP_Error   ['text' => string] or WP_Error
     */
    public static function chat_with_history($system, $messages) {
        if (!self::is_available()) {
            return new WP_Error('ai_not_configured', 'AI API key not configured');
        }

        // Sanitize messages and limit history to last 20 messages
        $clean = [];
        foreach (array_slice($messages, -20) as $msg) {
            $role = ($msg['role'] === 'assistant') ? 'assistant' : 'user';
            $content = sanitize_textarea_field($msg['content'] ?? '');
            if ($content !== '') {
                $clean[] = ['role' => $role, 'content' => $content];
            }
        }

        if (empty($clean)) {
            return new WP_Error('empty_message', 'No message provided');
        }

        $body = [
            'model'      => self::$model,
            'max_tokens' => self::$max_tokens,
            'system'     => $system,
            'messages'   => $clean,
        ];

        $response = wp_remote_post(self::$api_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => ANTHROPIC_API_KEY,
                'anthropic-version'  => '2023-06-01',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $err_msg = $data['error']['message'] ?? 'API error (HTTP ' . $code . ')';
            return new WP_Error('ai_api_error', $err_msg);
        }

        $text = $data['content'][0]['text'] ?? '';
        return ['text' => trim($text)];
    }

    /**
     * Analyze a repair problem description and return structured AI response
     *
     * @param array  $form_data  ['brand', 'model', 'problem', 'service_type']
     * @param string $lang       Response language
     * @return array|WP_Error
     */
    public static function analyze_repair($form_data, $lang = 'de') {
        $brand   = sanitize_text_field($form_data['brand'] ?? '');
        $model   = sanitize_text_field($form_data['model'] ?? '');
        $problem = sanitize_textarea_field($form_data['problem'] ?? '');
        $service = sanitize_text_field($form_data['service_type'] ?? 'Allgemein');

        if (empty($problem) || mb_strlen($problem) < 5) {
            return new WP_Error('too_short', 'Problem description too short');
        }

        $lang_names = [
            'de' => 'German', 'hu' => 'Hungarian', 'ro' => 'Romanian',
            'en' => 'English', 'it' => 'Italian',
        ];
        $lang_name = $lang_names[$lang] ?? 'German';

        $system = <<<PROMPT
You are an expert repair technician assistant for a repair shop. Analyze the customer's problem description and provide helpful information.

RULES:
- Respond ONLY in {$lang_name}
- Keep your response concise (max 3-4 short bullet points)
- Be helpful and professional
- If the device/brand is mentioned, give specific tips
- Format: use a simple structure with category, possible cause, and a tip
- Do NOT give price estimates - every shop has different pricing
- Do NOT use markdown headers or bold - just plain text with bullet points (•)
- Service type: {$service}
PROMPT;

        $device_info = '';
        if ($brand) $device_info .= "Brand: {$brand}\n";
        if ($model) $device_info .= "Model: {$model}\n";

        $user_msg = trim($device_info . "Problem: {$problem}");

        $result = self::chat($system, $user_msg, $lang);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'analysis' => $result['text'],
            'device'   => trim("{$brand} {$model}"),
        ];
    }
}
