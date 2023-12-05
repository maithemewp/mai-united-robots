<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_United_Robots_Hurricane_Listener extends Mai_United_Robots_Listener {
	/**
	 * Additional processing specific to this listener.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function process() {
		// Add (or create then add) the category.
		wp_set_object_terms( $this->post_id, __( 'Hurricane', 'mai-united-robots' ), 'category', false );
	}

	/**
	 * Get the image urls.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	function get_image_urls() {
		$image_urls = [];

		if ( isset( $this->body->description->images ) && ! empty( $this->body->description->images ) ) {
			foreach ( $this->body->description->images as $image_url ) {
				$image_urls[] = $image_url;
			}
		}

		return $image_urls;
	}
}
