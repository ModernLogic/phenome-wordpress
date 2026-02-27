<?php

function handle_add_result_meta()
{
	check_ajax_referer('custom_admin_nonce', 'nonce');

	if (!current_user_can('manage_woocommerce')) {
		wp_send_json_error('Unauthorized');
	}

	$meta_key = sanitize_text_field($_POST['meta_key']);
	$meta_index = intval($_POST['meta_index']);
	$item_id = intval($_POST['item_id']);
	$meta_value = sanitize_text_field($_POST['meta_value']);
	$meta_parent = intval($_POST['meta_parent']);

	// Assuming you want to add meta to the first line item
	if ($meta_value != '') {
		wc_update_order_item_meta($item_id, $meta_key . '_' . $meta_parent . '_' . $meta_index, $meta_value);
		wp_send_json_success('Meta added successfully');
	} else {
		wc_delete_order_item_meta($item_id, $meta_key . '_' . $meta_parent . '_' . $meta_index);

		wp_send_json_error('No line items found in order');
	}
}
add_action('wp_ajax_add_result_meta', 'handle_add_result_meta');

add_filter('woocommerce_order_item_get_formatted_meta_data', 'unset_specific_order_item_meta_data', 10, 2);
function unset_specific_order_item_meta_data($formatted_meta, $lineitem)
{
	global $wpdb;

	/*
// Only on emails notifications
if(is_admin() || is_wc_endpoint_url()) {
	return $formatted_meta;
}
*/
	foreach ($formatted_meta as $key => $meta) {

		if (strpos($meta->key, 'main_test_result') !== false || strpos($meta->key, 'sub_test_result') !== false) {
			unset($formatted_meta[$key]);
		}
	}
	$olddata = wc_get_order_item_meta($lineitem->get_id(), '_gravity_forms_history');

	if (isset($olddata) && $olddata != '') {

		$post_id = $_GET['post']; // Replace with your specific post ID
		$meta_key = '%snake_record_%';
		$meta_value_pattern = '%a:1:%';

		// Create the SQL query
		$query = $wpdb->prepare(
			"
    SELECT meta_key,meta_value
    FROM {$wpdb->postmeta}
    WHERE post_id = %d
    AND meta_key LIKE %s
    AND meta_value LIKE %s
    ",
			$post_id,
			$meta_key,
			$meta_value_pattern
		);
		//print_r($query);
		// Execute the query
		$results = $wpdb->get_results($query);

		if ($results !== null) {
			foreach ($results as $result) {
				$meta_value = $result->meta_value;
				// Unserialize the meta value if needed
				$meta_array = maybe_unserialize($meta_value);
				echo $result->meta_key . ':' . $meta_array[0], '<br/>';
				// Now you can work with the meta array
				// print_r($meta_array); // For debugging
			}
		}

		$ccb_calculator = wc_get_order_item_meta($lineitem->get_id(), 'ccb_calculator');
		if (!isset($ccb_calculator['product_id']) && !isset($ccb_calculator['calc_data'])) {
			//print_r($item->order_item_id);
			$dd = [];
			foreach ($formatted_meta as $key => $details) {

				$dd[] = [$details->key, $details->value];

			}
			// Initialize an empty associative array to hold the grouped data
			$groupedData = [];
			$currentIdentifier = '';
			$totalCost = 0;
			// Loop through each item in the data array
			foreach ($dd as $item) {
				// Check if the current item is a "Snake Identifier(User Defined)"
				if ($item[0] === 'Snake Identifier(User Defined)') {
					// Update the current identifier
					$currentIdentifier = $item[1];
					// Initialize an array for this identifier if not already present
					if (!isset($groupedData[$currentIdentifier])) {
						$groupedData[$currentIdentifier] = [];
					}
				}

				// Add the current item to the group of the current identifier
				$groupedData[$currentIdentifier][] = $item;

				// Extract and sum the cost from the item value if it exists
				if (preg_match_all('/\(\$(\d+\.\d{2})\)/', $item[1], $matches)) {
					foreach ($matches[1] as $price) {
						$totalCost += (float) $price;
					}
				}
			}

			// Initialize the final result array
			$result = [
				'product_id' => $lineitem['product_id'],
				'item_name' => $lineitem['name'],
				'order_id' => $lineitem['order_id'],
				'calc_data' => [],
				'ccb_total' => $totalCost,
			];

			// Initialize the calc_data array
			$calcData = [
				[
					'label' => 'SNAKES/TESTS',
					'value' => '',
				],
			];

			// Flatten the grouped data into the desired structure
			foreach ($groupedData as $identifier => $items) {
				foreach ($items as $item) {
					if ($item[0] == 'Add More Snakes/Tests.') {
						continue;
					}
					$calcData[] = [
						'label' => ($item[0] == 'Add Secondary Test' ? 'Add Secondary Test For Same Shed' : $item[0]),
						'value' => $item[1],
					];
				}
			}

			// Add the calc_data to the result array
			$result['calc_data'] = $calcData;

			wc_update_order_item_meta($lineitem->get_id(), 'ccb_calculator', $result);

			// Display the final result
			//print_r($result);
		}
	}
	return $formatted_meta;
}

