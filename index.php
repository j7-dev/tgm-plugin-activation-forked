<?php

require_once __DIR__ . '/class-utils.php';
require_once __DIR__ . '/class-list-table.php';
require_once __DIR__ . '/class-activation.php';
require_once __DIR__ . '/class-noused.php';

if ( ! function_exists( 'j7rp' ) ) {
	/**
	 * Helper function to register a collection of required plugins.
	 *
	 * @since 2.0.0
	 * @api
	 *
	 * @param array $plugins An array of plugin arrays.
	 * @param array $config  Optional. An array of configuration values.
	 */
	function j7rp( $plugins, $config = array() ) {
		$id       = $config['id'] ?? 'j7rp';
		$instance = J7_Required_Plugins::get_instance( $id );

		foreach ( $plugins as $plugin ) {
			call_user_func( array( $instance, 'register' ), $plugin );
		}

		if ( ! empty( $config ) && is_array( $config ) ) {
			call_user_func( array( $instance, 'config' ), $config );
		}

		if ( ! function_exists( 'load_j7_required_plugins' ) ) {
			/**
			 * Ensure only one instance of the class is ever invoked.
			 *
			 * @since 2.5.0
			 */
			function load_j7_required_plugins( $id ) {
				$GLOBALS['j7rp'][ $id ] = J7_Required_Plugins::get_instance( $id );
			}
		}

		if ( did_action( 'plugins_loaded' ) ) {
			load_j7_required_plugins( $id );
		} else {
			add_action(
				'plugins_loaded',
				function () use ( $id ) {
					load_j7_required_plugins( $id );
				}
			);
		}
	}
}
