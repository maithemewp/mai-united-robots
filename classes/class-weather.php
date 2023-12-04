<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_United_Robots_Weather_Listener extends Mai_United_Robots_Listener {
	/**
	 * Additional processing specific to this listener.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function process() {
		// Add (or create then add) the category.
		wp_set_object_terms( $this->post_id, __( 'Weather', 'mai-united-robots' ), 'category', true );
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

		if ( isset( $this->body['description']['images']['WeatherWarningImageHorizontal'] ) && ! empty( $this->body['description']['images']['WeatherWarningImageHorizontal'] ) ) {
			$image_urls[] = $this->body['description']['images']['WeatherWarningImageHorizontal'];
		} elseif ( isset( $this->body['description']['images']['WeatherWarningImage'] ) && ! empty( $this->body['description']['images']['WeatherWarningImage'] ) ) {
			$image_urls[] = $this->body['description']['images']['WeatherImage'];
		}

		return $image_urls;
	}
}
