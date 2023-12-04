<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_United_Robots_Real_Estate_Listener extends Mai_United_Robots_Listener {
	/**
	 * Additional processing specific to this listener.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function process() {
		// Add (or create then add) the category.
		wp_set_object_terms( $this->post_id, __( 'Real Estate', 'mai-united-robots' ), 'category', true );

		// $properties = [];
		// $images     = isset( $this->body['description']['streetviews'] ) ? $this->body['description']['streetviews'] : [];

		// if ( isset( $this->body['description']['streetviews_addresses_full'] ) && ! empty( $this->body['description']['streetviews_addresses_full'] ) ) {
		// 	foreach ( $this->body['description']['streetviews_addresses_full'] as $index => $address ) {
		// 		$data = [];

		// 		// Set address.
		// 		$data['address'] = $address;

		// 		// Maybe add image.
		// 		if ( isset( $images[ $index ] ) && ! empty( $images[ $index ] ) ) {
		// 			$data['image'] = $images[ $index ];
		// 		} else {
		// 			$data['image'] = '';
		// 		}

		// 		$properties[] = $data;
		// 	}
		// }
	}

	/**
	 * Import and replace streetview placeholders before converting to blocks.
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
			if ( ! isset( $matches[1] ) ) {
				return '';
			}

			// Upload image to media library.
			$image_url = str_replace( 'http://', 'https://', $matches[1] );
			$image_id = $this->upload_image( $image_url, $this->post_id );

			// Bail if no image ID or error.
			if ( ! $image_id || is_wp_error( $image_id ) ) {
				return '';
			}

			// Return image src.
			return sprintf( '<img src="%s">', wp_get_attachment_image( $image_id, 'large' ) );

		}, $content );

		return $result;
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

		return $image_urls;
	}
}
