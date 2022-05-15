<?php

namespace Skip405\Kickbox\WordPress;

/**
 * Kickbox API client using WordPress request functions
 */
class Client {
	const API_ENDPOINT    = 'https://api.kickbox.com/v2';
	const HOOKS_NAMESPACE = 'skip405_kickbox';

	/**
	 * Short description
	 *
	 * @var string $api_key Kickbox API key.
	 */
	private $api_key;

	/**
	 * Creates a client instance with the provided API key
	 *
	 * @param string $api_key Kickbox API key.
	 */
	public function __construct( string $api_key ) {
		if ( empty( $api_key ) ) {
			wp_die( 'Kickbox API key is missing', 500 );
		}

		$this->api_key = $api_key;
	}

	/**
	 * Verifies a single email address
	 *
	 * @param string $email Email address to verify.
	 * @param int    $timeout Timeout in seconds.
	 *
	 * @return array
	 */
	public function verify( string $email, int $timeout = 6 ) : array {
		do_action( self::HOOKS_NAMESPACE . '_before_email_verification', $email );

		$response = wp_remote_get(
			add_query_arg(
				array(
					'email'   => rawurlencode( $email ),
					'apikey'  => $this->api_key,
					'timeout' => $timeout,
				),
				self::API_ENDPOINT . '/verify'
			),
			apply_filters( self::HOOKS_NAMESPACE . '_email_verification_call_args', array( 'timeout' => $timeout ) )
		);

		if ( is_wp_error( $response ) ) {
			$verification = apply_filters(
				self::HOOKS_NAMESPACE . '_email_verification_error',
				array(
					'success' => false,
					'data'    => $response,
				)
			);

			do_action( self::HOOKS_NAMESPACE . '_after_email_verification', $email, $verification );

			return apply_filters( self::HOOKS_NAMESPACE . '_email_verification', $verification );
		}

		$response_headers = wp_remote_retrieve_headers( $response );

		if ( ! empty( $response_headers ) ) {
			$response_headers = $response_headers->getAll();
		}

		$verification = apply_filters(
			self::HOOKS_NAMESPACE . '_email_verification_success',
			array(
				'success' => true,
				'data'    => array(
					'code'    => wp_remote_retrieve_response_code( $response ),
					'body'    => json_decode( wp_remote_retrieve_body( $response ), true ),
					'headers' => $response_headers,
				),
			)
		);

		do_action( self::HOOKS_NAMESPACE . '_after_email_verification', $email, $verification );

		return apply_filters( self::HOOKS_NAMESPACE . '_email_verification', $verification );
	}

	/**
	 * Perform a Batch Verification
	 *
	 * @param array $emails Emails to check.
	 * @param array $options An array with `filename` and `callback-url` keys.
	 *
	 * @return array
	 */
	public function verify_batch( array $emails, array $options = array() ) : array {
		$callback_url = $options['callback-url'] ?? '';
		$filename     = apply_filters(
			self::HOOKS_NAMESPACE . '_batch_verification_filename',
			$options['filename'] ?? 'Batch Verification - ' . gmdate( 'm-d-Y-H-i-s' )
		);

		$headers = array(
			'Content-Type'       => 'text/csv',
			'X-Kickbox-Filename' => $filename,
		);

		if ( ! empty( $callback_url ) ) {
			$headers['X-Kickbox-Callback'] = $callback_url;
		}

		do_action( self::HOOKS_NAMESPACE . '_before_batch_verification', $emails, $filename, $callback_url );

		$response = wp_remote_request(
			add_query_arg(
				array( 'apikey' => $this->api_key ),
				self::API_ENDPOINT . '/verify-batch'
			),
			apply_filters(
				self::HOOKS_NAMESPACE . '_batch_verification_call_args',
				array(
					'method'  => 'PUT',
					'headers' => $headers,
					'body'    => join( "\n", $emails ),
					'timeout' => 6,
				)
			)
		);

		if ( is_wp_error( $response ) ) {
			$verification = apply_filters(
				self::HOOKS_NAMESPACE . '_batch_verification_error',
				array(
					'success' => false,
					'data'    => $response,
				)
			);
		} else {
			$response_headers = wp_remote_retrieve_headers( $response );

			if ( ! empty( $response_headers ) ) {
				$response_headers = $response_headers->getAll();
			}

			$verification = apply_filters(
				self::HOOKS_NAMESPACE . '_batch_verification_success',
				array(
					'success' => true,
					'data'    => array(
						'code'    => wp_remote_retrieve_response_code( $response ),
						'body'    => json_decode( wp_remote_retrieve_body( $response ), true ),
						'headers' => $response_headers,
					),
				)
			);
		}

		do_action( self::HOOKS_NAMESPACE . '_after_batch_verification', $verification, $filename, $callback_url );

		return apply_filters( self::HOOKS_NAMESPACE . '_batch_verification', $verification );
	}

	/**
	 * Checks a Batch Verification Status
	 *
	 * @param string $job_id Kickbox job ID to check.
	 *
	 * @return array
	 */
	public function check_batch( string $job_id ) : array {
		do_action( self::HOOKS_NAMESPACE . '_before_batch_check', $job_id );

		$response = wp_remote_get(
			add_query_arg(
				array( 'apikey' => $this->api_key ),
				self::API_ENDPOINT . '/verify-batch/' . $job_id
			),
			apply_filters( self::HOOKS_NAMESPACE . '_batch_check_call_args', array( 'timeout' => 6 ) )
		);

		if ( is_wp_error( $response ) ) {
			$check = apply_filters(
				self::HOOKS_NAMESPACE . '_batch_check_error',
				array(
					'success' => false,
					'data'    => $response,
				)
			);
		} else {
			$response_headers = wp_remote_retrieve_headers( $response );

			if ( ! empty( $response_headers ) ) {
				$response_headers = $response_headers->getAll();
			}

			$check = apply_filters(
				self::HOOKS_NAMESPACE . '_batch_check_success',
				array(
					'success' => true,
					'data'    => array(
						'code'    => wp_remote_retrieve_response_code( $response ),
						'body'    => json_decode( wp_remote_retrieve_body( $response ), true ),
						'headers' => $response_headers,
					),
				)
			);
		}

		do_action( self::HOOKS_NAMESPACE . '_after_batch_check', $job_id, $check );

		return apply_filters( self::HOOKS_NAMESPACE . '_batch_check', $check );
	}
}
