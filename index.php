<?php

require_once __DIR__ . '/class-utils.php';
require_once __DIR__ . '/class-list-table.php';
require_once __DIR__ . '/class-activation.php';
require_once __DIR__ . '/class-noused.php';



if ( ! function_exists( 'tgmpa' ) ) {
	/**
	 * Helper function to register a collection of required plugins.
	 *
	 * @since 2.0.0
	 * @api
	 *
	 * @param array $plugins An array of plugin arrays.
	 * @param array $config  Optional. An array of configuration values.
	 */
	function tgmpa( $plugins, $config = array() ) {
		$instance = call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) );

		foreach ( $plugins as $plugin ) {
			call_user_func( array( $instance, 'register' ), $plugin );
		}

		if ( ! empty( $config ) && is_array( $config ) ) {
			// Send out notices for deprecated arguments passed.
			if ( isset( $config['notices'] ) ) {
				_deprecated_argument( __FUNCTION__, '2.2.0', 'The `notices` config parameter was renamed to `has_notices` in TGMPA 2.2.0. Please adjust your configuration.' );
				if ( ! isset( $config['has_notices'] ) ) {
					$config['has_notices'] = $config['notices'];
				}
			}

			if ( isset( $config['parent_menu_slug'] ) ) {
				_deprecated_argument( __FUNCTION__, '2.4.0', 'The `parent_menu_slug` config parameter was removed in TGMPA 2.4.0. In TGMPA 2.5.0 an alternative was (re-)introduced. Please adjust your configuration. For more information visit the website: http://tgmpluginactivation.com/configuration/#h-configuration-options.' );
			}
			if ( isset( $config['parent_url_slug'] ) ) {
				_deprecated_argument( __FUNCTION__, '2.4.0', 'The `parent_url_slug` config parameter was removed in TGMPA 2.4.0. In TGMPA 2.5.0 an alternative was (re-)introduced. Please adjust your configuration. For more information visit the website: http://tgmpluginactivation.com/configuration/#h-configuration-options.' );
			}

			call_user_func( array( $instance, 'config' ), $config );
		}
	}
}


/**
 * The WP_Upgrader file isn't always available. If it isn't available,
 * we load it here.
 *
 * We check to make sure no action or activation keys are set so that WordPress
 * does not try to re-include the class when processing upgrades or installs outside
 * of the class.
 *
 * @since 2.2.0
 */
