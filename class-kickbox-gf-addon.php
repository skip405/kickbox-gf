<?php

use Skip405\Kickbox\WordPress\Client as Kickbox_Client;

GFForms::include_addon_framework();

class Kickbox_GF_Addon extends GFAddOn {
	protected $_version                  = KICKBOX_GF_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug                     = 'kickbox';
	protected $_path                     = 'kickbox-gf/kickbox-gf.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Gravity Forms Kickbox Add-On';
	protected $_short_title              = 'Kickbox';

	private static $_instance                    = null;
	private static $_suggested_email_placeholder = '%suggested-email%';

	/**
	 * Get an instance of this class.
	 *
	 * @return Kickbox_GF_Addon
	 */
	public static function get_instance() : Kickbox_GF_Addon {
		if ( null === self::$_instance ) {
			self::$_instance = new Kickbox_GF_Addon();
		}

		return self::$_instance;
	}

	/**
	 * Get a suggested email placeholder.
	 *
	 * @return string
	 */
	public static function get_suggested_email_placeholder() : string {
		return self::$_suggested_email_placeholder;
	}

	/**
	 * Handles hooks and loading of language files.
	 *
	 * @return void
	 */
	public function init() {
		parent::init();

		add_action( 'gform_field_advanced_settings', array( $this, 'add_field_setting' ), 10, 2 );
		add_filter( 'gform_validation', array( $this, 'verify_via_kickbox' ), apply_filters( 'kickbox_gf_verification_priority', 11 ) );
		add_action( 'gform_editor_js', array( $this, 'editor_script' ) );

		add_action( Kickbox_GF_Cache::CRON_ACTION, array( 'GFKickboxCache', 'prune_old_verifications' ) );

		if ( ! wp_next_scheduled( Kickbox_GF_Cache::CRON_ACTION ) ) {
			wp_schedule_event( time(), 'daily', Kickbox_GF_Cache::CRON_ACTION );
		}

		do_action( 'kickbox_gf_init' );
	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() : array {
		return array(
			array(
				'title'  => esc_html__( 'Kickbox Settings', 'kickbox-gf' ),
				'fields' => array(
					array(
						'name'    => 'api_key',
						'tooltip' => esc_html__( 'Enter your API key here', 'kickbox-gf' ),
						/* translators: 1: Number of days */
						'label'   => sprintf(
							'%1$s (<small><a href="%2$s" target="_blank">%3$s</a></small>)',
							esc_html__( 'Kickbox API Key', 'kickbox-gf' ),
							'https://docs.kickbox.com/docs/using-the-api#getting-an-api-key',
							esc_html__( 'Documentation', 'kickbox-gf' )
						),
						'type'    => 'text',
						'class'   => 'small',
					),
					array(
						'label'       => esc_html__( 'Disable Kickbox Verification', 'kickbox-gf' ),
						'description' => esc_html__( 'Tick this if you want to disable Kickbox Verifications globally, even if they are enabled in forms.', 'kickbox-gf' ),
						'type'        => 'checkbox',
						'name'        => 'globally_disabled',
						'choices'     => array(
							array(
								'label' => esc_html__( 'Disabled', 'kickbox-gf' ),
								'name'  => 'globally_disabled',
							),
						),
					),
				),
			),
			$this->get_verification_configuration_section(),
			$this->get_custom_configuration_settings_section(),
			array(
				'title'       => esc_html__( 'Verification Results Caching Settings', 'kickbox-gf' ),
				'description' => esc_html__( 'Enable this setting if you want to cache Kickbox Verification Results and store them in the database. This will allow you to avoid excessive checking when multiple submissions from the same user are expected.', 'kickbox-gf' ),
				'fields'      => array(
					array(
						'label'   => esc_html__( 'Enable Kickbox Verification Results Caching', 'kickbox-gf' ),
						'type'    => 'checkbox',
						'name'    => 'results_caching_enabled',
						'choices' => array(
							array(
								'label' => esc_html__( 'Enabled', 'kickbox-gf' ),
								'name'  => 'results_caching_enabled',
							),
						),
					),
					array(
						'name'          => 'results_caching_duration',
						'label'         => esc_html__( 'Caching Duration (in days)', 'kickbox-gf' ),
						'description'   => sprintf(
							/* translators: 1: Number of days */
							esc_html__( 'Default duration is %1$s days.', 'kickbox-gf' ),
							'7'
						),
						'type'          => 'text',
						'default_value' => '7',
						'min'           => 0,
						'input_type'    => 'number',
					),
					array(
						'label'       => esc_html_x( 'Cache Domains', 'Cache is a verb here', 'kickbox-gf' ),
						'description' => sprintf(
							/* translators: 1, 2, 4: Email addresses 3: Domain name */
							esc_html__( 'Emails are used as cache keys. By default this will verify both %1$s and %2$s. When enabled, verifications will be cached for %3$s and %4$s will not be verified.', 'kickbox-gf' ),
							'<code>john.doe@example.com</code>',
							'<code>jane.doe@example.com</code>',
							'<code>example.com</code>',
							'<code>john.smith@example.com</code>'
						),
						'type'        => 'checkbox',
						'name'        => 'domain_caching_enabled',
						'choices'     => array(
							array(
								'label' => esc_html__( 'Enabled', 'kickbox-gf' ),
								'name'  => 'domain_caching_enabled',
							),
						),
					),
				),
			),
			$this->get_error_messages_section(),
		);
	}

	/**
	 * Outputs verification configuration types description.
	 *
	 * @return void
	 */
	public function settings_verification_setup_type() {
		printf(
			'<p>%s</p>',
			esc_html__( 'Verification configuration allows you to choose what you can afford to receive as a valid response from Kickbox.', 'kickbox-gf' )
		);
		?>
		<ul>
			<li>
				<b>
					<?php
					/* translators: Name of a predefined configuration choice */
					esc_html_e( 'Strict', 'kickbox-gf' );
					?>
					:
				</b>
				<?php
				printf(
				/* translators: 1: Response result type named 'deliverable'
					2: Sendex value
				*/
					esc_html__( 'allows %1$s emails only with Sendex of %2$s and higher.', 'kickbox-gf' ),
					'<code>deliverable</code>',
					'<code>0.7</code>'
				);
				?>
			</li>
			<li>
				<b>
					<?php
					/* translators: Name of a predefined configuration choice */
					esc_html_e( 'Permissive', 'kickbox-gf' );
					?>
					:
				</b>
				<?php
				printf(
				/* translators: 1: Response result type named 'deliverable'
					2: Response result type named 'risky'
					3: Response result type named 'unknown'
					4: Sendex value
				*/
					esc_html__( 'allows emails with result types %1$s, %2$s and %3$s with Sendex of %4$s and higher.', 'kickbox-gf' ),
					'<code>deliverable</code>',
					'<code>risky</code>',
					'<code>unknown</code>',
					'<code>0.4</code>'
				);
				?>
			</li>
			<li>
				<b>
					<?php
					/* translators: Name of a predefined configuration choice */
					esc_html_e( 'Custom', 'kickbox-gf' );
					?>
					:
				</b>
				<?php esc_html_e( 'allows you to set everything up (see section below).', 'kickbox-gf' ); ?>
			</li>
			<?php
			$addon = get_kickbox_gf_addon();
			$form  = $addon->get_current_form();

			if ( ! empty( $form ) ) {
				?>
				<li>
					<b>
						<?php
						esc_html_e( 'Disabled', 'kickbox-gf' );
						?>
						:
					</b>
					<?php esc_html_e( 'doesn\'t override the configuration from the plugin settings.', 'kickbox-gf' ); ?>
				</li>
				<?php
			}
			?>
		</ul>
		<?php
	}

	/**
	 * Configures the settings which should be rendered on the Form Settings > Kickbox tab.
	 *
	 * @param $form
	 *
	 * @return array
	 */
	public function form_settings_fields( $form ) : array {
		return array(
			$this->get_verification_configuration_section( 'form' ),
			$this->get_custom_configuration_settings_section( 'form' ),
			$this->get_error_messages_section( 'form' ),
		);
	}

	/**
	 * Checks if passed value is a valid Sendex.
	 *
	 * @param string|float $value Value to check.
	 * @return bool
	 */
	public function is_valid_sendex_setting( $value ) : bool {
		$float_value = (float) $value;

		return $float_value > 0 && $float_value <= 1;
	}

	/**
	 * Filters submitted values and checks the needed one via Kickbox.
	 *
	 * @return array Validation result
	 */
	public function verify_via_kickbox( $validation_result ) : array {
		$addon             = get_kickbox_gf_addon();
		$disabled_globally = $addon->get_plugin_setting( 'disabled' );

		if ( ! empty( $disabled_globally ) ) {
			return $validation_result;
		}

		$api_key = apply_filters( 'kickbox_gf_api_key', $addon->get_plugin_setting( 'api_key' ) );

		if ( empty( $api_key ) ) {
			return $validation_result;
		}

		$plugin_configuration = $addon->get_plugin_setting( 'verification_configuration' );

		if ( empty( $plugin_configuration ) ) {
			return $validation_result;
		}

		$form           = $validation_result['form'];
		$local_settings = $form['kickbox'] ?? array();

		if ( ! empty( $local_settings['disabled'] ) ) {
			return $validation_result;
		}

		$current_page = rgpost( 'gform_source_page_number_' . $form['id'] ) ? rgpost( 'gform_source_page_number_' . $form['id'] ) : 1;

		foreach ( $form['fields'] as $field ) {
			if ( ! $field->should_verify_via_kickbox || $field->failed_validation ) {
				continue;
			}

			$field_page = $field->pageNumber;
			$is_hidden  = RGFormsModel::is_field_hidden( $form, $field, array() );

			if ( ( (int) $field_page !== (int) $current_page ) || $is_hidden ) {
				continue;
			}

			$field_name  = apply_filters( 'kickbox_gf_field_name_before_verification', "input_{$field['id']}" );
			$field_value = apply_filters( 'kickbox_gf_field_value_before_verification', rgpost( $field_name ) );

			$is_fresh_cache = Kickbox_GF_Cache::is_cached_verification_fresh( $field_value );

			if ( $is_fresh_cache ) {
				$verification = $this->get_verification_from_cache( $field_value );
			} else {
				$verification = $this->get_verification_from_kickbox( $field_value, $api_key );
			}

			$interpretation = $this->interpret_verification( $verification );

			if ( $interpretation['valid'] ) {
				if ( ! $is_fresh_cache ) {
					Kickbox_GF_Cache::cache_verification( $verification );
				}

				continue;
			}

			$validation_result['is_valid'] = false;

			$field->failed_validation  = true;
			$field->validation_message = $interpretation['message'];
		}

		$validation_result['form'] = $form;

		return $validation_result;
	}

	/**
	 * Outputs Kickbox verification field settings in the Advanced section.
	 *
	 * @return void
	 */
	public function add_field_setting( $position ) {
		if ( -1 === $position ) {
			?>
			<li class="verify_via_kickbox_setting field_setting">
				<input type="checkbox" id="should_verify_via_kickbox" onclick="SetFieldProperty('should_verify_via_kickbox', this.checked);" />

				<label for="should_verify_via_kickbox">
					<?php esc_html_e( 'Verify the value from this field via Kickbox', 'kickbox-gf' ); ?>
				</label>
			</li>
			<?php
		}
	}

	/**
	 * Outputs Kickbox verification field editor script.
	 *
	 * @return void
	 */
	public function editor_script() {
		?>
		<script type='text/javascript'>
			fieldSettings.email += ', .verify_via_kickbox_setting';

			jQuery(document).on('gform_load_field_settings', function(event, field) {
				jQuery( '#should_verify_via_kickbox' ).prop( 'checked', Boolean( rgar( field, 'should_verify_via_kickbox' ) ) );
			});
		</script>
		<?php
	}

	/**
	 * Gets a cached verification from the database.
	 *
	 * @param string $email Email being verified.
	 *
	 * @return array Cached verification
	 */
	private function get_verification_from_cache( string $email ) : array {
		$cache_key           = Kickbox_GF_Cache::get_verification_cache_key( $email );
		$cached_verification = Kickbox_GF_Cache::get_cached_verification( $cache_key );

		$cached_verification['kickbox']['from-cache'] = true;

		do_action( 'kickbox_gf_cached_verification', $email, $cached_verification );

		return apply_filters( 'kickbox_gf_verification', $cached_verification['kickbox'] );
	}

	/**
	 * Filters submitted values and checks the needed one via Kickbox.
	 *
	 * @param string $value   The field value.
	 * @param string $api_key Kickbox API key.
	 *
	 * @return array Verification details
	 */
	private function get_verification_from_kickbox( string $value, string $api_key ) : array {
		$kickbox_client = new Kickbox_Client( $api_key );
		$verification   = $kickbox_client->verify( $value );

		do_action( 'kickbox_gf_pre_verification', $value );

		if ( is_wp_error( $verification['data'] ) ) {
			$verification = apply_filters(
				'kickbox_gf_verification_error',
				array(
					'success' => false,
					'data'    => $verification['data'],
				)
			);
		} else {
			$verification = apply_filters(
				'kickbox_gf_verification_success',
				array(
					'success'    => true,
					'from-cache' => false,
					'data'       => $verification['data'],
				)
			);
		}

		do_action( 'kickbox_gf_past_verification', $value, $verification );

		return apply_filters( 'kickbox_gf_verification', $verification );
	}

	/**
	 * Interprets Kickbox verification and returns the verdict.
	 *
	 * @param array $verification The field value.
	 *
	 * @return array Verification interpretation
	 */
	private function interpret_verification( array $verification ) : array {
		$interpretation = array(
			'valid'   => true,
			'message' => '',
		);

		// interpret as valid if the HTTP-call errored or there's insufficient balance
		if ( ! $verification['success'] || ! $verification['data']['body']['success'] ) {
			do_action( 'kickbox_gf_verification_interpreted', $verification, $interpretation );

			return apply_filters( 'kickbox_gf_interpretation', $interpretation );
		}

		$ignored_validation_reasons = apply_filters(
			'kickbox_gf_ignored_reasons',
			array(
				'timeout',
				'unexpected_error',
			)
		);

		if ( in_array( $verification['data']['body']['reason'], $ignored_validation_reasons, true ) ) {
			do_action( 'kickbox_gf_verification_interpreted', $verification, $interpretation );

			return apply_filters( 'kickbox_gf_interpretation', $interpretation );
		}

		$config = $this->get_kickbox_configuration();

		if ( ! in_array( $verification['data']['body']['result'], $config['valid-types'], true ) ) {
			$message = Kickbox_GF_Error_Messages::get_message( $verification['data']['body']['reason'] );

			if ( ! empty( $verification['data']['body']['did_you_mean'] ) ) {
				$message = str_replace(
					self::$_suggested_email_placeholder,
					$verification['data']['body']['did_you_mean'],
					Kickbox_GF_Error_Messages::get_message( 'suggested_email' )
				);
			}

			$interpretation = array(
				'valid'   => false,
				'message' => $message,
			);
		} elseif ( $verification['data']['body']['sendex'] <= $config['sendex'] ) {
			$interpretation = array(
				'valid'   => false,
				'message' => Kickbox_GF_Error_Messages::get_message( $verification['data']['body']['reason'] ),
			);
		}

		do_action( 'kickbox_gf_verification_interpreted', $verification, $interpretation );

		return apply_filters( 'kickbox_gf_interpretation', $interpretation );
	}

	/**
	 * Returns configuration choices labels and values.
	 *
	 * @return array[] Verification choices
	 */
	private function get_configuration_choices( $location ) : array {
		$configuration_choices = array();

		if ( 'form' === $location ) {
			$configuration_choices[] = array(
				'label' => esc_html__( 'Disabled', 'kickbox-gf' ),
				'value' => 'disabled',
			);
		}

		return array_merge(
			array(
				array(
					'label' => esc_html__( 'Strict', 'kickbox-gf' ),
					'value' => 'strict',
				),
				array(
					'label' => esc_html__( 'Permissive', 'kickbox-gf' ),
					'value' => 'permissive',
				),
				array(
					'label' => esc_html__( 'Custom', 'kickbox-gf' ),
					'value' => 'custom',
				),
			),
			$configuration_choices
		);
	}

	/**
	 * Returns the Verification Configuration section settings for the plugin and form settings pages.
	 *
	 * @param string $location Location where the section is used. 'plugin' and 'form' are valid values.
	 *
	 * @return array Verification Configuration section settings
	 */
	private function get_verification_configuration_section( string $location = 'plugin' ) : array {
		$configuration_choices = $this->get_configuration_choices( $location );
		$settings_description  = '';
		$required              = true;

		if ( 'form' === $location ) {
			$addon                = get_kickbox_gf_addon();
			$plugin_configuration = $addon->get_plugin_setting( 'verification_configuration' );
			$required             = false;

			$current_choice = array_values(
				array_filter(
					$configuration_choices,
					function( $choice ) use ( $plugin_configuration ) {
						return $plugin_configuration === $choice['value'];
					}
				)
			);

			$settings_description = esc_html__( 'Use these settings to override the plugin defaults.', 'kickbox-gf' );

			if ( ! empty( $current_choice ) ) {
				$settings_description .= sprintf(
					' %s <code>%s</code>',
					esc_html__( 'Configuration type on the plugin settings page:', 'kickbox-gf' ),
					$current_choice[0]['label']
				);
			}
		}

		return array(
			'title'  => esc_html__( 'Kickbox Verification Settings', 'kickbox-gf' ),
			'fields' => array(
				array(
					'label'       => esc_html__( 'Verification Configuration', 'kickbox-gf' ),
					'description' => $settings_description,
					'type'        => 'radio',
					'name'        => 'verification_configuration',
					'required'    => $required,
					'horizontal'  => true,
					'choices'     => $configuration_choices,
				),
				array(
					'type' => 'verification_setup_type',
					'name' => 'verification_setup_description',
				),
			),
		);
	}

	/**
	 * Returns the Custom Configuration section settings for the plugin and form settings pages.
	 *
	 * @param string $location Location where the section is used. 'plugin' and 'form' are valid values.
	 *
	 * @return array Custom Configuration section settings
	 */
	private function get_custom_configuration_settings_section( string $location = 'plugin' ) : array {
		$settings_description = sprintf(
			/* translators: Name of a predefined configuration choice */
			esc_html__( 'These settings work if %1$s is chosen as the Verification Configuration above.', 'kickbox-gf' ),
			'<code>' . esc_html__( 'Custom', 'kickbox-gf' ) . '</code>'
		);

		if ( 'form' === $location ) {
			$settings_description = sprintf(
			/* translators: Name of a predefined configuration choice */
				esc_html__( 'These settings work if %1$s is chosen as the Verification Configuration above or on the plugin settings page.', 'kickbox-gf' ),
				'<code>' . esc_html__( 'Custom', 'kickbox-gf' ) . '</code>'
			);

			$settings_description = esc_html__( 'Use these settings to override the plugin defaults.', 'kickbox-gf' ) . ' ' . $settings_description;
		}

		return array(
			'title'       => esc_html__( 'Custom Configuration Settings', 'kickbox-gf' ),
			'description' => $settings_description,
			'fields'      => array(
				array(
					'description' => esc_html__( 'Choose which Kickbox result types are considered valid (will not result in a validation error).', 'kickbox-gf' ),
					'label'       => sprintf(
						'%s (<small><a href="https://docs.kickbox.com/docs/terminology" target="_blank">%s</a></small>)',
						esc_html__( 'Valid Kickbox Result Types', 'kickbox-gf' ),
						esc_html__( 'Documentation', 'kickbox-gf' )
					),
					'type'        => 'checkbox',
					'name'        => 'custom_valid_result_types',
					'choices'     => array(
						array(
							'label'         => 'deliverable',
							'name'          => 'type_deliverable',
							'disabled'      => 'disabled',
							'default_value' => true,
						),
						array(
							'label' => 'undeliverable',
							'name'  => 'type_undeliverable',
						),
						array(
							'label' => 'risky',
							'name'  => 'type_risky',
						),
						array(
							'label' => 'unknown',
							'name'  => 'type_unknown',
						),
					),
				),
				array(
					'feedback_callback' => array( $this, 'is_valid_sendex_setting' ),
					'label'             => sprintf(
						'%s (<small><a href="https://docs.kickbox.com/docs/the-sendex" target="_blank">%s</a></small>)',
						esc_html__( 'Minimal Sendex Value', 'kickbox-gf' ),
						esc_html__( 'Documentation', 'kickbox-gf' )
					),
					'description'       => sprintf(
						/* translators: default Sendex value */
						esc_html__( 'Count emails with such Sendex value or lower as not valid. Please use a value between 0 and 1. Default is %s.', 'kickbox-gf' ),
						'<code>0.4</code>'
					),
					'type'              => 'text',
					'name'              => 'custom_minimal_sendex_value',
					'input_type'        => 'number',
					'min'               => 0,
					'max'               => 1,
					'step'              => 0.1,
				),
			),
		);
	}

	/**
	 * Returns the Error Messages section settings for the plugin and form settings pages.
	 *
	 * @param string $location Location where the section is used. 'plugin' and 'form' are valid values.
	 *
	 * @return array Error Messages section settings
	 */
	private function get_error_messages_section( string $location = 'plugin' ) : array {
		$description = esc_html__( 'Specify error messages to show when Kickbox verifies an email as not valid.', 'kickbox-gf' );

		if ( 'form' === $location ) {
			$description = esc_html__( 'Use these settings to override the plugin defaults.', 'kickbox-gf' ) . ' ' . $description;
		}

		return array(
			'title'       => esc_html__( 'Error Messages', 'kickbox-gf' ),
			'description' => $description,
			'fields'      => array(
				array(
					'label'       => esc_html__( 'Generic Error Message', 'kickbox-gf' ),
					'type'        => 'text',
					'name'        => 'generic_error_message',
					'after_input' => esc_html__( 'Specifying error messages for specific Kickbox result reasons is available to developers via filters.', 'kickbox-gf' ),
				),
				array(
					'label'       => esc_html__( 'Suggested Email Error Message', 'kickbox-gf' ),
					'description' => sprintf(
						/* translators:
							1: An example of email correction
							2, 3: Suggested Email placeholder
							4: Current/Default error message
						*/
						esc_html__( 'This error message will be shown when a suggested email is returned if a possible spelling error was detected (%1$s). Use %2$s in the message, it\'ll be replaced with the one from Kickbox. Example: %3$s seems more like it.', 'kickbox-gf' ),
						'<code>jane.doe@gamil.com</code> -> <code>jane.doe@gmail.com</code>',
						'<code>' . self::$_suggested_email_placeholder . '</code>',
						self::$_suggested_email_placeholder
					),
					'type'        => 'text',
					'name'        => 'suggested_email_error_message',
				),
			),
		);
	}

	/**
	 * Constructs the Configuration to check verifications against.
	 *
	 * @return array Configuration
	 */
	private function get_kickbox_configuration() : array {
		$config = array(
			'valid-types' => array( 'deliverable' ),
			'sendex'      => 0.4,
		);

		$form_settings_override = false;
		$addon                  = get_kickbox_gf_addon();
		$configuration          = $addon->get_plugin_setting( 'verification_configuration' );
		$form_settings          = $addon->get_form_settings( $addon->get_current_form() );

		if ( ! empty( $form_settings['verification_configuration'] ) && 'disabled' !== $form_settings['verification_configuration'] ) {
			$configuration          = $form_settings['verification_configuration'];
			$form_settings_override = true;
		}

		switch ( $configuration ) {
			case 'strict':
				$config['sendex'] = 0.7;
				break;
			case 'permissive':
				$config['valid-types'] = array( 'deliverable', 'risky', 'unknown' );
				break;
			case 'custom':
				$potential_valid_types = array( 'undeliverable', 'risky', 'unknown' );
				$sendex_value          = $config['sendex'];

				if ( $form_settings_override ) {
					if ( ! empty( $form_settings['custom_minimal_sendex_value'] ) ) {
						$sendex_value = (float) $form_settings['custom_minimal_sendex_value'];
					}

					foreach ( $potential_valid_types as $type ) {
						if ( ! empty( $form_settings["type_$type"] ) ) {
							$config['valid-types'][] = $type;
						}
					}
				} else {
					$custom_minimal_sendex_value = $addon->get_plugin_setting( 'custom_minimal_sendex_value' );

					if ( ! empty( $custom_minimal_sendex_value ) ) {
						$sendex_value = (float) $custom_minimal_sendex_value;
					}

					foreach ( $potential_valid_types as $type ) {
						if ( ! empty( $addon->get_plugin_setting( "type_$type" ) ) ) {
							$config['valid-types'][] = $type;
						}
					}
				}

				if ( $this->is_valid_sendex_setting( $sendex_value ) ) {
					$config['sendex'] = $sendex_value;
				}

				break;
			default:
				break;
		}

		return apply_filters( 'kickbox_gf_configuration', $config );
	}
}
