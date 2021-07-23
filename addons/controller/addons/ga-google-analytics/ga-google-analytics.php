<?php

namespace cookiebot_addons\controller\addons\ga_google_analytics;

use cookiebot_addons\controller\addons\Base_Cookiebot_Addon;

/**
 * Class Ga_Google_Analytics
 * @package cookiebot_addons\controller\addons\ga_google_analytics
 */
class Ga_Google_Analytics extends Base_Cookiebot_Addon {
	const ADDON_NAME                  = 'GA Google Analytics';
	const OPTION_NAME                 = 'ga_google_analytics';
	const PLUGIN_FILE_PATH            = 'ga-google-analytics/ga-google-analytics.php';
	const DEFAULT_COOKIE_TYPES        = array( 'statistics' );
	const DEFAULT_PLACEHOLDER_CONTENT = 'Please accept [renew_consent]%cookie_types[/renew_consent] cookies to watch this video.';

	public function load_addon_configuration() {

		//Remove GA Google action and replace it with our own
		if ( has_action( 'wp_head', 'ga_google_analytics_tracking_code' ) ) {
			$this->buffer_output->add_tag(
				'wp_head',
				10,
				array(
					'gtag('                                => $this->get_cookie_types(),
					'google-analytics'                     => $this->get_cookie_types(),
					'_gaq'                                 => $this->get_cookie_types(),
					'www.googletagmanager.com/gtag/js?id=' => $this->get_cookie_types(),
				),
				false
			);

		} elseif ( has_action( 'wp_footer', 'ga_google_analytics_tracking_code' ) ) {
			/**
			 * Consent not given - no cache
			 */
			$this->buffer_output->add_tag(
				'wp_footer',
				10,
				array(
					'gtag('                                => $this->get_cookie_types(),
					'google-analytics'                     => $this->get_cookie_types(),
					'_gaq'                                 => $this->get_cookie_types(),
					'www.googletagmanager.com/gtag/js?id=' => $this->get_cookie_types(),
				),
				false
			);
		}
	}

	/**
	 * Adds extra information under the label
	 *
	 * @return string
	 *
	 * @since 1.8.0
	 */
	public function get_extra_information() {
		return '';
	}

	/**
	 * Returns the url of WordPress SVN repository or another link where we can verify the plugin file.
	 *
	 * @return string
	 *
	 * @since 1.8.0
	 */
	public function get_svn_url() {
		return 'http://plugins.svn.wordpress.org/ga-google-analytics/trunk/ga-google-analytics.php';
	}
}
