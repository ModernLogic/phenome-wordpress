<?php
add_action('bulk_actions-edit-shop_order', 'register_bulk_export_orders_csv');
function register_bulk_export_orders_csv($bulk_actions)
{
    $bulk_actions['export_selected_orders_csv'] = 'Export Selected Orders (CSV)';
    return $bulk_actions;
}

add_filter('handle_bulk_actions-edit-shop_order', 'handle_bulk_export_orders_csv', 10, 3);
function handle_bulk_export_orders_csv($redirect_to, $action, $post_ids)
{
    if ($action !== 'export_selected_orders_csv') {
        return $redirect_to;
    }

    $csv_headers = ['Customer Name', 'Snake ID','Unique ID', 'Test', 'Result'];
    $csv_rows = [];

    foreach ($post_ids as $order_id) {
        $order = wc_get_order($order_id);
        $customer_name = $order->get_formatted_billing_full_name();

        foreach ($order->get_items() as $item_id => $item_obj) {
            $data = wc_get_order_item_meta($item_id, 'ccb_calculator');
 $snakes = wc_get_order_item_meta($item_id, 'snakes_panel', true);
        if (!empty($snakes)) {
             $i = 1;
             foreach ($snakes as $snake) {
                 $snake_id = sanitize_text_field($snake['id']);
                $tests = $snake['tests'] ?? [];
                 foreach ($tests as $key => $test) {
                      $unique_id = $item_id . '-S_' . $i . '_'.$key;
                      $csv_rows[] = [$customer_name,$snake_id, $unique_id, $test, $result];

                 }
 $i++;
             }

        }else{
            if (isset($data['product_id']) && isset($data['calc_data'])) {
                $test = $result = '';
                $groupedData = [];
                $currentSnake = '';

                $i = 1; // Group index

                // Group calc_data based on "Snake Identifier(User Defined)"
                foreach ($data['calc_data'] as $key => $snakeitem) {
                    if ($snakeitem['label'] === "Snake Identifier(User Defined)") {
                        $currentSnake = trim($snakeitem['value']);
                        if (empty($currentSnake)) {
                            continue;
                        }
                        if (!isset($groupedData[$currentSnake])) {
                            $groupedData[$currentSnake] = [];
                        }
                    }
                    if (!empty($currentSnake)) {
                        $snakeitem['key'] = $key;
                        $groupedData[$currentSnake][] = $snakeitem;
                    }
                }

                foreach ($groupedData as $identifier => $details) {
                    foreach ($details as $detail) {
                        $value = removeTrailingNumber($detail['value']);
                        $value = removeBrackets($value);
                        $value = str_replace(' )', '', $value);
                        
                        // Handle multiple test fields
                        if (in_array($detail['label'], ['Add Secondary Test For Same Shed', 'Pick 3 Tests'])) {
                            $snakeitems = strpos($value, ',') !== false ? explode(',', $value) : [$value];
                            foreach ($snakeitems as $key=>$snakeitem) {
                                $snakeitem = trim($snakeitem);
                                if (!empty($snakeitem)) {
                                    $unique_id = $item_id . '-S_' . $i . '_'.$key;
                                    $test = $snakeitem;
                                    $csv_rows[] = [$customer_name,$identifier, $unique_id, $test, $result];
                                }
                            }
                        }

                        // Handle single Select Test
                        if ($detail['label'] === 'Select Test' && !empty($value)) {
                            $unique_id = $item_id . '-M_' . $i . '_0';

                            $test = $value;
                            $csv_rows[] = [$customer_name, $identifier,$unique_id, $test, $result];
                        }
                    }
                    $i++;
                }
            }
        }
        }
    }

    // Output CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=selected-orders-export.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, $csv_headers);

    foreach ($csv_rows as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

add_action('admin_menu', 'register_result_csv_upload_page');

function register_result_csv_upload_page() {
    add_submenu_page(
        'woocommerce', 
        'Upload Test Results', 
        'Upload Test Results', 
        'manage_woocommerce', 
        'upload-test-results', 
        'render_test_result_upload_form'
    );
}

function render_test_result_upload_form() {
    ?>
    <div class="wrap">
        <h1>Upload Test Results CSV</h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('upload_test_results_csv', 'upload_test_results_csv_nonce'); ?>
            <input type="file" name="results_csv" accept=".csv" required>
            <input type="submit" name="upload_results_csv" class="button button-primary" value="Upload and Update">
        </form>
    </div>
    <?php

    if (isset($_POST['upload_results_csv']) && check_admin_referer('upload_test_results_csv', 'upload_test_results_csv_nonce')) {
        if (!empty($_FILES['results_csv']['tmp_name'])) {
            $csv = array_map('str_getcsv', file($_FILES['results_csv']['tmp_name']));
            $headers = array_map('trim', array_shift($csv)); // Remove headers

            $snake_id_index = array_search('Unique ID', $headers);
            $result_index = array_search('Result', $headers);

            if ($snake_id_index === false || $result_index === false) {
                echo '<div class="notice notice-error"><p>CSV must contain Unique ID and Result columns.</p></div>';
                return;
            }

            $success = 0;
            $fail = 0;

            foreach ($csv as $row) {
                $snake_id = trim($row[$snake_id_index]);
                $result = trim($row[$result_index]);

               $snake_id = trim($row[$snake_id_index]); // e.g., 1234-M_1_0
$result = trim($row[$result_index]);

// Remove item ID before the dash and split remaining part
$parts = explode('-', $snake_id, 2);
if (count($parts) === 2) {
    $item_id = intval($parts[0]);           // This is used in wc_update_order_item_meta
    $suffix = $parts[1];                    // e.g., M_1_0 or S_2_0

    // Replace M/S with correct meta key prefix
    if (strpos($suffix, 'M_') === 0) {
        $meta_key = str_replace('M_', '_main_test_result_', $suffix); // _main_test_result_1_0
    } elseif (strpos($suffix, 'S_') === 0) {
        $meta_key = str_replace('S_', '_sub_test_result_', $suffix);  // _sub_test_result_2_0
    } else {
        // Invalid suffix
        $meta_key = null;
    }
//echo $item_id.": ".$meta_key.": ".$result,'<br />';
    if (!empty($meta_key)) {
      wc_update_order_item_meta($item_id, $meta_key, $result);
        $success++;
    } else {
        $fail++;
    }
}

 }
            echo '<div class="notice notice-success"><p>' . esc_html("$success results updated.") . '</p></div>';
            if ($fail > 0) {
                echo '<div class="notice notice-warning"><p>' . esc_html("$fail rows skipped due to invalid Snake IDs.") . '</p></div>';
            }
        }
    }
}
