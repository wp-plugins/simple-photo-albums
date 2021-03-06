<?php
/**
 * Simple Photo Albums
 *
 * @package SimplePhotoAlbums\GalleryScript
 * @author Brady Vercher <brady@audiotheme.com>
 * @copyright Copyright (c) 2013, AudioTheme, LLC
 * @license GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Simple Photo Albums (Jetpack Carousel)
 * Plugin URI: http://wordpress.org/plugins/simple-photo-albums/
 * Description: Jetpack Carousel support for Simple Photo Albums.
 * Version: 1.0.0
 * Author: AudioTheme
 * Author URI: https://audiotheme.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Jetpack Carousel support for Simple Photo Albums.
 *
 * Demonstrates how to extend the main plugin to integrate support for a
 * third-party gallery script.
 *
 * @package SimplePhotoAlbums\GalleryScript
 * @author Brady Vercher <brady@audiotheme.com>
 * @since 1.0.0
 */
class Sphoa_GalleryScript_Jetpack_Carousel {
	/**
	 * Load Jetpack Carousel extension.
	 *
	 * @since 1.0.0
	 */
	public static function load() {
		if ( ! class_exists( 'Jetpack_Carousel' ) ) {
			return;
		}

		add_filter( 'simple_photo_albums_gallery_scripts', array( __CLASS__, 'register_addon' ) );

		// Don't register if the this isn't the active script.
		$settings = sphoa()->get_settings();
		if ( 'jetpack' != $settings['gallery_script'] ) {
			return;
		}

		add_action( 'simple_photo_albums_print_footer_scripts', array( __CLASS__, 'print_script' ) );
	}

	/**
	 * Register the Jetpack Carousel gallery script.
	 *
	 * @since 1.0.0
	 *
	 * @param array $scripts List of gallery scripts.
	 * @return array
	 */
	public static function register_addon( $scripts ) {
		$scripts['jetpack-carousel'] = __( 'Jetpack Carousel', 'simple-photo-albums' );
		return $scripts;
	}

	/**
	 * Print scripts for integrating albums with the Jetpack Carousel.
	 *
	 * @since 1.0.0
	 *
	 * @param array $albums List of photo albums.
	 */
	public static function print_script( $albums ) {
		// Build an array for output as a JSON object.
		foreach ( $albums as $gallery_id => $gallery ) {
			$images = array();
			foreach ( $gallery['attachment_ids'] as $id ) {
				$images[] = self::get_image( $id );
			}

			$data[ 'album-' . $gallery_id ]['extraData'] = self::get_gallery_data( $gallery['post_id'] );
			$data[ 'album-' . $gallery_id ]['images']    = implode( '', $images );
		}

		// Print a JSON object.
		echo "<script type='text/javascript'>\n";
		echo "/* <![CDATA[ */\n";
		echo "var simplePhotoAlbums = " . json_encode( $data ) . ";\n";
		echo "/* ]]> */\n";
		echo "</script>\n";
		?>
		<script type="text/javascript">
		jQuery(function($) {
			var $albums = $('.simple-photo-albums div.gallery');

			$(document.body).off('click', 'div.gallery,div.tiled-gallery');

			// Set the carousel on regular galleries again.
			// @see jetpack/modules/carousel/jetpack-carousel.js
			// @todo Galleries following photo albums won't register because of the way Jetpack works.
			$('div.gallery,.div.tiled-gallery').not($albums).on('click', function(e) {
				if ( ! $(this).jp_carousel( 'testForData', e.currentTarget ) )
					return;

				if ( $(e.target).parent().hasClass('gallery-caption') )
					return;

				e.preventDefault();

				$(this).jp_carousel('open', {start_index: $(this).find('.gallery-item, .tiled-gallery-item').index($(e.target).parents('.gallery-item, .tiled-gallery-item'))});
			});

			// Set up album galleries.
			$('.simple-photo-albums').on('click', 'a[data-album-id]', function(e) {
				var $self = $(this),
					gallery = simplePhotoAlbums[ $self.data('album-id') ],
					$galleryEl = $self.closest('.gallery-item'),
					galleryTag = $galleryEl.is('dl') ? '<dd>' : '<span>';

				e.preventDefault();
				e.stopPropagation();

				// Add extra data needed by the carousel script.
				for ( key in gallery.extraData ) {
					$galleryEl.data(key.replace('data-', ''), gallery.extraData[ key ]);
				}

				// Add gallery photos to the album if they haven't already been added.
				if ( ! $galleryEl.hasClass('is-setup' ) ) {
					$galleryEl.append( $( galleryTag, {
						html: gallery.images,
						css: {
							display: 'none'
						}
					} ) ).addClass('is-setup');
				}

				// Open the carousel.
				$galleryEl.jp_carousel('open', { items_selector: '[data-attachment-id]' });
			});
		});
		</script>
		<?php
	}

