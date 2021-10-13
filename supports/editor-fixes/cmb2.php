<?php
add_filter('termmeta_box_args_CMB2_hookup', 'termmeta_box_args_CMB2_Hookup');
add_filter('termmeta_box_args_CMB2_Hookup', 'termmeta_box_args_CMB2_Hookup');
function termmeta_box_args_CMB2_Hookup($args) {
		$args = array_merge($args, array(
			'id' => $args['callback'][0]->cmb->cmb_id,
			'title' => $args['callback'][0]->cmb->prop( 'title' ),
			'context' => $args['callback'][0]->cmb->prop( 'context' ),
			'priority' => $args['callback'][0]->cmb->prop( 'priority' )
		));
		return $args;
}
