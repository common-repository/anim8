<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'anim8_Plupload_Image_Field' ) )
{
	class anim8_Plupload_Image_Field extends anim8_Image_Field
	{
		/**
		 * Add field actions
		 *
		 * @return	void
		 */
		static function add_actions()
		{
			parent::add_actions();
			add_action( 'wp_ajax_anim8_plupload_image_upload', array( __CLASS__, 'handle_upload' ) );
		}

		/**
		 * Upload
		 * Ajax callback function
		 *
		 * @return string Error or (XML-)response
		 */
		static function handle_upload()
		{
			check_admin_referer( 'anim8-upload-images_' . $_REQUEST['field_id'] );

			$post_id = 0;
			if ( is_numeric( $_REQUEST['post_id'] ) )
				$post_id = (int) $_REQUEST['post_id'];

			// You can use WP's wp_handle_upload() function:
			$file       = $_FILES['async-upload'];
			$file_attr  = wp_handle_upload( $file, array( 'test_form' => false ) );
			$attachment = array(
				'guid'           => $file_attr['url'],
				'post_mime_type' => $file_attr['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file['name'] ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			// Adds file as attachment to WordPress
			$id = wp_insert_attachment( $attachment, $file_attr['file'], $post_id );
			if ( ! is_wp_error( $id ) )
			{
				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file_attr['file'] ) );

				// Save file ID in meta field
				if ( isset( $_REQUEST['field_id'] ) )
					add_post_meta( $post_id, $_REQUEST['field_id'], $id, false );

				anim8_Meta_Box::ajax_response( self::img_html( $id, $_REQUEST['field_id'] ), 'success' );
			}

			exit;
		}

		/**
		 * Enqueue scripts and styles
		 *
		 * @return void
		 */
		static function admin_enqueue_scripts()
		{
			// Enqueue same scripts and styles as for file field
			parent::admin_enqueue_scripts();
			wp_enqueue_style( 'anim8-plupload-image', API_CSS_URL . 'plupload-image.css', array( 'wp-admin' ), API_VER );
			wp_enqueue_script( 'anim8-plupload-image', API_JS_URL . 'plupload-image.js', array( 'jquery-ui-sortable', 'wp-ajax-response', 'plupload-all' ), API_VER, true );
			wp_localize_script( 'anim8-plupload-image', 'anim8', array( 'url' => API_URL ) );
			wp_localize_script(
				'anim8-plupload-image', 'anim8_plupload_defaults', array(
					'runtimes'            => 'html5,silverlight,flash,html4',
					'file_data_name'      => 'async-upload',
					'multiple_queues'     => true,
					'max_file_size'       => wp_max_upload_size() . 'b',
					'url'                 => admin_url( 'admin-ajax.php' ),
					'flash_swf_url'       => includes_url( 'js/plupload/plupload.flash.swf' ),
					'silverlight_xap_url' => includes_url( 'js/plupload/plupload.silverlight.xap' ),
					'filters'             => array(
						array(
							'title'      => _x( 'Allowed Image Files', 'image upload', 'anim8' ),
							'extensions' => 'jpg,jpeg,gif,png',
						),
					),
					'multipart'        => true,
					'urlstream_upload' => true,
				)
			);
		}

		/**
		 * Get field HTML
		 *
		 * @param string $html
		 * @param mixed  $meta
		 * @param array  $field
		 *
		 * @return string
		 */
		static function html( $html, $meta, $field )
		{
			if ( ! is_array( $meta ) )
				$meta = ( array ) $meta;

			// Filter to change the drag & drop box background string
			$i18n_drop   = apply_filters( 'anim8_upload_drop_string', _x( 'Drop images here', 'image upload', 'anim8' ) );
			$i18n_or     = _x( 'or', 'image upload', 'anim8' );
			$i18n_select = _x( 'Select Files', 'image upload', 'anim8' );
			$img_prefix  = $field['id'];

			$html  = wp_nonce_field( "anim8-delete-file_{$field['id']}", "nonce-delete-file_{$field['id']}", false, false );
			$html .= wp_nonce_field( "anim8-reorder-images_{$field['id']}", "nonce-reorder-images_{$field['id']}", false, false );
			$html .= wp_nonce_field( "anim8-upload-images_{$field['id']}", "nonce-upload-images_{$field['id']}", false, false );
			$html .= "<input type='hidden' class='field-id anim8-image-prefix' value='{$field['id']}' />";

			// Uploaded images
			$html .= "<div id='{$img_prefix}-container'>";

			// Check for max_file_uploads
			$classes = array( 'anim8-drag-drop', 'drag-drop', 'hide-if-no-js' );
			if ( ! empty( $field['max_file_uploads'] ) )
			{
				$max_file_uploads = (int) $field['max_file_uploads'];
				$html .= "<input class='max_file_uploads' type='hidden' value='{$max_file_uploads}' />";
				if ( count( $meta ) >= $max_file_uploads )
					$classes[] = 'hidden';
			}

			$html .= self::get_uploaded_images( $meta, $field );

			// Show form upload
			$html .= sprintf(
				'<div id="%s-dragdrop" class="%s">
					<div class = "drag-drop-inside">
						<p class="drag-drop-info">%s</p>
						<p>%s</p>
						<p class="drag-drop-buttons"><input id="%s-browse-button" type="button" value="%s" class="button" /></p>
					</div>
				</div>',
				$img_prefix,
				implode( ' ', $classes ),
				$i18n_drop,
				$i18n_or,
				$img_prefix,
				$i18n_select
			);

			$html .= '</div>';

			return $html;
		}

		/**
		 * Get field value
		 * It's the combination of new (uploaded) images and saved images
		 *
		 * @param array $new
		 * @param array $old
		 * @param int   $post_id
		 * @param array $field
		 *
		 * @return array|mixed
		 */
		static function value( $new, $old, $post_id, $field )
		{
			$new = (array) $new;
			return array_unique( array_merge( $old, $new ) );
		}
	}
}