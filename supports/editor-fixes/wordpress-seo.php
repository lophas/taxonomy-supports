<?php
//	add_filter('termmeta_box_args_WPSEO_Taxonomy', '__return_false');return;

	add_filter('termmeta_box_args_WPSEO_Taxonomy', function($args) {
		$args['id'] = 'wpseo_meta_box';
		$args['title'] = 'Yoast SEO';
		add_action('admin_footer', function() {
			?><script>
			jQuery(document).ready( function($) {
				yoast_seobox = $('#wpseo_meta');
				if(yoast_seobox.length) { //yoast wp-seo fix
					$('.form-field.term-name-wrap input').clone().appendTo('body').hide(); //expected by scraper
					$('.form-field.term-description-wrap').detach().appendTo('body').hide(); //expected by scraper
					$('#wpseo_meta_box > h2 span').replaceWith(yoast_seobox.find('h2 span')); //title
					$('#wpseo_meta_box > .inside').replaceWith(yoast_seobox.find('.inside')); //content
				}
			});
			</script><?php
		},9);//important to run before termmeta_boxes footer
		return $args;
	});
