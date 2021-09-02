<?php

namespace cybot\cookiebot;

/*
Plugin Name: Cookiebot | GDPR/CCPA Compliant Cookie Consent and Control
Plugin URI: https://cookiebot.com/
Description: Cookiebot is a cloud-driven solution that automatically controls cookies and trackers, enabling full GDPR/ePrivacy and CCPA compliance for websites.
Author: Cybot A/S
Version: 3.11.0
Author URI: http://cookiebot.com
Text Domain: cookiebot
Domain Path: /langs
*/

use cybot\cookiebot\addons\Cookiebot_Addons;
use cybot\cookiebot\admin_notices\Cookiebot_Recommendation_Notice;
use cybot\cookiebot\lib\Cookiebot_Activated;
use cybot\cookiebot\lib\Cookiebot_Deactivated;
use cybot\cookiebot\settings\Menu_Settings;
use cybot\cookiebot\settings\Network_Menu_Settings;
use cybot\cookiebot\widgets\Cookiebot_Declaration_Widget;
use Exception;
use RuntimeException;
use function cybot\cookiebot\lib\asset_url;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once 'vendor/autoload.php';
require_once 'src/lib/helper.php';

define( 'COOKIEBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'COOKIEBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

if ( ! class_exists( 'Cookiebot_WP' ) ) :

	final class Cookiebot_WP {
		const COOKIEBOT_PLUGIN_VERSION  = '3.11.0';
		const COOKIEBOT_MIN_PHP_VERSION = '5.6.0';

		/**
		 * @var   Cookiebot_WP The single instance of the class
		 * @since 1.0.0
		 */
		protected static $instance = null;

		/**
		 * Main Cookiebot_WP Instance
		 *
		 * Ensures only one instance of Cookiebot_WP is loaded or can be loaded.
		 *
		 * @return  Cookiebot_WP - Main instance
		 * @since   1.0.0
		 * @static
		 * @version 1.0.0
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Cookiebot_WP Constructor.
		 *
		 * @version 2.1.4
		 * @since   1.0.0
		 * @access  public
		 */
		public function __construct() {
			$this->throw_exception_if_php_version_is_incompatible();

			add_action( 'after_setup_theme', array( $this, 'cookiebot_init' ), 5 );
			register_activation_hook( __FILE__, array( new Cookiebot_Activated(), 'run' ) );
			register_deactivation_hook( __FILE__, array( new Cookiebot_Deactivated(), 'run' ) );

			$this->cookiebot_fix_plugin_conflicts();
		}

		private function throw_exception_if_php_version_is_incompatible() {
			if ( version_compare( PHP_VERSION, self::COOKIEBOT_MIN_PHP_VERSION, '<' ) ) {
				$message = sprintf(
				// translators: The placeholder is for the COOKIEBOT_MIN_PHP_VERSION constant
					__( 'The Cookiebot plugin requires PHP version %s or greater.', 'cookiebot' ),
					self::COOKIEBOT_MIN_PHP_VERSION
				);
				throw new RuntimeException( $message );
			}
		}

		public function cookiebot_init() {
			Cookiebot_Addons::instance();

			if ( is_admin() ) {

				//Adding menu to WP admin
				( new Menu_Settings() )->add_menu();

				if ( is_multisite() ) {
					( new Network_Menu_Settings() )->add_menu();
				}

				//Adding dashboard widgets
				add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );

				( new Cookiebot_Recommendation_Notice() )->register_hooks();

				//Check if we should show cookie consent banner on admin pages
				if ( ! $this->cookiebot_disabled_in_admin() ) {
					//adding cookie banner in admin area too
					add_action( 'admin_head', array( $this, 'add_js' ), - 9999 );
				}
			}

			//Include integration to WP Consent Level API if available
			if ( $this->is_wp_consent_api_active() ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'cookiebot_enqueue_consent_api_scripts' ) );
			}

			// Set up localisation
			load_plugin_textdomain( 'cookiebot', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

			//add JS
			add_action( 'wp_head', array( $this, 'add_js' ), - 9997 );
			add_action( 'wp_head', array( $this, 'add_GTM' ), - 9998 );
			add_action( 'wp_head', array( $this, 'add_GCM' ), - 9999 );
			add_shortcode( 'cookie_declaration', array( $this, 'show_declaration' ) );

			//Add filter if WP rocket is enabled
			if ( defined( 'WP_ROCKET_VERSION' ) ) {
				add_filter( 'rocket_minify_excluded_external_js', array( $this, 'wp_rocket_exclude_external_js' ) );
			}

			//Add filter
			add_filter( 'sgo_javascript_combine_excluded_external_paths', array( $this, 'sgo_exclude_external_js' ) );

			//Automatic update plugin
			if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
				add_filter( 'auto_update_plugin', array( $this, 'automatic_updates' ), 10, 2 );
			}

			//Loading widgets
			add_action( 'widgets_init', array( $this, 'register_widgets' ) );

			//Add Gutenberg block
			add_action( 'init', array( $this, 'gutenberg_block_setup' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'gutenberg_block_admin_assets' ) );
		}


		/**
		 * Cookiebot_WP Setup Gutenberg block
		 *
		 * @version 3.7.0
		 * @since       3.7.0
		 */
		public function gutenberg_block_setup() {
			if ( ! function_exists( 'register_block_type' ) ) {
				return; //gutenberg not active
			}

			register_block_type(
				'cookiebot/cookie-declaration',
				array(
					'render_callback' => array( $this, 'block_cookie_declaration' ),
				)
			);
		}

		/**
		 * Cookiebot_WP Add block JS
		 *
		 * @version 3.7.1
		 * @since       3.7.1
		 */
		public function gutenberg_block_admin_assets() {
			//Add Gutenberg Widget
			wp_enqueue_script(
				'cookiebot-declaration',
				asset_url( 'js/backend/gutenberg/cookie-declaration-gutenberg-block.js' ),
				array( 'wp-blocks', 'wp-i18n', 'wp-element' ), // Required scripts for the block
				self::COOKIEBOT_PLUGIN_VERSION,
				false
			);
		}

		/**
		 * Cookiebot_WP Render Cookiebot Declaration as Gutenberg block
		 *
		 * @version 3.7.0
		 * @since       3.7.0
		 */
		public function block_cookie_declaration() {
			return $this->show_declaration();
		}

		/**
		 * Cookiebot_WP Load text domain
		 *
		 * @version 2.0.0
		 * @since       2.0.0
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'cookiebot', false, basename( dirname( __FILE__ ) ) . '/langs' );
		}

		/**
		 * Cookiebot_WP Register widgets
		 *
		 * @version 2.5.0
		 * @since   2.5.0
		 */
		public function register_widgets() {
			register_widget( Cookiebot_Declaration_Widget::class );
		}

		/**
		 * Cookiebot_WP Add dashboard widgets to admin
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */

		public function add_dashboard_widgets() {
			wp_add_dashboard_widget(
				'cookiebot_status',
				esc_html__( 'Cookiebot Status', 'cookiebot' ),
				array(
					$this,
					'dashboard_widget_status',
				)
			);
		}

		/**
		 * Cookiebot_WP Output Dashboard Status Widget
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function dashboard_widget_status() {
			$cbid = $this->get_cbid();
			if ( empty( $cbid ) ) {
				echo '<p>' . esc_html__( 'You need to enter your Cookiebot ID.', 'cookiebot' ) . '</p>';
				echo '<p><a href="options-general.php?page=cookiebot">';
				echo esc_html__( 'Update your Cookiebot ID', 'cookiebot' );
				echo '</a></p>';
			} else {
				echo '<p>' . esc_html_e( 'Your Cookiebot is working!', 'cookiebot' ) . '</p>';
			}
		}

		/**
		 * Cookiebot_WP Automatic update plugin if activated
		 *
		 * @version 2.2.0
		 * @since       1.5.0
		 */
		public function automatic_updates( $update, $item ) {
			//Do not update from subsite on a multisite installation
			if ( is_multisite() && ! is_main_site() ) {
				return $update;
			}

			//Check if we have everything we need
			$item = (array) $item;
			if ( ! isset( $item['new_version'] ) || ! isset( $item['slug'] ) ) {
				return $update;
			}

			//It is not Cookiebot
			if ( $item['slug'] !== 'cookiebot' ) {
				return $update;
			}

			// Check if cookiebot autoupdate is disabled
			if ( ! get_option( 'cookiebot-autoupdate', false ) ) {
				return $update;
			}

			// Check if multisite autoupdate is disabled
			if ( is_multisite() && ! get_site_option( 'cookiebot-autoupdate', false ) ) {
				return $update;
			}

			return true;
		}


		/**
		 * Cookiebot_WP Get list of supported languages
		 *
		 * @version 1.4.0
		 * @since       1.4.0
		 */
		public static function get_supported_languages() {
			$supported_languages       = array();
			$supported_languages['nb'] = __( 'Norwegian Bokmål', 'cookiebot' );
			$supported_languages['tr'] = __( 'Turkish', 'cookiebot' );
			$supported_languages['de'] = __( 'German', 'cookiebot' );
			$supported_languages['cs'] = __( 'Czech', 'cookiebot' );
			$supported_languages['da'] = __( 'Danish', 'cookiebot' );
			$supported_languages['sq'] = __( 'Albanian', 'cookiebot' );
			$supported_languages['he'] = __( 'Hebrew', 'cookiebot' );
			$supported_languages['ko'] = __( 'Korean', 'cookiebot' );
			$supported_languages['it'] = __( 'Italian', 'cookiebot' );
			$supported_languages['nl'] = __( 'Dutch', 'cookiebot' );
			$supported_languages['vi'] = __( 'Vietnamese', 'cookiebot' );
			$supported_languages['ta'] = __( 'Tamil', 'cookiebot' );
			$supported_languages['is'] = __( 'Icelandic', 'cookiebot' );
			$supported_languages['ro'] = __( 'Romanian', 'cookiebot' );
			$supported_languages['si'] = __( 'Sinhala', 'cookiebot' );
			$supported_languages['ca'] = __( 'Catalan', 'cookiebot' );
			$supported_languages['bg'] = __( 'Bulgarian', 'cookiebot' );
			$supported_languages['uk'] = __( 'Ukrainian', 'cookiebot' );
			$supported_languages['zh'] = __( 'Chinese', 'cookiebot' );
			$supported_languages['en'] = __( 'English', 'cookiebot' );
			$supported_languages['ar'] = __( 'Arabic', 'cookiebot' );
			$supported_languages['hr'] = __( 'Croatian', 'cookiebot' );
			$supported_languages['th'] = __( 'Thai', 'cookiebot' );
			$supported_languages['el'] = __( 'Greek', 'cookiebot' );
			$supported_languages['lt'] = __( 'Lithuanian', 'cookiebot' );
			$supported_languages['pl'] = __( 'Polish', 'cookiebot' );
			$supported_languages['lv'] = __( 'Latvian', 'cookiebot' );
			$supported_languages['fr'] = __( 'French', 'cookiebot' );
			$supported_languages['id'] = __( 'Indonesian', 'cookiebot' );
			$supported_languages['mk'] = __( 'Macedonian', 'cookiebot' );
			$supported_languages['et'] = __( 'Estonian', 'cookiebot' );
			$supported_languages['pt'] = __( 'Portuguese', 'cookiebot' );
			$supported_languages['ga'] = __( 'Irish', 'cookiebot' );
			$supported_languages['ms'] = __( 'Malay', 'cookiebot' );
			$supported_languages['sl'] = __( 'Slovenian', 'cookiebot' );
			$supported_languages['ru'] = __( 'Russian', 'cookiebot' );
			$supported_languages['ja'] = __( 'Japanese', 'cookiebot' );
			$supported_languages['hi'] = __( 'Hindi', 'cookiebot' );
			$supported_languages['sk'] = __( 'Slovak', 'cookiebot' );
			$supported_languages['es'] = __( 'Spanish', 'cookiebot' );
			$supported_languages['sv'] = __( 'Swedish', 'cookiebot' );
			$supported_languages['sr'] = __( 'Serbian', 'cookiebot' );
			$supported_languages['fi'] = __( 'Finnish', 'cookiebot' );
			$supported_languages['eu'] = __( 'Basque', 'cookiebot' );
			$supported_languages['hu'] = __( 'Hungarian', 'cookiebot' );
			asort( $supported_languages, SORT_LOCALE_STRING );

			return $supported_languages;
		}

		/**
		 * Cookiebot_WP Add Cookiebot JS to <head>
		 *
		 * @version 3.9.0
		 * @since   1.0.0
		 */
		public function add_js( $printTag = true ) {
			$cbid = $this->get_cbid();
			if ( ! empty( $cbid ) && ! defined( 'COOKIEBOT_DISABLE_ON_PAGE' ) ) {
				if ( is_multisite() && get_site_option( 'cookiebot-nooutput', false ) ) {
					return; //Is multisite - and disabled output is checked as network setting
				}

				if ( get_option( 'cookiebot-nooutput', false ) ) {
					return; //Do not show JS - output disabled
				}

				if ( $this->get_cookie_blocking_mode() == 'auto' && $this->can_current_user_edit_theme() && $printTag !== false && get_site_option( 'cookiebot-output-logged-in' ) == false ) {
					return;
				}

				$lang = $this->get_language();
				if ( ! empty( $lang ) ) {
					$lang = ' data-culture="' . strtoupper( $lang ) . '"'; //Use data-culture to define language
				}

				if ( ! is_multisite() || get_site_option( 'cookiebot-script-tag-uc-attribute', 'custom' ) == 'custom' ) {
					$tagAttr = get_option( 'cookiebot-script-tag-uc-attribute', 'async' );
				} else {
					$tagAttr = get_site_option( 'cookiebot-script-tag-uc-attribute' );
				}

				if ( $this->get_cookie_blocking_mode() == 'auto' ) {
					$tagAttr = 'data-blockingmode="auto"';
				}

				if ( get_option( 'cookiebot-gtm' ) != false ) {
					if ( empty( get_option( 'cookiebot-data-layer' ) ) ) {
						$data_layer = 'data-layer-name="dataLayer"';
					} else {
						$data_layer = 'data-layer-name="' . get_option( 'cookiebot-data-layer' ) . '"';
					}
				} else {
					$data_layer = '';
				}

				$iab = ( get_option( 'cookiebot-iab' ) != false ) ? 'data-framework="IAB"' : '';

				$ccpa = ( get_option( 'cookiebot-ccpa' ) != false ) ? 'data-georegions="{\'region\':\'US-06\',\'cbid\':\'' . get_option( 'cookiebot-ccpa-domain-group-id' ) . '\'}"' : '';

				$tag = '<script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" ' . $iab . ' ' . $ccpa . ' ' . $data_layer . ' data-cbid="' . $cbid . '"' . $lang . ' type="text/javascript" ' . $tagAttr . '></script>';
				if ( $printTag === false ) {
					return $tag;
				}
				echo $tag;
			}
		}

		/**
		 * Cookiebot_WP Add Google Tag Manager JS to <head>
		 *
		 * @version 3.8.1
		 * @since   3.8.1
		 */

		public function add_GTM( $printTag = true ) {

			if ( get_option( 'cookiebot-gtm' ) != false ) {

				if ( empty( get_option( 'cookiebot-data-layer' ) ) ) {
					$data_layer = 'dataLayer';
				} else {
					$data_layer = get_option( 'cookiebot-data-layer' );
				}

				$GTM = '<script>';
				if ( get_option( 'cookiebot-iab' ) ) {
					$GTM .= 'window ["gtag_enable_tcf_support"] = true;';
				}

				$GTM .= "(function (w, d, s, l, i) {
				w[l] = w[l] || []; w[l].push({'gtm.start':new Date().getTime(), event: 'gtm.js'}); 
			  var f = d.getElementsByTagName(s)[0],  j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : ''; 
			  j.async = true; j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl; 
			  f.parentNode.insertBefore(j, f);})
			  (window, document, 'script', '" . $data_layer . "', '" . get_option( 'cookiebot-gtm-id' ) . "');";

				$GTM .= '</script>';

				if ( $printTag === false ) {
					return $GTM;
				}

				echo $GTM;
			}
		}

		/**
		 * Cookiebot_WP Add Google Consent Mode JS to <head>
		 *
		 * @version 3.8.1
		 * @since   3.8.1
		 */

		public function add_GCM( $printTag = true ) {

			if ( get_option( 'cookiebot-gcm' ) != false ) {

				if ( empty( get_option( 'cookiebot-data-layer' ) ) ) {
					$data_layer = 'dataLayer';
				} else {
					$data_layer = get_option( 'cookiebot-data-layer' );
				}

				$GCM = '<script data-cookieconsent="ignore">
			(function(w,d,l){w[l]=w[l]||[];function gtag(){w[l].push(arguments)};
			gtag("consent","default",{ad_storage:d,analytics_storage:d,wait_for_update:500,});
			gtag("set", "ads_data_redaction", true);})(window,"denied","' . $data_layer . '");';

				$GCM .= '</script>';

				if ( $printTag === false ) {
					return $GCM;
				}

				echo $GCM;
			}
		}

		/**
		 * Returns true if an user is logged in and has an edit_themes capability
		 *
		 * @return bool
		 *
		 * @since 3.3.1
		 * @version 3.4.1
		 */
		public function can_current_user_edit_theme() {
			if ( is_user_logged_in() ) {
				if ( current_user_can( 'edit_themes' ) ) {
					return true;
				}

				if ( current_user_can( 'edit_pages' ) ) {
					return true;
				}

				if ( current_user_can( 'edit_posts' ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Cookiebot_WP Output declation shortcode [cookie_declaration]
		 * Support attribute lang="LANGUAGE_CODE". Eg. lang="en".
		 *
		 * @version 2.2.0
		 * @since   1.0.0
		 */
		public function show_declaration( $atts = array() ) {
			$cbid = $this->get_cbid();
			$lang = '';
			if ( ! empty( $cbid ) ) {

				$atts = shortcode_atts(
					array(
						'lang' => $this->get_language(),
					),
					$atts,
					'cookie_declaration'
				);

				if ( ! empty( $atts['lang'] ) ) {
					$lang = ' data-culture="' . strtoupper( $atts['lang'] ) . '"'; //Use data-culture to define language
				}

				if ( ! is_multisite() || get_site_option( 'cookiebot-script-tag-cd-attribute', 'custom' ) == 'custom' ) {
					$tagAttr = get_option( 'cookiebot-script-tag-cd-attribute', 'async' );
				} else {
					$tagAttr = get_site_option( 'cookiebot-script-tag-cd-attribute' );
				}

				return '<script id="CookieDeclaration" src="https://consent.cookiebot.com/' . $cbid . '/cd.js"' . $lang . ' type="text/javascript" ' . $tagAttr . '></script>';
			} else {
				return esc_html__( 'Please add your Cookiebot ID to show Cookie Declarations', 'cookiebot' );
			}
		}

		/**
		 * @return string
		 */
		public static function get_cbid() {
			$network_setting = (string) get_site_option( 'cookiebot-cbid', '' );
			$setting         = (string) get_option( 'cookiebot-cbid', $network_setting );

			return empty( $setting ) ? $network_setting : $setting;
		}

		/**
		 * @return string
		 */
		public static function get_cookie_blocking_mode() {
			$allowed_modes   = array( 'auto', 'manual' );
			$network_setting = (string) get_site_option( 'cookiebot-cookie-blocking-mode', 'manual' );
			$setting         = (string) get_option( 'cookiebot-cookie-blocking-mode', $network_setting );

			return in_array( $setting, $allowed_modes, true ) ? $setting : 'manual';
		}

		/**
		 * Cookiebot_WP Check if Cookiebot is active in admin
		 *
		 * @version 3.1.0
		 * @since       3.1.0
		 */
		public static function cookiebot_disabled_in_admin() {
			if ( is_multisite() && get_site_option( 'cookiebot-nooutput-admin', false ) ) {
				return true;
			} elseif ( get_option( 'cookiebot-nooutput-admin', false ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Cookiebot_WP Get the language code for Cookiebot
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function get_language( $onlyFromSetting = false ) {
			// Get language set in setting page - if empty use WP language info
			$lang = get_option( 'cookiebot-language' );
			if ( ! empty( $lang ) ) {
				if ( $lang != '_wp' ) {
					return $lang;
				}
			}

			if ( $onlyFromSetting ) {
				return $lang; //We want only to get if already set
			}

			//Language not set - use WP language
			if ( $lang == '_wp' ) {
				$lang = get_bloginfo( 'language' ); //Gets language in en-US format
				if ( ! empty( $lang ) ) {
					list( $lang ) = explode( '-', $lang ); //Changes format from eg. en-US to en.
				}
			}

			return $lang;
		}

		/**
		 * Cookiebot_WP Adding Cookiebot domain(s) to exclude list for WP Rocket minification.
		 *
		 * @version 1.6.1
		 * @since   1.6.1
		 */
		public function wp_rocket_exclude_external_js( $external_js_hosts ) {
			$external_js_hosts[] = 'consent.cookiebot.com';      // Add cookiebot domains
			$external_js_hosts[] = 'consentcdn.cookiebot.com';

			return $external_js_hosts;
		}

		/**
		 * Cookiebot_WP Adding Cookiebot domain(s) to exclude list for SGO minification.
		 *
		 * @version 3.6.5
		 * @since   3.6.5
		 */
		public function sgo_exclude_external_js( $exclude_list ) {
			//Uses same format as WP Rocket - for now we just use WP Rocket function
			return wp_rocket_exclude_external_js( $exclude_list );
		}


		/**
		 * Cookiebot_WP Check if WP Cookie Consent API is active
		 *
		 * @version 3.5.0
		 * @since       3.5.0
		 */
		public function is_wp_consent_api_active() {
			if ( class_exists( 'WP_CONSENT_API' ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Cookiebot_WP Default consent level mappings
		 *
		 * @version 3.5.0
		 * @since   3.5.0
		 */
		public function get_default_wp_consent_api_mapping() {
			return array(
				'n=1;p=1;s=1;m=1' =>
					array(
						'preferences'          => 1,
						'statistics'           => 1,
						'statistics-anonymous' => 0,
						'marketing'            => 1,
					),
				'n=1;p=1;s=1;m=0' =>
					array(
						'preferences'          => 1,
						'statistics'           => 1,
						'statistics-anonymous' => 1,
						'marketing'            => 0,
					),
				'n=1;p=1;s=0;m=1' =>
					array(
						'preferences'          => 1,
						'statistics'           => 0,
						'statistics-anonymous' => 0,
						'marketing'            => 1,
					),
				'n=1;p=1;s=0;m=0' =>
					array(
						'preferences'          => 1,
						'statistics'           => 0,
						'statistics-anonymous' => 0,
						'marketing'            => 0,
					),
				'n=1;p=0;s=1;m=1' =>
					array(
						'preferences'          => 0,
						'statistics'           => 1,
						'statistics-anonymous' => 0,
						'marketing'            => 1,
					),
				'n=1;p=0;s=1;m=0' =>
					array(
						'preferences'          => 0,
						'statistics'           => 1,
						'statistics-anonymous' => 0,
						'marketing'            => 0,
					),
				'n=1;p=0;s=0;m=1' =>
					array(
						'preferences'          => 0,
						'statistics'           => 0,
						'statistics-anonymous' => 0,
						'marketing'            => 1,
					),
				'n=1;p=0;s=0;m=0' =>
					array(
						'preferences'          => 0,
						'statistics'           => 0,
						'statistics-anonymous' => 0,
						'marketing'            => 0,
					),
			);

		}

		/**
		 * Cookiebot_WP Get the mapping between Consent Level API and Cookiebot
		 * Returns array where key is the consent level api category and value
		 * is the mapped Cookiebot category.
		 *
		 * @version 3.5.0
		 * @since   3.5.0
		 */
		public function get_wp_consent_api_mapping() {
			$mDefault = $this->get_default_wp_consent_api_mapping();
			$mapping  = get_option( 'cookiebot-consent-mapping', $mDefault );

			$mapping = ( '' === $mapping ) ? $mDefault : $mapping;

			foreach ( $mDefault as $k => $v ) {
				if ( ! isset( $mapping[ $k ] ) ) {
					$mapping[ $k ] = $v;
				} else {
					foreach ( $v as $vck => $vcv ) {
						if ( ! isset( $mapping[ $k ][ $vck ] ) ) {
							$mapping[ $k ][ $vck ] = $vcv;
						}
					}
				}
			}

			return $mapping;
		}

		/**
		 * Cookiebot_WP Enqueue JS for integration with WP Consent Level API
		 *
		 * @version 3.5.0
		 * @since   3.5.0
		 */
		public function cookiebot_enqueue_consent_api_scripts() {
			wp_register_script(
				'cookiebot-wp-consent-level-api-integration',
				asset_url( 'js/frontend/cookiebot-wp-consent-level-api-integration.js' ),
				null,
				self::COOKIEBOT_PLUGIN_VERSION,
				false
			);
			wp_enqueue_script( 'cookiebot-wp-consent-level-api-integration' );
			wp_localize_script( 'cookiebot-wp-consent-level-api-integration', 'cookiebot_category_mapping', $this->get_wp_consent_api_mapping() );
		}

		/**
		 * Cookiebot_WP Fix plugin conflicts related to Cookiebot
		 *
		 * @version 3.2.0
		 * @since   3.3.0
		 */
		public function cookiebot_fix_plugin_conflicts() {
			//Fix for Divi Page Builder
			//Disabled - using another method now (can_current_user_edit_theme())
			//add_action( 'wp', array( $this, '_cookiebot_plugin_conflict_divi' ), 100 );

			//Fix for Elementor and WPBakery Page Builder Builder
			//Disabled - using another method now (can_current_user_edit_theme())
			//add_filter( 'script_loader_tag', array( $this, '_cookiebot_plugin_conflict_scripttags' ), 10, 2 );
		}

		/**
		 * Cookiebot_WP Fix Divi builder conflict when blocking mode is in auto.
		 *
		 * @version 3.2.0
		 * @since   3.2.0
		 */
		public function _cookiebot_plugin_conflict_divi() {
			if ( defined( 'ET_FB_ENABLED' ) ) {
				if ( ET_FB_ENABLED &&
				     $this->cookiebot_disabled_in_admin() &&
				     $this->get_cookie_blocking_mode() == 'auto' ) {

					define( 'COOKIEBOT_DISABLE_ON_PAGE', true ); //Disable Cookiebot on the current page

				}
			}
		}

		/**
		 * Cookiebot_WP Fix plugin conflicts with page builders - whitelist JS files in automode
		 *
		 * @version 3.2.0
		 * @since   3.3.0
		 */
		public function _cookiebot_plugin_conflict_scripttags( $tag, $handle ) {

			//Check if Elementor Page Builder active
			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				if ( in_array(
					$handle,
					array(
						'jquery-core',
						'elementor-frontend-modules',
						'elementor-frontend',
						'wp-tinymce',
						'underscore',
						'backbone',
						'backbone-marionette',
						'backbone-radio',
						'elementor-common-modules',
						'elementor-dialog',
						'elementor-common',
					)
				) ) {
					$tag = str_replace( '<script ', '<script data-cookieconsent="ignore" ', $tag );
				}
			}

			//Check if WPBakery Page Builder active
			if ( defined( 'WPB_VC_VERSION' ) ) {
				if ( in_array(
					$handle,
					array(
						'jquery-core',
						'jquery-ui-core',
						'jquery-ui-sortable',
						'jquery-ui-mouse',
						'jquery-ui-widget',
						'vc_editors-templates-preview-js',
						'vc-frontend-editor-min-js',
						'vc_inline_iframe_js',
						'wpb_composer_front_js',
					)
				) ) {
					$tag = str_replace( '<script ', '<script data-cookieconsent="ignore" ', $tag );
				}
			}

			return $tag;
		}

	}
endif;


/**
 * @param  string|string[]  $type
 *
 * @return string
 */
function cookiebot_assist( $type = 'statistics' ) {
	$type_array = array_filter(
		is_array( $type ) ? $type : array( $type ),
		function ( $type ) {
			return in_array( $type, array( 'marketing', 'statistics', 'preferences' ), true );
		}
	);

	if ( count( $type_array ) > 0 ) {
		return ' type="text/plain" data-cookieconsent="' . implode( ',', $type ) . '"';
	}

	return '';
}


/**
 * Helper function to check if cookiebot is active.
 * Useful for other plugins adding support for Cookiebot.
 *
 * @return  string
 * @since   1.2
 * @version 2.2.2
 */
function cookiebot_active() {
	$cbid = Cookiebot_WP::get_cbid();
	if ( ! empty( $cbid ) ) {
		return true;
	}

	return false;
}


if ( ! function_exists( 'cookiebot' ) ) {
	/**
	 * Returns the main instance of Cookiebot_WO to prevent the need to use globals.
	 *
	 * @return  Cookiebot_WP
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	function cookiebot() {
		return Cookiebot_WP::instance();
	}
}

cookiebot();
