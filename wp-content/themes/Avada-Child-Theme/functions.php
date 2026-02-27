<?php
add_filter('xmlrpc_enabled', '__return_false');

remove_action('wp_head', 'wp_generator');
function enqueue_xeditable_assets()
{


    // Enqueue jQuery (ensure compatible version)
    wp_enqueue_script('jquery');
    if (isset($_GET['post'], $_GET['action']) && $_GET['action'] === 'edit') {
        $post_id = intval($_GET['post']);
        $post_type = get_post_type($post_id);

        if ($post_type === 'shop_order') {

            // Enqueue Bootstrap (if needed)
            wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css');
            wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js', ['jquery'], null, true);

            // Enqueue X-editable CSS and JS
            wp_enqueue_style('x-editable-bootstrap-css', 'https://cdn.jsdelivr.net/npm/x-editable@1.5.1/dist/bootstrap3-editable/css/bootstrap-editable.css');
            wp_enqueue_script('x-editable-bootstrap', 'https://cdn.jsdelivr.net/npm/x-editable@1.5.1/dist/bootstrap3-editable/js/bootstrap-editable.min.js', ['jquery', 'bootstrap-js'], null, true);

            // Custom JS for X-editable
            wp_enqueue_script('xeditable-init', get_stylesheet_directory_uri() . '/admin/js/xeditable-init.js?t=' . time(), array('jquery'), '1.0', true);

            // Pass AJAX URL to the script
            wp_localize_script('xeditable-init', 'xeditable_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('update_testdetails_nonce'),
            ]);
        }
    }
}
add_action('admin_enqueue_scripts', 'enqueue_xeditable_assets');

function enqueue_admin_custom_script()
{

    // wp_enqueue_script('jqueryui-custom-script', get_stylesheet_directory_uri() . '/admin/jqueryui-editable/js/jquery-ui-1.10.1.custom.min.js', array('jquery'), '1.0', true);
    // wp_enqueue_style('jqueryui-custom-style', get_stylesheet_directory_uri() . '/admin/jqueryui-editable/css/jquery-ui-1.10.1.custom.css', [] );


    // wp_enqueue_script('jqueryui-editable-script', get_stylesheet_directory_uri() . '/admin/jqueryui-editable/js/jqueryui-editable.min.js', array('jquery'), '1.0', true);

    // wp_enqueue_style('jqueryui-editable-style', get_stylesheet_directory_uri() . '/admin/jqueryui-editable/css/jqueryui-editable.css', [] );

    wp_enqueue_script('custom-admin-script', get_stylesheet_directory_uri() . '/admin/js/admin-order.js?t=' . time(), array('jquery'), '1.0', true);

    // Localize script to pass data from PHP to JS
    wp_localize_script('custom-admin-script', 'custom_admin_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('custom_admin_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'enqueue_admin_custom_script');

function theme_enqueue_styles()
{
    wp_enqueue_script('custom-admin-script', get_stylesheet_directory_uri() . '/admin/js/admin-order.js?t=' . time(), array('jquery'), '1.0', true);
    wp_localize_script('custom-admin-script', 'custom_admin_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('custom_admin_nonce')
    ));
    wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', []);

}

add_action('wp_enqueue_scripts', 'theme_enqueue_styles', 20);
include_once get_stylesheet_directory() . '/admin/admin_all_order_export.php';
include_once get_stylesheet_directory() . '/admin/admin_order_test_html.php';
include_once get_stylesheet_directory() . '/admin/generate_pdf.php';
include_once get_stylesheet_directory() . '/admin/generate_labels.php';
include_once get_stylesheet_directory() . '/admin/generate_emaill.php';
include_once get_stylesheet_directory() . '/admin/generate_order_test_template.php';
include_once get_stylesheet_directory() . '/snake-test/custom-snake-tests.php';


function avada_lang_setup()
{

    $lang = get_stylesheet_directory() . '/languages';

    load_child_theme_textdomain('Avada', $lang);

}

add_action('after_setup_theme', 'avada_lang_setup');

