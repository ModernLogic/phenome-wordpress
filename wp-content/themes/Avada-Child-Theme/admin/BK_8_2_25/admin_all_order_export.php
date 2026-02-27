<?php
function enqueue_jquery_ui_sortable_for_export_page($hook)
{
    // Check if we are on the "Custom Order Export" admin page
    if ($hook != 'toplevel_page_custom_order_export') {
        return;
    }

    // Enqueue jQuery (if not already loaded)
    wp_enqueue_script('jquery');

    // Enqueue jQuery UI (for sortable functionality)
    wp_enqueue_script('jquery-ui-sortable');

    // Enqueue additional styles if needed (optional)
    wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
}
add_action('admin_enqueue_scripts', 'enqueue_jquery_ui_sortable_for_export_page');
function clean_string($input)
{
    // Remove content within brackets and the price
    $cleaned = preg_replace('/\[\$.*?\]|\(\$.*?\)/', '', $input);

    // Remove any extra spaces around the string
    $cleaned = trim($cleaned);

    // Append a line break
    return $cleaned . PHP_EOL;
}
// Add custom export page
add_action('admin_menu', 'custom_order_export_menu');
function custom_order_export_menu()
{
    add_menu_page(
        'Custom Order Export',
        'Order Export',
        'manage_options',
        'custom_order_export',
        'custom_order_export_page',
        'dashicons-download',
        20
    );
}

// Display custom export page
function custom_order_export_page()
{
   
    

    ?>
    <div class="wrap">
        <h1>Order Export</h1>
        <form method="post" action="admin-post.php?action=custom_order_export">
            <?php wp_nonce_field('custom_order_export_nonce', 'custom_order_export_nonce_field'); ?>

            <!-- Date Range Filter -->
            <h2>Filter by Date Range</h2>
            <label for="start_date">Start Date:</label>
            <input type="date" id="start_date" name="start_date" value="">

            <!-- <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr(date('Y-m-01')); ?>"> -->
            <label for="end_date">End Date:</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr(date('Y-m-d')); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">

            <!-- Order Status Filter -->
            <h2>Filter by Order Status</h2>
            <select name="order_status">
                <option value="">All</option>
                <?php
                $statuses = wc_get_order_statuses();
                foreach ($statuses as $status_slug => $status_name) {
                    echo '<option value="' . esc_attr($status_slug) . '">' . esc_html($status_name) . '</option>';
                }
                ?>
            </select>

           <!-- Customer Filter -->
<h2>Filter by Customer</h2>
<select name="customer_id">
    <option value="">All Customers</option>
    <?php
    $customers = get_users('orderby=meta_value&meta_key=first_name&role=customer'); // Get all customers
    foreach ($customers as $customer) {
        $first_name = get_user_meta($customer->ID, 'first_name', true); // Get user's first name
        $last_name = get_user_meta($customer->ID, 'last_name', true); 
        echo '<option value="' . esc_attr($customer->ID) . '">' . esc_html($first_name) .' ' .esc_html($last_name).' ' .'(' . esc_html( $customer->user_email ) . ')</option>';
    }
    ?>
</select>


            <!-- Column Selection (already existing part) -->
            <div style="display: none;">
                <div style="width: 45%; margin-right: 5%;">
                    <h2>Available Columns</h2>
                    <ul id="available-columns" class="sortable-list">
                        <li data-column="order_id">Order ID</li>
                        <li data-column="order_date">Order Date</li>
                        <li data-column="customer_name">Customer Name</li>
                        <li data-column="customer_email">Customer Email</li>
                        <li data-column="item_name">Item Name</li>
                        <li data-column="shade_id">Shade Id</li>
                        <li data-column="known_genetics">Known Genetics</li>
                        <li data-column="comment">Comment</li>
                        <li data-column="mutation">Mutation</li>
                        <li data-column="item_quantity">Item Quantity</li>
                        <li data-column="item_price">Item Price</li>
                        <li data-column="item_total">Item Total</li>
                        <!-- Add more columns as needed -->
                    </ul>
                </div>
                <div style="width: 45%;">
                    <h2>Selected Columns</h2>
                    <ul id="selected-columns" class="sortable-list">
                        <!-- Selected columns will be dragged here -->
                    </ul>
                </div>
            </div>

            <button type="submit" name="export_orders" class="button button-primary">Export Orders</button>
        </form>
        <?php
        if(isset($_GET['notfound'])&&$_GET['notfound']!=''){
echo "<h2>Records not found</h2>";
        }
        ?>
        <script>
            jQuery(function ($) {
                // Make the lists sortable and connect them
                $('#available-columns, #selected-columns').sortable({
                    connectWith: '.sortable-list',  // Allow drag-and-drop between these two lists
                    placeholder: 'sortable-placeholder',
                    stop: function (event, ui) {
                        // Optional: Prevent empty 'Available Columns' list from being dragged
                        if ($('#available-columns li').length == 0) {
                            ui.item.removeClass('ui-state-default'); // Reset styles
                        }
                    }
                }).disableSelection();

                // Handle form submission
                $('form').on('submit', function () {
                    var selectedColumns = [];
                    $('#selected-columns li').each(function () {
                        selectedColumns.push($(this).data('column')); // Collect the column data
                    });

                    // Add hidden input for selected columns
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'columns',
                        value: JSON.stringify(selectedColumns)
                    }).appendTo('form');
                });
            });
        </script>

        <style>
            .sortable-list {
                list-style-type: none;
                padding: 0;
                margin: 0;
                border: 1px solid #ddd;
                min-height: 100px;
                /* Ensure some space is available when the list is empty */
            }

            .sortable-list li {
                margin: 5px;
                padding: 10px;
                border: 1px solid #ddd;
                background: #f9f9f9;
                cursor: move;
                font-size: 14px;
                text-align: center;
            }

            .sortable-placeholder {
                border: 1px dashed #ccc;
                background: #f0f0f0;
                height: 40px;
                margin: 5px;
            }
        </style>
    </div>
    <?php
}

