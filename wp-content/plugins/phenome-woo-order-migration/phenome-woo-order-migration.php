<?php
/**
 * Plugin Name: Phenome Woo Order Migration
 * Description: Upload 4 SQL exports and run incremental WooCommerce order migration (non-HPOS). Settings under Plugins menu.
 * Version: 1.0.0
 * Author: Phenome
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PHENOME_WOO_MIGRATION_VERSION', '1.0.0' );
define( 'PHENOME_WOO_MIGRATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PHENOME_WOO_MIGRATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Match uploaded files to roles by filename (e.g. molo_wc_orders_posts.sql -> posts).
 * Checks for table name suffix; longer suffixes first so postmeta matches before posts.
 *
 * @param array $files $_FILES['phenome_sql_files'] with name, type, tmp_name, error, size arrays.
 * @return array|WP_Error Keys file_posts, file_postmeta, file_items, file_itemmeta with single-file array each.
 */
function phenome_woo_migration_match_files_by_name( $files ) {
	$suffixes = array(
		'file_itemmeta' => 'wc_orders_itemmeta',
		'file_items'    => 'wc_orders_items',
		'file_postmeta' => 'wc_orders_postmeta',
		'file_posts'    => 'wc_orders_posts',
	);
	$matched = array(
		'file_posts'    => null,
		'file_postmeta' => null,
		'file_items'    => null,
		'file_itemmeta' => null,
	);
	$n = count( $files['tmp_name'] );
	for ( $i = 0; $i < $n; $i++ ) {
		$name = strtolower( $files['name'][ $i ] );
		$assigned = false;
		foreach ( $suffixes as $role => $suffix ) {
			if ( strpos( $name, $suffix ) !== false ) {
				if ( $matched[ $role ] !== null ) {
					return new WP_Error( 'duplicate_file', sprintf( __( 'More than one file matches "%s". Each file must match a different table (e.g. %s.sql).', 'phenome-woo-order-migration' ), $suffix, $suffix ) );
				}
				$matched[ $role ] = array(
					'name'     => $files['name'][ $i ],
					'type'     => $files['type'][ $i ],
					'tmp_name' => $files['tmp_name'][ $i ],
					'error'    => $files['error'][ $i ],
					'size'     => $files['size'][ $i ],
				);
				$assigned = true;
				break;
			}
		}
		if ( ! $assigned ) {
			return new WP_Error( 'unrecognized_file', sprintf( __( 'Filename "%s" does not match any expected table name (e.g. prefix_wc_orders_posts.sql, prefix_wc_orders_postmeta.sql, prefix_wc_orders_items.sql, prefix_wc_orders_itemmeta.sql).', 'phenome-woo-order-migration' ), $files['name'][ $i ] ) );
		}
	}
	foreach ( $matched as $role => $file ) {
		if ( $file === null ) {
			return new WP_Error( 'missing_file', sprintf( __( 'No file matched the "%s" table. Ensure filenames contain the table name (e.g. molo_wc_orders_posts.sql).', 'phenome-woo-order-migration' ), $suffixes[ $role ] ) );
		}
	}
	return $matched;
}

add_action( 'admin_menu', 'phenome_woo_migration_add_menu' );
add_action( 'admin_post_phenome_woo_migration_run', 'phenome_woo_migration_handle_run' );

function phenome_woo_migration_add_menu() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_plugins_page(
		__( 'Woo Order Migration', 'phenome-woo-order-migration' ),
		__( 'Order Migration', 'phenome-woo-order-migration' ),
		'manage_options',
		'phenome-woo-order-migration',
		'phenome_woo_migration_render_settings_page'
	);
}

function phenome_woo_migration_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions.', 'phenome-woo-order-migration' ) );
	}
	require_once PHENOME_WOO_MIGRATION_PLUGIN_DIR . 'admin/settings-page.php';
}

function phenome_woo_migration_handle_run() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions.', 'phenome-woo-order-migration' ) );
	}
	check_admin_referer( 'phenome_woo_migration_run' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_safe_redirect( add_query_arg( array(
			'page'   => 'phenome-woo-order-migration',
			'error'  => 1,
			'msg'    => rawurlencode( __( 'WooCommerce is not active.', 'phenome-woo-order-migration' ) ),
		), admin_url( 'plugins.php' ) ) );
		exit;
	}

	require_once PHENOME_WOO_MIGRATION_PLUGIN_DIR . 'includes/class-migration-runner.php';
	$runner = new Phenome_Woo_Order_Migration_Runner();

	$prefix = isset( $_POST['phenome_migration_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['phenome_migration_prefix'] ) ) : 'molo_';
	update_option( 'phenome_woo_migration_prefix', $prefix );

	$files = isset( $_FILES['phenome_sql_files'] ) && is_array( $_FILES['phenome_sql_files']['tmp_name'] ) ? $_FILES['phenome_sql_files'] : null;
	$file_count = $files ? count( $files['tmp_name'] ) : 0;
	if ( $file_count !== 4 ) {
		wp_safe_redirect( add_query_arg( array(
			'page'  => 'phenome-woo-order-migration',
			'error' => 1,
			'msg'   => rawurlencode( __( 'Please select exactly 4 SQL files (orders posts, postmeta, order items, order itemmeta).', 'phenome-woo-order-migration' ) ),
		), admin_url( 'plugins.php' ) ) );
		exit;
	}
	$matched = phenome_woo_migration_match_files_by_name( $files );
	if ( is_wp_error( $matched ) ) {
		wp_safe_redirect( add_query_arg( array(
			'page'  => 'phenome-woo-order-migration',
			'error' => 1,
			'msg'   => rawurlencode( $matched->get_error_message() ),
		), admin_url( 'plugins.php' ) ) );
		exit;
	}

	$result = $runner->run( array(
		'prefix'        => $prefix,
		'file_posts'    => $matched['file_posts'],
		'file_postmeta' => $matched['file_postmeta'],
		'file_items'    => $matched['file_items'],
		'file_itemmeta' => $matched['file_itemmeta'],
	) );

	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( array(
			'page'  => 'phenome-woo-order-migration',
			'error' => 1,
			'msg'   => rawurlencode( $result->get_error_message() ),
		), admin_url( 'plugins.php' ) ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( array(
		'page'    => 'phenome-woo-order-migration',
		'success' => 1,
		'msg'     => rawurlencode( __( 'Migration completed successfully.', 'phenome-woo-order-migration' ) ),
	), admin_url( 'plugins.php' ) ) );
	exit;
}
