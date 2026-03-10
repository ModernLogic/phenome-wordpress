<?php
/**
 * Runs the WooCommerce order migration: verify SQL, load staging, build incremental, map IDs, insert/update destination.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Phenome_Woo_Order_Migration_Runner {

	private $prefix;
	private $wpdb;
	private $posts_tbl;
	private $postmeta_tbl;
	private $order_items_tbl;
	private $order_itemmeta_tbl;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Normalize and set prefix.
	 */
	private function set_prefix( $prefix ) {
		$prefix = preg_replace( '/[^a-zA-Z0-9_]/', '', $prefix );
		if ( $prefix !== '' && substr( $prefix, -1 ) !== '_' ) {
			$prefix .= '_';
		}
		$this->prefix = $prefix;
		$this->posts_tbl       = $this->wpdb->prefix . 'posts';
		$this->postmeta_tbl    = $this->wpdb->prefix . 'postmeta';
		$this->order_items_tbl = $this->wpdb->prefix . 'woocommerce_order_items';
		$this->order_itemmeta_tbl = $this->wpdb->prefix . 'woocommerce_order_itemmeta';
	}

	private function staging_posts() { return $this->prefix . 'wc_orders_posts'; }
	private function staging_postmeta() { return $this->prefix . 'wc_orders_postmeta'; }
	private function staging_items() { return $this->prefix . 'wc_orders_items'; }
	private function staging_itemmeta() { return $this->prefix . 'wc_orders_itemmeta'; }
	private function inc_posts() { return $this->prefix . 'inc_orders_posts'; }
	private function inc_postmeta() { return $this->prefix . 'inc_orders_postmeta'; }
	private function inc_items() { return $this->prefix . 'inc_orders_items'; }
	private function inc_itemmeta() { return $this->prefix . 'inc_orders_itemmeta'; }
	private function post_id_map() { return $this->prefix . 'post_id_map'; }
	private function item_id_map() { return $this->prefix . 'item_id_map'; }

	/**
	 * Run the full migration.
	 *
	 * @param array $args 'prefix', 'file_posts', 'file_postmeta', 'file_items', 'file_itemmeta'
	 * @return true|WP_Error
	 */
	public function run( $args ) {
		$prefix = isset( $args['prefix'] ) ? $args['prefix'] : 'molo_';
		$this->set_prefix( $prefix );

		$files = array(
			'posts'    => isset( $args['file_posts'] ) ? $args['file_posts'] : null,
			'postmeta' => isset( $args['file_postmeta'] ) ? $args['file_postmeta'] : null,
			'items'    => isset( $args['file_items'] ) ? $args['file_items'] : null,
			'itemmeta' => isset( $args['file_itemmeta'] ) ? $args['file_itemmeta'] : null,
		);

		foreach ( $files as $key => $file ) {
			if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				return new WP_Error( 'missing_file', sprintf( __( 'Missing or invalid SQL file: %s', 'phenome-woo-order-migration' ), $key ) );
			}
		}

		$err = $this->drop_staging_tables();
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		foreach ( $files as $file ) {
			$err = $this->execute_sql_file( $file['tmp_name'] );
			if ( is_wp_error( $err ) ) {
				return $err;
			}
		}

		$err = $this->step_create_incremental_tables();
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$err = $this->step_fill_incremental_tables();
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$err = $this->step_ensure_mapping_tables();
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$err = $this->step_backfill_mappings();
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$err = $this->step_generate_mappings();
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$err = $this->step_insert_new();
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		$err = $this->step_update_existing();
		if ( is_wp_error( $err ) ) {
			return $err;
		}

		return true;
	}

	/**
	 * Drop the four staging tables so uploaded SQL dumps (CREATE TABLE without IF NOT EXISTS) succeed.
	 */
	private function drop_staging_tables() {
		$tables = array(
			$this->staging_posts(),
			$this->staging_postmeta(),
			$this->staging_items(),
			$this->staging_itemmeta(),
		);
		foreach ( $tables as $t ) {
			$this->wpdb->query( "DROP TABLE IF EXISTS `{$t}`" );
			if ( $this->wpdb->last_error ) {
				return new WP_Error( 'sql_error', $this->wpdb->last_error );
			}
		}
		return true;
	}

	/**
	 * Execute SQL file in one go via mysqli_multi_query.
	 * Only use with trusted dump files. Consumes all result sets so the connection remains usable.
	 */
	private function execute_sql_file( $filepath ) {
		$sql = file_get_contents( $filepath );
		if ( $sql === false ) {
			return new WP_Error( 'read_file', __( 'Could not read SQL file.', 'phenome-woo-order-migration' ) );
		}

		$mysqli = $this->wpdb->dbh;
		if ( ! ( $mysqli instanceof mysqli ) ) {
			return new WP_Error( 'db_driver', __( 'SQL import requires MySQLi. Your database connection is not MySQLi.', 'phenome-woo-order-migration' ) );
		}

		if ( ! mysqli_multi_query( $mysqli, $sql ) ) {
			return new WP_Error( 'sql_error', sprintf( __( 'Import failed: %s', 'phenome-woo-order-migration' ), mysqli_error( $mysqli ) ) );
		}

		do {
			if ( $result = mysqli_store_result( $mysqli ) ) {
				mysqli_free_result( $result );
			}
		} while ( mysqli_more_results( $mysqli ) && mysqli_next_result( $mysqli ) );

		if ( mysqli_errno( $mysqli ) ) {
			return new WP_Error( 'sql_error', sprintf( __( 'Import failed after multi_query: %s', 'phenome-woo-order-migration' ), mysqli_error( $mysqli ) ) );
		}

		return true;
	}

	private function step_create_incremental_tables() {
		$staging_posts = $this->staging_posts();
		$staging_postmeta = $this->staging_postmeta();
		$staging_items = $this->staging_items();
		$staging_itemmeta = $this->staging_itemmeta();
		$inc_posts = $this->inc_posts();
		$inc_postmeta = $this->inc_postmeta();
		$inc_items = $this->inc_items();
		$inc_itemmeta = $this->inc_itemmeta();

		$this->wpdb->query( "DROP TABLE IF EXISTS `{$inc_itemmeta}`" );
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$inc_items}`" );
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$inc_postmeta}`" );
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$inc_posts}`" );

		$this->wpdb->query( "CREATE TABLE `{$inc_posts}` LIKE `{$staging_posts}`" );
		$this->wpdb->query( "CREATE TABLE `{$inc_postmeta}` LIKE `{$staging_postmeta}`" );
		$this->wpdb->query( "CREATE TABLE `{$inc_items}` LIKE `{$staging_items}`" );
		$this->wpdb->query( "CREATE TABLE `{$inc_itemmeta}` LIKE `{$staging_itemmeta}`" );

		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}
		return true;
	}

	private function step_fill_incremental_tables() {
		$staging_posts = $this->staging_posts();
		$staging_postmeta = $this->staging_postmeta();
		$staging_items = $this->staging_items();
		$staging_itemmeta = $this->staging_itemmeta();
		$inc_posts = $this->inc_posts();
		$inc_postmeta = $this->inc_postmeta();
		$inc_items = $this->inc_items();
		$inc_itemmeta = $this->inc_itemmeta();
		$posts_tbl = $this->posts_tbl;

		$q = "INSERT INTO `{$inc_posts}` SELECT * FROM `{$staging_posts}` WHERE post_type = 'shop_order' AND post_date_gmt > ( SELECT MAX(post_date_gmt) FROM `{$posts_tbl}` WHERE post_type = 'shop_order' )";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$inc_posts}` SELECT p.* FROM `{$staging_posts}` p INNER JOIN `{$inc_posts}` o ON p.post_parent = o.ID WHERE p.post_type = 'shop_order_refund'";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$inc_postmeta}` SELECT pm.* FROM `{$staging_postmeta}` pm INNER JOIN `{$inc_posts}` p ON pm.post_id = p.ID";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$inc_items}` SELECT oi.* FROM `{$staging_items}` oi INNER JOIN `{$inc_posts}` p ON oi.order_id = p.ID";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$inc_itemmeta}` SELECT oim.* FROM `{$staging_itemmeta}` oim INNER JOIN `{$inc_items}` oi ON oim.order_item_id = oi.order_item_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		return true;
	}

	private function step_ensure_mapping_tables() {
		$post_map = $this->post_id_map();
		$item_map = $this->item_id_map();

		// Ensure mapping tables exist and keep their data across runs.
		// Use IF NOT EXISTS and define the primary key inline so existing tables are preserved.
		$this->wpdb->query( "CREATE TABLE IF NOT EXISTS `{$post_map}` ( old_id BIGINT PRIMARY KEY, new_id BIGINT )" );
		$this->wpdb->query( "CREATE TABLE IF NOT EXISTS `{$item_map}` ( old_id BIGINT PRIMARY KEY, new_id BIGINT )" );

		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}
		return true;
	}

	private function step_backfill_mappings() {
		$staging_posts = $this->staging_posts();
		$staging_items = $this->staging_items();
		$post_map = $this->post_id_map();
		$item_map = $this->item_id_map();
		$posts_tbl = $this->posts_tbl;
		$order_items_tbl = $this->order_items_tbl;

		$q = "INSERT INTO `{$post_map}` (old_id, new_id) SELECT s.ID, s.ID FROM `{$staging_posts}` s INNER JOIN `{$posts_tbl}` d ON d.ID = s.ID WHERE s.post_type IN ('shop_order', 'shop_order_refund') AND d.post_type IN ('shop_order', 'shop_order_refund') AND NOT EXISTS ( SELECT 1 FROM `{$post_map}` m WHERE m.old_id = s.ID )";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$item_map}` (old_id, new_id) SELECT s.order_item_id, s.order_item_id FROM `{$staging_items}` s INNER JOIN `{$order_items_tbl}` d ON d.order_item_id = s.order_item_id INNER JOIN `{$post_map}` pm ON pm.old_id = s.order_id AND pm.new_id = s.order_id WHERE NOT EXISTS ( SELECT 1 FROM `{$item_map}` m WHERE m.old_id = s.order_item_id )";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		return true;
	}

	/**
	 * Generate new IDs for incremental posts/items not yet in map (deterministic ordering).
	 */
	private function step_generate_mappings() {
		$inc_posts = $this->inc_posts();
		$inc_items = $this->inc_items();
		$post_map = $this->post_id_map();
		$item_map = $this->item_id_map();
		$posts_tbl = $this->posts_tbl;
		$order_items_tbl = $this->order_items_tbl;

		$base_id = (int) $this->wpdb->get_var( "SELECT COALESCE(MAX(ID), 0) FROM `{$posts_tbl}`" );
		$ids = $this->wpdb->get_col( "SELECT p.ID FROM `{$inc_posts}` p LEFT JOIN `{$post_map}` m ON m.old_id = p.ID WHERE m.old_id IS NULL ORDER BY p.ID" );
		if ( ! empty( $ids ) ) {
			$values = array();
			foreach ( $ids as $i => $old_id ) {
				$new_id = $base_id + $i + 1;
				$values[] = '(' . (int) $old_id . ',' . $new_id . ')';
			}
			$this->wpdb->query( "INSERT INTO `{$post_map}` (old_id, new_id) VALUES " . implode( ',', $values ) );
		}
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$base_item_id = (int) $this->wpdb->get_var( "SELECT COALESCE(MAX(order_item_id), 0) FROM `{$order_items_tbl}`" );
		$item_ids = $this->wpdb->get_col( "SELECT oi.order_item_id FROM `{$inc_items}` oi LEFT JOIN `{$item_map}` m ON m.old_id = oi.order_item_id WHERE m.old_id IS NULL ORDER BY oi.order_item_id" );
		if ( ! empty( $item_ids ) ) {
			$values = array();
			foreach ( $item_ids as $i => $old_id ) {
				$new_id = $base_item_id + $i + 1;
				$values[] = '(' . (int) $old_id . ',' . $new_id . ')';
			}
			$this->wpdb->query( "INSERT INTO `{$item_map}` (old_id, new_id) VALUES " . implode( ',', $values ) );
		}
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		return true;
	}

	private function step_insert_new() {
		$inc_posts = $this->inc_posts();
		$inc_postmeta = $this->inc_postmeta();
		$inc_items = $this->inc_items();
		$inc_itemmeta = $this->inc_itemmeta();
		$post_map = $this->post_id_map();
		$item_map = $this->item_id_map();
		$posts_tbl = $this->posts_tbl;
		$postmeta_tbl = $this->postmeta_tbl;
		$order_items_tbl = $this->order_items_tbl;
		$order_itemmeta_tbl = $this->order_itemmeta_tbl;

		$q = "INSERT INTO `{$posts_tbl}` ( ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count )
			SELECT map.new_id, p.post_author, p.post_date, p.post_date_gmt, p.post_content, p.post_title, p.post_excerpt, p.post_status, p.comment_status, p.ping_status, p.post_password, p.post_name, p.to_ping, p.pinged, p.post_modified, p.post_modified_gmt, p.post_content_filtered, COALESCE(parent_map.new_id, p.post_parent), p.guid, p.menu_order, p.post_type, p.post_mime_type, p.comment_count
			FROM `{$inc_posts}` p INNER JOIN `{$post_map}` map ON p.ID = map.old_id LEFT JOIN `{$post_map}` parent_map ON p.post_parent = parent_map.old_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$postmeta_tbl}` ( post_id, meta_key, meta_value ) SELECT map.new_id, pm.meta_key, pm.meta_value FROM `{$inc_postmeta}` pm INNER JOIN `{$post_map}` map ON pm.post_id = map.old_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$order_items_tbl}` ( order_item_id, order_item_name, order_item_type, order_id ) SELECT item_map.new_id, oi.order_item_name, oi.order_item_type, post_map.new_id FROM `{$inc_items}` oi INNER JOIN `{$item_map}` item_map ON oi.order_item_id = item_map.old_id INNER JOIN `{$post_map}` post_map ON oi.order_id = post_map.old_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$order_itemmeta_tbl}` ( order_item_id, meta_key, meta_value ) SELECT item_map.new_id, oim.meta_key, oim.meta_value FROM `{$inc_itemmeta}` oim INNER JOIN `{$item_map}` item_map ON oim.order_item_id = item_map.old_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		return true;
	}

	private function step_update_existing() {
		$staging_posts = $this->staging_posts();
		$staging_postmeta = $this->staging_postmeta();
		$staging_items = $this->staging_items();
		$staging_itemmeta = $this->staging_itemmeta();
		$post_map = $this->post_id_map();
		$item_map = $this->item_id_map();
		$posts_tbl = $this->posts_tbl;
		$postmeta_tbl = $this->postmeta_tbl;
		$order_items_tbl = $this->order_items_tbl;
		$order_itemmeta_tbl = $this->order_itemmeta_tbl;

		$q = "UPDATE `{$posts_tbl}` d INNER JOIN `{$post_map}` pm ON d.ID = pm.new_id INNER JOIN `{$staging_posts}` s ON s.ID = pm.old_id LEFT JOIN `{$post_map}` parent_pm ON s.post_parent = parent_pm.old_id SET d.post_author = s.post_author, d.post_date = s.post_date, d.post_date_gmt = s.post_date_gmt, d.post_content = s.post_content, d.post_title = s.post_title, d.post_excerpt = s.post_excerpt, d.post_status = s.post_status, d.comment_status = s.comment_status, d.ping_status = s.ping_status, d.post_password = s.post_password, d.post_name = s.post_name, d.to_ping = s.to_ping, d.pinged = s.pinged, d.post_modified = s.post_modified, d.post_modified_gmt = s.post_modified_gmt, d.post_content_filtered = s.post_content_filtered, d.post_parent = COALESCE(parent_pm.new_id, s.post_parent), d.menu_order = s.menu_order, d.post_type = s.post_type, d.post_mime_type = s.post_mime_type, d.comment_count = s.comment_count WHERE s.post_type IN ('shop_order', 'shop_order_refund') AND d.post_type IN ('shop_order', 'shop_order_refund')";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "DELETE d FROM `{$postmeta_tbl}` d INNER JOIN `{$post_map}` pm ON d.post_id = pm.new_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$postmeta_tbl}` ( post_id, meta_key, meta_value ) SELECT pm.new_id, s.meta_key, s.meta_value FROM `{$staging_postmeta}` s INNER JOIN `{$post_map}` pm ON s.post_id = pm.old_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "UPDATE `{$order_items_tbl}` d INNER JOIN `{$item_map}` im ON d.order_item_id = im.new_id INNER JOIN `{$staging_items}` s ON s.order_item_id = im.old_id INNER JOIN `{$post_map}` pm ON s.order_id = pm.old_id SET d.order_item_name = s.order_item_name, d.order_item_type = s.order_item_type, d.order_id = pm.new_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$order_items_tbl}` ( order_item_id, order_item_name, order_item_type, order_id ) SELECT im.new_id, s.order_item_name, s.order_item_type, pm.new_id FROM `{$staging_items}` s INNER JOIN `{$item_map}` im ON s.order_item_id = im.old_id INNER JOIN `{$post_map}` pm ON s.order_id = pm.old_id LEFT JOIN `{$order_items_tbl}` d ON d.order_item_id = im.new_id WHERE d.order_item_id IS NULL";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "DELETE d FROM `{$order_itemmeta_tbl}` d INNER JOIN `{$item_map}` im ON d.order_item_id = im.new_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		$q = "INSERT INTO `{$order_itemmeta_tbl}` ( order_item_id, meta_key, meta_value ) SELECT im.new_id, s.meta_key, s.meta_value FROM `{$staging_itemmeta}` s INNER JOIN `{$item_map}` im ON s.order_item_id = im.old_id";
		$this->wpdb->query( $q );
		if ( $this->wpdb->last_error ) {
			return new WP_Error( 'sql_error', $this->wpdb->last_error );
		}

		return true;
	}
}
