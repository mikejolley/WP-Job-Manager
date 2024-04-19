/**
 * Internal dependencies.
 */
import { initializePromoteModals } from './admin/promote-job-modals';

jQuery(document).ready(function($) {
	// Tooltips
	$( '.tips, .help_tip' ).each( function() {
		var $self = $(this);
		var tipText = $self.attr( 'data-tip' );

		if ( tipText ) {
			$(this).tipTip( {
				'content': '',
				'fadeIn': 50,
				'fadeOut': 50,
				'delay': 200,
				'enter': function () {
					$(tiptip_content).text( tipText );
				}
			} );
		}
	} );

	// Settings page

	const $settings_submit_button = $( '.job-manager-settings-submit' );
	$( '.job-manager-options' ).on( 'change keyup', () => $settings_submit_button.removeClass( 'is-outline' ) )

	if( $settings_submit_button.length ) {
		window.scrollTo( 0, 0 );
		setTimeout( () => window.scrollTo( 0, 0 ), 0 );
	}

	$( '.job-manager-settings-wrap' ).each( function() {
		$( '#wpbody-content > .notice' ).hide();
	})

	// Author
	$( 'p.form-field-author' ).on( 'click', 'a.change-author', function() {
		$(this).closest( 'p' ).find('.current-author').hide();
		var $changeAuthor = $(this).closest( 'p' ).find('.change-author');
		$changeAuthor.show();
		$changeAuthor.find(':input.wpjm-user-search').trigger( 'init.user_search' );

		return false;
	});

	// User search box. Inspired by WooCommerce's approach.
	$( '#wpbody' ).on( 'init.user_search', ':input.wpjm-user-search', function() {
		var select2_args = {
			allowClear:  !! $( this ).data( 'allow_clear' ),
			placeholder: $( this ).data( 'placeholder' ),
			minimumInputLength: $( this ).data( 'minimum_input_length' ) ? $( this ).data( 'minimum_input_length' ) : '1',
			errorLoading: job_manager_admin_params.user_selection_strings.searching,
			inputTooShort: function( args ) {
				var remainingChars = args.minimum - args.input.length;

				if ( 1 === remainingChars ) {
					return job_manager_admin_params.user_selection_strings.input_too_short_1;
				}

				return job_manager_admin_params.user_selection_strings.input_too_short_n.replace( '%qty%', remainingChars );
			},
			loadingMore: function() {
				return job_manager_admin_params.user_selection_strings.load_more;
			},
			noResults: function() {
				return job_manager_admin_params.user_selection_strings.no_matches;
			},
			searching: function() {
				return job_manager_admin_params.user_selection_strings.searching;
			},
			templateResult: function (result) {
				return result.text;
			},
			templateSelection: function (selection) {
				return selection.text;
			},
			width: '100%',
			ajax: {
				url:         job_manager_admin_params.ajax_url,
				dataType:    'json',
				delay:       1000,
				data:        function( params ) {
					return {
						term:     params.term,
						action:   'job_manager_search_users',
						security: job_manager_admin_params.search_users_nonce,
						page:     params.page
					};
				},
				processResults: function( data ) {
					var terms = [];
					if ( data && data.results ) {
						$.each( data.results, function( id, text ) {
							terms.push({
								id: id,
								text: text
							});
						});
					}
					return {
						results: terms,
						pagination: {
							more: data.more
						}
					};
				},
				cache: true
			}
		};

		$( this ).select2( select2_args );
	});
	$( ':input.wpjm-user-search:visible' ).trigger( 'init.user_search' );

	// Uploading files
	var file_frame;
	var file_target_input;
	var file_target_wrapper;

	$( document.body ).on('click', '.wp_job_manager_add_another_file_button', function( event ){
		event.preventDefault();

		var field_name        = $( this ).data( 'field_name' );
		var field_placeholder = $( this ).data( 'field_placeholder' );
		var button_text       = $( this ).data( 'uploader_button_text' );
		var button            = $( this ).data( 'uploader_button' );
		var view_button       = $( this ).data( 'view_button' );

		$( this ).before( '<span class="file_url"><input type="text" name="' + field_name + '[]" placeholder="' + field_placeholder + '" /><button class="button button-small wp_job_manager_upload_file_button" data-uploader_button_text="' + button_text + '">' + button + '</button><button class="button button-small wp_job_manager_view_file_button">' + view_button + '</button></span>' );
	} );

	$( document.body ).on('click', '.wp_job_manager_view_file_button', function ( event ) {
		event.preventDefault();

		var attachment_url = $( this ).data( 'download-url' );

		if ( ! attachment_url ) {
			file_target_wrapper = $( this ).closest( '.file_url' );
			file_target_input = file_target_wrapper.find( 'input' );

			var attachment_url = file_target_input.val();
		}

		if ( attachment_url.indexOf( '://' ) > - 1 ) {
			window.open( attachment_url, '_blank' );
		} else {
			file_target_input.addClass( 'file_no_url' );
			setTimeout( function () {
				file_target_input.removeClass( 'file_no_url' );
			}, 1000 );
		}

	});

	$( document.body ).on('click', '.wp_job_manager_upload_file_button', function( event ){
		event.preventDefault();

		file_target_wrapper = $( this ).closest('.file_url');
		file_target_input   = file_target_wrapper.find('input');

		// If the media frame already exists, reopen it.
		if ( file_frame ) {
			file_frame.open();
			return;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: $( this ).data( 'uploader_title' ),
			button: {
				text: $( this ).data( 'uploader_button_text' )
			},
			multiple: false  // Set to true to allow multiple files to be selected
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one image from the uploader
			var attachment = file_frame.state().get('selection').first().toJSON();

			$( file_target_input ).val( attachment.url );
		});

		// Finally, open the modal
		file_frame.open();
	});
});

jQuery(document).ready(function($) {
	var taxonomy = 'job_listing_type';
	$('#' + taxonomy + 'checklist li :radio, #' + taxonomy + 'checklist-pop :radio').on( 'click', function(){
		var t = $(this), c = t.is(':checked'), id = t.val();
		$('#' + taxonomy + 'checklist li :radio, #' + taxonomy + 'checklist-pop :radio').prop('checked',false);
		$('#in-' + taxonomy + '-' + id + ', #in-popular-' + taxonomy + '-' + id).prop( 'checked', c );
	});
});

if ( job_manager_admin_params.promoted_jobs_enabled ) {
	initializePromoteModals();
}
