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
 *                              wp eval-file /home/maitwn01/domains/grandstrandlocal.com/public_html/wp-content/plugins/mai-united-robots/feeds/feed.php
 *
 * wp eval 'echo file_exists("/home/maitwn01/domains/grandstrandlocal.com/public_html/wp-content/plugins/mai-united-robots/feeds/feed.php") ? "File exists" : "File does not exist";'
 */

$test = 'false';
// $test     = 'real-estate';
// $test     = 'weather';
// $test     = 'hurricane';
$url      = home_url( sprintf( '/wp-json/maiunitedrobots/v1/%s', $test ) );
$file     = plugin_dir_path( __FILE__ ) . $test . '-example.json';
$name     = defined( 'MAI_UNITED_ROBOTS_AUTH_UN' ) ? MAI_UNITED_ROBOTS_AUTH_UN : '';
$password = defined( 'MAI_UNITED_ROBOTS_AUTH_PW' ) ? MAI_UNITED_ROBOTS_AUTH_PW : '';

WP_CLI::log( 'Starting' );

if ( ! $name ) {
	WP_CLI::log( 'No name found via MAI_UNITED_ROBOTS_AUTH_UN constant.' );
	return;
}

if ( ! $password ) {
	WP_CLI::log( 'No password found via MAI_UNITED_ROBOTS_AUTH_PW constant.' );
	return;
}

if ( ! file_exists( $file ) ) {
	WP_CLI::log( 'File does not exists via ' . $file );
	return;
}

WP_CLI::log( $url );

// Data to be sent in the JSON packet.
// Get content from json file.
$data = file_get_contents( $file );

// Bail if no test data.
if ( ! $data ) {
	WP_CLI::log( 'No data found via ' . $file );
	return;
}

// Prepare the request arguments
$args = array(
	'headers' => array(
		'Authorization' => 'Basic ' . base64_encode( $name . ':' . $password ),
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

WP_CLI::success( 'Done' );