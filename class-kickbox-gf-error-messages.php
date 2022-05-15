<?php

class Kickbox_GF_Error_Messages {
	/**
	 * Returns an error message. Defaults to a 'generic' message.
	 *
	 * @param string $reason Kickbox reasons or 'generic' or 'suggested_email'.
	 *
	 * @return string
	 */
	public static function get_message( string $reason ) : string {
		$error_message = self::get_generic_message();

		if ( 'suggested_email' === $reason ) {
			$error_message = sprintf(
				/* translators: A suggested email */
				esc_html__( 'Did you mean %s?', 'kickbox-gf' ),
				Kickbox_GF_Addon::get_suggested_email_placeholder()
			);
		}

		$plugin_error_message = self::get_message_from_plugin_settings( $reason );
		$form_error_message   = self::get_message_from_form_settings( $reason );

		if ( ! empty( $plugin_error_message ) ) {
			$error_message = $plugin_error_message;
		}

		if ( ! empty( $form_error_message ) ) {
			$error_message = $form_error_message;
		}

		return self::get_filterable_error_message( $reason, $error_message );
	}

	/**
	 * Returns an error message from the plugin settings page.
	 *
	 * @param string $reason Kickbox reasons or 'generic' or 'suggested_email'.
	 *
	 * @return string Returns an empty string if no setting was found.
	 */
	private static function get_message_from_plugin_settings( string $reason ) : string {
		$addon                = get_kickbox_gf_addon();
		$plugin_error_message = $addon->get_plugin_setting( "{$reason}_error_message" );

		return empty( $plugin_error_message ) ? '' : $plugin_error_message;
	}

	/**
	 * Returns an error message from the form settings page.
	 *
	 * @param string $reason Kickbox reasons or 'generic' or 'suggested_email'.
	 *
	 * @return string Returns an empty string if no setting was found.
	 */
	private static function get_message_from_form_settings( string $reason ) : string {
		$addon         = get_kickbox_gf_addon();
		$form_settings = $addon->get_form_settings( $addon->get_current_form() );

		if ( ! empty( $form_settings["{$reason}_error_message"] ) ) {
			return $form_settings["{$reason}_error_message"];
		}

		return '';
	}

	/**
	 * Returns a generic error message. This will be the message if no other messages are defined.
	 *
	 * @return string
	 */
	private static function get_generic_message() : string {
		$error_message = esc_html__( 'There seems to be an issue with your email address.', 'kickbox-gf' );

		$plugin_error_message = self::get_message_from_plugin_settings( 'generic' );
		$form_error_message   = self::get_message_from_form_settings( 'generic' );

		if ( ! empty( $plugin_error_message ) ) {
			$error_message = $plugin_error_message;
		}

		if ( ! empty( $form_error_message ) ) {
			$error_message = $form_error_message;
		}

		return apply_filters( 'kickbox_gf_generic_error_message', $error_message );
	}

	/**
	 * Adds filters to the error message.
	 *
	 * @param string $reason Kickbox reasons or 'generic' or 'suggested_email'.
	 * @param string $message Error message.
	 *
	 * @return string
	 */
	private static function get_filterable_error_message( string $reason, string $message ) : string {
		$addon         = get_kickbox_gf_addon();
		$form          = $addon->get_current_form();
		$error_message = apply_filters( "kickbox_gf_{$reason}_error_message", $message );

		if ( ! empty( $form ) ) {
			$error_message = apply_filters( "kickbox_gf_form_{$form['id']}_{$reason}_error_message", $error_message );
		}

		return $error_message;
	}
}
