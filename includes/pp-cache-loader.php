<?php
if ( ! defined('ABSPATH') ) exit;
/**
 * Late loader to include pp-cache-admin.php if the main file didn't require it yet.
 */
add_action('plugins_loaded', function(){
  $file = PPV_DIR.'includes/pp-cache-admin.php';
  if ( file_exists($file) ) { include_once $file; }
}, 20);
