<?php
add_filter('termmeta_box_args_acf_form_taxonomy', function($args, $taxonomy, $term) {
	$args['title'] = 'ACF';
	add_action('admin_footer', function() {
		?><script>
		jQuery(document).ready( function($) {
			var acf_forms = $('.form-table tr.acf-field:first-of-type').closest('.form-table');
			$('#acf_form_taxonomy .inside h2').each(function(i){
				$(this).after('<table>' + acf_forms.eq(i).html() + '</table>');
				acf_forms.eq(i).remove();
			});
			jQuery('td.acf-input').css('width','100%');
		});
		</script><?php
	}, 9);//important to run before termmeta_boxes footer
return $args;	
}, 10, 3);

