<?php
if ( ! defined('ABSPATH') ) { exit; }
add_action('template_redirect', function(){
    if ( is_user_logged_in() ) {
        if ( ! defined('DONOTCACHEPAGE') ) { define('DONOTCACHEPAGE', true); }
        nocache_headers();
        @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        @header('Pragma: no-cache');
        @header('Expires: 0');
    }
}, 0);
