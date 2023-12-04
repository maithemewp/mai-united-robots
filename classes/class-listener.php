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
		$title   = isset( $this->body['article']['text']['title'] ) ? $this->body['article']['text']['title'] : '';
		$content = isset( $this->body['article']['text']['bodyParts'] ) ? $this->body['article']['text']['bodyParts'] : '';
		$excerpt = isset( $this->body['description']['seo']['summary'] ) ? $this->body['description']['seo']['summary'] : '';

		// Bail if we don't have title and content.
		if ( ! ( $title && $content ) ) {
			wp_send_json_error('Custom error message', 400);
		}

		// Insert the post.
		$this->post_id = wp_insert_post(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
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

		// This should be overridden in child classes.
		$this->process();

		// Handle images.
		$this->handle_images();
	}

	/**
	 * Additional processing specific to each listener.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function process() {
		// This should be overridden in child classes.
	}

	/**
	 * Get image urls.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function get_image_urls() {
		// This should be overridden in child classes.
		return [];
	}

	/**
	 * Convert blocks.
	 *
	 * @since 0.1.0
	 *
	 * @param string $content The raw HTML content.
	 *
	 * @return string The converted content.
	 */
	function handle_content( $content ) {
		// Convert <i> to <em>.
		$content = str_replace( '<i>', '<em>', $content );
		$content = str_replace( '</i>', '</em>', $content );

		// Convert <b> to <strong>.
		$content = str_replace( '<b>', '<strong>', $content );
		$content = str_replace( '</b>', '</strong>', $content );

		// Allow overriding content before it's processed.
		$content = $this->before_process_content( $content );

		// Add paragraphs.
		$content = wpautop( $content );

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
		if ( isset( $this->body['referenceId'] ) && ! empty( $this->body['referenceId'] ) ) {
			$meta['reference_id'] = $this->body['referenceId'];
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

		// Force https.
		$url = str_replace( 'http:', 'https:', $url );

		// Build a temp url.
		$tmp = download_url( $url );

		// Bail if error.
		if ( is_wp_error( $tmp ) ) {
			// Remove the original image and return the error.
			@unlink( $tmp );
			return $tmp;
		}

		// Build the file array.
		$file_array = [
			'name'     => basename( $url ),
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

		return $image_id;
	}
}
