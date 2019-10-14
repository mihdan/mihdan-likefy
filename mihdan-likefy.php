<?php
/**
 * Plugin Name: Mihdan: Likefy
 * Version: 1.0.0
 */
namespace Mihdan\Likefy;

define( 'MIHDAN_LIKEFY_VERSION', '1.0.0' );
define( 'MIHDAN_LIKEFY_DIR', __DIR__ );

static $plugin;

if ( ! isset( $plugin ) ) {
	require_once MIHDAN_LIKEFY_DIR . '/includes/class-main.php';
	$plugin = new Main();
}

// eol.