// Function to remove the actions
function remove_ccbproajaxactions_hooks()
{
	// Remove the 'woocommerce_order_item_meta_end' action added by CCBWooCheckout::calc_order_item_meta
	remove_filter('woocommerce_order_item_meta_end', ['cBuilder\Classes\CCBWooCheckout', 'calc_order_item_meta'], 99);

	// Remove the 'woocommerce_after_order_itemmeta' action added by CCBWooCheckout::calc_order_item_meta
	remove_action('woocommerce_after_order_itemmeta', ['cBuilder\Classes\CCBWooCheckout', 'calc_order_item_meta'], 99);
}

// Hook the removal function to 'init'
add_action('init', 'remove_ccbproajaxactions_hooks', 100);
function removeTrailingNumber($input)
{
	// Use regular expression to remove any number at the end of the string
	return preg_replace("/\s\d+$/", "", $input);
}
function removeBrackets($input)
{
	// Specify characters to remove as starting and ending brackets
	return trim($input, '()');
}

add_action('wp_ajax_update_testdetails', 'update_testdetails');
add_action('wp_ajax_nopriv_update_testdetails', 'update_testdetails');
function update_testdetails()
{
	check_ajax_referer('update_testdetails_nonce', 'security');
	$item_id = $_POST['item_id'];
	$key = $_POST['key'];
	$value = $_POST['meta_value'];
	$item_type = $_POST['item_type'];
	$sub_key = $_POST['sub_key'];
	$snakes = wc_get_order_item_meta($item_id, 'snakes_panel', true);

	if (wc_update_order_item_meta($item_id, '_snakes_panel_backup', $snakes) == '') {
		wc_update_order_item_meta($item_id, '_snakes_panel_backup', $snakes );

	}
	if($snakes!=''){
			if ($sub_key != '' && $item_type == 'tests') { 
				$snakes[$key]['tests'][$sub_key] = $value;
			}else {
				$snakes[$key][trim($item_type)] = $value;
			}
	wc_update_order_item_meta($item_id, 'snakes_panel', $snakes);
	}else{
	
	$data = wc_get_order_item_meta($item_id, 'ccb_calculator');
	if (wc_update_order_item_meta($item_id, '_ccb_calculator_backup', $data) == '') {
		wc_update_order_item_meta($item_id, '_ccb_calculator_backup', $data);

	}

	if ($sub_key != '' && $item_type == 'subtest') { // Check if underscore exists
		//$parts = explode('_', $key); // Split the string by underscore
		//$key = $parts[0];
		$subkey = $sub_key;                              // Array: [0 => "10", 1 => "1"]
		// Extract the part inside parentheses
		$subtests = $data['calc_data'][$key];
		// Split the string by commas

		// Extract the part inside parentheses
		preg_match('/\((.*)\)\s*(\d+)/', $subtests['value'], $matches);

		$attributes = [];
		$number = null;

		if (!empty($matches)) {
			// Get the attributes and split them
			$innerString = $matches[1]; // Content inside parentheses
			$number = $matches[2]; // The number outside parentheses

			// Match individual attributes, handling possible nested parentheses
			preg_match_all('/([^,]+(?:\([^)]+\))?)/', $innerString, $attributeMatches);

			if (!empty($attributeMatches[1])) {
				$attributes = array_map('trim', $attributeMatches[1]); // Clean up spaces
			}
		}

		// Print the results
		$attributes[$subkey] = $value;
		$updatedvalue = implode(', ', $attributes);
		$makevalue = '(' . $updatedvalue . ') ' . $number;
	} else {
		$makevalue = $value;
	}
	$data['calc_data'][$key]['value'] = $makevalue;
	$updateddata = $data;
	wc_update_order_item_meta($item_id, 'ccb_calculator', $updateddata);
	}
	wp_send_json_success(['message' => 'Meta updated successfully']);

	//wp_send_json($_POST);
	die();

}

add_action('wp_ajax_get_latest_test_options', 'get_latest_test_options');
add_action('wp_ajax_nopriv_get_latest_test_options', 'get_latest_test_options'); // For unauthenticated users, if required.

function get_latest_test_options()
{
	
	$selectTest = get_post_meta(intval($_GET['id']), '_snake_tests', true);
	$data = array_map(function ($option) {
		return [
			"value" => $option,
			"text" => $option,
		];
	}, $selectTest);
	// print_r($selectTestdataSource);


	// Send the JSON response
	wp_send_json($data);
}

add_action('wp_ajax_get_test_options', 'get_test_options');
add_action('wp_ajax_nopriv_get_test_options', 'get_test_options'); // For unauthenticated users, if required.

