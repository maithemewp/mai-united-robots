<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

use Alley\WP\Block_Converter\Block_Converter;

class Mai_United_Robots_Listener {
	protected $body;
	protected $post_id;

	/**
	 * Construct the class.
	 */
	function __construct( $body ) {
		$this->body = $body;
		$this->run();
	}

	/**
	 * Run the logic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function run() {
		$title   = isset( $this->body->article->text->title ) ? $this->body->article->text->title : '';
		$content = isset( $this->body->article->text->bodyParts ) ? $this->body->article->text->bodyParts : [];
		$excerpt = isset( $this->body->description->seo->summary ) ? $this->body->description->seo->summary : '';

		// Bail if we don't have title and content.
		if ( ! ( $title && $content ) ) {
			wp_send_json_error( 'Missing title and content', 400 );
		}

		// Get BB user.
		$user    = get_user_by( 'email', 'team@bizbudding.com' );
		$user_id = $user ? $user->ID : 0;

		// Insert the post.
		$this->post_id = wp_insert_post(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_author'  => $user_id,
				'post_title'   => $title,
				'post_content' => $this->handle_content( $content ),
				'post_excerpt' => $excerpt,
				'meta_input'   => $this->get_meta(),
			]
		);

		// Bail if we don't have a post ID or there was an error.
		if ( ! $this->post_id || is_wp_error( $this->post_id ) ) {
			return;
		}

		// Save the body for reference.
		update_post_meta( $this->post_id, 'unitedrobots_body', json_encode( $this->body ) );

		// This should be overridden in child classes.
		$this->process();

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
		wp_send_json_success( 'Post ' . $this->post_id . ' imported successfully', 200 );
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
		// Loop through content and add p tags to any empty items.
		foreach ( $content as &$item ) {
			// Skip if a placeholder.
			if ( str_starts_with( $item, '{PLACEHOLDER' ) ) {
				continue;
			}

			// Check if item already has an element.
			$wrap = false;
			$tags = new WP_HTML_Tag_Processor( $item );

			while ( $tags->next_tag() ) {
				$wrap = true;
				break;
			}

			// If no wrap, add p tags.
			if ( ! $wrap ) {
				$item = "<p>{$item}</p>";
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
		if ( class_exists( 'Alley\WP\Block_Converter\Block_Converter' ) ) {
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
		if ( isset( $this->body->referenceId ) && ! empty( $this->body->referenceId ) ) {
			$meta['reference_id'] = $this->body->referenceId;
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

		// Set the unitedrobots URL.
		$unitedrobots_url = $image_url;

		// Build a temp url.
		$tmp = download_url( $image_url );

		// Bail if error.
		if ( is_wp_error( $tmp ) ) {
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
