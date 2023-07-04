<?php
/**
 * @var string $cookie_categories_disabled
 * @var string $gcm_enabled_option
 * @var string $gcm_url_passthrough_option
 * @var string $auto_disabled
 * @var bool $is_preferences
 * @var bool $is_statistics
 * @var bool $is_marketing
 */
?>
<div class="cb-general__consent__mode">
	<h2 class="cb-general__info__title">
		<?php esc_html_e( 'What is Google Consent Mode and why should you enable it?', 'cookiebot' ); ?>
	</h2>
	<p class="cb-general__info__text">
		<?php esc_html_e( 'Google Consent Mode is a way for your website to measure conversions and get analytics insights while being fully GDPR-compliant when using services like Google Analytics, Google Tag Manager (GTM) and Google Ads.', 'cookiebot' ); ?>
	</p>
	<p class="cb-general__info__text">
		<?php esc_html_e( 'Cookiebot consent managment platform (CMP) and Google Consent Mode integrate seamlessly to offer you plug-and-play compliance and streamlined use of all Google\'s services in one easy solution.', 'cookiebot' ); ?>
	</p>
	<a class="cb-btn cb-link-btn" target="_blank" rel="noopener"
	   href="https://support.cookiebot.com/hc/en-us/articles/360016047000-Cookiebot-and-Google-Consent-Mode">
		<?php esc_html_e( 'Read more about Cookiebot CMP and Google Consent Mode', 'cookiebot' ); ?>
	</a>
</div>

<div class="cb-settings__config__item">
	<div class="cb-settings__config__content">
		<h3 class="cb-settings__config__subtitle">
			<?php esc_html_e( 'Google Consent Mode:', 'cookiebot' ); ?>
		</h3>
		<p class="cb-general__info__text">
			<?php esc_html_e( 'Enable Google Consent Mode with default settings on your WordPress page.', 'cookiebot' ); ?>
		</p>
		<a class="cb-btn cb-link-btn" target="_blank" rel="noopener"
		   href="https://support.cookiebot.com/hc/en-us/articles/360016047000-Cookiebot-and-Google-Consent-Mode">
			<?php esc_html_e( 'Read more', 'cookiebot' ); ?>
		</a>
	</div>
	<div class="cb-settings__config__data">
		<div class="cb-settings__config__data__inner">
			<label class="switch-checkbox" for="gcm">
				<input
					type="checkbox"
					name="cookiebot-gcm"
					id="gcm"
					value="1" <?php checked( 1, $gcm_enabled_option ); ?>>
				<div class="switcher"></div>
				<?php esc_html_e( 'Google Consent Mode', 'cookiebot' ); ?>
				<?php echo ( $gcm_enabled_option === '1' ) ? 'enabled' : 'disabled'; ?>
			</label>
			<input type="hidden" name="cookiebot-gcm-first-run" value="1">
		</div>
	</div>
</div>

<div class="cb-settings__config__item"<?php echo ( $gcm_enabled_option === '1' ) ? '' : ' style="display: none"'; ?>>
	<div class="cb-settings__config__content">
		<h3 class="cb-settings__config__subtitle">
			<?php esc_html_e( 'URL passthrough:', 'cookiebot' ); ?>
		</h3>
		<p class="cb-general__info__text">
			<?php esc_html_e( 'This feature will allow you to pass data between pages when not able to use cookies without/prior consent.', 'cookiebot' ); ?>
		</p>
		<a class="cb-btn cb-link-btn" target="_blank" rel="noopener"
		   href="https://developers.google.com/tag-platform/devguides/consent#passing_ad_click_client_id_and_session_id_information_in_urls">
			<?php esc_html_e( 'Read more', 'cookiebot' ); ?>
		</a>
	</div>
	<div class="cb-settings__config__data">
		<div class="cb-settings__config__data__inner">
			<label class="switch-checkbox" for="gcm-url-pasthrough">
				<input
					type="checkbox"
					name="cookiebot-gcm-url-passthrough"
					id="gcm-url-pasthrough"
					value="1" <?php checked( 1, $gcm_url_passthrough_option ); ?>>
				<div class="switcher"></div>
				<?php esc_html_e( 'URL passthrough', 'cookiebot' ); ?>
				<?php echo ( $gcm_url_passthrough_option === '1' ) ? 'enabled' : 'disabled'; ?>
			</label>
		</div>
	</div>
</div>