add_action( 'admin_init', 'tgmpa_load_bulk_installer' );
if ( ! function_exists( 'tgmpa_load_bulk_installer' ) ) {
	/**
	 * Load bulk installer
	 */
	function tgmpa_load_bulk_installer() {
		// Silently fail if 2.5+ is loaded *after* an older version.
		if ( ! isset( $GLOBALS['tgmpa'] ) ) {
			return;
		}

		// Get TGMPA class instance.
		$tgmpa_instance = call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) );

		if ( isset( $_GET['page'] ) && $tgmpa_instance->menu === $_GET['page'] ) {
			if ( ! class_exists( 'Plugin_Upgrader', false ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			if ( ! class_exists( 'TGMPA_Bulk_Installer' ) ) {

				/**
				 * Installer class to handle bulk plugin installations.
				 *
				 * Extends WP_Upgrader and customizes to suit the installation of multiple
				 * plugins.
				 *
				 * @since 2.2.0
				 *
				 * {@internal Since 2.5.0 the class is an extension of Plugin_Upgrader rather than WP_Upgrader.}}
				 * {@internal Since 2.5.2 the class has been renamed from TGM_Bulk_Installer to TGMPA_Bulk_Installer.
				 *            This was done to prevent backward compatibility issues with v2.3.6.}}
				 *
				 * @package TGM-Plugin-Activation
				 * @author  Thomas Griffin
				 * @author  Gary Jones
				 */
				class TGMPA_Bulk_Installer extends Plugin_Upgrader {
					/**
					 * Holds result of bulk plugin installation.
					 *
					 * @since 2.2.0
					 *
					 * @var string
					 */
					public $result;

					/**
					 * Flag to check if bulk installation is occurring or not.
					 *
					 * @since 2.2.0
					 *
					 * @var boolean
					 */
					public $bulk = false;

					/**
					 * TGMPA instance
					 *
					 * @since 2.5.0
					 *
					 * @var object
					 */
					protected $tgmpa;

					/**
					 * Whether or not the destination directory needs to be cleared ( = on update).
					 *
					 * @since 2.5.0
					 *
					 * @var bool
					 */
					protected $clear_destination = false;

					/**
					 * References parent constructor and sets defaults for class.
					 *
					 * @since 2.2.0
					 *
					 * @param \Bulk_Upgrader_Skin|null $skin Installer skin.
					 */
					public function __construct( $skin = null ) {
						// Get TGMPA class instance.
						$this->tgmpa = call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) );

						parent::__construct( $skin );

						if ( isset( $this->skin->options['install_type'] ) && 'update' === $this->skin->options['install_type'] ) {
							$this->clear_destination = true;
						}

						if ( $this->tgmpa->is_automatic ) {
							$this->activate_strings();
						}

						add_action( 'upgrader_process_complete', array( $this->tgmpa, 'populate_file_path' ) );
					}

					/**
					 * Sets the correct activation strings for the installer skin to use.
					 *
					 * @since 2.2.0
					 */
					public function activate_strings() {
						$this->strings['activation_failed']  = __( 'Plugin activation failed.', 'tgmpa' );
						$this->strings['activation_success'] = __( 'Plugin activated successfully.', 'tgmpa' );
					}

					/**
					 * Performs the actual installation of each plugin.
					 *
					 * @since 2.2.0
					 *
					 * @see WP_Upgrader::run()
					 *
					 * @param array $options The installation config options.
					 * @return null|array Return early if error, array of installation data on success.
					 */
					public function run( $options ) {
						$result = parent::run( $options );

						// Reset the strings in case we changed one during automatic activation.
						if ( $this->tgmpa->is_automatic ) {
							if ( 'update' === $this->skin->options['install_type'] ) {
								$this->upgrade_strings();
							} else {
								$this->install_strings();
							}
						}

						return $result;
					}

					/**
					 * Processes the bulk installation of plugins.
					 *
					 * @since 2.2.0
					 *
					 * {@internal This is basically a near identical copy of the WP Core
					 * Plugin_Upgrader::bulk_upgrade() method, with minor adjustments to deal with
					 * new installs instead of upgrades.
					 * For ease of future synchronizations, the adjustments are clearly commented, but no other
					 * comments are added. Code style has been made to comply.}}
					 *
					 * @see Plugin_Upgrader::bulk_upgrade()
					 * @see https://core.trac.wordpress.org/browser/tags/4.2.1/src/wp-admin/includes/class-wp-upgrader.php#L838
					 * (@internal Last synced: Dec 31st 2015 against https://core.trac.wordpress.org/browser/trunk?rev=36134}}
					 *
					 * @param array $plugins The plugin sources needed for installation.
					 * @param array $args    Arbitrary passed extra arguments.
					 * @return array|false   Install confirmation messages on success, false on failure.
					 */
					public function bulk_install( $plugins, $args = array() ) {
						// [TGMPA + ] Hook auto-activation in.
						add_filter( 'upgrader_post_install', array( $this, 'auto_activate' ), 10 );

						$defaults    = array(
							'clear_update_cache' => true,
						);
						$parsed_args = wp_parse_args( $args, $defaults );

						$this->init();
						$this->bulk = true;

						$this->install_strings(); // [TGMPA + ] adjusted.

						/* [TGMPA - ] $current = get_site_transient( 'update_plugins' ); */

						/* [TGMPA - ] add_filter('upgrader_clear_destination', array($this, 'delete_old_plugin'), 10, 4); */

						$this->skin->header();

						// Connect to the Filesystem first.
						$res = $this->fs_connect( array( WP_CONTENT_DIR, WP_PLUGIN_DIR ) );
						if ( ! $res ) {
							$this->skin->footer();
							return false;
						}

						$this->skin->bulk_header();

						/*
						 * Only start maintenance mode if:
						 * - running Multisite and there are one or more plugins specified, OR
						 * - a plugin with an update available is currently active.
						 * @TODO: For multisite, maintenance mode should only kick in for individual sites if at all possible.
						 */
						$maintenance = ( is_multisite() && ! empty( $plugins ) );

						/*
						[TGMPA - ]
						foreach ( $plugins as $plugin )
							$maintenance = $maintenance || ( is_plugin_active( $plugin ) && isset( $current->response[ $plugin] ) );
						*/
						if ( $maintenance ) {
							$this->maintenance_mode( true );
						}

						$results = array();

						$this->update_count   = count( $plugins );
						$this->update_current = 0;
						foreach ( $plugins as $plugin ) {
							++$this->update_current;

							/*
							[TGMPA - ]
							$this->skin->plugin_info = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, true);

							if ( !isset( $current->response[ $plugin ] ) ) {
								$this->skin->set_result('up_to_date');
								$this->skin->before();
								$this->skin->feedback('up_to_date');
								$this->skin->after();
								$results[$plugin] = true;
								continue;
							}

							// Get the URL to the zip file.
							$r = $current->response[ $plugin ];

							$this->skin->plugin_active = is_plugin_active($plugin);
							*/

							$result = $this->run(
								array(
									'package'           => $plugin, // [TGMPA + ] adjusted.
									'destination'       => WP_PLUGIN_DIR,
									'clear_destination' => false, // [TGMPA + ] adjusted.
									'clear_working'     => true,
									'is_multi'          => true,
									'hook_extra'        => array(
										'plugin' => $plugin,
									),
								)
							);

							$results[ $plugin ] = $this->result;

							// Prevent credentials auth screen from displaying multiple times.
							if ( false === $result ) {
								break;
							}
						} //end foreach $plugins

						$this->maintenance_mode( false );

						/**
						 * Fires when the bulk upgrader process is complete.
						 *
						 * @since WP 3.6.0 / TGMPA 2.5.0
						 *
						 * @param Plugin_Upgrader $this Plugin_Upgrader instance. In other contexts, $this, might
						 *                              be a Theme_Upgrader or Core_Upgrade instance.
						 * @param array           $data {
						 *     Array of bulk item update data.
						 *
						 *     @type string $action   Type of action. Default 'update'.
						 *     @type string $type     Type of update process. Accepts 'plugin', 'theme', or 'core'.
						 *     @type bool   $bulk     Whether the update process is a bulk update. Default true.
						 *     @type array  $packages Array of plugin, theme, or core packages to update.
						 * }
						 */
						do_action(
							'upgrader_process_complete',
							$this,
							array(
								'action'  => 'install', // [TGMPA + ] adjusted.
								'type'    => 'plugin',
								'bulk'    => true,
								'plugins' => $plugins,
							)
						);

						$this->skin->bulk_footer();

						$this->skin->footer();

						// Cleanup our hooks, in case something else does a upgrade on this connection.
						/* [TGMPA - ] remove_filter('upgrader_clear_destination', array($this, 'delete_old_plugin')); */

						// [TGMPA + ] Remove our auto-activation hook.
						remove_filter( 'upgrader_post_install', array( $this, 'auto_activate' ), 10 );

						// Force refresh of plugin update information.
						wp_clean_plugins_cache( $parsed_args['clear_update_cache'] );

						return $results;
					}

					/**
					 * Handle a bulk upgrade request.
					 *
					 * @since 2.5.0
					 *
					 * @see Plugin_Upgrader::bulk_upgrade()
					 *
					 * @param array $plugins The local WP file_path's of the plugins which should be upgraded.
					 * @param array $args    Arbitrary passed extra arguments.
					 * @return string|bool Install confirmation messages on success, false on failure.
					 */
					public function bulk_upgrade( $plugins, $args = array() ) {

						add_filter( 'upgrader_post_install', array( $this, 'auto_activate' ), 10 );

						$result = parent::bulk_upgrade( $plugins, $args );

						remove_filter( 'upgrader_post_install', array( $this, 'auto_activate' ), 10 );

						return $result;
					}

					/**
					 * Abuse a filter to auto-activate plugins after installation.
					 *
					 * Hooked into the 'upgrader_post_install' filter hook.
					 *
					 * @since 2.5.0
					 *
					 * @param bool $bool The value we need to give back (true).
					 * @return bool
					 */
					public function auto_activate( $bool ) {
						// Only process the activation of installed plugins if the automatic flag is set to true.
						if ( $this->tgmpa->is_automatic ) {
							// Flush plugins cache so the headers of the newly installed plugins will be read correctly.
							wp_clean_plugins_cache();

							// Get the installed plugin file.
							$plugin_info = $this->plugin_info();

							// Don't try to activate on upgrade of active plugin as WP will do this already.
							if ( ! is_plugin_active( $plugin_info ) ) {
								$activate = activate_plugin( $plugin_info );

								// Adjust the success string based on the activation result.
								$this->strings['process_success'] = $this->strings['process_success'] . "<br />\n";

								if ( is_wp_error( $activate ) ) {
									$this->skin->error( $activate );
									$this->strings['process_success'] .= $this->strings['activation_failed'];
								} else {
									$this->strings['process_success'] .= $this->strings['activation_success'];
								}
							}
						}

						return $bool;
					}
				}
			}

			if ( ! class_exists( 'TGMPA_Bulk_Installer_Skin' ) ) {

				/**
				 * Installer skin to set strings for the bulk plugin installations..
				 *
				 * Extends Bulk_Upgrader_Skin and customizes to suit the installation of multiple
				 * plugins.
				 *
				 * @since 2.2.0
				 *
				 * {@internal Since 2.5.2 the class has been renamed from TGM_Bulk_Installer_Skin to
				 *            TGMPA_Bulk_Installer_Skin.
				 *            This was done to prevent backward compatibility issues with v2.3.6.}}
				 *
				 * @see https://core.trac.wordpress.org/browser/trunk/src/wp-admin/includes/class-wp-upgrader-skins.php
				 *
				 * @package TGM-Plugin-Activation
				 * @author  Thomas Griffin
				 * @author  Gary Jones
				 */
				class TGMPA_Bulk_Installer_Skin extends Bulk_Upgrader_Skin {
					/**
					 * Holds plugin info for each individual plugin installation.
					 *
					 * @since 2.2.0
					 *
					 * @var array
					 */
					public $plugin_info = array();

					/**
					 * Holds names of plugins that are undergoing bulk installations.
					 *
					 * @since 2.2.0
					 *
					 * @var array
					 */
					public $plugin_names = array();

					/**
					 * Integer to use for iteration through each plugin installation.
					 *
					 * @since 2.2.0
					 *
					 * @var integer
					 */
					public $i = 0;

					/**
					 * TGMPA instance
					 *
					 * @since 2.5.0
					 *
					 * @var object
					 */
					protected $tgmpa;

					/**
					 * Constructor. Parses default args with new ones and extracts them for use.
					 *
					 * @since 2.2.0
					 *
					 * @param array $args Arguments to pass for use within the class.
					 */
					public function __construct( $args = array() ) {
						// Get TGMPA class instance.
						$this->tgmpa = call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) );

						// Parse default and new args.
						$defaults = array(
							'url'          => '',
							'nonce'        => '',
							'names'        => array(),
							'install_type' => 'install',
						);
						$args     = wp_parse_args( $args, $defaults );

						// Set plugin names to $this->plugin_names property.
						$this->plugin_names = $args['names'];

						// Extract the new args.
						parent::__construct( $args );
					}

					/**
					 * Sets install skin strings for each individual plugin.
					 *
					 * Checks to see if the automatic activation flag is set and uses the
					 * the proper strings accordingly.
					 *
					 * @since 2.2.0
					 */
					public function add_strings() {
						if ( 'update' === $this->options['install_type'] ) {
							parent::add_strings();
							/* translators: 1: plugin name, 2: action number 3: total number of actions. */
							$this->upgrader->strings['skin_before_update_header'] = __( 'Updating Plugin %1$s (%2$d/%3$d)', 'tgmpa' );
						} else {
							/* translators: 1: plugin name, 2: error message. */
							$this->upgrader->strings['skin_update_failed_error'] = __( 'An error occurred while installing %1$s: <strong>%2$s</strong>.', 'tgmpa' );
							/* translators: 1: plugin name. */
							$this->upgrader->strings['skin_update_failed'] = __( 'The installation of %1$s failed.', 'tgmpa' );

							if ( $this->tgmpa->is_automatic ) {
								// Automatic activation strings.
								$this->upgrader->strings['skin_upgrade_start'] = __( 'The installation and activation process is starting. This process may take a while on some hosts, so please be patient.', 'tgmpa' );
								/* translators: 1: plugin name. */
								$this->upgrader->strings['skin_update_successful'] = __( '%1$s installed and activated successfully.', 'tgmpa' ) . ' <a href="#" class="hide-if-no-js" onclick="%2$s"><span>' . esc_html__( 'Show Details', 'tgmpa' ) . '</span><span class="hidden">' . esc_html__( 'Hide Details', 'tgmpa' ) . '</span>.</a>';
								$this->upgrader->strings['skin_upgrade_end']       = __( 'All installations and activations have been completed.', 'tgmpa' );
								/* translators: 1: plugin name, 2: action number 3: total number of actions. */
								$this->upgrader->strings['skin_before_update_header'] = __( 'Installing and Activating Plugin %1$s (%2$d/%3$d)', 'tgmpa' );
							} else {
								// Default installation strings.
								$this->upgrader->strings['skin_upgrade_start'] = __( 'The installation process is starting. This process may take a while on some hosts, so please be patient.', 'tgmpa' );
								/* translators: 1: plugin name. */
								$this->upgrader->strings['skin_update_successful'] = esc_html__( '%1$s installed successfully.', 'tgmpa' ) . ' <a href="#" class="hide-if-no-js" onclick="%2$s"><span>' . esc_html__( 'Show Details', 'tgmpa' ) . '</span><span class="hidden">' . esc_html__( 'Hide Details', 'tgmpa' ) . '</span>.</a>';
								$this->upgrader->strings['skin_upgrade_end']       = __( 'All installations have been completed.', 'tgmpa' );
								/* translators: 1: plugin name, 2: action number 3: total number of actions. */
								$this->upgrader->strings['skin_before_update_header'] = __( 'Installing Plugin %1$s (%2$d/%3$d)', 'tgmpa' );
							}
						}
					}

					/**
					 * Outputs the header strings and necessary JS before each plugin installation.
					 *
					 * @since 2.2.0
					 *
					 * @param string $title Unused in this implementation.
					 */
					public function before( $title = '' ) {
						if ( empty( $title ) ) {
							$title = esc_html( $this->plugin_names[ $this->i ] );
						}
						parent::before( $title );
					}

					/**
					 * Outputs the footer strings and necessary JS after each plugin installation.
					 *
					 * Checks for any errors and outputs them if they exist, else output
					 * success strings.
					 *
					 * @since 2.2.0
					 *
					 * @param string $title Unused in this implementation.
					 */
					public function after( $title = '' ) {
						if ( empty( $title ) ) {
							$title = esc_html( $this->plugin_names[ $this->i ] );
						}
						parent::after( $title );

						++$this->i;
					}

					/**
					 * Outputs links after bulk plugin installation is complete.
					 *
					 * @since 2.2.0
					 */
					public function bulk_footer() {
						// Serve up the string to say installations (and possibly activations) are complete.
						parent::bulk_footer();

						// Flush plugins cache so we can make sure that the installed plugins list is always up to date.
						wp_clean_plugins_cache();

						$this->tgmpa->show_tgmpa_version();

						// Display message based on if all plugins are now active or not.
						$update_actions = array();

						if ( $this->tgmpa->is_tgmpa_complete() ) {
							// All plugins are active, so we display the complete string and hide the menu to protect users.
							echo '<style type="text/css">#adminmenu .wp-submenu li.current { display: none !important; }</style>';
							$update_actions['dashboard'] = sprintf(
								esc_html( $this->tgmpa->strings['complete'] ),
								'<a href="' . esc_url( self_admin_url() ) . '">' . esc_html__( 'Return to the Dashboard', 'tgmpa' ) . '</a>'
							);
						} else {
							$update_actions['tgmpa_page'] = '<a href="' . esc_url( $this->tgmpa->get_tgmpa_url() ) . '" target="_parent">' . esc_html( $this->tgmpa->strings['return'] ) . '</a>';
						}

						/**
						 * Filter the list of action links available following bulk plugin installs/updates.
						 *
						 * @since 2.5.0
						 *
						 * @param array $update_actions Array of plugin action links.
						 * @param array $plugin_info    Array of information for the last-handled plugin.
						 */
						$update_actions = apply_filters( 'tgmpa_update_bulk_plugins_complete_actions', $update_actions, $this->plugin_info );

						if ( ! empty( $update_actions ) ) {
							$this->feedback( implode( ' | ', (array) $update_actions ) );
						}
					}

					/* *********** DEPRECATED METHODS *********** */

					/**
					 * Flush header output buffer.
					 *
					 * @since      2.2.0
					 * @deprecated 2.5.0 use {@see Bulk_Upgrader_Skin::flush_output()} instead
					 * @see        Bulk_Upgrader_Skin::flush_output()
					 */
					public function before_flush_output() {
						_deprecated_function( __FUNCTION__, 'TGMPA 2.5.0', 'Bulk_Upgrader_Skin::flush_output()' );
						$this->flush_output();
					}

					/**
					 * Flush footer output buffer and iterate $this->i to make sure the
					 * installation strings reference the correct plugin.
					 *
					 * @since      2.2.0
					 * @deprecated 2.5.0 use {@see Bulk_Upgrader_Skin::flush_output()} instead
					 * @see        Bulk_Upgrader_Skin::flush_output()
					 */
					public function after_flush_output() {
						_deprecated_function( __FUNCTION__, 'TGMPA 2.5.0', 'Bulk_Upgrader_Skin::flush_output()' );
						$this->flush_output();
						++$this->i;
					}
				}
			}
		}
	}
}
