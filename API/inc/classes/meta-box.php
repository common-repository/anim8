<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

// Meta Box Class
if ( ! class_exists( 'anim8_Meta_Box' ) )
{
	/**
	 * A class to rapid develop meta boxes for custom & built in content types
	 * Piggybacks on WordPress
	 *
	 * @author Rilwis
	 * @author Co-Authors @see https://github.com/rilwis/meta-box
	 * @license GNU GPL2+
	 * @package RW Meta Box
	 */
	class anim8_Meta_Box
	{
		/**
		 * Meta box information
		 */
		var $anim8_box;

		/**
		 * Fields information
		 */
		var $fields;

		/**
		 * Contains all field types of current meta box
		 */
		var $types;

		/**
		 * Validation information
		 */
		var $validation;

		/**
		 * Create meta box based on given data
		 *
		 * @see demo/demo.php file for details
		 *
		 * @param array $anim8_box Meta box definition
		 *
		 * @return \anim8_Meta_Box
		 */
		function __construct( $anim8_box )
		{
			// Run script only in admin area
			if ( ! is_admin() )
				return;

			// Assign meta box values to local variables and add it's missed values
			$this->meta_box   = self::normalize( $anim8_box );
			$this->fields     = &$this->meta_box['fields'];
			$this->validation = &$this->meta_box['validation'];

			// Enqueue common styles and scripts
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

			// Add additional actions for fields
			foreach ( $this->fields as $field )
			{
				$class = self::get_class_name( $field );

				if ( method_exists( $class, 'add_actions' ) )
					call_user_func( array( $class, 'add_actions' ) );
			}

			// Add meta box
			foreach ( $this->meta_box['pages'] as $page )
			{
				add_action( "add_meta_boxes_{$page}", array( $this, 'add_meta_boxes' ) );
			}

			// Save post meta
			add_action( 'save_post', array( $this, 'save_post' ) );
		}

		/**
		 * Enqueue common styles
		 *
		 * @return void
		 */
		function admin_enqueue_scripts()
		{
			$screen = get_current_screen();

			// Enqueue scripts and styles for registered pages (post types) only
			if ( 'post' != $screen->base || ! in_array( $screen->post_type, $this->meta_box['pages'] ) )
				return;

			wp_enqueue_style( 'anim8', API_CSS_URL . 'style.css', API_VER );

			// Load clone script conditionally
			$has_clone = false;
			foreach ( $this->fields as $field )
			{
				if ( $field['clone'] )
					$has_clone = true;

				// Enqueue scripts and styles for fields
				$class = self::get_class_name( $field );
				if ( method_exists( $class, 'admin_enqueue_scripts' ) )
					call_user_func( array( $class, 'admin_enqueue_scripts' ) );
			}

			if ( $has_clone )
				wp_enqueue_script( 'anim8-clone', API_JS_URL . 'clone.js', array( 'jquery' ), API_VER, true );

			if ( $this->validation )
			{
				wp_enqueue_script( 'jquery-validate', API_JS_URL . 'jquery.validate.min.js', array( 'jquery' ), API_VER, true );
				wp_enqueue_script( 'anim8-validate', API_JS_URL . 'validate.js', array( 'jquery-validate' ), API_VER, true );
			}
		}

		/**************************************************
		 SHOW META BOX
		 **************************************************/

		/**
		 * Add meta box for multiple post types
		 *
		 * @return void
		 */
		function add_meta_boxes()
		{
			foreach ( $this->meta_box['pages'] as $page )
			{
				// Allow users to show/hide meta boxes
				// 1st action applies to all meta boxes
				// 2nd action applies to only current meta box
				$show = true;
				$show = apply_filters( 'anim8_show', $show, $this->meta_box );
				$show = apply_filters( "anim8_show_{$this->meta_box['id']}", $show, $this->meta_box );
				if ( !$show )
					continue;

				add_meta_box(
					$this->meta_box['id'],
					$this->meta_box['title'],
					array( $this, 'show' ),
					$page,
					$this->meta_box['context'],
					$this->meta_box['priority']
				);
			}
		}

		/**
		 * Callback function to show fields in meta box
		 *
		 * @return void
		 */
		public function show()
		{
			global $post;

			$saved = self::has_been_saved( $post->ID, $this->fields );

			wp_nonce_field( "anim8-save-{$this->meta_box['id']}", "nonce_{$this->meta_box['id']}" );

			// Allow users to add custom code before meta box content
			// 1st action applies to all meta boxes
			// 2nd action applies to only current meta box
			do_action( 'anim8_before' );
			do_action( "anim8_before_{$this->meta_box['id']}" );

			foreach ( $this->fields as $field )
			{
				$group = '';	// Empty the clone-group field
				$type = $field['type'];
				$id   = $field['id'];
				$meta = self::apply_field_class_filters( $field, 'meta', '', $post->ID, $saved );
				$meta = apply_filters( "anim8_{$type}_meta", $meta );
				$meta = apply_filters( "anim8_{$id}_meta", $meta );

				$begin = self::apply_field_class_filters( $field, 'begin_html', '', $meta );

				// Apply filter to field begin HTML
				// 1st filter applies to all fields
				// 2nd filter applies to all fields with the same type
				// 3rd filter applies to current field only
				$begin = apply_filters( 'anim8_begin_html', $begin, $field, $meta );
				$begin = apply_filters( "anim8_{$type}_begin_html", $begin, $field, $meta );
				$begin = apply_filters( "anim8_{$id}_begin_html", $begin, $field, $meta );

				// Separate code for cloneable and non-cloneable fields to make easy to maintain

				// Cloneable fields
				if ( $field['clone'] )
				{
					if ( isset( $field['clone-group'] ) )
						$group = " clone-group='{$field['clone-group']}'";

					$meta = (array) $meta;

					$field_html = '';

					foreach ( $meta as $index => $anim8_data )
					{
						$sub_field = $field;
						$sub_field['field_name'] = $field['field_name'] . "[{$index}]";
						if ( $field['multiple'] )
							$sub_field['field_name'] .= '[]';

						add_filter( "anim8_{$id}_html", array( $this, 'add_clone_buttons' ), 10, 3 );

						// Wrap field HTML in a div with class="anim8-clone" if needed
						$input_html = '<div class="anim8-clone">';

						// Call separated methods for displaying each type of field
						$input_html .= self::apply_field_class_filters( $sub_field, 'html', '', $anim8_data );

						// Apply filter to field HTML
						// 1st filter applies to all fields with the same type
						// 2nd filter applies to current field only
						$input_html = apply_filters( "anim8_{$type}_html", $input_html, $field, $anim8_data );
						$input_html = apply_filters( "anim8_{$id}_html", $input_html, $field, $anim8_data );

						$input_html .= '</div>';

						$field_html .= $input_html;
					}
				}
				// Non-cloneable fields
				else
				{
					// Call separated methods for displaying each type of field
					$field_html = self::apply_field_class_filters( $field, 'html', '', $meta );

					// Apply filter to field HTML
					// 1st filter applies to all fields with the same type
					// 2nd filter applies to current field only
					$field_html = apply_filters( "anim8_{$type}_html", $field_html, $field, $meta );
					$field_html = apply_filters( "anim8_{$id}_html", $field_html, $field, $meta );
				}

				$end = self::apply_field_class_filters( $field, 'end_html', '', $meta );

				// Apply filter to field end HTML
				// 1st filter applies to all fields
				// 2nd filter applies to all fields with the same type
				// 3rd filter applies to current field only
				$end = apply_filters( 'anim8_end_html', $end, $field, $meta );
				$end = apply_filters( "anim8_{$type}_end_html", $end, $field, $meta );
				$end = apply_filters( "anim8_{$id}_end_html", $end, $field, $meta );

				// Apply filter to field wrapper
				// This allow users to change whole HTML markup of the field wrapper (i.e. table row)
				// 1st filter applies to all fields with the same type
				// 2nd filter applies to current field only
				$html = apply_filters( "anim8_{$type}_wrapper_html", "{$begin}{$field_html}{$end}", $field, $meta );
				$html = apply_filters( "anim8_{$id}_wrapper_html", $html, $field, $meta );

				// Display label and input in DIV and allow user-defined classes to be appended
				$classes = array( 'anim8-field', "anim8-{$field['type']}-wrapper" );
				if ( 'hidden' === $field['type'] )
					$classes[] = 'hidden';
				if ( !empty( $field['required'] ) )
					$classes[] = 'required';
				if ( !empty( $field['class'] ) )
					$classes[] = $field['class'];

				printf(
					'<div class="%s"%s>%s</div>',
					implode( ' ', $classes ),
					$group,
					$html
				);
			}

			// Include validation settings for this meta-box
			if ( isset( $this->validation ) && $this->validation )
			{
				echo '
					<script type="text/javascript">
						if ( typeof anim8 == "undefined" )
						{
							var anim8 = {
								validationOptions : jQuery.parseJSON( \'' . json_encode( $this->validation ) . '\' ),
								summaryMessage : "' . __( 'Please correct the errors highlighted below and try again.', 'anim8' ) . '"
							};
						}
						else
						{
							var tempOptions = jQuery.parseJSON( \'' . json_encode( $this->validation ) . '\' );
							jQuery.extend( true, anim8.validationOptions, tempOptions );
						};
					</script>
				';
			}

			// Allow users to add custom code after meta box content
			// 1st action applies to all meta boxes
			// 2nd action applies to only current meta box
			do_action( 'anim8_after' );
			do_action( "anim8_after_{$this->meta_box['id']}" );
		}

		/**
		 * Show begin HTML markup for fields
		 *
		 * @param string $html
		 * @param mixed  $meta
		 * @param array  $field
		 *
		 * @return string
		 */
		static function begin_html( $html, $meta, $field )
		{
			if ( empty( $field['name'] ) )
				return '<div class="anim8-input">';

			return sprintf(
				'<div class="anim8-label">
					<label for="%s">%s</label>
				</div>
				<div class="anim8-input">',
				$field['id'],
				$field['name']
			);
		}

		/**
		 * Show end HTML markup for fields
		 *
		 * @param string $html
		 * @param mixed  $meta
		 * @param array  $field
		 *
		 * @return string
		 */
		static function end_html( $html, $meta, $field )
		{
			$id = $field['id'];

			$button = '';
			if ( $field['clone'] )
				$button = '<a href="#" class="anim8-button button-primary add-clone">' . __( '+', 'anim8' ) . '</a>';

			$desc = ! empty( $field['desc'] ) ? "<p id='{$id}_description' class='description'>{$field['desc']}</p>" : '';

			// Closes the container
			$html = "{$button}{$desc}</div>";

			return $html;
		}

		/**
		 * Callback function to add clone buttons on demand
		 * Hooks on the flight into the "anim8_{$field_id}_html" filter before the closing div
		 *
		 * @param string $html
		 * @param array  $field
		 * @param mixed  $anim8_data
		 *
		 * @return string $html
		 */
		static function add_clone_buttons( $html, $field, $anim8_data )
		{
			$button = '<a href="#" class="anim8-button button remove-clone">' . __( '&#8211;', 'anim8' ) . '</a>';

			return "{$html}{$button}";
		}

		/**
		 * Standard meta retrieval
		 *
		 * @param mixed $meta
		 * @param int   $post_id
		 * @param array $field
		 * @param bool  $saved
		 *
		 * @return mixed
		 */
		static function meta( $meta, $post_id, $saved, $field )
		{
			$meta = get_post_meta( $post_id, $field['id'], !$field['multiple'] );

			// Use $field['std'] only when the meta box hasn't been saved (i.e. the first time we run)
			$meta = ( !$saved && '' === $meta || array() === $meta ) ? $field['std'] : $meta;

			// Escape attributes for non-wysiwyg fields
			if ( 'wysiwyg' !== $field['type'] )
				$meta = is_array( $meta ) ? array_map( 'esc_attr', $meta ) : esc_attr( $meta );

			return $meta;
		}

		/**************************************************
		 SAVE META BOX
		 **************************************************/

		/**
		 * Save data from meta box
		 *
		 * @param int $post_id Post ID
		 *
		 * @return int|void
		 */
		function save_post( $post_id )
		{
			global $post_type;
			$post_type_object = get_post_type_object( $post_type );

			// Check whether:
			// - the post is autosaved
			// - the post is a revision
			// - current post type is supported
			// - user has proper capability
			if (
				( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				|| ( ! isset( $_POST['post_ID'] ) || $post_id != $_POST['post_ID'] )
				|| ( ! in_array( $post_type, $this->meta_box['pages'] ) )
				|| ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) )
			)
			{
				return $post_id;
			}

			// Verify nonce
			check_admin_referer( "anim8-save-{$this->meta_box['id']}", "nonce_{$this->meta_box['id']}" );

			foreach ( $this->fields as $field )
			{
				$name = $field['id'];
				$old  = get_post_meta( $post_id, $name, !$field['multiple'] );
				$new  = isset( $_POST[$name] ) ? $_POST[$name] : ( $field['multiple'] ? array() : '' );

				// Allow field class change the value
				$new = self::apply_field_class_filters( $field, 'value', $new, $old, $post_id );

				// Use filter to change field value
				// 1st filter applies to all fields with the same type
				// 2nd filter applies to current field only
				$new = apply_filters( "anim8_{$field['type']}_value", $new, $field, $old );
				$new = apply_filters( "anim8_{$name}_value", $new, $field, $old );

				// Call defined method to save meta value, if there's no methods, call common one
				self::do_field_class_actions( $field, 'save', $new, $old, $post_id );
			}
		}

		/**
		 * Common functions for saving field
		 *
		 * @param mixed $new
		 * @param mixed $old
		 * @param int   $post_id
		 * @param array $field
		 *
		 * @return void
		 */
		static function save( $new, $old, $post_id, $field )
		{
			$name = $field['id'];

			if ( '' === $new || array() === $new )
			{
				delete_post_meta( $post_id, $name );
				return;
			}

			if ( $field['multiple'] )
			{
				foreach ( $new as $new_value )
				{
					if ( !in_array( $new_value, $old ) )
						add_post_meta( $post_id, $name, $new_value, false );
				}
				foreach ( $old as $old_value )
				{
					if ( !in_array( $old_value, $new ) )
						delete_post_meta( $post_id, $name, $old_value );
				}
			}
			else
			{
				update_post_meta( $post_id, $name, $new );
			}
		}

		/**************************************************
		 HELPER FUNCTIONS
		 **************************************************/

		/**
		 * Normalize parameters for meta box
		 *
		 * @param array $anim8_box Meta box definition
		 *
		 * @return array $anim8_box Normalized meta box
		 */
		static function normalize( $anim8_box )
		{
			// Set default values for meta box
			$anim8_box = wp_parse_args( $anim8_box, array(
				'id'       => sanitize_title( $anim8_box['title'] ),
				'context'  => 'normal',
				'priority' => 'high',
				'pages'    => array( 'post' )
			) );

			// Set default values for fields
			foreach ( $anim8_box['fields'] as &$field )
			{
				$field = wp_parse_args( $field, array(
					'multiple' => false,
					'clone'    => false,
					'std'      => '',
					'desc'     => '',
					'format'   => '',
				) );

				// Allow field class add/change default field values
				$field = self::apply_field_class_filters( $field, 'normalize_field', $field );

				// Allow field class to manually change field_name
				// @see taxonomy.php for example
				if ( ! isset( $field['field_name'] ) )
					$field['field_name'] = $field['id'];
			}

			return $anim8_box;
		}

		/**
		 * Get field class name
		 *
		 * @param array $field Field array
		 *
		 * @return bool|string Field class name OR false on failure
		 */
		static function get_class_name( $field )
		{
			$type  = ucwords( $field['type'] );
			$class = "anim8_{$type}_Field";

			if ( class_exists( $class ) )
				return $class;

			return false;
		}

		/**
		 * Apply filters by field class, fallback to anim8_Meta_Box method
		 *
		 * @param array  $field
		 * @param string $method_name
		 * @param mixed  $value
		 *
		 * @return mixed $value
		 */
		static function apply_field_class_filters( $field, $method_name, $value )
		{
			$args   = array_slice( func_get_args(), 2 );
			$args[] = $field;

			// Call:     field class method
			// Fallback: anim8_Meta_Box method
			$class = self::get_class_name( $field );
			if ( method_exists( $class, $method_name ) )
			{
				$value = call_user_func_array( array( $class, $method_name ), $args );
			}
			elseif ( method_exists( __CLASS__, $method_name ) )
			{
				$value = call_user_func_array( array( __CLASS__, $method_name ), $args );
			}

			return $value;
		}

		/**
		 * Call field class method for actions, fallback to anim8_Meta_Box method
		 *
		 * @param array  $field
		 * @param string $method_name
		 *
		 * @return mixed
		 */
		static function do_field_class_actions( $field, $method_name )
		{
			$args   = array_slice( func_get_args(), 2 );
			$args[] = $field;

			// Call:     field class method
			// Fallback: anim8_Meta_Box method
			$class = self::get_class_name( $field );
			if ( method_exists( $class, $method_name ) )
			{
				call_user_func_array( array( $class, $method_name ), $args );
			}
			elseif ( method_exists( __CLASS__, $method_name ) )
			{
				call_user_func_array( array( __CLASS__, $method_name ), $args );
			}
		}

		/**
		 * Format Ajax response
		 *
		 * @param string $message
		 * @param string $status
		 *
		 * @return void
		 */
		static function ajax_response( $message, $status )
		{
			$response = array( 'what' => 'meta-box' );
			$response['data'] = 'error' === $status ? new WP_Error( 'error', $message ) : $message;
			$x = new WP_Ajax_Response( $response );
			$x->send();
		}

		/**
		 * Check if meta box has been saved
		 * This helps saving empty value in meta fields (for text box, check box, etc.)
		 *
		 * @param int   $post_id
		 * @param array $fields
		 *
		 * @return bool
		 */
		static function has_been_saved( $post_id, $fields )
		{
			$saved = false;
			foreach ( $fields as $field )
			{
				if ( get_post_meta( $post_id, $field['id'], !$field['multiple'] ) )
				{
					$saved = true;
					break;
				}
			}
			return $saved;
		}
	}
}