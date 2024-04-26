<?php
/**
 * Shows term `select` form field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/term-select-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     2.3.0
 *
 * @var array $key Form field name.
 * @var array $field Form field data.
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$placeholder = array_key_exists( 'placeholder', $field ) && ! empty( $field['placeholder'] ) ? $field['placeholder'] : esc_html__( 'Select an Option...', 'wp-job-manager' );
$selected = null;

// Get selected value.
if ( isset( $field['value'] ) ) {
	$selected = $field['value'];
} elseif ( is_int( $field['default'] ) ) {
	$selected = $field['default'];
} elseif ( ! empty( $field['default'] ) ) {
	$default = get_term_by( 'slug', $field['default'], $field['taxonomy'] );
	if ( ! empty( $default ) ) {
		$selected = $default->term_id;
	}
} else {
	$selected = '';
}

// Select only supports 1 value.
if ( is_array( $selected ) ) {
	$selected = current( $selected );
}

$args = [
	'taxonomy'          => $field['taxonomy'],
	'hierarchical'      => 1,
	'show_option_all'   => false,
	'option_none_value' => '',
	'show_option_none'  => $placeholder,
	'name'              => isset( $field['name'] ) ? $field['name'] : $key,
	'orderby'           => 'name',
	'selected'          => $selected,
	'hide_empty'        => false,
];

wp_dropdown_categories( apply_filters( 'job_manager_term_select_field_wp_dropdown_categories_args', $args, $key, $field ) );

if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo wp_kses_post( $field['description'] ); ?></small><?php endif; ?>
