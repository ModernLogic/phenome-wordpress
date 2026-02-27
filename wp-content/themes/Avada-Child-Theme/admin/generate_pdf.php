<?php


add_action('wp_ajax_generate_pdf', 'my_generate_pdf');
add_action('wp_ajax_nopriv_generate_pdf', 'my_generate_pdf');

function my_generate_pdf()
{
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    // if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'my-generate-pdf-nonce' ) ) {
    //     die('Invalid security token');
    // }

    // Generate the PDF using dompdf
    include_once get_stylesheet_directory() . '/admin/dompdf/autoload.inc.php';


    // Get the order object
    $order = wc_get_order($order_id);



    // $html = '<h1>Order Details</h1>';
    // $html .= '<p><strong>Order ID:</strong> ' . $order->get_id() . '</p>';
    // $html .= '<p><strong>Order Date:</strong> ' . $order->get_date_created()->format( 'Y-m-d H:i:s' ) . '</p>';
    // $html .= '<p><strong>Order Total:</strong> ' . $order->get_total() . '</p>';
    // $html .= '<h2>Order It';


    // Load HTML content for the PDF template


    $html = '<!DOCTYPE html><html><head><title>Test Report</title>';

    $html .= '<style>.logo { float: left; width: 40%; margin-right: 80px; } .text { float: left; width: 60%; padding-left: 20px; } .table { clear: both; width: 100%; margin-top: 20px; } body { font-family: Arial, sans-serif; } th { text-align:left; border-bottom: 1px solid; padding: 5px; } td { border-bottom: 1px solid; padding: 5px; } table {border-bottom: 1px solid;border-collapse: collapse;  } .longtext {clear: both;width: 100%;}</style>';
    $html .= '</head><body><div class="logo">';
    $html .= '<img src="data:image/png;base64,' . base64_encode(file_get_contents(get_stylesheet_directory_uri() . '/admin/logo.jpg')) . '" alt="MzLogo" width="293" height="272">';
    $html .= '</div>
      <div class="text">
        <p>Grey Rider Reptiles <br> PO Box 13 <br> West Sand Lake, NY 12196 <br> charlie@shedtesting.com</p>
      </div><div class="longtext"> <h4 style="text-align:center">Snake Details</h4> </div>';




    // $items = $order->get_items();

    foreach ($order->get_items() as $item_id => $item_obj) {
                $data = wc_get_order_item_meta($item_id, 'ccb_calculator');

        $snakes = wc_get_order_item_meta($item_id, 'snakes_panel', true);
        if (!empty($snakes)) {
            ob_start();
            $i = 1;
           
            foreach ($snakes as $snake) {
                 echo ' <table style="width:100%;margin-bottom:25px"> <tbody>';
                $snake_id = sanitize_text_field($snake['id']);
                $known_genetics = sanitize_text_field($snake['genetics']);

                $tests = $snake['tests'] ?? [];
                echo '<tr><td><strong>Snake ID</strong></td><td style="text-align: right;">' . $snake_id . '</td></tr>';
                echo '<tr><td><strong>Known Genetics</strong></td><td style="text-align: right;">' . $known_genetics . '</td></tr>';
                echo ' <tr >
                    <th>Test</th>
                    <th style="text-align: right;">Result</th>
                </tr>';

                $ii = 1;
                foreach ($tests as $key => $test) {
                    $test_name = sanitize_text_field($test);
                    $currenttesresult = wc_get_order_item_meta($item_id, '_sub_test_result_' . $i . '_' . $key);
                    echo '<tr><td>' . $test_name . '</td><td style="text-align: right;">';
                    echo $currenttesresult;
                    echo '</td></tr>';
                    $ii++;
                }
                $i++;
                echo ' </tbody> </table>';
            }

        } else {


            if (isset($data['product_id']) && isset($data['calc_data'])) {

                $groupedData = [];
                $currentSnake = "";

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
                ob_start();
                foreach ($groupedData as $identifier => $details): ?>
                    <table style="width:100%;margin-bottom:25px">
                        <tbody>
                            <?php foreach ($details as $detail): ?>


                                <?php if ($detail['label'] == "Add Secondary Test For Same Shed" || $detail['label'] == "Pick 3 Tests") { ?>
                                    <?php
                                    $snakeitems = array();
                                    $value = removeTrailingNumber($detail['value']);
                                    $value2 = str_replace(" )", "", $value);
                                    if ($value2 != '') {
                                        if (strpos($value2, ',') !== false) {
                                            $snakeitems = explode(',', $value2);
                                        } else {
                                            $value3 = str_replace(" )", "", $value2);
                                            $value3 = str_replace("()", "", $value3);
                                            if ($value3 != '') {
                                                $snakeitems[] = $value3;
                                            }

                                        }
                                        if ($detail['label'] == "Select Test") {
                                            $testtype = 'Main Test';

                                        } elseif ($detail['label'] == "Pick 3 Tests") {
                                            $testtype = '3 Tests';

                                        } else {
                                            $testtype = 'Secondary Test';
                                        }

                                        if (count($snakeitems) > 0) {
                                            echo "
                                            
                                            <tr >
                                                <th>" . $testtype . count($snakeitems) . " </th>
                                                <th style='text-align: right;'>Result</th>
                                            </tr>";
                                            foreach ($snakeitems as $key => $snakeitem):
                                                $currenttesresult = wc_get_order_item_meta($item_id, '_sub_test_result_' . $i . '_' . $key);
                                                echo '<tr><td>' . $snakeitem . '</td><td style="text-align: right;">';
                                                echo $currenttesresult;
                                                echo '</td></tr>';
                                            endforeach;
                                        }
                                    }
                                    ?>
                                <?php } elseif ($detail['label'] == "Select Test") { ?>
                                    <?php
                                    $value = removeTrailingNumber($detail['value']);
                                    $value = removeBrackets($value);

                                    if ($detail['label'] == "Select Test") {
                                        $testtype = 'Main Test';

                                    } else {
                                        $testtype = 'Secondary Test';
                                    }

                                    echo "
                                            <tr>
                                            <th>" . $testtype . "</th>
                                                <th style='text-align: right;'>Result</th>
                                            </tr>
                                            ";
                                    if ($value != '') {
                                        $currenttesresult = wc_get_order_item_meta($item_id, '_main_test_result_' . $i . '_0');
                                        echo '<tr><td><strong>' . $value . '</strong></td><td style="text-align: right;">';
                                        echo $currenttesresult;
                                        echo '</td></tr>';
                                    }
                                    ?>
                                <?php } else {
                                    if ($detail['label'] != 'Comment') {
                                        ?>
                                        <?php echo '<tr><td><strong>' . $detail['label'] . '</strong></td><td style="text-align: right;">' . $detail['value'] . '</td></tr>'; ?>
                                    <?php }
                                } ?>

                            <?php endforeach; ?>
                            <?php if ($data['product_id'] == 5593) {
                                echo
                                    $currenttesresult = wc_get_order_item_meta($item_id, '_main_test_result_' . $i . '_0');
                                echo '<tr>';
                                // if($post->ID == '4340'){
                                // 	echo '<a class="editable" href="#" id="test__data_'.$i.'_'.$ii.'" data-type="select" data-source="/wp-admin/admin-ajax.php?action=get_test_options&type=maintest"  data-itemid="'.$item_id.'" data-pk="'.$detail['key'].'"  data-title="Select '.($detail['label']=="Select Test"?"Main Test":"Secondary Test").'">'.$value.'</a>';
                                // }else{
                                // 	echo $value;
                                // }
        
                                echo '<td>Result</td><td style="text-align: right;">'; ?>
                                <?php echo $currenttesresult; ?>
                                <?php echo '</td></tr>';
                            } ?>
                            <?php $i++; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php }
        }
        $html .= ob_get_clean();
    }

    $html .= '<br><br> <div class="longtext"> <p>Please contact <strong>charlie@shedtesting.com</strong> if you have any questions regarding your results.</p> </div> </body> </html>';

    $dompdf = new Dompdf\Dompdf();

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output the generated PDF as a response to the AJAX request
    $pdf = $dompdf->output();
    $pdf_base64 = base64_encode($pdf);

    // echo $pdf;
    echo $pdf_base64;

    wp_die();

}
