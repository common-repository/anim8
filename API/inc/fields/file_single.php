<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'anim8_File_Field' ) )
{
	class anim8_File_Field
	{
		/**
		 * Enqueue scripts and styles
		 *
		 * @return void
		 */
		static function admin_enqueue_scripts()
		{
			wp_enqueue_script( 'anim8-file', API_JS_URL . 'file.js', array( 'jquery', 'wp-ajax-response' ), API_VER, true );
		}

		/**
		 * Add actions
		 *
		 * @return void
		 */
		static function add_actions()
		{
			// Add data encoding type for file uploading
			add_action( 'post_edit_form_tag', array( __CLASS__, 'post_edit_form_tag' ) );

			// Delete file via Ajax
			add_action( 'wp_ajax_anim8_delete_file', array( __CLASS__, 'wp_ajax_delete_file' ) );
		}

		/**
		 * Add data encoding type for file uploading
		 *
		 * @return void
		 */
		static function post_edit_form_tag()
		{
			echo ' enctype="multipart/form-data"';
		}

		/**
		 * Ajax callback for deleting files.
		 * Modified from a function used by "Verve Meta Boxes" plugin
		 *
		 * @link http://goo.gl/LzYSq
		 * @return void
		 */
		static function wp_ajax_delete_file()
		{
			$post_id       = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
			$field_id      = isset( $_POST['field_id'] ) ? $_POST['field_id'] : 0;
			$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;

			check_admin_referer( "anim8-delete-file_{$field_id}" );

			delete_post_meta( $post_id, $field_id, $attachment_id );
			$ok = wp_delete_attachment( $attachment_id );

			if ( $ok )
				anim8_Meta_Box::ajax_response( '', 'success' );
			else
				anim8_Meta_Box::ajax_response( __( 'Error: Cannot delete file', 'anim8' ), 'error' );
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
			$i18n_delete = _x( 'Delete', 'file upload', 'anim8' );

			$html = wp_nonce_field( "anim8-delete-file_{$field['id']}", "nonce-delete-file_{$field['id']}", false, false );

			if ( $meta )
			{
				$html .= '<ol class="anim8-uploaded">';
				$li = '<li>%s (<a title="%s" class="anim8-delete-file" href="#" data-field_id="%s" data-attachment_id="%s">%s</a>)</li>';

				$attachment = wp_get_attachment_link( $meta );
				$html .= sprintf(
					$li,
					$attachment,
					$i18n_delete,
					$field['id'],
					$meta,
					$i18n_delete
				);

				$html .= '</ol>';
			}

			// Show form upload
			$html .= sprintf(
				'<input type="file" name="%s" />',
				$field['field_name']
			);

			return $html;
		}

		/**
		 * Get meta values to save
		 *
		 * @param mixed $new
		 * @param mixed $old
		 * @param int   $post_id
		 * @param array $field
		 *
		 * @return array|mixed
		 */
		static function value( $new, $old, $post_id, $field )
		{
			$name = $field['id'];
			if ( empty( $_FILES[$name] ) )
				return $new;

			$new = array();
			if ( $field['clone'] )
				$files = self::fix_file_array( $_FILES[$name] );
			else
				$files = array( $_FILES[$name] );

			foreach ( $files as $file_item )
			{
				$file = wp_handle_upload( $file_item, array( 'test_form' => false ) );

				if ( ! isset( $file['file'] ) )
					continue;

				$file_name = $file['file'];

				$attachment = array(
					'post_mime_type' => $file['type'],
					'guid'           => $file['url'],
					'post_parent'    => $post_id,
					'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ),
					'post_content'   => '',
				);
				$id = wp_insert_attachment( $attachment, $file_name, $post_id );

				if ( ! is_wp_error( $id ) )
				{
					wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file_name ) );

					// Save file ID in meta field
					$new[] = $id;
				}
			}

			return $field['clone'] ? $new : array_shift( $new );
		}

		/**
		 * Fixes the odd indexing of multiple file uploads from the format:
		 *	 $_FILES['field']['key']['index']
		 * To the more standard and appropriate:
		 *	 $_FILES['field']['index']['key']
		 *
		 * @param array $files
		 *
		 * @return array
		 */
		static function fix_file_array( $files )
		{
			$output = array();
			foreach ( $files as $key => $list )
			{
				foreach ( $list as $index => $value )
				{
					$output[$index][$key] = $value;
				}
			}
			return $output;
		}

		/**
		 * Normalize parameters for field
		 *
		 * @param array $field
		 *
		 * @return array
		 */
//		static function normalize_field( $field )
//		{
//			$field['multiple'] = true;
//			$field['std'] = empty( $field['std'] ) ? array() : $field['std'];
//			return $field;
//		}
	}
}