	/**
	 * Get an image tag with the required Jetpack data attributes.
	 *
	 * @since 1.0.0
	 * @see Jetpack_Carousel::add_data_to_images()
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public function get_image( $attachment_id ) {
		$attachment_id   = intval( $attachment_id );
		$orig_file       = wp_get_attachment_image_src( $attachment_id, 'full' );
		$orig_file       = isset( $orig_file[0] ) ? $orig_file[0] : wp_get_attachment_url( $attachment_id );
		$meta            = wp_get_attachment_metadata( $attachment_id );
		$size            = isset( $meta['width'] ) ? intval( $meta['width'] ) . ',' . intval( $meta['height'] ) : '';
		$img_meta        = ( ! empty( $meta['image_meta'] ) ) ? (array) $meta['image_meta'] : array();
		$comments_opened = intval( comments_open( $attachment_id ) );

		$medium_file_info = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$medium_file      = isset( $medium_file_info[0] ) ? $medium_file_info[0] : '';
		$large_file_info  = wp_get_attachment_image_src( $attachment_id, 'large' );
		$large_file       = isset( $large_file_info[0] ) ? $large_file_info[0] : '';

		$attachment       = get_post( $attachment_id );
		$attachment_title = wptexturize( $attachment->post_title );
		$attachment_desc  = wpautop( wptexturize( $attachment->post_content ) );

		if ( ! empty( $img_meta ) ) {
			foreach ( $img_meta as $k => $v ) {
				if ( 'latitude' == $k || 'longitude' == $k )
					unset( $img_meta[$k] );
			}
		}

		$img_meta = json_encode( array_map( 'strval', $img_meta ) );

		return wp_get_attachment_image(
			$attachment_id,
			'full',
			false,
			array(
				'data-attachment-id'     => $attachment_id,
				'data-orig-file'         => esc_attr( $orig_file ),
				'data-orig-size'         => $size,
				'data-comments-opened'   => $comments_opened,
				'data-image-meta'        => esc_attr( $img_meta ),
				'data-image-title'       => esc_attr( $attachment_title ),
				'data-image-description' => esc_attr( $attachment_desc ),
				'data-medium-file'       => esc_attr( $medium_file ),
				'data-large-file'        => esc_attr( $large_file ),
			)
		);
	}

	/**
	 * Get Jetpack gallery data.
	 *
	 * @since 1.0.0
	 * @see Jetpack_Carousel::add_data_to_container()
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_gallery_data( $post_id ) {
		$blog_id = (int) get_current_blog_id();

		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			$likes_blog_id = $blog_id;
		} else {
			$jetpack = Jetpack::init();
			$likes_blog_id = $jetpack->get_option( 'id' );
		}

		$extra_data = array(
			'data-carousel-extra' => array(
				'blog_id'       => $blog_id,
				'permalink'     => get_permalink( $post_id ),
				'likes_blog_id' => $likes_blog_id,
			),
		);

		$extra_data = apply_filters( 'jp_carousel_add_data_to_container', $extra_data );

		return $extra_data;
	}
}
add_action( 'init', array( 'Sphoa_GalleryScript_Jetpack_Carousel', 'load' ) );
