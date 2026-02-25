<?php
/**
 * Theme back compatibility functionality
 *
 * Mitigates issues arising from changes to select controls in the Customizer that switched from slug-based values to ID-based values.
 * This file provides filters that automatically convert legacy slug selections to their corresponding IDs,
 * ensuring that existing theme settings continue to work without requiring manual updates from users.
 *
 * @package BlogHash
 * @author Peregrine Themes
 * @since   1.0.28
 */

/**
 * Do not allow direct script access.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register filters to migrate legacy slug-based selections to IDs.
 *
 * @since 1.0.28
 * @return void
 */
function bloghash_register_select_id_migration_filters() {
	$map = bloghash_get_select_id_migration_map();

	foreach ( $map as $setting_id => $config ) {
		add_filter(
			"theme_mod_{$setting_id}",
			function( $value ) use ( $setting_id, $config ) {
				return bloghash_maybe_migrate_select_ids( $value, $setting_id, $config );
			},
			20
		);
	}
}
add_action( 'after_setup_theme', 'bloghash_register_select_id_migration_filters', 20 );

/**
 * Map select settings to migration rules.
 *
 * @since 1.0.28
 * @return array
 */
function bloghash_get_select_id_migration_map() {
	return array(
		'bloghash_ticker_category'        		  => array( 'type' => 'term', 'taxonomy'  => 'category' ),
		'bloghash_hero_slider_category'   		  => array( 'type' => 'term', 'taxonomy'  => 'category' ),
		'bloghash_popular_post_category'          => array( 'type' => 'term', 'taxonomy'  => 'category' ),
		'bloghash_popular_post_post'        	  => array( 'type' => 'post', 'post_type' => 'post' ),
		'bloghash_editors_choice_category'        => array( 'type' => 'term', 'taxonomy'  => 'category' ),
		'bloghash_editors_choice_post'        	  => array( 'type' => 'post', 'post_type' => 'post' ),
		'bloghash_pyml_category'         		  => array( 'type' => 'term', 'taxonomy'  => 'category' ),
	);
}

/**
 * Migrate legacy slug selections to IDs for select controls.
 *
 * @since 1.0.28
 * @param mixed  $value      Current setting value.
 * @param string $setting_id Setting ID.
 * @param array  $config     Migration config.
 * @return mixed
 */
function bloghash_maybe_migrate_select_ids( $value, $setting_id, $config ) {
	if ( empty( $value ) ) {
		return $value;
	}

	$original_value = $value;

	if ( is_string( $value ) ) {
		$value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
	} elseif ( ! is_array( $value ) ) {
		$value = array( $value );
	}

	$converted   = array();
	$needs_update = ! is_array( $original_value );

	foreach ( $value as $item ) {
		$item_string = is_scalar( $item ) ? (string) $item : '';

		if ( '' !== $item_string && ctype_digit( $item_string ) ) {
			$converted[] = (int) $item_string;
			continue;
		}

		$needs_update = true;

		$candidates = array( $item_string );

		if ( 'term' === $config['type'] ) {
			foreach ( $candidates as $candidate ) {
				$term = get_term_by( 'slug', $candidate, $config['taxonomy'] );
				if ( $term && ! is_wp_error( $term ) ) {
					$converted[] = (int) $term->term_id;
					continue 2;
				}
			}

			foreach ( $candidates as $candidate ) {
				$term = get_term_by( 'name', $candidate, $config['taxonomy'] );
				if ( $term && ! is_wp_error( $term ) ) {
					$converted[] = (int) $term->term_id;
					continue 2;
				}
			}
		} elseif ( 'post' === $config['type'] ) {
			foreach ( $candidates as $candidate ) {
				$post = get_page_by_path( $candidate, OBJECT, $config['post_type'] );
				if ( $post ) {
					$converted[] = (int) $post->ID;
					continue 2;
				}
			}
		}
	}

	$converted = array_values( array_unique( array_filter( $converted ) ) );

	if ( empty( $converted ) ) {
		if ( $needs_update ) {
			bloghash()->options->set( $setting_id, array() );
		}
		return array();
	}

	if ( $needs_update || $converted !== $original_value ) {
		bloghash()->options->set( $setting_id, $converted );
	}

	return $converted;
}