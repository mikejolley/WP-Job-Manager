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
 * @version     $$next-version$$
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$term = get_term_by( 'slug', $field['default'], $field['taxonomy'] );

// Get selected value.
if ( isset( $field['value'] ) ) {
	$selected = $field['value'];
} elseif ( is_int( $field['default'] ) ) {
	$selected = $field['default'];
} elseif ( ! empty( $field['default'] ) && $term ) {
	$selected = $term->term_id;
} else {
	$selected = '';
}

// Select only supports 1 value.
if ( is_array( $selected ) ) {
	$selected = current( $selected );
}

$args = [
	'taxonomy'         => $field['taxonomy'],
	'hierarchical'     => 1,
	'show_option_all'  => true,
	'show_option_none' => $field['required'] ? '' : '-',
	'name'             => isset( $field['name'] ) ? $field['name'] : $key,
	'orderby'          => 'name',
	'selected'         => $selected,
	'hide_empty'       => false,
	'multiple'         => false,
];

if ( isset( $field['placeholder'] ) && ! empty( $field['placeholder'] ) ) {
	$args['placeholder'] = $field['placeholder'];
}

job_manager_dropdown_categories( apply_filters( 'job_manager_term_select_field_wp_dropdown_categories_args', $args ) );

if ( ! empty( $field['description'] ) ) : ?><small class="description"><?php echo wp_kses_post( $field['description'] ); ?></small><?php endif; ?>
