<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use Alley\WP\Block_Converter\Block_Converter;

class Mai_United_Robots_Listener {
	protected $body;
	protected $return_json;
	protected $post_id;
	protected $published_iso;
	protected $modified_iso;

	/**
	 * Construct the class.
	 */
	function __construct( $body, $return_json = true ) {
		$this->body        = is_string( $body ) ? json_decode( $body, true ) : $body;
		$this->return_json = $return_json;
		$this->run();
	}

	/**
	 * Get post ID.
	 *
	 * @since 0.2.0
	 *
	 * @return int
	 */
	function get_post_id() {
		return $this->post_id;
	}

	/**
	 * Run the logic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function run() {
		$update  = false;
		$title   = isset( $this->body['article']['text']['title'] ) ? $this->body['article']['text']['title'] : '';
		$content = isset( $this->body['article']['text']['bodyParts'] ) ? $this->body['article']['text']['bodyParts'] : [];
		$excerpt = isset( $this->body['description']['seo']['summary'] ) ? $this->body['description']['seo']['summary'] : '';

		// Bail if we don't have title and content.
		if ( ! ( $title && $content ) ) {
			mai_united_robots_logger( 'Missing title and content' );
			mai_united_robots_logger( $this->body );

			return $this->send_json_error( 'Missing title and content' );
		}

		// Set default user.
		$email   = mai_united_robots_get_author_email();
		$user    = get_user_by( 'email', $email );
		$user_id = $user ? $user->ID : 0;

		// Set post args.
		$post_args = [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => $user_id,
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'meta_input'   => $this->get_meta(),
		];

		// Force modified time.
		add_action( 'wp_insert_post_data', [ $this, 'force_modified_date' ], 20, 2 );

		// Get times.
		$this->published_iso = isset( $this->body['sent']['first'] ) && $this->body['sent']['first'] ? $this->body['sent']['first'] : '';
		$this->modified_iso  = isset( $this->body['sent']['latest'] ) && $this->body['sent']['latest'] ? $this->body['sent']['latest'] : '';

		// If published time.
		if ( $this->published_iso ) {
			$published = $this->get_date( $this->published_iso );

			// If this date is not in the future.
			if ( $this->published_iso < wp_date( DATE_RFC3339, time(), new DateTimeZone( 'UTC' ) ) ) {
				$post_args['post_date'] = $published;
			}
		}

		// Get article id.
		$ref_id = isset( $this->body['article']['id'] ) ? $this->body['article']['id'] : '';

		// If we have a reference id, get the post ID.
		if ( $ref_id ) {
			// Get post with a meta key of reference_id and meta value of the ref_id.
			$existing = get_posts(
				[
					'post_type'    => 'post',
					'post_status'  => 'any',
					'meta_key'     => 'reference_id',
					'meta_value'   => $ref_id,
					'meta_compare' => '=',
					'fields'       => 'ids',
					'numberposts'  => 1,
				]
			);

			// Get first.
			$existing = $existing && isset( $existing[0] ) ? $existing[0] : 0;

			// If we have an existing post, update it.
			if ( $existing ) {
				$update          = true;
				$post_args['ID'] = $existing;

				// If modified time.
				if ( ! $this->modified_iso && $this->published_iso ) {
					$this->modified_iso = $this->published_iso;
				}

				/**
				 * If ends with a dash and a number, try to fix the url.
				 * This was to fix a prior bug with scheduled posts duplicating.
				 */
				if ( preg_match( '/-\d+$/', get_post( $existing )->post_name, $matches ) ) {
					$suffix                 = $matches[0];
					$post_args['post_name'] = sanitize_title_with_dashes( $title );
				}
			}
		}

		// Insert or update the post.
		$this->post_id = wp_insert_post( $post_args );

		// Bail if we don't have a post ID or there was an error.
		if ( ! $this->post_id || is_wp_error( $this->post_id ) ) {
			if ( is_wp_error( $this->post_id ) ) {
				mai_united_robots_logger( $this->post_id->get_error_code() . ': ' . $this->post_id->get_error_message() );

				return $this->send_json_error( $this->post_id->get_error_message(), $this->post_id->get_error_code() );
			}

			return $this->send_json_error( 'Failed during wp_insert_post()' );
		}

		// Set post content. This runs after so we can attach images to the post ID.
		$updated_id = wp_update_post(
			[
				'ID'           => $this->post_id,
				'post_content' => $this->handle_content( $content ),
			]
		);

		// Save the body for reference.
		update_post_meta( $this->post_id, 'unitedrobots_body', wp_json_encode( $this->body ) );

		// If not updating an existing post.
		if ( ! $update ) {
			// This should be overridden in child classes.
			$this->process();
		}

		// Handle images.
		$this->handle_images();

		// If not featured image, get first image from post content.
		if ( ! has_post_thumbnail( $this->post_id ) ) {
			$image_id = 0;
			$content  = get_post_field( 'post_content', $this->post_id );
			$tags     = new WP_HTML_Tag_Processor( $content );

			while ( $tags->next_tag( [ 'tag_name' => 'img' ] ) ) {
				$src      = $tags->get_attribute( 'src' );
				$image_id = $src ? attachment_url_to_postid( $src ) : 0;

				if ( ! $image_id ) {
					continue;
				}

				break;
			}

			// Set the featured image.
			if ( $image_id ) {
				set_post_thumbnail( $this->post_id, $image_id );
			}
		}

		// Return success.
		$text = $update ? 'updated successfully' : 'imported successfully';

		return $this->send_json_success( 'Post ' . $this->post_id . ' ' . $text, 200 );
	}

	/**
	 * Use modified date from JSON.
	 *
	 * @since 0.3.0
	 *
	 * @param array $data Slashed post data.
	 * @param array $postarr Raw post data.
	 *
	 * @return array Slashed post data with modified post_modified and post_modified_gmt.
	 */
	function force_modified_date( $data, $postarr ) {
		if ( $this->modified_iso && ( $this->modified_iso < wp_date( DATE_RFC3339, time(), new DateTimeZone( 'UTC' ) ) ) ) {
			$data['post_modified']     = $this->get_date( $this->modified_iso );
			$data['post_modified_gmt'] = get_gmt_from_date( $data['post_modified'] );
		}

		return $data;
	}

	/**
	 * Gets a formmatted date string in the current website timezone,
	 * for use in `wp_insert_post()`.
	 *
	 * @since 0.4.0
	 *
	 * @param string $date_time Any date string that works with `strtotime()`.
	 *
	 * @return string
	 */
	function get_date( $date_time ) {
		return wp_date( 'Y-m-d H:i:s', strtotime( $date_time ) );
	}

	/**
	 * Maybe send json error.
	 *
	 * @since 0.3.0
	 *
	 * @return JSON|void
	 */
	function send_json_error( $message, $code = null ) {
		if ( $this->return_json ) {
			return wp_send_json_error( $message, $code );
		}

		return;
	}

	/**
	 * Maybe send json success.
	 *
	 * @since 0.3.0
	 *
	 * @return JSON|void
	 */
	function send_json_success( $message, $code = null ) {
		if ( $this->return_json ) {
			return wp_send_json_success( $message, $code );
		}

		return;
	}

	/**
	 * Additional processing specific to each listener.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function process() {
		// This can be overridden in child classes.
	}

	/**
	 * Get image urls for automatic import.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function get_image_urls() {
		// This can be overridden in child classes.
		return [];
	}

	/**
	 * Convert blocks.
	 *
	 * @since 0.1.0
	 *
	 * @param array $content The array of items.
	 *
	 * @return string The converted content.
	 */
	function handle_content( $content ) {
		$list = false;

		// Loop through content and add p tags to any empty items.
		foreach ( $content as $index => $item ) {
			// Skip if a placeholder. This was the old way of doing it, but some stored JSON may reference it still.
			if ( str_starts_with( trim( $item ), '{PLACEHOLDER' ) ) {
				continue;
			}

			// Skip if footer placeholder.
			if ( 'FOOTER PLACEHOLDER' === trim( $item ) ) {
				// Unset item from array.
				unset( $content[ $index ] );
				continue;
			}

			// Check if item already has an element.
			$wrap = false;
			$tags = new WP_HTML_Tag_Processor( $item );

			while ( $tags->next_tag() ) {
				$wrap = true;

				// If it's an <img> tag.
				if ( 'IMG' === $tags->get_tag() ) {
					$src = $tags->get_attribute( 'src' );

					// Skip if no src.
					if ( ! $src ) {
						continue;
					}

					// Skip if src already contains the home url.
					if ( str_contains( home_url(), $src ) ) {
						continue;
					}

					// Check if there is an existing image.
					$existing_ids = get_posts(
						[
							'post_type'    => 'attachment',
							'post_status'  => 'any',
							'meta_key'     => 'unitedrobots_url',
							'meta_value'   => $src,
							'meta_compare' => '=',
							'fields'       => 'ids',
							'numberposts'  => 1,
						]
					);

					// If we have an existing image, use it.
					if ( $existing_ids && isset( $existing_ids[0] ) ) {
						$content[ $index ] = wp_get_attachment_image( $existing_ids[0], 'large' );
						continue;
					}

					// Upload the image.
					$image_id = $this->upload_image( $src, $this->post_id );

					// Skip if no image ID or error.
					if ( ! $image_id || is_wp_error( $image_id ) ) {
						continue;
					}

					$content[ $index ] = wp_get_attachment_image( $image_id, 'large' );
					continue;
				}
			}

			// If no wrap.
			if ( ! $wrap ) {
				// $html = '';

				// $one = str_starts_with( $item, 'u00b7 ' ) || str_starts_with( $item, 'u2022 ' );
				// $two = str_starts_with( $item, '• ' );

				// // If a faux-list.
				// if ( $one || $two ) {
				// 	$html = '';

				// 	if ( ! $list ) {
				// 		$html .= '<ul>';
				// 		$list  = true;
				// 	}

				// 	if ( $one ) {
				// 		$item = str_replace( 'u00b7 ', '', $item );
				// 		$item = str_replace( 'u2022 ', '', $item );
				// 	} else {
				// 		$item = str_replace( '• ', '', $item );
				// 	}

				// 	$content[ $index ] = sprintf( '%s<li>%s</li>', $html, $item );
				// }
				// // In a list, but this one is not a list item.
				// elseif ( $list ) {
				// 	$content[ $index - 1 ] .= '</ul>';
				// 	$content[ $index ]      = "<p>{$item}</p>";
				// 	$lits                   = false;
				// }
				// // Paragraph.
				// else {
				// 	$content[ $index ] = "<p>{$item}</p>";
				// }

				$item              = str_replace( 'u00b7 ', '', $item );
				$item              = str_replace( 'u2022 ', '', $item );
				$item              = str_replace( '• ', '', $item );
				$content[ $index ] = "<p>{$item}</p>";
			}
		}

		// Convert content to string.
		$content = implode( PHP_EOL . PHP_EOL, $content );

		// Convert <i> to <em>.
		$content = str_replace( '<i>', '<em>', $content );
		$content = str_replace( '</i>', '</em>', $content );

		// Convert <b> to <strong>.
		$content = str_replace( '<b>', '<strong>', $content );
		$content = str_replace( '</b>', '</strong>', $content );

		// Allow overriding content before it's processed.
		$content = $this->before_process_content( $content );

		// Convert to blocks.
		if ( ! has_blocks( $content ) && class_exists( 'Alley\WP\Block_Converter\Block_Converter' ) ) {
			$converter = new Block_Converter( $content );
			$content   = $converter->convert();
		}

		// Allow overriding content after it's processed.
		$content = $this->after_process_content( $content );

		return $content;
	}

	/**
	 * Process content before converting blocks.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	function before_process_content( $content ) {
		// This can be overridden in child classes.
		return $content;
	}

	/**
	 * Process content after converting blocks.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	function after_process_content( $content ) {
		// This can be overridden in child classes.
		return $content;
	}

	/**
	 * Upload the images to the media library.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	function handle_images() {
		$image_ids  = [];
		$image_urls = $this->get_image_urls();

		// Loop through image urls.
		foreach ( $image_urls as $image_url ) {
			// Upload image to media library.
			$image_id = $this->upload_image( $image_url, $this->post_id );

			// Skip if no image ID or error.
			if ( ! $image_id || is_wp_error( $image_id ) ) {
				continue;
			}

			$image_ids[] = $image_id;
		}

		// If we have images, set the featured image and add the rest to the gallery.
		if ( $image_ids ) {
			// Set the featured image.
			set_post_thumbnail( $this->post_id, $image_ids[0] );

			// Remove first image from gallery.
			unset( $image_ids[0] );

			// Add the rest of the images to the gallery.
			update_post_meta( $this->post_id, 'image_gallery', array_values( $image_ids ) );
		}
	}

	/**
	 * Get the meta.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	function get_meta() {
		$meta = [];

		// Reference ID.
		if ( isset( $this->body['article']['id'] ) && ! empty( $this->body['article']['id'] ) ) {
			$meta['reference_id'] = $this->body['article']['id'];
		}

		return $meta;
	}

	/**
	 * Downloads a remote file and inserts it into the WP Media Library.
	 *
	 * @access private
	 *
	 * @see https://developer.wordpress.org/reference/functions/media_handle_sideload/
	 *
	 * @param string $url     HTTP URL address of a remote file.
	 * @param int    $post_id The post ID the media is associated with.
	 *
	 * @return int|WP_Error The ID of the attachment or a WP_Error on failure.
	 */
	function upload_image( $image_url, $post_id ) {
		// Make sure we have the functions we need.
		if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}

		// Check if there is an attachment with unitedrobots_url meta key and value of $image_url.
		$existing_ids = get_posts(
			[
				'post_type'    => 'attachment',
				'post_status'  => 'any',
				'meta_key'     => 'unitedrobots_url',
				'meta_value'   => $image_url,
				'meta_compare' => '=',
				'fields'       => 'ids',
				'numberposts'  => 1,
			]
		);

		// Bail if the image already exists.
		if ( $existing_ids ) {
			return $existing_ids[0];
		}

		// Set the unitedrobots URL.
		$unitedrobots_url = $image_url;

		// Check if the image is a streetview image.
		$streetview_url = str_contains( $image_url, 'maps.googleapis.com/maps/api/streetview' );

		// If streetview.
		if ( $streetview_url ) {
			$image_contents = file_get_contents( $image_url );
			$image_hashed   = md5( $image_url ) . '.jpg';

			if ( $image_contents ) {
				// Get the uploads directory.
				$upload_dir = wp_get_upload_dir();
				$upload_url = $upload_dir['baseurl'];

				// Specify the path to the destination directory within uploads.
				$destination_dir = $upload_dir['basedir'] . '/mai-united-robots/';

				// Create the destination directory if it doesn't exist.
				if ( ! file_exists( $destination_dir ) ) {
					mkdir( $destination_dir, 0755, true );
				}

				// Specify the path to the destination file.
				$destination_file = $destination_dir . $image_hashed;

				// Save the image to the destination file.
				file_put_contents( $destination_file, $image_contents );

				// Bail if the file doesn't exist.
				if ( ! file_exists( $destination_file ) ) {
					return 0;
				}

				$image_url = $image_hashed;
			}

			// Build the image url.
			$image_url = untrailingslashit( $upload_url ) . '/mai-united-robots/' . $image_hashed;
		}

		// Build a temp url.
		$tmp = download_url( $image_url );

		// If streetview.
		if ( $streetview_url ) {
			// Remove the temp file.
			@unlink( $destination_file );
		}

		// Bail if error.
		if ( is_wp_error( $tmp ) ) {
			mai_united_robots_logger( $tmp->get_error_code() . ': upload_image() 1 ' . $image_url . ' ' . $tmp->get_error_message() );

			// Remove the original image and return the error.
			@unlink( $tmp );
			return 0;
		}

		// Build the file array.
		$file_array = [
			'name'     => basename( $image_url ),
			'tmp_name' => $tmp,
		];

		// Add the image to the media library.
		$image_id = media_handle_sideload( $file_array, $post_id );

		// Bail if error.
		if ( is_wp_error( $image_id ) ) {
			mai_united_robots_logger( $image_id->get_error_code() . ': upload_image() 2 ' . $image_url . ' ' . $image_id->get_error_message() );

			// Remove the original image and return the error.
			@unlink( $file_array[ 'tmp_name' ] );
			return $image_id;
		}

		// Remove the original image.
		@unlink( $file_array[ 'tmp_name' ] );

		// Set the external url for possible reference later.
		update_post_meta( $image_id, 'unitedrobots_url', $unitedrobots_url );

		// Set image meta for allyinteractive block importer.
		update_post_meta( $image_id, 'original_url', wp_get_attachment_image_url( $image_id, 'full' ) );

		return $image_id;
	}
}