// Handle export logic
add_action('admin_post_custom_order_export', 'handle_custom_order_export');

function handle_custom_order_export()
{
    if (!isset($_POST['custom_order_export_nonce_field']) || !wp_verify_nonce($_POST['custom_order_export_nonce_field'], 'custom_order_export_nonce')) {
        return;
    }

    // $columns = isset($_POST['columns']) ? json_decode(stripslashes($_POST['columns']), true) : array();

    // // Ensure at least one column is selected
    // if (empty($columns)) {
    //     wp_die('Please select at least one column.');
    // }

    // Retrieve filter values
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $order_status = isset($_POST['order_status']) ? sanitize_text_field($_POST['order_status']) : '';
    $product_ids = array(1020, 4403);
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : '';

    // Prepare query arguments for fetching orders
    $args = array(
        'limit' => -1,
        'type'=> 'shop_order',
        'status' => $order_status ? array($order_status) : array('completed', 'processing', 'on-hold')
     
    );
   
    if (!empty($start_date)) {
        $args['date_created'] =$start_date .'...'. $end_date;
    }
// // If a customer is selected, add it to the query arguments
if (!empty($customer_id)) {
    $args['customer'] = $customer_id;
}
    $data = [];

    $orders = wc_get_orders($args);
  
    // Filter orders by multiple product IDs
$filtered_orders = array_filter($orders, function($order) use ($product_ids) {
    foreach ($order->get_items() as $item) {
        if (in_array($item->get_product_id(), $product_ids)) {
            return true;
        }
    }
    return false;
});
if(count($filtered_orders)>0){
    foreach ($filtered_orders  as $order) {
        // Get order ID
        $order_id = $order->get_id();
    
    // Prepare line item data
    $customerName = $order->get_billing_first_name() .' '.$order->get_billing_last_name();
    
    foreach ($order->get_items() as $item_id => $item_obj) {
        // Fetch the 'ccb_calculator' meta data
        $itemMeta = wc_get_order_item_meta($item_id, 'ccb_calculator');
        if (isset($itemMeta['product_id']) && isset($itemMeta['calc_data'])) {
            $groupedData = [];
            $currentSnake = "";
    
            // Group data by snake identifier
            foreach ($itemMeta['calc_data'] as $snakeitem) {
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
                    $groupedData[$currentSnake][] = $snakeitem;
                }
            }
    
            // Iterate through grouped data and prepare rows for CSV
            foreach ($groupedData as $identifier => $details) {
                $row = [
                    'Customer' => $customerName,
                    'Shade Id' => $identifier,
                ];
    
                foreach ($details as $detail) {
                    $label = $detail['label'];
                    if ($label === "Known Genetics" || $label === "Comment" || $label === "Total" || $label === "SNAKES/TESTS") {
                        continue;
                    }
    
                    if ($label === "Add Secondary Test For Same Shed" || $label === "Pick 3 Tests") {
                        $snakeitems = [];
                        $value = removeTrailingNumber($detail['value']);
                        $value = clean_string($value);
                        $value2 = str_replace(" )", "", $value);
                        $value2 = str_replace("()", "", $value2);
                        if ($value2 != '') {
                            if (strpos($value2, ',') !== false) {
                                $snakeitems = explode(',', $value2);
                            } else {
                                $value3 = str_replace(" )", "", $value);
                                $value3 = str_replace("()", "", $value3);
                                $snakeitems[] = trim($value3);
                            }
                            $i=2;
                            foreach ($snakeitems as $key => $snakeitem) {
                                if ($snakeitem != '') {
                                    $row['mutation'.$i] = preg_replace('/[\(\)]/', '', $snakeitem);
                                }
                                $i++;
                            }
                        }
                    } elseif ($label === "Select Test") {
                        $value = removeTrailingNumber($detail['value']);
                        $value = trim(clean_string($value));
                        if ($value != '') {
                            $row['mutation1'] = preg_replace('/[\(\)]/', '', $value);
                        }
                    } else {
                        if ($label === "Snake Identifier(User Defined)") {
                           $label = "Shade Id";
                       }
                        $row[$label] = $detail['value'];
                    }
                }
    
                $data[] = $row;
            }
        }
    }
}
    // Step 1: Determine all unique mutations and assign counters
$uniqueMutations = [];
foreach ($data as $row) {
    foreach ($row as $mutation => $value) {
        if (preg_match('/mutation(\d+)/', $mutation, $matches)) {
            $mutationName = 'mutation ' . $matches[1]; // Format mutation names with space
            if (!isset($uniqueMutations[$mutationName])) {
                $uniqueMutations[$mutationName] = count($uniqueMutations) + 1;
            }
        } else {
            // Add other columns directly
            $uniqueMutations[$mutation] = 0;
        }
    }
}
// echo '<pre>'; print_r($uniqueMutations); echo '</pre>';
// exit();
// Step 2: Create CSV header with additional columns and properly formatted mutation names
$header = array_merge(
    array_map(fn($mutation, $counter) => ($counter ? $mutation . $counter : $mutation), array_keys($uniqueMutations), $uniqueMutations),
    ['Shade Id', 'Customer']
);
$header2 = array_merge(
    array_map(fn($mutation, $counter) => ($counter ? $mutation  : $mutation), array_keys($uniqueMutations), $uniqueMutations),
    ['Shade Id', 'Customer']
);
// Remove any duplicate "Shade Id" and "Customer" from the header
$header = array_unique($header);
$header2 = array_unique($header2);

$file_path = wp_upload_dir()['basedir'] . '/order_exports/';
if (!file_exists($file_path)) {
    mkdir($file_path, 0755, true); // Create directory if it doesn't exist
}

$file_name = 'custom_order_export_' . date('Ymd_His') . '.csv';
$fp = fopen($file_path . $file_name, 'w');

// Write header to the CSV file
fputcsv($fp, $header2);

// Step 3: Write rows to the CSV file
foreach ($data as $row) {
    // Initialize row with empty values for all mutations and additional columns
    $csvRow = array_fill(0, count($header), '');

    foreach ($row as $key => $value) {
        if (preg_match('/mutation(\d+)/', $key, $matches)) {
            // Format mutation names with space and check for index
            $formattedKey = 'mutation ' . $matches[1] . $uniqueMutations['mutation ' . $matches[1]];
            $index = array_search($formattedKey, $header);
            if ($index !== false) {
                $csvRow[$index] = $value;
            }
        } elseif (in_array($key, ['Shade Id', 'Customer'])) {
            $index = array_search($key, $header);
            if ($index !== false) {
                $csvRow[$index] = $value;
            }
        }
    }

    // Write the row to the CSV file
    fputcsv($fp, $csvRow);
}

// Close the output stream
fclose($fp);


    // Redirect to download file
    wp_redirect(wp_upload_dir()['baseurl'] . '/order_exports/' . $file_name);
}else{
    wp_redirect('https://shedtesting.com/wp-admin/admin.php?page=custom_order_export&notfound=1');
}
    exit;
}
