<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_United_Robots_Real_Estate_Listener extends Mai_United_Robots_Listener {
	protected $image_url = '';

	/**
	 * Additional processing specific to this listener.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function process() {
		$category = __( 'Real Estate', 'mai-united-robots' );
		$tag      = false;

		if ( isset( $this->body['description']['city'] ) ) {
			$tag = $this->body['description']['city'];
		} elseif ( isset( $this->body['description']['zipGroup'] ) ) {
			$tag = $this->body['description']['zipGroup'];
		}

		// Add (or create then add) the category.
		wp_set_object_terms( $this->post_id, $category, 'category', $append = false );

		// Add (or create then add) the tag.
		if ( $tag ) {
			wp_set_object_terms( $this->post_id, sanitize_text_field( $tag ), 'post_tag', $append = true );
		}
	}

	/**
	 * Convert streetview placeholders to <img> tags.
	 * Upload the images to the media library.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	function before_process_content( $content ) {
		// Define the pattern to match the placeholders and extract the URLs.
		$pattern = '/{PLACEHOLDER:STREETVIEW_(.*?)}/';

		// Use preg_replace_callback to replace each match with an <img> tag.
		$result = preg_replace_callback( $pattern, function( $matches ) {
			if ( ! isset( $matches[1] ) || empty( $matches[1] ) ) {
				return '';
			}

			// Upload image to media library.
			$image_id = $this->upload_image( $matches[1], $this->post_id );

			// Bail if no image ID or error.
			if ( ! $image_id || is_wp_error( $image_id ) ) {
				return '';
			}

			// Return image src.
			return wp_get_attachment_image( $image_id, 'large' );

		}, $content );

		return $result;
	}
}
