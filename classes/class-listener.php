<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use Alley\WP\Block_Converter\Block_Converter;

class Mai_United_Robots_Listener {
	protected $body;
	protected $return_json;
	protected $post_id;

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

		// Get times.
		$published  = isset( $this->body['sent']['first'] ) && $this->body['sent']['first'] ? $this->body['sent']['first'] : '';
		$modified   = isset( $this->body['sent']['latest'] ) && $this->body['sent']['latest'] ? $this->body['sent']['latest'] : '';
		$gmt_offset = get_option( 'gmt_offset' ) * 3600;

		// If published time.
		if ( $published ) {
			// Create a DateTime object with the modified time and adjust for GMT offset.
			$datetime = new DateTime( $published );
			$datetime->modify( "{$gmt_offset} seconds" );

			$post_args['post_date'] = $datetime->format( 'Y-m-d H:i:s' );
		}

		// Get article id.
		$ref_id = isset( $this->body['article']['id'] ) ? $this->body['article']['id'] : '';

		// If we have a reference id, get the post ID.
		if ( $ref_id ) {
			// Get post with a meta key of reference_id and meta value of the ref_id.
			$existing = get_posts(
				[
					'post_type'    => 'post',
					'meta_key'     => 'reference_id',
					'meta_value'   => $ref_id,
					'meta_compare' => '=',
					'fields'       => 'ids',
					'numberposts'  => 1,
				]
			);

			mai_united_robots_logger( 'get_posts()' );
			mai_united_robots_logger( $existing );

			// Get first.
			$existing = $existing && isset( $existing[0] ) ? $existing[0] : 0;

			// If we have an existing post, update it.
			if ( $existing ) {
				$update          = true;
				$post_args['ID'] = $existing;

				// If modified time.
				if ( $modified ) {
					// Create a DateTime object with the modified time and adjust for GMT offset.
					$datetime = new DateTime( $modified );
					$datetime->modify( "{$gmt_offset} seconds" );

					// Set the post_modified field with the adjusted timestamp.
					$post_args['post_modified'] = $datetime->format( 'Y-m-d H:i:s' );
				}
			}
		}

		// Insert or update the post.
		$this->post_id = wp_insert_post( $post_args );

		// Log post data.
		mai_united_robots_logger( $this->post_id );
		mai_united_robots_logger( $post_args );

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

		// If not updating an existing post.
		if ( ! $update ) {
			// Save the body for reference.
			update_post_meta( $this->post_id, 'unitedrobots_body', wp_json_encode( $this->body ) );

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
					$existing_id = get_posts(
						[
							'post_type'    => 'attachment',
							'meta_key'     => 'unitedrobots_url',
							'meta_value'   => $src,
							'meta_compare' => '=',
							'fields'       => 'ids',
						]
					);

					// If we have an existing image, use it.
					if ( $existing_id && isset( $existing_id[0] ) ) {
						$content[ $index ] = wp_get_attachment_image( $existing_id[0], 'large' );
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
		$existing_id = get_posts(
			[
				'post_type'    => 'attachment',
				'meta_key'     => 'unitedrobots_url',
				'meta_value'   => $image_url,
				'meta_compare' => '=',
				'fields'       => 'ids',
			]
		);

		// Bail if the image already exists.
		if ( $existing_id ) {
			return 0;
		}

		// Set the unitedrobots URL.
		$unitedrobots_url = $image_url;

		// Build a temp url.
		$tmp = download_url( $image_url );

		// Bail if error.
		if ( is_wp_error( $tmp ) ) {
			mai_united_robots_logger( $tmp->get_error_code() . ': upload_image() 1 ' . $tmp->get_error_message() );

			// Remove the original image and return the error.
			@unlink( $tmp );
			return $tmp;
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
			mai_united_robots_logger( $tmp->get_error_code() . ': upload_image() 2 ' . $tmp->get_error_message() );

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
