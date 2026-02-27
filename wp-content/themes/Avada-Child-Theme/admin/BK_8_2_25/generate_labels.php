<?php
add_action( 'wp_ajax_generate_labels', 'my_generate_labels' );
add_action( 'wp_ajax_nopriv_generate_labels', 'my_generate_labels' );

function my_generate_labels() {

    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
   
    include_once get_stylesheet_directory() .'/admin/dompdf/autoload.inc.php';

    
    // Get the order object
    $order = wc_get_order($order_id);
    $mz_first_name = $order->get_billing_first_name();
    $mz_last_name = $order->get_billing_last_name();
    
    
    $html = '<!DOCTYPE html><html><head><title>Test Report</title>';
    $html .= '<style>.logo { float: left; width: 40%; margin-right: 80px; } .text { float: left; width: 60%; padding-left: 20px; } .table { clear: both; width: 100%; margin-top: 20px; border:0px; } body { font-family: Arial, sans-serif; } th { text-align:left; border-bottom: 1px solid; padding: 5px; border-left:1px solid black; } td { border-bottom: 1px solid; padding: 5px; border-right:1px solid black; } table {border-top: 1px solid;border-collapse: collapse;  } .longtext {clear: both;width: 100%;}</style>';
    $html .= '</head><body style="padding-left:5em;padding-right:5em;">';
      
        
       foreach ($order->get_items() as $item_id => $item_obj) {
         $data = wc_get_order_item_meta( $item_id, 'ccb_calculator' );
    
        if ( isset( $data['product_id'] ) && isset( $data['calc_data'] ) ) {
    
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
            
            $i=1;
            ob_start();
            //print_r($groupedData);
                             foreach ($groupedData as $identifier => $details): ?>
                                <?php foreach ($details as $detail): ?>
                                        <?php
                                        if($detail['label'] == 'Known Genetics') {
                                            $Genetics=$detail['value'];
                                        }
                                        if($detail['label'] == 'Snake Identifier(User Defined)'){
                                           $Identifier=$detail['value'];
                                        }
                                        ?>
                                    <?php if ($detail['label']=="Add Secondary Test For Same Shed" || $detail['label']=="Pick 3 Tests"){?> 
                                    
                                    <?php
                                     $snakeitems=array();
                                                    $value=removeTrailingNumber($detail['value']);
                                                    $value = removeBrackets($value);
													$value2 = str_replace(" )", "", $value);
											if($value2!=''){
                                                    if (strpos($value2, ',') !== false) {
                                                    $snakeitems = explode(',', $value2);
                                                    }else{
														$value3 = str_replace(" )", "", $value);
                                                        $snakeitems[] =$value3;
                                                    }
                                                          
                                                  
                                                    foreach ($snakeitems as $key=>$snakeitem):
                                                        echo "<table class='table'> <tbody>";
                                                        echo  '<tr style="border-top:1px solid black;border-bottom:0px;"><th>Name & Company</th>';
                                                        echo  '<td>'.$mz_first_name.' '.$mz_last_name.'</td></tr>';
                                                        echo  '<tr><th>Order #</th>';
                                                        echo  '<td>'.$order_id.'</td></tr>';
                                                        echo '<tr><th>Snake ID #</th>';
                                                        echo '<td>'.$Identifier.'</td></tr>';
                                                        echo '<tr><th>Additional Test</th>';
                                                        echo '<td>'.$snakeitem.'</td></tr>';
                                                        echo '<tr><th>Known Genetics</th>';
                                                        echo '<td>'.$Genetics.'</td></tr>';
                                                        echo "</tbody></table>";
                                                    endforeach;
											}
                                                    ?>
                                    <?php } else if ($detail['label']=="Select Test"){
                                        $value=removeTrailingNumber($detail['value']);
                                        $value = removeBrackets($value);
                                        echo "<table class='table'> <tbody>";
                                        echo  '<tr style="border-top:1px solid black;border-bottom:0px;"><th>Name & Company</th>';
                                        echo  '<td>'.$mz_first_name.' '.$mz_last_name.'</td></tr>';
                                        echo  '<tr><th>Order #</th>';
                                        echo  '<td>'.$order_id.'</td></tr>';
                                            echo '<tr><th>Snake ID #</th>';
                                            echo '<td>'.$Identifier.'</td></tr>';
                                            echo '<tr><th>Requested Test</th>';
                                            echo '<td>'.$value.'</td></tr>';
                                            echo '<tr><th>Known Genetics</th>';
                                            echo '<td>'.$Genetics.'</td></tr>';
                                        echo "</tbody></table>";
                                         } ?>
                                    <?php endforeach; ?>
                                  <?php 
                                   if($data['product_id']== 5593){
                                        
                                    echo "<table class='table'> <tbody>";
                                    echo  '<tr style="border-top:1px solid black;border-bottom:0px;"><th>Name & Company</th>';
                                    echo  '<td>'.$mz_first_name.' '.$mz_last_name.'</td></tr>';
                                    echo  '<tr><th>Order #</th>';
                                    echo  '<td>'.$order_id.'</td></tr>';
                                        echo '<tr><th>Snake ID #</th>';
                                        echo '<td>'.$Identifier.'</td></tr>';
                                        echo '<tr><th>Requested Test</th>';
                                        echo '<td>Colubrid Sex</td></tr>';
                                        echo '<tr><th>Known Genetics</th>';
                                        echo '<td>'.$Genetics.'</td></tr>';
                                    echo "</tbody></table>";
                                }
                                  ?>
                            <?php endforeach;?>
  
                            <?php
                $html .= ob_get_clean();
}
 }
    
    $html .= ' </body> </html>';
  
    $dompdf = new Dompdf\Dompdf();
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Output the generated PDF as a response to the AJAX request
    $pdf = $dompdf->output();
    $pdf_base64 = base64_encode($pdf);
    print_r($pdf_base64 );
    exit();
    // echo $pdf;
    echo $pdf_base64;
    
    wp_die();
    
}
