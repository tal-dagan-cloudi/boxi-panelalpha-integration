<?php
/**
 * Rate Limiter
 *
 * Implements token bucket algorithm for API rate limiting.
 */

class Boxi_Rate_Limiter {

	/**
	 * Transient key for storing bucket state
	 */
	const TRANSIENT_KEY = 'boxi_rate_limiter_bucket';

	/**
	 * Maximum number of tokens in bucket (requests per minute)
	 *
	 * @var int
	 */
	private $max_tokens;

	/**
	 * Token refill rate (tokens per second)
	 *
	 * @var float
	 */
	private $refill_rate;

	/**
	 * Constructor
	 *
	 * @param int $max_requests Maximum requests per minute (default: 55).
	 */
	public function __construct( $max_requests = null ) {
		// Get rate limit from settings
		if ( null === $max_requests ) {
			$config = new Boxi_Config_Manager();
			$settings = $config->get_settings();
			$max_requests = isset( $settings['rate_limit'] ) ? absint( $settings['rate_limit'] ) : 55;
		}

		$this->max_tokens = $max_requests;
		$this->refill_rate = $max_requests / 60; // Tokens per second
	}

	/**
	 * Consume a token from the bucket
	 *
	 * @return bool True if token consumed, false if rate limit exceeded.
	 */
	public function consume() {
		$bucket = $this->get_bucket();

		// Refill tokens based on time elapsed
		$now = microtime( true );
		$time_elapsed = $now - $bucket['last_refill'];
		$tokens_to_add = $time_elapsed * $this->refill_rate;

		$bucket['tokens'] = min( $this->max_tokens, $bucket['tokens'] + $tokens_to_add );
		$bucket['last_refill'] = $now;

		// Check if we have tokens available
		if ( $bucket['tokens'] < 1 ) {
			$this->save_bucket( $bucket );
			return false;
		}

		// Consume one token
		$bucket['tokens'] -= 1;
		$this->save_bucket( $bucket );

		return true;
	}

	/**
	 * Get time to wait before next request (in seconds)
	 *
	 * @return int Seconds to wait (0 if no wait needed).
	 */
	public function get_wait_time() {
		$bucket = $this->get_bucket();

		// Refill tokens based on time elapsed
		$now = microtime( true );
		$time_elapsed = $now - $bucket['last_refill'];
		$tokens_to_add = $time_elapsed * $this->refill_rate;

		$current_tokens = min( $this->max_tokens, $bucket['tokens'] + $tokens_to_add );

		// If we have tokens, no need to wait
		if ( $current_tokens >= 1 ) {
			return 0;
		}

		// Calculate wait time to get 1 token
		$tokens_needed = 1 - $current_tokens;
		$wait_time = ceil( $tokens_needed / $this->refill_rate );

		return $wait_time;
	}

	/**
	 * Reset the rate limiter bucket
	 *
	 * @return bool True on success.
	 */
	public function reset() {
		delete_transient( self::TRANSIENT_KEY );
		return true;
	}

	/**
	 * Get current bucket state
	 *
	 * @return array Bucket state with 'tokens' and 'last_refill'.
	 */
	private function get_bucket() {
		$bucket = get_transient( self::TRANSIENT_KEY );

		if ( false === $bucket || ! is_array( $bucket ) ) {
			// Initialize bucket
			$bucket = array(
				'tokens'      => $this->max_tokens,
				'last_refill' => microtime( true ),
			);
			$this->save_bucket( $bucket );
		}

		return $bucket;
	}

	/**
	 * Save bucket state
	 *
	 * @param array $bucket Bucket state.
	 * @return bool True on success.
	 */
	private function save_bucket( $bucket ) {
		// Store for 2 minutes (longer than rate window to preserve state)
		return set_transient( self::TRANSIENT_KEY, $bucket, 120 );
	}

	/**
	 * Get current token count (for debugging)
	 *
	 * @return float Current number of tokens in bucket.
	 */
	public function get_token_count() {
		$bucket = $this->get_bucket();

		// Refill tokens based on time elapsed
		$now = microtime( true );
		$time_elapsed = $now - $bucket['last_refill'];
		$tokens_to_add = $time_elapsed * $this->refill_rate;

		return min( $this->max_tokens, $bucket['tokens'] + $tokens_to_add );
	}
}