add_action('woocommerce_order_details_after_order_table', 'order_details_after_order_table_callback', 10, 1);
function order_details_after_order_table_callback($order)
{
    $order_id = $order->get_id();
    echo '<button type="button" class="button generate-pdf" data-pathh="' . WP_CONTENT_DIR . '" data-order-id="' . esc_attr($order_id) . '">Generate Test PDF</button>&nbsp;<button type="button" class="button generate-labels" data-pathh="' . WP_CONTENT_DIR . '" data-order-id="' . esc_attr($order_id) . '">Generate Labels</button>';

}

add_action('woocommerce_order_item_add_action_buttons', 'wc_order_item_add_action_buttons_callback', 10, 1);
function wc_order_item_add_action_buttons_callback($order)
{
    $order_id = $order->get_id();
    echo '<button type="button" class="button generate-pdf" data-pathh="' . WP_CONTENT_DIR . '" data-order-id="' . esc_attr($order_id) . '">Generate Test PDF</button>&nbsp;<button type="button" class="button generate-labels" data-pathh="' . WP_CONTENT_DIR . '" data-order-id="' . esc_attr($order_id) . '">Generate Labels</button>&nbsp;<button type="button" class="button generate-emaill" data-pathh="' . WP_CONTENT_DIR . '" data-order-id="' . esc_attr($order_id) . '">Generate Email</button>';

}

function shedtest_change_quantity_input($product_quantity, $cart_item_key, $cart_item)
{
    $product_id = $cart_item['product_id'];
    // whatever logic you want to determine whether or not to alter the input
     $tests = get_post_meta($product_id, '_snake_tests', true) ?: [];
    $recessives = get_post_meta($product_id, '_snake_recessive_tests', true) ?: [];
    $pricing_array = get_post_meta($product_id, '_snake_pricing', true) ?: '';
   $full_panel_threshold = get_post_meta($product_id, '_snake_full_panel_threshold', true) ?: '';



    if ($product_id == 1020 || $product_id == 4403 || (!empty($tests) && !empty($recessives) && !empty($pricing_array) && $full_panel_threshold !='')) {
        return '<span>' . $cart_item['quantity'] . '</span>';
    }

    return $product_quantity;
}
add_filter('woocommerce_cart_item_quantity', 'shedtest_change_quantity_input', 10, 3);


function add_aria_labels_from_screen_reader_text($items, $args)
{
    $target_menu_name = 'account-menu';

    // Check if the menu name matches the target menu
    if ($args->menu === $target_menu_name || $args->menu === 'header-blocks-menu-cart') {
        // Load the menu items into a DOM parser
        $doc = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress warnings for malformed HTML
        $doc->loadHTML(mb_convert_encoding($items, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // Iterate over each menu item with a screen-reader-text
        foreach ($xpath->query("//li[contains(@class, 'menu-item')]") as $li) {
            $screenReaderText = $xpath->query(".//span[contains(@class, 'menu-text')]", $li);
            $link = $xpath->query(".//a", $li);

            if ($screenReaderText->length > 0 && $link->length > 0) {
                $ariaLabel = trim($screenReaderText->item(0)->nodeValue);
                if ($ariaLabel) {
                    $link->item(0)->setAttribute('aria-label', $ariaLabel);
                }
            }
        }

        // Save the updated HTML
        $items = $doc->saveHTML();
    }
    return $items;
}
add_filter('wp_nav_menu_items', 'add_aria_labels_from_screen_reader_text', 10, 2);

//Hide Price when Price is Zero
add_filter('woocommerce_get_price_html', 'maybe_hide_price', 10, 2);
function maybe_hide_price($price_html, $product)
{
    if ($product->get_price() > 0) {
        return $price_html;
    }
    return '';
}
// End of above code

function custom_product_description_shortcode($atts)
{
    // Get product ID from shortcode or current product
    $atts = shortcode_atts(array(
        'id' => get_the_ID(),
    ), $atts);

    $product = wc_get_product($atts['id']);
    if (!$product) {
        return '';
    }

    // Get the description
    return apply_filters('the_content', $product->get_description());
}
add_shortcode('product_description', 'custom_product_description_shortcode');
