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
	 * @since TBD
	 *
	 * @return void
	 */
	function get_environment() {
		WP_CLI::log( sprintf( 'Environment: %s', wp_get_environment_type() ) );
	}

	/**
	 * Update reference ids from United Robots.
	 *
	 * Usage: wp maiunitedrobots update_ids --post_type=post --posts_per_page=10 --offset=0
	 *
	 * @since TBD
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

		$query = new WP_Query(
			[
				'post_type'              => $assoc_args['post_type'],
				'posts_per_page'         => $assoc_args['posts_per_page'],
				'offset'                 => $assoc_args['offset'],
				'post_status'            => 'any',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

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
				$body = json_decode( $body );

				// Get article reference id.
				$ref_id = isset( $body->article->id ) ? $body->article->id : '';

				// Skip if no id.
				if ( ! $ref_id ) {
					WP_CLI::line( 'Skipped. No reference ID found. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Get post with a meta key of unitedrobots_id and meta value of the ref_id.
				$existing = get_posts(
					[
						'post_type'      => $assoc_args['post_type'],
						'meta_key'       => 'unitedrobots_id',
						'meta_value'     => $ref_id,
						'meta_compare'   => '=',
						'fields'         => 'ids',
						'numberposts'    => 1,
					]
				);

				// Skip if existing.
				if ( ! $existing ) {
					WP_CLI::line( 'Skipped. ID already matches. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Update post meta.
				update_post_meta( get_the_ID(), 'unitedrobots_id', $ref_id );

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
	 * Updates posts from stored United Robots data.
	 *
	 * Usage: wp maiunitedrobots update_posts --post_type=post --posts_per_page=10 --offset=0
	 *
	 * @since TBD
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

		$lines = [];
		$query = new WP_Query(
			[
				'post_type'              => $assoc_args['post_type'],
				'posts_per_page'         => $assoc_args['posts_per_page'],
				'offset'                 => $assoc_args['offset'],
				'post_status'            => 'any',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

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
				$body = json_decode( $body );

				// Get article reference id.
				$ref_id = isset( $body->article->id ) ? $body->article->id : '';

				// Skip if no id.
				if ( ! $ref_id ) {
					WP_CLI::line( 'Skipped. No reference ID found. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				// Get post with a meta key of unitedrobots_id and meta value of the ref_id.
				$existing = get_posts(
					[
						'post_type'      => $assoc_args['post_type'],
						'meta_key'       => 'unitedrobots_id',
						'meta_value'     => $ref_id,
						'meta_compare'   => '=',
						'fields'         => 'ids',
						'numberposts'    => 1,
					]
				);

				// Skip if existing.
				if ( ! $existing ) {
					WP_CLI::line( 'Skipped. ID already matches. ' . get_permalink() );
					$progress->tick();
					continue;
				}

				$progress->tick();
			endwhile;

			$progress->finish();

			if ( $titles ) {
				foreach ( $titles as $title ) {
					WP_CLI::line( $title );
				}
			}

			WP_CLI::success( 'Done.' );
		} else {
			WP_CLI::success( 'No posts found.' );
		}

		wp_reset_postdata();
	}
}
