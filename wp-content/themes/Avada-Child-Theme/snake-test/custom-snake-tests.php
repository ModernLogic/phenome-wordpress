<?php
add_action('init', 'start_php_session', 1);
function start_php_session() {
    if (!session_id()) {
        session_start();
    }
}
add_action('switch_to_user', function($user_id, $old_user_id, $new_token, $old_token) {
  $user = get_userdata($old_user_id);

if ($user && in_array('administrator', (array) $user->roles)) {
  

        $_SESSION['switched_by_admin'] = true;
        $_SESSION['original_admin_id'] = $old_user_id;
    }
}, 10, 5);

function snake_test_enqueue_styles()
{
 if(is_product()){
    $product_id = get_the_ID();

    // Get the meta values
    $tests = get_post_meta($product_id, '_snake_tests', true) ?: [];
    $recessives = get_post_meta($product_id, '_snake_recessive_tests', true) ?: [];
    $pricing_array = get_post_meta($product_id, '_snake_pricing', true) ?: '';
   $full_panel_threshold = get_post_meta($product_id, '_snake_full_panel_threshold', true) ?: '';

    if (!empty($tests) && !empty($recessives) && !empty($pricing_array) && $full_panel_threshold !='') {




  

    wp_enqueue_script('snake-test-script', get_stylesheet_directory_uri() . '/snake-test/snake-test.js?t='.time(), array('jquery'), '1.0', true);
    wp_localize_script('snake-test-script', 'snake_test_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'full_panel'=>$tests,
         'recessives'=>$recessives,
         'prices'=> $pricing_array,
          'fullPanelPrice'=> end($pricing_array),
          'full_panel_threshold'=> $full_panel_threshold,
        'nonce' => wp_create_nonce('custom_snake_test_nonce')
    ));
    wp_enqueue_style('snake-test-style', get_stylesheet_directory_uri() . '/snake-test/snake-test.css?t='.time(), []);
}
    }
}

add_action('wp_enqueue_scripts', 'snake_test_enqueue_styles', 20);
add_action('woocommerce_before_add_to_cart_button', 'custom_snake_form_multiple');
function custom_snake_form_multiple()
{
      global $post;

      
    // Get post meta values
    $tests = get_post_meta($post->ID, '_snake_tests', true);
    $recessives = get_post_meta($post->ID, '_snake_recessive_tests', true);
    $pricing_string = get_post_meta($post->ID, '_snake_pricing', true);
   $full_panel_threshold = get_post_meta($post->ID, '_snake_full_panel_threshold', true) ?: '';

    // Check if all exist and are not empty
    if (!empty($tests) && !empty($recessives) && !empty($pricing_string) && $full_panel_threshold !='') {

    // Only load on a specific product if needed (e.g., by ID)
    // if (!is_product(123)) return;

    ?>
    <div id="snake-forms-container">
   <?php
   $current_user = wp_get_current_user();
if (user_can( $current_user, 'administrator' ) || $_SESSION['switched_by_admin']) {
  // user is an admin
  ?>
        <!-- Upload Section -->
    <div class="upload-content" id="drop-zone">
            <div class="upload-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </div>
            <h3 class="upload-title">Drop files here or click to upload</h3>
            <p class="upload-subtitle">Strictly CSV file only.</p>
            <button class="upload-button" type="button" id="upload-btn">Choose Files</button>
            <input type="file" id="csv-upload" accept=".csv" hidden>
        </div>
 
        <div class="section-separator">
            <span>or manually add below</span>
        </div>
<?php } ?>

        <div id="snake-forms" class="snake-form-container">
        <?php echo trim(get_snake_form_html($tests)); ?>

        </div>
    </div>
    <div class="form_bottom">
        <button type="button" class="button alt" id="add-snake-btn" onclick="addSnakeForm()">+ Add Another Snake</button>
        <div class="total__price"><strong>Total Price: <span id="grand-total">$0.00</span></strong></div>
    </div>
    <style>
      
    </style>
    <script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>

    <script type="text/template" id="snake-form-template">
                <?php echo trim(get_snake_form_html($tests)); ?>
     </script>
   

    <?php
    }
}