function get_test_options()
{
	$stm_ccb_form_settings = json_decode(get_option('ccb_demo_import_content'), true);
	if ($_GET['type'] == "subtest") {
		$selectTest = $stm_ccb_form_settings['calculators'][0]['ccb_fields'][1]['groupElements'][4]['options'];

	} else {
		$selectTest = $stm_ccb_form_settings['calculators'][0]['ccb_fields'][1]['groupElements'][3]['options'];
	}
	$data = array_map(function ($option) {
		return [
			"value" => $option["optionText"],
			"text" => $option["optionText"],
		];
	}, $selectTest);
	// print_r($selectTestdataSource);
	// print_r($secondaryTest);

	// Send the JSON response
	wp_send_json($data);
}



add_action('woocommerce_order_item_line_item_html', 'custom_link_after_order_itemmeta', 99, 3);

function custom_link_after_order_itemmeta($item_id, $item, $product)
{
	global $post;
$product_id = $item->get_product_id();
	if ($product->get_id() && is_admin()) {
		$currentSnake = "";
		$options = [
			"Heterozygous",
			"Homozygous",
			"Negative",
			"Need new shed",
			"Rerun in progress",
			"Shed Not Received",
		];
		$indicates = [
			"Female",
			"Male",
			"Need new shed",
			"Rerun in progress",
			"Shed Not Received",
		];
		$snakes = wc_get_order_item_meta($item_id, 'snakes_panel', true);
		
		if (!empty($snakes)) {
			echo '<h3>Snake Genetic Test Info</h3>';


			$i = 1;
			foreach ($snakes as $mkey => $snake) {
				$snake_id = sanitize_text_field($snake['id']);
				$known_genetics = sanitize_text_field($snake['genetics']);
				$test_price = wc_price(floatval($snake['price']));

				$tests = $snake['tests'] ?? [];
				echo '
					<tr>
					<td colspan="6">
						<div class="metacollapsible__wrap">
							<button type="button" class="metacollapsible">SNAKES/TESTS #' . $i . '</button>
							<div class="metacontent">';
				echo '<p><span>Snake Id</span><span><a class="editable" href="#" id="test__data_' . $i . '_' . $item_id . '"  data-type="text"  data-itemid="' . $item_id . '" data-pk="' . $mkey . '" data-item_type="id" data-subkey=""  data-title="Enter Snake ID">' . $snake_id . '</a></span></p>';
				echo '<p><span>Known Genetics</span><span><a class="editable" href="#" id="test__data_' . $i . '_' . $item_id . '" data-type="text"  data-itemid="' . $item_id . '" data-pk="' . $mkey . '" data-item_type="genetics" data-subkey=""  data-title="Enter  known genetics">' . $known_genetics . '</a></span></p>';
				echo '<p><span>Price</span><span>' . $test_price . '</span></p>';

				echo "<table>";
				echo "<thead>
										<tr>
											<th>Test</th>
											<th>Result</th>
											<th style='text-align: right;'>Set Details</th>
										</tr>
										</thead><tbody>";
				$ii = 1;
				foreach ($tests as $key => $test) {
					$test_name = sanitize_text_field($test);

					$currenttesresult = wc_get_order_item_meta($item_id, '_sub_test_result_' . $i . '_' . $key);
					echo '<tr><td>';

					echo '<a class="editable" href="#" id="test__data_' . $i . '_' . $ii . '" data-source="/wp-admin/admin-ajax.php?action=get_latest_test_options&type=subtest&id='.$product_id.'" data-type="select" data-itemid="' . $item_id . '" data-item_type="tests" data-pk="' . $mkey . '" data-subkey="' . $key . '"  data-title="Select Test">' . $test_name . '</a>';

					echo '</td><td>' . ($currenttesresult != '' ? 'Complete' : 'Pending') . '</td><td style="text-align: right;">'; ?>
					<select data-parent="<?php echo $i; ?>" data-index="<?php echo $key; ?>" data-itemid="<?php echo $item_id; ?>"
						class="add_result_meta" data-type="_sub_test_result" name="sub_test_results">
						<option>Select</option>
						<?php
						// Loop through the options array to generate select options
						foreach ($options as $option) {
							echo "<option " . ($currenttesresult == $option ? 'selected=selected' : '') . " value='$option'>$option</option>";
						}
						?>
					</select>
					<?php echo '</td></tr>';
					$ii++;
				}
				echo '</td></tr></tbody></table>';
				echo '</div>';
				$i++;
			}

			echo '</div>
					</td>
				</tr>';
		}


		$data = wc_get_order_item_meta($item_id, 'ccb_calculator');
		// if($post->ID == '5024' || $post->ID == '4340'){
		// print_r($data );
		// }
		if (isset($data['product_id']) && isset($data['calc_data'])) {
			echo '<style>
	#order_line_items tr.item  table.display_meta{display:none}
	</style>';

			$groupedData = [];

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

			$i = 1;

			//print_r($groupedData);
			foreach ($groupedData as $identifier => $details):
				$ii = 1; ?>
				<tr>
					<td colspan="6">
						<div class="metacollapsible__wrap">
							<button type="button" class="metacollapsible">SNAKES/TESTS #<?php echo $i; ?></button>
							<div class="metacontent">

								<?php foreach ($details as $detail): ?>
									<?php if ($detail['label'] == "Add Secondary Test For Same Shed" || $detail['label'] == "Pick 3 Tests") { ?>
										<?php
										$snakeitems = [];
										$value = removeTrailingNumber($detail['value']);
										$value = removeBrackets($value);
										$value2 = str_replace(" )", "", $value);
										if ($value2 != '') {
											if (strpos($value2, ',') !== false) {
												$snakeitems = explode(',', $value2);
											} else {
												$value3 = str_replace(" )", "", $value);
												$snakeitems[] = $value3;
											}
											if ($detail['label'] == "Select Test") {
												echo '<p><span style="color: #a99bff!important;">Main Test</span><p>';

											} elseif ($detail['label'] == "Pick 3 Tests") {
												echo '<p><span style="color: #a99bff!important;">3 Tests</span><p>';

											} else {
												echo '<p><span style="color: #a99bff!important;">Secondary Test</span><p>';
											}
											echo "<table>";
											echo "<thead>
										<tr>
											<th>Test</th>
											<th>Result</th>
											<th style='text-align: right;'>Set Details</th>
										</tr>
										</thead><tbody>";

											foreach ($snakeitems as $key => $snakeitem):
												if ($snakeitem != '') {
													$currenttesresult = wc_get_order_item_meta($item_id, '_sub_test_result_' . $i . '_' . $key);
													echo '<tr><td>';
													// if($post->ID == '4340'){
													// 	echo '<a class="editable" href="#" id="test__data_'.$i.'_'.$ii.'" data-source="/wp-admin/admin-ajax.php?action=get_test_options&type=subtest" data-type="select" data-itemid="'.$item_id.'" data-pk="'.$detail['key'].'_'.$key.'"  data-title="Select '.($detail['label']=="Select Test"?"Main Test":"Secondary Test").'">'.$snakeitem.'</a>';
													// }else{
													// 	echo $snakeitem;
													// }
													echo '<a class="editable" href="#" id="test__data_' . $i . '_' . $ii . '" data-source="/wp-admin/admin-ajax.php?action=get_test_options&type=subtest" data-type="select" data-itemid="' . $item_id . '" data-item_type="subtest" data-pk="' . $detail['key'] . '" data-subkey="' . $key . '"  data-title="Select ' . ($detail['label'] == "Select Test" ? "Main Test" : "Secondary Test") . '">' . $snakeitem . '</a>';

													echo '</td><td>' . ($currenttesresult != '' ? 'Complete' : 'Pending') . '</td><td style="text-align: right;">'; ?>
													<select data-parent="<?php echo $i; ?>" data-index="<?php echo $key; ?>"
														data-itemid="<?php echo $item_id; ?>" class="add_result_meta" data-type="_sub_test_result"
														name="sub_test_results">
														<option>Select</option>
														<?php
														// Loop through the options array to generate select options
														foreach ($options as $option) {
															echo "<option " . ($currenttesresult == $option ? 'selected=selected' : '') . " value='$option'>$option</option>";
														}
														?>
													</select>
													<?php echo '</td></tr>';
												}
											endforeach;
											echo "</tbody></table>";
										}
										?>
									<?php } elseif ($detail['label'] == "Select Test") { ?>
										<?php

										$value = removeTrailingNumber($detail['value']);
										$value = removeBrackets($value);

										if ($detail['label'] == "Select Test") {
											echo '<p><span style="color: #a99bff!important;">Main Test</span><p>';

										} else {
											echo '<p><span style="color: #a99bff!important;">Secondary Test</span><p>';
										}
										echo "<table>";
										echo "<thead>
										<tr>
											<th>Test</th>
											<th>Result</th>
											<th style='text-align: right;'>Set Details</th>
										</tr>
										</thead><tbody>";
										if ($value != '') {
											$currenttesresult = wc_get_order_item_meta($item_id, '_main_test_result_' . $i . '_0');
											echo '<tr><td>';
											// if($post->ID == '4340'){
											// 	echo '<a class="editable" href="#" id="test__data_'.$i.'_'.$ii.'" data-type="select" data-source="/wp-admin/admin-ajax.php?action=get_test_options&type=maintest"  data-itemid="'.$item_id.'" data-pk="'.$detail['key'].'"  data-title="Select '.($detail['label']=="Select Test"?"Main Test":"Secondary Test").'">'.$value.'</a>';
											// }else{
											// 	echo $value;
											// }
											echo '<a class="editable" href="#" id="test__data_' . $i . '_' . $ii . '" data-type="select" data-source="/wp-admin/admin-ajax.php?action=get_test_options&type=maintest"  data-itemid="' . $item_id . '" data-item_type="test" data-pk="' . $detail['key'] . '" data-subkey="" data-title="Select ' . ($detail['label'] == "Select Test" ? "Main Test" : "Secondary Test") . '">' . $value . '</a>';

											echo '</td><td>' . ($currenttesresult != '' ? 'Complete' : 'Pending') . '</td><td style="text-align: right;">'; ?>
											<select data-parent="<?php echo $i; ?>" data-index="0" data-itemid="<?php echo $item_id; ?>"
												data-type="_main_test_result" class="add_result_meta" name="main_test_results">
												<option>Select</option>
												<?php
												// Loop through the options array to generate select options
												foreach ($options as $option) {
													echo "<option " . ($currenttesresult == $option ? 'selected=selected' : '') . "  value='$option'>$option</option>";
												}
												?>
											</select>
											<?php echo '</td></tr>';
										}
										echo "</tbody></table>";

										?>
									<?php } else {

										?>
										<?php /* if($post->ID == '4340'){ ?>
																			  <?php echo '<p><span>'.$detail['label'].'</span><span><a class="editable" href="#" id="test__data_'.$i.'_'.$ii.'" data-type="text"  data-itemid="'.$item_id.'" data-pk="'.$detail['key'].'"   data-title="Enter '.$detail['label'].'">'.$detail['value'].'</a></span></p>';?>
																			  <?php }else{?>
																		  <?php echo '<p><span>'.$detail['label'].'</span><span>'.$detail['value'].'</span></p>';?>
																		  <?php }*/ ?>
										<?php echo '<p><span>' . $detail['label'] . '</span><span><a class="editable" href="#" id="test__data_' . $i . '_' . $ii . '" data-type="text"  data-itemid="' . $item_id . '" data-pk="' . $detail['key'] . '" data-item_type="text" data-subkey=""  data-title="Enter ' . $detail['label'] . '">' . $detail['value'] . '</a></span></p>'; ?>

									<?php } ?>
									<?php $ii++; ?>
								<?php endforeach; ?>
								<?php if ($data['product_id'] == 5593) {
									echo "<table>";
									echo "<thead>
										<tr>
											<th></th>
											<th>Result</th>
											<th style='text-align: right;'>Set Details</th>
										</tr>
										</thead><tbody>";
									$currenttesresult = wc_get_order_item_meta($item_id, '_main_test_result_' . $i . '_0');
									echo '<tr><td>';
									// if($post->ID == '4340'){
									// 	echo '<a class="editable" href="#" id="test__data_'.$i.'_'.$ii.'" data-type="select" data-source="/wp-admin/admin-ajax.php?action=get_test_options&type=maintest"  data-itemid="'.$item_id.'" data-pk="'.$detail['key'].'"  data-title="Select '.($detail['label']=="Select Test"?"Main Test":"Secondary Test").'">'.$value.'</a>';
									// }else{
									// 	echo $value;
									// }
				
									echo '</td><td>' . ($currenttesresult != '' ? 'Complete' : 'Pending') . '</td><td style="text-align: right;">'; ?>
									<select data-parent="<?php echo $i; ?>" data-index="0" data-itemid="<?php echo $item_id; ?>"
										data-type="_main_test_result" class="add_result_meta" name="main_test_results">
										<option>Select</option>
										<?php
										// Loop through the options array to generate select options
										foreach ($indicates as $option) {
											echo "<option " . ($currenttesresult == $option ? 'selected=selected' : '') . "  value='$option'>$option</option>";
										}
										?>
									</select>
									<?php echo '</td></tr>';
									echo "</tbody></table>";
								} ?>
							</div>
							<?php $i++; ?>
						</div>
					</td>
				</tr>

			<?php endforeach; ?>

		<?php }

	}
}

