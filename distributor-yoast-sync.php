<?php
/**
 * Plugin Name:     Distributor / Yoast Sync
 * Plugin URI:      https://github.com/timstl/distributor-yoast-sync
 * Description:     Sync social images from Yoast SEO when post is pushed or pulled by Distributor plugin.
 * Version:         1.0.1
 * Author:          Tim Gieseking, timstl@gmail.com
 * Author URI:      http://timgweb.com/
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     basetheme
 *
 * @package WordPress
 * @subpackage Distributor / Yoast Sync
 * @since 1.0
 * @version 1.0
 */

/* Abort! */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Utility function for logging.
 */
require_once plugin_dir_path( __FILE__ ) . 'lib/utils.php';

/**
 * The Yoast meta keys to sync.
 * These will have '-id' appended to the end of the key.
 *
 * Example: _yoast_wpseo_opengraph-image is the URL and _yoast_wpseo_opengraph-image-id is the media ID.
 */
function dty_yoast_meta_keys( $prepend = '_' ) {
	/**
	 * Allow filtering of meta keys.
	 */
	$meta_keys = apply_filters(
		'dty_yoast_meta_keys',
		array(
			'yoast_wpseo_opengraph-image',
			'yoast_wpseo_twitter-image',
		)
	);

	/**
	 * Add prefix to each key.
	 */
	$prepended = array();
	foreach ( $meta_keys as $meta_key ) {
		$prepended[] = $prepend . $meta_key;
	}

	return $prepended;
}

/**
 * Update opengraph meta on sending site, prior to subscription pushing.
 * When a notification is sent, Yoast has not yet saved the new Open Graph images for some reason.
 * This feels like a big hack, but without it new opengraph meta is only sent after updating twice.
 *
 * @param  array  $post_body The request body to send.
 * @param  object $post      The WP_Post that is being pushed.
 */
function dty_fix_opengraph_meta_on_update( $post_body, $post ) {

	/**
	 * The $_POST keys are not prepended with `_` but the $post_body keys are prepended.
	 */
	foreach ( dty_yoast_meta_keys( '' ) as $yoast_meta_key ) {
		$image_id  = 0;
		$image_url = null;
		if ( isset( $_POST[ $yoast_meta_key . '-id' ] ) ) {
			/**
			 * If the new ID is set in $_POST, try to get the attachment using this URL.
			 * This seems more reliable and safe than using the URL from $_POST.
			 * If for some reason we can't get the attachment URL, use the one in $_POST.
			 */
			$image_id  = intval( $_POST[ $yoast_meta_key . '-id' ] );
			$image_url = wp_get_attachment_url( $image_id );
			if ( ! $image_url ) {
				$image_url = wp_strip_all_tags( $_POST[ $yoast_meta_key ] );
			}

			/**
			 * Update the distributor_meta in $post_body.
			 */
			if ( $image_id > 0 && $image_url ) {
				$post_body['post_data']['distributor_meta'][ '_' . $yoast_meta_key . '-id' ][0] = $image_id;
				$post_body['post_data']['distributor_meta'][ '_' . $yoast_meta_key ][0]         = $image_url;
			}
		}

		if ( $image_id <= 0 || ! $image_url ) {
			/**
			 * No ID in $post_body for this key. Unset from meta to delete from receiving site.
			 */
			if ( isset( $post_body['post_data']['distributor_meta'][ '_' . $yoast_meta_key ] ) ) {
				unset( $post_body['post_data']['distributor_meta'][ '_' . $yoast_meta_key ] );
			}
			if ( isset( $post_body['post_data']['distributor_meta'][ '_' . $yoast_meta_key . '-id' ] ) ) {
				unset( $post_body['post_data']['distributor_meta'][ '_' . $yoast_meta_key . '-id' ] );
			}
		}
	}

	return $post_body;
}
add_filter( 'dt_subscription_post_args', 'dty_fix_opengraph_meta_on_update', 1, 2 );

/**
 * Hook into Distributor's pull post action: dt_pull_post
 *
 * @param int                $new_post   The newly created post ID.
 * @param ExternalConnection $connection       The distributor connection pulling the post.
 * @param array              $post_array The original post data retrieved via the connection.
 */
function dty_sync_opengraph_image_pull( $new_post, $connection, $post_array ) {
	foreach ( dty_yoast_meta_keys() as $yoast_meta_key ) {
		if ( isset( $post_array['meta'][ $yoast_meta_key . '-id' ] ) ) {
			dty_sync_opengraph_image( $new_post, $post_array['meta'][ $yoast_meta_key . '-id' ], $yoast_meta_key );
		} else {
			dty_remove_opengraph_image( $new_post, $yoast_meta_key );
		}
	}
}
add_action( 'dt_pull_post', 'dty_sync_opengraph_image_pull', 1, 3 );