function get_snake_form_html($tests)
{
    ob_start(); ?>
    <div class="snake-form" data-index="0">
        <label>Snake ID: <input type="text" class="snake-id-input" name="snake_id[0]" required></label><br>
        <label>Known Genetics: <input type="text" class="genetics-input" name="known_genetics[0]"></label><br><br>
        <div class="action-buttons">
            <button type="button" class="select-all-btn  button alt">Select All (Full Panel)</button>
            <button type="button" class="select-recessive-btn  button alt">Select Recessives</button>
            <button type="button" class="deselect-all-btn active  button alt">Deselect All</button>
        </div>
        <div class="snake-tests">
            <?php foreach ($tests as $test): ?>
                <label><input type="checkbox" data-test-name="<?php echo esc_attr($test); ?>" class="genetic-test"
                        name="genetic_tests[0][]" value="<?php echo esc_attr($test); ?>"><span>
                        <?php echo esc_html($test); ?></span></label>
            <?php endforeach; ?>
        </div>
         <p class="test_cost"><strong>Cost Per Test: <span class="price-per-test">$0.00</span></strong></p>
        <p class="test_subtotal"><strong>Subtotal: <span class="price-display">$0.00</span></strong></p>
        <button type="button" class="delete-snake" onclick="deleteSnakeForm(this)"><i class="fa fa-trash-alt"></i></button>

    </div>
    <?php
    return ob_get_clean();
}


add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (!empty($cart_item['snakes'])) {
        foreach ($cart_item['snakes'] as $i => $snake) {
            $item_data[] = [
                'name' => "Snake #" . ($i + 1) . " ID",
                'value' => $snake['id']
            ];
            $item_data[] = [
                'name' => "Snake #" . ($i + 1) . " Genetics",
                'value' => $snake['genetics']
            ];
            $item_data[] = [
                'name' => "Snake #" . ($i + 1) . " Tests",
                'value' => implode(', ', $snake['tests'])
            ];
        }
    }
    return $item_data;
}, 10, 2);

add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
     $tests = get_post_meta($product_id, '_snake_tests', true) ?: [];
    $recessives = get_post_meta($product_id, '_snake_recessive_tests', true) ?: [];
    $pricing_array = get_post_meta($product_id, '_snake_pricing', true) ?: '';
   $full_panel_threshold = get_post_meta($product_id, '_snake_full_panel_threshold', true) ?: '';

    if (!empty($tests) && !empty($recessives) && !empty($pricing_array) && $full_panel_threshold !='') {
    $priceMap =  $pricing_array;
    $fullPanelPrice = end($pricing_array);

    $total_price = 0;
    $snakes = [];

    foreach ($_POST['snake_id'] as $index => $id) {
        $snake_tests = $_POST['genetic_tests'][$index] ?? [];
        $test_count = count($snake_tests);
        $price = 0;

        if ($test_count >= $full_panel_threshold ) {
            $price = $fullPanelPrice;
        } elseif ($test_count > 0) {
            $price = $priceMap[$test_count] ?? $fullPanelPrice;
        }

        $total_price += $price;

        $snakes[] = [
            'id' => sanitize_text_field($id),
            'genetics' => sanitize_text_field($_POST['known_genetics'][$index]),
            'tests' => array_map('sanitize_text_field', $snake_tests),
            'price' => $price,
        ];
    }

    $cart_item_data['snakes'] = $snakes;
    $cart_item_data['custom_total_price'] = $total_price;
    }
    return $cart_item_data;
}, 10, 3);
add_filter('woocommerce_add_to_cart_validation', 'validate_snake_tests_per_form', 10, 3);

function validate_snake_tests_per_form($passed, $product_id, $quantity) {
     $tests = get_post_meta($product_id, '_snake_tests', true) ?: [];
      if (!empty($tests)){
            foreach ($_POST['snake_id'] as $index => $id) {
            $snake_tests = $_POST['genetic_tests'][$index] ?? [];
            $test_count = count($snake_tests);
                if ($test_count < 1) {
                    wc_add_notice(__('You must add at least one snake with one test.'), 'error');
                    return false;
            }
        }
    }
    return $passed;
}


add_filter('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX'))
        return;

    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['custom_total_price'])) {
            $cart_item['data']->set_price($cart_item['custom_total_price']);
        }
    }
});

