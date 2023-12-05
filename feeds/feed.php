<?php

/**
 * A rough WP-CLI command/file to test example feed data.
 *
 * @since 0.1.0
 *
 * HOW TO USE:
 *
 * 1. Create an application un/pw via your WP user account.
 * 2. Set un/pw in wp-config.php via `MAI_UNITED_ROBOTS_AUTH_UN` and `MAI_UNITED_ROBOTS_AUTH_PW` constants.
 * 3. Copy the path to this file.
 * 4. Execute via command line: wp eval-file /Users/jivedig/Plugins/mai-united-robots/feeds/feed.php
 */

$test = 'false';
// $test     = 'real-estate';
// $test     = 'weather';
// $test     = 'hurricane';
$url      = home_url( sprintf( '/wp-json/maiunitedrobots/v1/%s', $test ) );
$name     = defined( 'MAI_UNITED_ROBOTS_AUTH_UN' ) ? MAI_UNITED_ROBOTS_AUTH_UN : '';
$password = defined( 'MAI_UNITED_ROBOTS_AUTH_PW' ) ? MAI_UNITED_ROBOTS_AUTH_PW : '';

if ( ! $name ) {
	WP_CLI::log( 'No name found via MAI_UNITED_ROBOTS_AUTH_UN constant.' );
	return;
}

if ( ! $password ) {
	WP_CLI::log( 'No password found via MAI_UNITED_ROBOTS_AUTH_PW constant.' );
	return;
}

WP_CLI::log( $url );

// Data to be sent in the JSON packet.
// Get content from json file.
$data = file_get_contents( MAI_UNITED_ROBOTS_PLUGIN_DIR . 'feeds/' . $test . '-example.json' );

// Bail if no test data.
if ( ! $data ) {
	WP_CLI::log( 'No data found via ' . MAI_UNITED_ROBOTS_PLUGIN_DIR . 'feeds/' . $test . '-example.json' );
	return;
}

// Prepare the request arguments
$args = array(
	'headers' => array(
		'Authorization' => 'Basic ' . base64_encode( 'United Robots' . ':' . $password ),
	),
	'body' => $data,
);

// Make the POST request.
$response = wp_remote_post( $url, $args );

// Check if the request was successful
if ( is_wp_error( $response ) ) {
	// Handle error
	WP_CLI::log( 'Error: ' . $response->get_error_message() );
} else {
	// Decode the response body
	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	WP_CLI::log( $code . ' : ' . $body );
}

WP_CLI::success( 'Done, okay.' );