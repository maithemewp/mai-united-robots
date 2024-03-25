<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Gets it started.
 *
 * @since 0.1.0
 *
 * @link https://docs.wpvip.com/how-tos/write-custom-wp-cli-commands/
 * @link https://webdevstudios.com/2019/10/08/making-wp-cli-commands/
 *
 * @return void
 */
add_action( 'cli_init', function() {
	WP_CLI::add_command( 'maiunitedrobots', 'Mai_United_Robots_CLI' );
});

/**
 * Main Mai_United_Robots_CLI Class.
 *
 * @since 0.1.0
 */
class Mai_United_Robots_CLI {
	/**
	 * Gets environment.
	 *
	 * Usage: wp maiunitedrobots get_environment
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	function get_environment() {
		WP_CLI::log( sprintf( 'Environment: %s', wp_get_environment_type() ) );
	}

	/**
	 * Updates posts from stored United Robots data.
	 *
	 * Usage: wp maiunitedrobots update_posts --post_type=post --posts_per_page=10 --offset=0
	 *
	 * @since 0.2.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function update_posts( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'      => 'post',
				'posts_per_page' => 10,
				'offset'         => 0,
			]
		);

		WP_CLI::line( 'WP_CLI is running.' );

		$args = array_merge( $assoc_args, [
			'post_status'            => 'any',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		if ( isset( $args['post__in'] ) ) {
			$args['post__in'] = explode( ',', $args['post__in'] );
		}

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			$progress = \WP_CLI\Utils\make_progress_bar( 'Running...', $query->post_count );

			while ( $query->have_posts() ) : $query->the_post();
				// Get original body from United Robots.
				$body = get_post_meta( get_the_ID(), 'unitedrobots_body', true );

				// Skip if no body.
				if ( ! $body ) {
					WP_CLI::line( 'Skipped. No body found. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Decode body.
				$body = mai_united_robots_json_decode( $body );

				// Skip if no body.
				if ( ! $body ) {
					WP_CLI::line( 'Skipped. Body failed decoding. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Get article reference id.
				$ref_id = isset( $body['article']['id'] ) ? $body['article']['id'] : '';

				// Skip if no id.
				if ( ! $ref_id ) {
					WP_CLI::line( 'Skipped. No reference ID found. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Get reference id from post meta.
				$meta_ref_id = get_post_meta( get_the_ID(), 'reference_id', true );

				// Skip if no existing post.
				if ( $meta_ref_id !== $ref_id ) {
					WP_CLI::line( 'Skipped. Reference ID does not match this post. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Update post via listener class.
				if ( isset( $body['description']['price'] ) || isset( $body['description']['saleTypes'] ) ) {
					$listener = new Mai_United_Robots_Real_Estate_Listener( $body, false );
					$listener->process();
				} else {
					$listener = new Mai_United_Robots_Listener( $body, false );
				}

				WP_CLI::line( 'Updated ' . $listener->get_post_id() . ': ' . get_permalink( $listener->get_post_id() ) );

				$progress->tick();
			endwhile;

			$progress->finish();

			WP_CLI::success( 'Done.' );
		} else {
			WP_CLI::success( 'No posts found.' );
		}

		wp_reset_postdata();
	}

	/**
	 * Update the body from United Robots if it's stored as raw JSON.
	 *
	 * Usage: wp maiunitedrobots update_json --post_type=post --posts_per_page=10 --offset=0
	 *
	 * @since 0.2.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function update_json( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'      => 'post',
				'posts_per_page' => 100,
				'offset'         => 0,
			]
		);

		WP_CLI::line( 'WP_CLI is running.' );

		$args = array_merge( $assoc_args, [
			'post_status'            => 'any',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		if ( isset( $args['post__in'] ) ) {
			$args['post__in'] = explode( ',', $args['post__in'] );
		}

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			$progress = \WP_CLI\Utils\make_progress_bar( 'Running...', $query->post_count );

			while ( $query->have_posts() ) : $query->the_post();
				// Get original body from United Robots.
				$body = get_post_meta( get_the_ID(), 'unitedrobots_body', true );

				// Skip if no body.
				if ( ! $body ) {
					WP_CLI::line( 'Skipped. No body found. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Decode.
				$body = mai_united_robots_json_decode( $body );

				// Skip if not JSON.
				if ( ! $body ) {
					WP_CLI::line( 'Skipped. Not valid JSON. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Update post meta.
				update_post_meta( get_the_ID(), 'unitedrobots_body', wp_json_encode( $body ) );

				WP_CLI::line( 'Updated! ' . get_permalink() );
				$progress->tick();
			endwhile;

			$progress->finish();

			WP_CLI::success( 'Done.' );
		} else {
			WP_CLI::success( 'No posts found.' );
		}
	}

	/**
	 * Update reference ids from United Robots.
	 *
	 * Usage: wp maiunitedrobots update_ids --post_type=post --posts_per_page=10 --offset=0
	 *
	 * @since 0.2.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function update_ids( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'      => 'post',
				'posts_per_page' => 100,
				'offset'         => 0,
			]
		);

		WP_CLI::line( 'WP_CLI is running.' );

		$args = array_merge( $assoc_args, [
			'post_status'            => 'any',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		] );

		if ( isset( $args['post__in'] ) ) {
			$args['post__in'] = explode( ',', $args['post__in'] );
		}

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			$progress = \WP_CLI\Utils\make_progress_bar( 'Running...', $query->post_count );

			// Function to add slashes if not already present.
			$add_slashes = function( $string ) {
				// Check if the string already contains a backslash before double quotes
				if ( false === strpos( $string, '\"' ) ) {
					// Add slashes to double quotes
					$string = str_replace( '"', '\"', $string );
				}

				return $string;
			};

			while ( $query->have_posts() ) : $query->the_post();
				// Get original body from United Robots.
				$body = get_post_meta( get_the_ID(), 'unitedrobots_body', true );

				// Skip if no body.
				if ( ! $body ) {
					WP_CLI::line( 'Skipped. No body found. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Decode.
				$body = mai_united_robots_json_decode( $body );

				// Skip if no body.
				if ( ! $body ) {
					WP_CLI::line( 'Skipped. Body failed decoding. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Get article reference id.
				$ref_id = isset( $body['article']['id'] ) ? $body['article']['id'] : '';

				// Skip if no id.
				if ( ! $ref_id ) {
					WP_CLI::line( 'Skipped. No reference ID found. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Get post with a meta key of reference_id and meta value of the ref_id.
				$existing = get_posts(
					[
						'post_type'      => $args['post_type'],
						'meta_key'       => 'reference_id',
						'meta_value'     => $ref_id,
						'meta_compare'   => '=',
						'fields'         => 'ids',
						'numberposts'    => 1,
					]
				);

				// Skip if no existing post.
				if ( $existing ) {
					WP_CLI::line( 'Skipped. ID already matches. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Update post meta.
				update_post_meta( get_the_ID(), 'reference_id', $ref_id );

				WP_CLI::line( 'Updated! ' . get_permalink() );
				$progress->tick();
			endwhile;

			$progress->finish();

			WP_CLI::success( 'Done.' );
		} else {
			WP_CLI::success( 'No posts found.' );
		}
	}

	/**
	 * Test feeds from local JSON files.
	 *
	 * 1. Create an application un/pw via your WP user account.
	 * 2. Set un/pw in wp-config.php via `MAI_UNITED_ROBOTS_AUTH_UN` (user login) and `MAI_UNITED_ROBOTS_AUTH_PW` constants.
	 * 3. Copy the path to this file.
	 * 4. Execute via command line:
	 *    wp maiunitedrobots test_feed --feed=traffic
	 *
	 * @since TBD
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --posts_per_page and --offset.
	 *
	 * @return void
	 */
	function test_feed( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'feed' => 'traffic', // traffic, real-estate, weather, hurricane.
			]
		);

		// Set data.
		$feed     = $assoc_args['feed'];
		$url      = home_url( sprintf( '/wp-json/maiunitedrobots/v1/%s', $feed ) );
		$file     = MAI_UNITED_ROBOTS_PLUGIN_DIR . 'examples/' . $feed . '-example.json';
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

		// Prepare the request arguments.
		$args = [
			'method'  => 'PUT',
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $name . ':' . $password ),
			],
			'body' => $data,
		];

		// Make the POST request.
		$response = wp_remote_post( $url, $args );
		// $response = wp_remote_request( $url, $args );

		// Check if the request was successful.
		if ( is_wp_error( $response ) ) {
			// Handle error.
			WP_CLI::log( 'Error: ' . $response->get_error_message() );
		} else {
			// Decode the response body.
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			WP_CLI::log( $code . ' : ' . $body );
		}

		WP_CLI::success( 'Done' );
	}
}
