<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The real estate class.
 *
 * @since 0.1.0
 */
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
		$category = __( 'real-estate', 'mai-united-robots' );
		$child    = false;
		$tag      = false;

		// Maybe get child term and tag.
		if ( isset( $this->body['description']['city'] ) ) {
			$child = __( 'sold', 'mai-united-robots' );
			$tag   = $this->body['description']['city'];
		} elseif ( isset( $this->body['description']['zipGroup'] ) ) {
			$tag   = $this->body['description']['zipGroup'];
		}

		// Add (or create then add) the category.
		wp_set_object_terms( $this->post_id, $category, 'category', $append = false );

		// Maybe add a child category.
		if ( $child ) {
			wp_set_object_terms( $this->post_id, $child, 'category', $append = true );
		}

		// Maybe add a tag.
		if ( $tag ) {
			wp_set_object_terms( $this->post_id, $tag, 'post_tag', $append = true );
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

	/**
	 * Get image urls for automatic import.
	 *
	 * @return void
	 */
	function get_image_urls() {
		$image_urls = [];

		// Maybe add the streetview image.
		if ( isset( $this->body['description']['streetview'] ) ) {
			$image_urls[] = $this->body['description']['streetview'];
		}

		// Maybe add the streetview images.
		if ( isset( $this->body['description']['streetviews'] ) ) {
			foreach ( $this->body['description']['streetviews'] as $image ) {
				$image_urls[] = $image;
			}
		}

		return $image_urls;
	}
}