add_action('woocommerce_add_order_item_meta', function ($item_id, $values, $cart_item_key) {
    if (isset($values['snakes'])) {
         if (!empty($values['snakes'])) {
        wc_add_order_item_meta($item_id, 'snakes_panel', $values['snakes']);
    }
    }
}, 10, 3);

add_filter('woocommerce_order_item_meta_display', function ($formatted_meta, $item) {
    if (isset($item['item_meta']['Snake #1 ID'])) {
        $formatted_meta .= '<br><strong>Snake Testing Info:</strong>';
        foreach ($item['item_meta'] as $key => $meta) {
            if (strpos($key, 'Snake') !== false || strpos($key, 'Known Genetics') !== false || strpos($key, 'Selected Tests') !== false || strpos($key, 'Snake Price') !== false) {
                $formatted_meta .= '<br>' . esc_html($key) . ': ' . esc_html($meta);
            }
        }
    }
    return $formatted_meta;
}, 10, 2);


add_action('add_meta_boxes', function () {
    add_meta_box(
        'snake_test_settings',
        'Snake Genetic Test Settings',
        'render_snake_test_metabox',
        'product',
        'normal',
        'default'
    );
});

function render_snake_test_metabox($post) {
    $tests = get_post_meta($post->ID, '_snake_tests', true) ?: [];
    $recessives = get_post_meta($post->ID, '_snake_recessive_tests', true) ?: [];
    $pricing = get_post_meta($post->ID, '_snake_pricing', true) ?: [];
   $_snake_full_panel_threshold = get_post_meta($post->ID, '_snake_full_panel_threshold', true) ?: '';

    ?>
    <h4>Genetic Tests</h4>
    <textarea name="snake_tests" rows="5" style="width:100%;"><?php echo esc_textarea(implode("\n", $tests)); ?></textarea>
    <p>Enter one test per line.</p>

    <h4>Recessive Tests</h4>
    <textarea name="snake_recessive" rows="3" style="width:100%;"><?php echo esc_textarea(implode("\n", $recessives)); ?></textarea>
    <p>Match names with the list above.</p>

    <h4>Pricing Tiers (based on # of tests)</h4>
   <textarea name="snake_pricing" rows="6" style="width:100%;"><?php echo implode(', ', array_map(fn($count, $price) => "$count=$price", array_keys($pricing), $pricing));?></textarea>

    <p>Format: <code>1=30</code>, <code>2=40</code>...</p>

      <h4>Full Panel Threshold</h4>
   <input type="number" value="<?php echo $_snake_full_panel_threshold;?>" name="_snake_full_panel_threshold"  style="width:100%;"></input>
<small>Number of selected tests after which Full Panel applies (e.g. 25)</small>
    <?php
}

add_action('save_post_product', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
 if (
        defined('DOING_AJAX') && DOING_AJAX &&
        isset($_POST['_inline_edit']) &&
        $_POST['_inline_edit'] === '1'
    ) {
        return;
    }
    $tests = isset($_POST['snake_tests']) ? array_filter(array_map('trim', explode("\n", $_POST['snake_tests']))) : [];
    $recessives = isset($_POST['snake_recessive']) ? array_filter(array_map('trim', explode("\n", $_POST['snake_recessive']))) : [];

   $pricing_input = $_POST['snake_pricing'] ?? ''; // e.g., "1=30, 2=40, 3=50"
$pricing = [];

$pricing_parts = explode(',', $pricing_input); // split by comma

foreach ($pricing_parts as $part) {
    $part = trim($part);
    if (strpos($part, '=') !== false) {
        [$count, $price] = explode('=', $part);
        $count = trim($count);
        $price = trim($price);
        if (is_numeric($count) && is_numeric($price)) {
            $pricing[$count] = floatval($price);
        }
    }
}

if (isset($_POST['_snake_full_panel_threshold'])) {
        update_post_meta($post_id, '_snake_full_panel_threshold', intval($_POST['_snake_full_panel_threshold']));
    }

 if (isset($_POST['snake_tests'])) {
             update_post_meta($post_id, '_snake_tests', $tests);
    }

    if (isset($_POST['snake_recessive'])) {
    update_post_meta($post_id, '_snake_recessive_tests', $recessives);
    }
    if (isset($_POST['snake_pricing'])) {
    update_post_meta($post_id, '_snake_pricing', $pricing);
    }
});
