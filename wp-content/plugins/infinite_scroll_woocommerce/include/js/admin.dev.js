jQuery(document).ready(function($) {
    /***** Colour picker *****/
    $('.colorpicker').hide();
    $('.colorpicker').each( function() {
        $(this).farbtastic( $(this).closest('.color-picker').find('.color') );
    });
    $('.color').click(function() {
        $(this).closest('.color-picker').find('.colorpicker').fadeIn();
    });
    $(document).mousedown(function() {
        $('.colorpicker').each(function() {
            var display = $(this).css('display');
            if ( display == 'block' )
                $(this).fadeOut();
        });
    });
    /***** Uploading images *****/
    var file_frame;
    jQuery.fn.uploadMediaFile = function( button, preview_media ) {
        var button_id = button.attr('id');
        var field_id = button_id.replace( '_button', '' );
        var preview_id = button_id.replace( '_button', '_preview' );
        // If the media frame already exists, reopen it.
        if ( file_frame ) {
          file_frame.open();
          return;
        }
        // Create the media frame.
        file_frame = wp.media.frames.file_frame = wp.media({
          title: jQuery( this ).data( 'uploader_title' ),
          button: {
            text: jQuery( this ).data( 'uploader_button_text' ),
          },
          multiple: false
        });
        // When an image is selected, run a callback.
        file_frame.on( 'select', function() {
          attachment = file_frame.state().get('selection').first().toJSON();
          jQuery("#"+field_id).val(attachment.id);
          if( preview_media ) {
            jQuery("#"+preview_id).attr('src',attachment.sizes.thumbnail.url);
          }
        });
        // Finally, open the modal
        file_frame.open();
    }
    jQuery('.image_upload_button').click(function() {
        jQuery.fn.uploadMediaFile( jQuery(this), true );
    });
    jQuery('.image_delete_button').click(function() {
        jQuery(this).closest('td').find( '.image_data_field' ).val( '' );
        jQuery( '.image_preview' ).remove();
        return false;
    });
    /***** Navigation for settings page *****/
    // Make sure each heading has a unique ID.
    jQuery( 'ul#settings-sections.subsubsub' ).find( 'a' ).each( function ( i ) {
        var id_value = jQuery( this ).attr( 'href' ).replace( '#', '' );
        jQuery( 'h3:contains("' + jQuery( this ).text() + '")' ).attr( 'id', id_value ).addClass( 'section-heading' );
    });
    // Create nav links for settings page
    jQuery( '#isw_plugin_settings .subsubsub a.tab' ).click( function ( e ) {
        // Move the "current" CSS class.
        jQuery( this ).parents( '.subsubsub' ).find( '.current' ).removeClass( 'current' );
        jQuery( this ).addClass( 'current' );
        // If "All" is clicked, show all.
        if ( jQuery( this ).hasClass( 'all' ) ) {
            jQuery( '#isw_plugin_settings h3, #isw_plugin_settings form p, #isw_plugin_settings table.form-table, p.submit' ).fadeIn();
            return false;
        }
        // If the link is a tab, show only the specified tab.
        var toShow = jQuery( this ).attr( 'href' );
        // Remove the first occurance of # from the selected string (will be added manually below).
        toShow = toShow.replace( '#', '', toShow );
        jQuery( '#isw_plugin_settings h3, #isw_plugin_settings form div > p:not(".submit"), #isw_plugin_settings table' ).hide();
        jQuery( 'h3#' + toShow ).show().nextUntil( 'h3.section-heading', 'p, table, table p' ).fadeIn();
        return false;
    });
	
	//switch button on off
	jQuery("input[type=checkbox]").switchButton();
	if(!jQuery('#load_more_button_animate').is(':checked')){
		jQuery('#animation_method_load_more_button').prop('disabled', 'disabled');
	}
	//If no animation checked disable select box with animation css3 transitions 
	jQuery("body").on('change', '#load_more_button_animate', function(){
		if(!jQuery(this).is(':checked')){
			jQuery('#animation_method_load_more_button').prop('disabled', 'disabled');
		}else{
			jQuery('#animation_method_load_more_button').prop('disabled', false);
		}
	});
	
	
	if(!jQuery('#animate_to_top').is(':checked')){
		jQuery('#pixels_from_top').prop('disabled', 'disabled');
	}
	//If no animation checked disable select box with animation css3 transitions 
	jQuery('body').on('change', '#animate_to_top', function(){
		if(!jQuery(this).is(':checked')){
			jQuery('#pixels_from_top').prop('disabled', 'disabled');
		}else{
			jQuery('#pixels_from_top').prop('disabled', false);
		}
	});
	
	if(!jQuery('#masonry_bool').is(':checked')){
		jQuery('#masonry_item_selector,#layout_mode').prop('disabled', 'disabled');
	}
	jQuery('body').on('change', '#masonry_bool', function(){
		if(!jQuery(this).is(':checked')){
			jQuery('#masonry_item_selector,#layout_mode').prop('disabled', 'disabled');
		}else{
			jQuery('#masonry_item_selector,#layout_mode').prop('disabled', false);
		}
	});
	
	if(jQuery('#ajax_method').val()!="method_infinite_scroll"){
		jQuery('#start_loading_x_from_end').prop('disabled', 'disabled');
	}
	
	jQuery('body').on('change', '#ajax_method', function(){
		if(jQuery('#ajax_method').val()!="method_infinite_scroll"){
			jQuery('#start_loading_x_from_end').prop('disabled', 'disabled');
		}else{
			jQuery('#start_loading_x_from_end').prop('disabled', false);
		}
	});
	
	//preview animation
	$('select#animation_method_load_more_button').change( function(){
		$('#isw-load-more-button').removeClass();
		var val = $(this).val();
		console.log(val);
		if(val!="no_animation"){
				$('#isw-load-more-button').addClass("animated "+val);
		}
	});
});
