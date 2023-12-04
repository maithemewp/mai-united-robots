<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

add_action( 'genesis_footer', 'mai_united_robots_test_endpoint' );
function mai_united_robots_test_endpoint() {
	$test         = 'real-estate';
	// $test         = 'weather';
	// $test         = 'hurricane';
	$endpoint_url = sprintf( '%swp-json/maiunitedrobots/v1/%s/', MAI_UNITED_ROBOTS_PLUGIN_DIR, $test );
	$bearer_token = 'dnPs LWQ4 rwMg BI9V k5yU ZEmb'; // United Robots.

	// Data to be sent in the JSON packet.
	// Get content from json file.
	$data = file_get_contents( MAI_UNITED_ROBOTS_PLUGIN_DIR . 'feeds/' . $test . '-example.json' );

	// Bail if no test data.
	if ( ! $data ) {
		mai_united_robots_logger( 'No data found via ' . MAI_UNITED_ROBOTS_PLUGIN_DIR . 'feeds/' . $test . '-example.json' );
		return;
	}

	// Prepare the request arguments
	$args = array(
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( 'United Robots' . ':' . $application_password ),
		),
		'body' => $data,
	);

	// Make the POST request.
	$response = wp_remote_post( $endpoint_url, $args );

	// Check if the request was successful
	if ( is_wp_error( $response ) ) {
		// Handle error
		mai_united_robots_logger( 'Error: ' . $response->get_error_message() );
	} else {
		// Decode the response body
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Check the response
		if (isset($response_body['message'])) {
			mai_united_robots_logger( 'Response: ' . $response_body['message'] );
		} else {
			mai_united_robots_logger( 'Unexpected response format' );
		}
	}
}

/**
 * Log data to a file.
 *
 * @since 0.1.0
 *
 * @param mixed $data
 *
 * @return void
 */
function mai_united_robots_logger( $data ) {
	$file   = MAI_UNITED_ROBOTS_PLUGIN_DIR . 'debug.txt';
	$handle = fopen( $file, 'a' );
	ob_start();
	if ( is_array( $data ) || is_object( $data ) ) {
		print_r( $data );
	} elseif ( is_bool( $data ) ) {
		var_dump( $data );
	} else {
		echo $data;
	}
	echo "\r\n\r\n";
	fwrite( $handle, ob_get_clean() );
	fclose( $handle );
}