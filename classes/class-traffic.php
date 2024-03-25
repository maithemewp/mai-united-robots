<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The traffic class.
 *
 * @since 0.6.0
 */
class Mai_United_Robots_Traffic_Listener extends Mai_United_Robots_Listener {
	/**
	 * Additional processing specific to this listener.
	 *
	 * @since 0.6.0
	 *
	 * @return void
	 */
	function process() {
		// Add (or create then add) the category.
		wp_set_object_terms( $this->post_id, __( 'Traffic', 'mai-united-robots' ), 'category', $append = false );
	}

	/**
	 * Get the image urls.
	 *
	 * @since 0.6.0
	 *
	 * @return array
	 */
	function get_image_urls() {
		$image_urls = [];

		if ( isset( $this->body['description']['image'] ) && ! empty( $this->body['description']['image'] ) ) {
			$image_urls[] = $this->body['description']['image'];
		}

		return $image_urls;
	}
}
