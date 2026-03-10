<?php
/**
 * Admin settings page: upload 4 SQL files, set staging prefix, run migration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prefix = get_option( 'phenome_woo_migration_prefix', 'molo_' );
$p = ( $prefix !== '' && substr( $prefix, -1 ) !== '_' ) ? $prefix . '_' : $prefix;
$expected_posts    = $p . 'wc_orders_posts';
$expected_postmeta = $p . 'wc_orders_postmeta';
$expected_items    = $p . 'wc_orders_items';
$expected_itemmeta = $p . 'wc_orders_itemmeta';

$error_msg = isset( $_GET['error'] ) && isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
$success_msg = isset( $_GET['success'] ) && isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Woo Order Migration', 'phenome-woo-order-migration' ); ?></h1>
	<p><?php esc_html_e( 'Select all 4 SQL export files at once. Each file is matched by filename (e.g. molo_wc_orders_posts.sql). Order does not matter.', 'phenome-woo-order-migration' ); ?></p>

	<?php if ( $error_msg ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
	<?php endif; ?>
	<?php if ( $success_msg ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( $success_msg ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="phenome_woo_migration_run" />
		<?php wp_nonce_field( 'phenome_woo_migration_run' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><label for="phenome_migration_prefix"><?php esc_html_e( 'Staging table prefix', 'phenome-woo-order-migration' ); ?></label></th>
				<td>
					<input type="text" name="phenome_migration_prefix" id="phenome_migration_prefix" value="<?php echo esc_attr( $prefix ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Prefix for staging tables (e.g. molo_ gives molo_wc_orders_posts, molo_wc_orders_postmeta, …).', 'phenome-woo-order-migration' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="phenome_sql_files"><?php esc_html_e( 'SQL files (4 required)', 'phenome-woo-order-migration' ); ?></label></th>
				<td>
					<input type="file" name="phenome_sql_files[]" id="phenome_sql_files" accept=".sql,text/plain" multiple="multiple" />
					<p class="description"><?php esc_html_e( 'Select 4 files. Filenames must contain the table name (e.g. molo_wc_orders_posts.sql, molo_wc_orders_postmeta.sql).', 'phenome-woo-order-migration' ); ?></p>
					<p class="description"><strong><?php esc_html_e( 'Expected table names:', 'phenome-woo-order-migration' ); ?></strong> <?php echo esc_html( $expected_posts . ', ' . $expected_postmeta . ', ' . $expected_items . ', ' . $expected_itemmeta ); ?></p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Upload and run migration', 'phenome-woo-order-migration' ); ?></button>
		</p>
	</form>
</div>