/**
 * Hook into Distributor's REST API actions:
 * dt_process_distributor_attributes - Fires when a new post is created.
 * dt_process_subscription_attributes - Fires when a post is updated.
 *
 * @param \WP_Post         $new_post    Inserted or updated post object.
 * @param \WP_REST_Request $request Request object.
 * @param bool             $update  True when creating a post, false when updating.
 */
function dty_process_attributes( $new_post, $request, $update = false ) {
	$params = $request->get_params();

	/**
	 * Params array format varies in dt_process_distributor_attributes and dt_process_subscription_attributes hooks.
	 */
	if ( isset( $params['distributor_meta'] ) ) {
		$meta = $params['distributor_meta'];
	} elseif ( isset( $params['post_data']['distributor_meta'] ) ) {
		$meta = $params['post_data']['distributor_meta'];
	}

	foreach ( dty_yoast_meta_keys() as $yoast_meta_key ) {
		if ( isset( $meta[ $yoast_meta_key . '-id' ] ) && ! empty( $meta[ $yoast_meta_key . '-id' ] ) ) {
			dty_sync_opengraph_image( $new_post, $meta[ $yoast_meta_key . '-id' ], $yoast_meta_key );
		} else {
			dty_remove_opengraph_image( $new_post, $yoast_meta_key );
		}
	}
}
add_action( 'dt_process_distributor_attributes', 'dty_process_attributes', 1, 3 );
add_action( 'dt_process_subscription_attributes', 'dty_process_attributes', 1, 2 );

/**
 * Sync Yoast Open Graph image ID and image URL.
 *
 * @param int|object $new_post The new post's ID or a post object.
 * @param int|array  $dt_original_media_id The media ID from the external site. Used to find new attachment using meta key dt_original_media_id.
 * @param string     $yoast_meta_key The meta key for the Yoast image. Example: _yoast_wpseo_opengraph-image or _yoast_wpseo_twitter-image. The suffix '-id' will be appended.
 */
function dty_sync_opengraph_image( $new_post, $dt_original_media_id, $yoast_meta_key = '_yoast_wpseo_opengraph-image' ) {

	/**
	 * If $new_post is an object, get the ID. Otherwise use $new_post.
	 */
	$new_post_id = 0;
	if ( is_numeric( $new_post ) ) {
		$new_post_id = $new_post;
	} elseif ( is_object( $new_post ) && isset( $new_post->ID ) ) {
		$new_post_id = $new_post->ID;
	}

	/**
	 * No post ID, return.
	 */
	if ( $new_post_id === 0 ) {
		return false;
	}

	/**
	 * If the media ID is an array, grab the first value.
	 */
	if ( is_array( $dt_original_media_id ) ) {
		$dt_original_media_id = $dt_original_media_id[0];
	}
	$dt_original_media_id = intval( $dt_original_media_id );

	/**
	 * WP_Query to find the new attachment ID.
	 */
	global $post;
	$args    = array(
		'post_type'   => 'attachment',
		'post_status' => 'inherit',
		'meta_query'  => array(
			array(
				'key'   => 'dt_original_media_id',
				'value' => $dt_original_media_id,
			),
		),
	);
	$c_query = new WP_Query( $args );

	if ( $c_query->have_posts() ) {

		while ( $c_query->have_posts() ) {
			$c_query->the_post();

			$new_media_id  = $post->ID;
			$new_media_url = wp_get_attachment_image_url( $new_media_id, 'full' );

			/**
			 * If we find a new media ID and a new URL, update both Yoast meta keys.
			 */
			if ( $new_media_id && $new_media_url ) {
				update_post_meta( $new_post_id, $yoast_meta_key . '-id', $new_media_id );
				update_post_meta( $new_post_id, $yoast_meta_key, $new_media_url );

				/**
				 * Sometimes there can be more than one image with the same dt_original_media_id. We only want the first.
				 */
				break;
			}
		}
		wp_reset_postdata();

	} else {
		/**
		 * If we don't find the correct media, delete the meta keys.
		 */
		dty_remove_opengraph_image( $new_post_id, $yoast_meta_key );
	}
}

/**
 * Delete Yoast meta fields
 *
 * @param int|object $new_post The new post's ID or a post object.
 * @param string     $yoast_meta_key The meta key for the Yoast image. Example: _yoast_wpseo_opengraph-image or _yoast_wpseo_twitter-image. The suffix '-id' will be appended.
 */
function dty_remove_opengraph_image( $new_post, $yoast_meta_key ) {
	/**
	 * If $new_post is an object, get the ID. Otherwise use $new_post.
	 */
	$new_post_id = 0;
	if ( is_numeric( $new_post ) ) {
		$new_post_id = $new_post;
	} elseif ( is_object( $new_post ) && isset( $new_post->ID ) ) {
		$new_post_id = $new_post->ID;
	}

	/**
	 * No post ID, return.
	 */
	if ( $new_post_id === 0 ) {
		return false;
	}

	delete_post_meta( $new_post_id, $yoast_meta_key );
	delete_post_meta( $new_post_id, $yoast_meta_key . '-id' );
}
