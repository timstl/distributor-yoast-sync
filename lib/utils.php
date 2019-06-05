<?php
if ( ! function_exists( 'bt_log' ) ) {
	/**
	 * Logging function.
	 * In wp-config.php define the WP_DEBUG_LOG constant: define('WP_DEBUG_LOG', true);
	 *
	 * You can then use this function anywhere in your themes or plugin:
	 *
	 * bt_log("log message here");
	 *
	 * This will write to wp-content/debug.log.
	 * In terminal: tail -f debug.log
	 *
	 * @param string|int|object|array $log Debug info to log.
	 */
	function bt_log( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}
}
