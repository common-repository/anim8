jQuery( document ).ready( function($)
{
	// Reorder images
	$( '.anim8-images' ).each( function()
	{
		var $this		= $(this),
			field_id	= $this.parents( '.anim8-field' ).find( '.field-id' ).val(),
			data		= {
				action  : 'anim8_reorder_images',
				_wpnonce: $('#nonce-reorder-images_' + field_id).val(),
				post_id : $('#post_ID').val(),
				field_id: field_id
			};
		$this.sortable(
		{
			placeholder: 'ui-state-highlight',
			items: 'li',
			update: function () {
				data.order = $this.sortable( 'serialize' );

				$.post( ajaxurl, data, function( r )
				{
					var res = wpAjax.parseAjaxResponse( r, 'ajax-response' );
					if ( res.errors )
						alert( res.responses[0].errors[0].message );
				}, 'xml' );
			}
		} );
	} );
} );