function my_custom_admin_css()
{
	global $pagenow, $post_type;

	// Check if current admin page is a WooCommerce order page
	if ($pagenow === 'post.php' && $post_type === 'shop_order') {
		echo '
    <style>
	:root {
        color-scheme: light dark;
		--text-color: white;
     }
	 @media (prefers-color-scheme: dark) {
			.metacontent {color:white}
			.metacontent table th{
	color: white;
	}
	.metacontent table td{
	color: white;
	}
	.metacontent p{
	color: white;
	}
		}
	.multiple-selection {
		max-width: 200px;
		width: 100%;
		position: relative;
	  }

	  .multiple-selection label {
		color: #000;
		background: #ddd;
		font-size: 14px;
		border-radius: 4px;
		padding: 8px 16px;
		display: block;
		position: relative;
		-webkit-user-select: none; /* webkit (safari, chrome) browsers */
		-moz-user-select: none; /* mozilla browsers */
		-khtml-user-select: none; /* webkit (konqueror) browsers */
		-ms-user-select: none; /* IE10+ */
		cursor: pointer;
	  }

	  .multiple-selection label:after {
		content: "";
		width: 0;
		height: 0;
		border-left: 6px solid transparent;
		border-right: 6px solid transparent;
		border-top: 6px solid #000;
		border-bottom: 0px;
		right: 10px;
		position: absolute;
		top: 14px;
	  }

	  .multiple-selection.show label:after {
		width: 0;
		height: 0;
		border-left: 6px solid transparent;
		border-right: 6px solid transparent;
		border-top: 0px;
		border-bottom: 6px solid black;
	  }

	  .checkbox-dropdown {

z-index:9;
		box-shadow: 8px 8px 8px rgba(0, 0, 0, 0.05);
		overflow: hidden;
		margin-top: 10px;
		background: #ddd;
		border-radius: 4px;
		position: absolute;
		left: 0;
		right: 0;
	  }

	  .multiple-selection .checkbox-dropdown {
		max-height: 0px;
		transition: max-height 0.3s;
	  }

	  .multiple-selection.show .checkbox-dropdown {
		max-height: 300px;
	  }

	  .checkbox-dropdown input[type="checkbox"] {
		-webkit-appearance: none!important;
		display: block!important;
		padding: 10px 16px!important;
		width: 100%!important;
		margin: 0!important;
		position: relative!important;
		outline: none !important;
		transition: background 0.6s;
	  }
	  .checkbox-dropdown input[type="checkbox"]:hover {
		background: rgba(0, 0, 0, 0.1);
	  }

	  .checkbox-dropdown input[type="checkbox"]:checked {
		background: rgba(0, 255, 0, 0.3);
	  }

	  .checkbox-dropdown input[type="checkbox"]:checked:before {
		content: "×";
		position: absolute;
		font-weight: bold;
		right: 10px;
		top: 50%;
		font-size: 18px;
		line-height: 0;
	  }

	  .checkbox-dropdown input[type="checkbox"]:after {
		content: attr(value);
		font-weight: 400;
	  }

	.metacollapsible {
		background-color: #000;
		color: #a99bff;
		cursor: pointer;
		padding: 10px;
		width: 100%;
		border: none;
		text-align: left;
		outline: none;
		font-size: 15px;
		display: flex;
		justify-content: space-between;
		align-items: center;
			font-weight: 800;
letter-spacing: 1px;
	}
	.metacontent table{
		width: 100%;
	}

	.metacontent table th{
		background: none!important;
		font-weight: 400!important;
		color: #fff!important;
		text-transform: uppercase;
		padding: 5px!important;
	}
	.metacontent table td{
		background: none!important;
		color: #fff!important;
		padding: 5px!important;
	}
	 .metacontent p span:empty,.metacontent p:empty{display:none;}
	.metacontent p{
display: flex;
justify-content: space-between;
font-size: 14px;
align-items: center;
border-bottom: 1px dotted #a0a0a0;

padding-bottom: 5px;
}
.metacontent p span:nth-child(2){    text-align: right;}

	.metacollapsible::after {
		content: "\25BC"; /* Unicode character for down arrow */
		font-size: 13px;
		color: #a99bff;
		margin-left: 10px;
	}
	.metacontent {
		background: #000;
		color: #fff;
		padding: 10px;
		border-top: 1px solid;
	}
		.editable-click:not(.editable-empty){
    color:#c9c9c9;
    border-color: #a89afe;}
    </style>
    ';
	}
}
add_action('admin_head', 'my_custom_admin_css');

function my_custom_woocommerce_order_js()
{
	global $pagenow, $post_type;

	// Check if current admin page is a WooCommerce order page
	if ($pagenow === 'post.php' && $post_type === 'shop_order') {
		echo '
        <script type="text/javascript">
		document.addEventListener("DOMContentLoaded", function() {
			document.querySelectorAll(".multiple-selection").forEach(function(label) {
				label.onclick = function() {
					this.classList.toggle("show");
				};
			});
			var coll = document.getElementsByClassName("metacollapsible");
			for (var i = 0; i < coll.length; i++) {
				coll[i].addEventListener("click", function() {
					this.classList.toggle("metaactive");
					var content = this.nextElementSibling;
					if (content.style.display === "block") {
						content.style.display = "none";
					} else {
						content.style.display = "block";
					}
				});
			}
		});
        </script>
        ';
	}
}
add_action('admin_head', 'my_custom_woocommerce_order_js');

function wc_display_item_meta($item, $args = [])
{
	$strings = [];
	$html = '';
	$args = wp_parse_args(
		$args,
		[
			'before' => '<ul class="wc-item-meta"><li>',
			'after' => '</li></ul>',
			'separator' => '</li><li>',
			'echo' => false,
			'autop' => false,
			'label_before' => '<strong class="wc-item-meta-label">',
			'label_after' => ':</strong> ',
		]
	);
	$data = $item->get_meta('ccb_calculator');
	$currentSnake = "";
		
		$snakes = $item->get_meta('snakes_panel');
		if (!empty($snakes)) {
			$item_id = $item->get_id();
			echo '<h3>Snake Genetic Test Info</h3>';


			$i = 1;
			foreach ($snakes as $snake) {
				$snake_id = sanitize_text_field($snake['id']);
				$known_genetics = sanitize_text_field($snake['genetics']);
				$test_price = wc_price(floatval($snake['price']));

				$tests = $snake['tests'] ?? [];
				echo '
					<tr>
					<td colspan="6">
						<div class="metacollapsible__wrap">
							<button type="button" class="metacollapsible">SNAKES/TESTS #' . $i . '</button>
							<div class="metacontent">';
				echo '<p><span>Snake Id</span><span>' . $snake_id .'</span></p>';
				echo '<p><span>Known Genetics</span><span>' . $known_genetics . '</span></p>';
				echo '<p><span>Price</span><span>' . $test_price . '</span></p>';

				echo "<table>";
				echo "<thead>
										   <tr>
                                                <th style='color:#fff'>Test</th>
                                                <th style='color:#fff'>Status</th>
                                                <th style='text-align: right;color:#fff'>Result</th>
                                            </tr>
										</thead><tbody>";
				$ii = 1;
				foreach ($tests as $key => $test) {
					$test_name = sanitize_text_field($test);

					$currenttesresult = wc_get_order_item_meta($item_id, '_sub_test_result_' . $i . '_' . $key);
					echo '<tr><td style="color:#fff">' . $test_name . '</td><td>' . ($currenttesresult != '' ? 'Complete' : 'Pending') . '</td><td style="text-align: right;color:#fff">'; ?>
										<?php echo $currenttesresult; ?>
										<?php echo '</td></tr>';
				
					$ii++;
				}
				echo '</tbody></table>';
				echo '</div>';
				$i++;
			}

			echo '</div>
					</td>
				</tr>';
		}

	if (isset($data['product_id']) && isset($data['calc_data']) || !empty($snakes) ) {
		echo '<style>
	:root {
        color-scheme: light dark;
     }
	  @media (prefers-color-scheme: dark) {
			.metacontent {color:white}
			.metacontent table th{
	color: white;
	}
	.metacontent table td{
	color: white;
	}
	.metacontent p{
	color: white;
	}
		}
    .metacollapsible {
		background-color: #000;
		color: #a99bff;
		cursor: pointer;
		padding: 10px;
		width: 100%;
		border: none;
		text-align: left;
		outline: none;
		font-size: 15px;
		display: flex;
		justify-content: space-between;
		align-items: center;
			font-weight: 800;
letter-spacing: 1px;
	}
	.metacontent table{
		width: 100%;
	}
	.metacontent table th{
		background: none!important;
		font-weight: 400!important;
		color: #fff!important;
		text-transform: uppercase;
		padding: 5px!important;
	}
	.metacontent table td{
		background: none!important;
		color: #fff!important;
		padding: 5px!important;
	}
	 .metacontent p span:empty,.metacontent p:empty{display:none;}
	.metacontent p{
display: flex;
justify-content: space-between;
font-size: 14px;
align-items: center;
border-bottom: 1px dashed #ccc;

padding-bottom: 10px;
}
.metacontent p span:nth-child(2){    text-align: right;}

	.metacollapsible::after {
		content: "\25BC"; /* Unicode character for down arrow */
		font-size: 13px;
		color: #a99bff;
		margin-left: 10px;
	}
	.metacontent {
		background: #000;
		color: #fff;
		padding: 10px;
		border-top: 1px solid;
	}
    .metacontent table tbody th,.metacontent table  td  {
        padding: 1.5em 1em 1em;
        text-align: left;
        line-height: 1.5em;
        vertical-align: top;
        border-bottom: 1px solid #f8f8f8;
    }
    </style>';
	}
		if (isset($data['product_id']) && isset($data['calc_data'])) {

		$item_id = $item->get_id();
		$groupedData = [];
		$currentSnake = "";
		$options = [
			"Heterozygous",
			"Homozygous",
			"Negative",
			"Need new shed",
			"Rerun in progress",
			"Shed Not Received",
		];
		$indicates = [
			"Female",
			"Male",
			"Need new shed",
			"Rerun in progress",
			"Shed Not Received",
		];
		foreach ($data['calc_data'] as $snakeitem) {
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

		$i = 1;

		//print_r($groupedData);
		foreach ($groupedData as $identifier => $details): ?>
			<tr>
				<td colspan="6">
					<div class="metacollapsible__wrap">
						<button type="button" class="metacollapsible">SNAKES/TESTS #<?php echo $i; ?></button>
						<div class="metacontent">
							<?php foreach ($details as $detail): ?>
								<?php if ($detail['label'] == "Add Secondary Test For Same Shed" || $detail['label'] == "Pick 3 Tests") { ?>
									<?php
									$snakeitems = [];
									$value = removeTrailingNumber($detail['value']);
									$value = removeBrackets($value);
									if (strpos($value, ',') !== false) {
										$snakeitems = explode(',', $value);
									} else {
										$snakeitems[] = $value;
									}
									if ($detail['label'] == "Select Test") {
										echo '<p><span style="color: #a99bff!important;">Main Test</span><p>';

									} elseif ($detail['label'] == "Pick 3 Tests") {
										echo '<p><span style="color: #a99bff!important;">3 Tests</span><p>';

									} else {
										echo '<p><span style="color: #a99bff!important;">Secondary Test</span><p>';
									}
									echo "<table bgcolor='#000' style='background:#000;color:#fff'>";
									echo "<thead>
                                            <tr>
                                                <th style='color:#fff'>Test</th>
                                                <th style='color:#fff'>Status</th>
                                                <th style='text-align: right;color:#fff'>Result</th>
                                            </tr>
                                            </thead><tbody>";
									foreach ($snakeitems as $key => $snakeitem):
										$currenttesresult = wc_get_order_item_meta($item_id, '_sub_test_result_' . $i . '_' . $key);

										echo '<tr><td style="color:#fff">' . $snakeitem . '</td><td>' . ($currenttesresult != '' ? 'Complete' : 'Pending') . '</td><td style="text-align: right;color:#fff">'; ?>
										<?php echo $currenttesresult; ?>
										<?php echo '</td></tr>';
									endforeach;
									echo "</tbody></table>";

									?>
								<?php } elseif ($detail['label'] == "Select Test") { ?>
									<?php
									$value = removeTrailingNumber($detail['value']);
									$value = removeBrackets($value);

									if ($detail['label'] == "Select Test") {
										echo '<p><span style="color: #a99bff!important;">Main Test </span><p>';

									} else {
										echo '<p><span style="color: #a99bff!important;">Secondary Test</span><p>';
									}
									echo "<table bgcolor='#000' style='background:#000;color:#fff'>";
									echo "<thead>
                                            <tr>
                                                <th style='color:#fff'>Test</th>
                                                <th style='color:#fff'>Status</th>
                                                <th style='text-align: right;color:#fff'>Result</th>
                                            </tr>
                                            </thead><tbody>";
									$currenttesresult = wc_get_order_item_meta($item_id, '_main_test_result_' . $i . '_0');
									echo '<tr><td style="color:#fff">' . $value . '</td><td>' . ($currenttesresult != '' ? 'Complete' : 'Pending') . '</td><td style="text-align: right;color:#fff">'; ?>
									<?php echo $currenttesresult; ?>
									<?php echo '</td></tr>';
									echo "</tbody></table>";

									?>
								<?php } else { ?>
									<?php echo '<p style="color:#fff"><span>' . $detail['label'] . '</span><span>' . $detail['value'] . '</span></p>'; ?>
								<?php } ?>
							<?php endforeach; ?>
						</div>
						<?php $i++; ?>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
	<?php } else {
		foreach ($item->get_formatted_meta_data() as $meta_id => $meta) {
			$value = $args['autop'] ? wp_kses_post($meta->display_value) : wp_kses_post(make_clickable(trim($meta->display_value)));
			$strings[] = $args['label_before'] . wp_kses_post($meta->display_key) . $args['label_after'] . $value;
		}

		if ($strings) {
			$html = $args['before'] . implode($args['separator'], $strings) . $args['after'];
		}

		$html = apply_filters('woocommerce_display_item_meta', $html, $item, $args);

		if ($args['echo']) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
		} else {
			return $html;
		}
	}
}
// Add custom meta box for email notification
add_action('add_meta_boxes', function () {
	add_meta_box(
		'send_email_meta_box',                        // Meta box ID
		__('Email Notification', 'your-text-domain'), // Title
		'render_email_meta_box',                      // Callback function to render the box
		'shop_order',                                 // Post type (WooCommerce orders)
		'side',                                       // Context ('side', 'normal', 'advanced')
		'high'                                        // Priority
	);
});

// Render the meta box
function render_email_meta_box($post)
{
	?>
	<div>
		<label for="send_email_checkbox">
			<input type="checkbox" id="send_email_checkbox" name="send_email_checkbox" value="yes">
			<?php esc_html_e('Send email notification on order completed', 'your-text-domain'); ?>
		</label>
	</div>
	<?php
}
function prevent_email_for_specific_product_customer_completed_order_func($recipient, $order)
{

	if (!isset($_POST['send_email_checkbox']) || $_POST['send_email_checkbox'] !== 'yes') {
		$recipient = "";
	}

	return $recipient;

}

add_filter('woocommerce_email_recipient_customer_completed_order', 'prevent_email_for_specific_product_customer_completed_order_func', 10, 2);
