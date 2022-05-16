<?php

class Kickbox_GF_Cache {
	const DATABASE_KEY = 'kickbox_gf_verifications';
	const CRON_ACTION  = 'kickbox_gf_prune_old_verifications';

	/**
	 * Checks stored verifications and prunes them if they are older than defined Caching Duration.
	 *
	 * @return void
	 */
	public static function prune_old_verifications() {
		$addon                   = get_kickbox_gf_addon();
		$results_caching_enabled = $addon->get_plugin_setting( 'results_caching_enabled' );

		if ( empty( $results_caching_enabled ) ) {
			$scheduled_pruning = wp_next_scheduled( self::CRON_ACTION );

			if ( $scheduled_pruning ) {
				wp_unschedule_event( $scheduled_pruning, self::CRON_ACTION );
			}

			return;
		}

		$stored_verifications = get_option( self::DATABASE_KEY );

		do_action( 'kickbox_gf_verifications_before_pruning', $stored_verifications );

		if ( empty( $stored_verifications ) ) {
			return;
		}

		$time_now                 = time();
		$results_caching_duration = self::get_caching_duration();

		foreach ( $stored_verifications as $key => $value ) {
			if ( $time_now - $value['verification-timestamp'] > $results_caching_duration * DAY_IN_SECONDS ) {
				unset( $stored_verifications[ $key ] );
			}
		}

		update_option( self::DATABASE_KEY, $stored_verifications );

		do_action( 'kickbox_gf_verifications_pruned', $stored_verifications );
	}

	/**
	 * Stores a verification in the database for the defined Caching Duration.
	 *
	 * @param array $verification Verification details.
	 *
	 * @return void
	 */
	public static function cache_verification( array $verification ) {
		$addon                   = get_kickbox_gf_addon();
		$results_caching_enabled = $addon->get_plugin_setting( 'results_caching_enabled' );

		if ( ! empty( $results_caching_enabled ) ) {
			$cache_key = self::get_verification_cache_key( $verification['data']['body']['email'] );

			wp_cache_set( "kickbox_gf_{$cache_key}_result", $verification['data']['body'] );

			$stored_kickboxes      = get_option( self::DATABASE_KEY );
			$verification_to_store = apply_filters( 'kickbox_gf_cache_item', array(
				'kickbox'                => $verification,
				'verification-timestamp' => time(),
			), $cache_key );

			$stored_kickboxes[ $cache_key ] = $verification_to_store;

			update_option( self::DATABASE_KEY, $stored_kickboxes );

			do_action( 'kickbox_gf_verification_cached', $verification_to_store );
		}
	}

	/**
	 * Retrieves a cached verification from the database.
	 *
	 * @param string $key Cache key.
	 *
	 * @return array If verification is not found, returns an empty array
	 */
	public static function get_cached_verification( string $key ) : array {
		$stored_kickboxes = get_option( self::DATABASE_KEY );

		if ( empty( $stored_kickboxes ) || empty( $stored_kickboxes[ $key ] ) ) {
			return array();
		}

		return $stored_kickboxes[ $key ];
	}

	/**
	 * Constructs a cache key to be used to identify a verification.
	 *
	 * @param string $email Email that is being verified.
	 *
	 * @return string
	 */
	public static function get_verification_cache_key( string $email ) : string {
		$addon                  = get_kickbox_gf_addon();
		$cache_key              = $email;
		$domain_caching_enabled = $addon->get_plugin_setting( 'domain_caching_enabled' );

		if ( ! empty( $domain_caching_enabled ) ) {
			$email_parts = explode( '@', $cache_key );

			$cache_key = $email_parts[1];
		}

		return apply_filters( 'kickbox_gf_verification_cache_key', $cache_key );
	}

	/**
	 * Returns the caching duration for verifications.
	 *
	 * @return int
	 */
	public static function get_caching_duration() : int {
		$addon                    = get_kickbox_gf_addon();
		$results_caching_duration = $addon->get_plugin_setting( 'results_caching_duration' );

		if ( empty( $results_caching_duration ) ) {
			$results_caching_duration = 7;
		}

		return (int) apply_filters( 'kickbox_gf_results_caching_duration', $results_caching_duration );
	}

	/**
	 * Checks if it's OK to use the cached verification.
	 *
	 * @param string $email Email that is being verified.
	 *
	 * @return bool
	 */
	public static function is_cached_verification_fresh( string $email ) : bool {
		$is_cached_verification_fresh = false;

		$cache_key           = self::get_verification_cache_key( $email );
		$cached_verification = self::get_cached_verification( $cache_key );

		if ( ! empty( $cached_verification ) ) {
			$is_cached_verification_fresh = true;
			$results_caching_duration     = self::get_caching_duration();

			if ( time() - $cached_verification['verification-timestamp'] > $results_caching_duration * DAY_IN_SECONDS ) {
				$is_cached_verification_fresh = false;
			}
		}

		return $is_cached_verification_fresh;
	}
}
