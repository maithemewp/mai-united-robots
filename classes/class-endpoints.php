<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The endpoints class.
 *
 * @since 0.1.0
 */
class Mai_United_Robots_Endpoints {
	protected $token;
	protected $request;
	protected $body;

	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->token = defined( 'MAI_UNITED_ROBOTS_TOKEN' ) ? MAI_UNITED_ROBOTS_TOKEN : false;
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		add_filter( 'rest_api_init', [ $this, 'register_endpoint' ] );
	}

	/**
	 * Register the endpoints.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_endpoint() {
		/**
		 * /maiunitedrobots/v1/real-estate/
		 * /maiunitedrobots/v1/weather/
		 */
		$routes = [
			'hurricane'   => 'handle_hurricane_request',
			'real-estate' => 'handle_real_estate_request',
			'traffic'     => 'handle_traffic_request',
			'weather'     => 'handle_weather_request',
		];

		// Loop through routes and register them.
		foreach ( $routes as $path => $callback ) {
			register_rest_route( 'maiunitedrobots/v1', $path, [
				'methods'             => 'PUT', // The API does check for auth cookies and nonces when you make POST or PUT requests, but not GET requests.
				'callback'            => [ $this, $callback ],
				'permission_callback' => '__return_true',
			] );
		}
	}

	/**
	 * Handle the hurricane request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function handle_hurricane_request( $request ) {
		$this->validate_request( $request );

		$listener = new Mai_United_Robots_Hurricane_Listener( $this->body );
	}

	/**
	 * Handle the real estate request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function handle_real_estate_request( $request ) {
		$this->validate_request( $request );

		$listener = new Mai_United_Robots_Real_Estate_Listener( $this->body );
	}

	/**
	 * Handle the traffic request.
	 *
	 * @since 0.6.0
	 *
	 * @return void
	 */
	function handle_traffic_request( $request ) {
		$this->validate_request( $request );

		$listener = new Mai_United_Robots_Traffic_Listener( $this->body );
	}

	/**
	 * Handle the weather request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function handle_weather_request( $request ) {
		$this->validate_request( $request );

		$listener = new Mai_United_Robots_Weather_Listener( $this->body );
	}

	/**
	 * Validate the request.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function validate_request( $request ) {
		// Get the Authorization header from the request.
		$header = $request->get_header( 'Authorization' );

		// If the header is missing or it doesn't have the expected format.
		if ( ! $header || ! str_starts_with( $header, 'Basic ' ) ) {
			return wp_send_json_error( 'Unauthorized request', 401 );
		}

		// Remove 'Basic ' from the beginning.
		$credentials = substr( $header, 6 );

		// Decode.
		$credentials = base64_decode( $credentials );

		// Split the credentials into username and password.
		list( $username, $password ) = explode( ':', $credentials, 2 );

		// Sanitize.
		$username = sanitize_text_field( $username );
		$password = sanitize_text_field( $password );

		// Authenticate using wp_authenticate_application_password.
		$user = wp_authenticate_application_password( null, $username, $password );

		// If the authentication failed.
		if ( is_wp_error( $user ) ) {
			return wp_send_json_error( $user->get_error_message(), $user->get_error_code() );
		}

		// Get the request body.
		$body = $request->get_body();

		// Bail if no body.
		if ( ! $body ) {
			return wp_send_json_error( 'No body', 400 );
		}

		// Log decoded body.
		// mai_united_robots_logger( $body );

		// Bail if no body.
		if ( ! $body ) {
			return wp_send_json_error( 'No decoded body', 400 );
		}

		// Set request and body.
		$this->request = $request;
		$this->body    = $body;

		// Log the body.
		// mai_united_robots_logger( $this->body );
	}
}