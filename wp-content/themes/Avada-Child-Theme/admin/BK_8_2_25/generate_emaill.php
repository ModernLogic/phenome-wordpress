<?php

function generate_emaill() {

    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    
    $order = wc_get_order($order_id);

    $customer_email = $order->get_billing_email();
    $mz_first_name = $order->get_billing_first_name();


    $to = $customer_email;
    // $to = 'dev.itvative@gmail.com';
    $subject = 'Tests Update';
    $message = '<html><body><div style="background-color:#f5f5f5;margin:0;padding:3%;width:100%"> <table border="0" cellpadding="0" cellspacing="0" height="100%" class="m_-505009148846401985wrapper-table" style="margin:auto;max-width:900px;width:100%"> <tbody> <tr> <td align="center" valign="top"> <div id="m_-505009148846401985template_header_image" style="width:100%"> </div> <table border="0" cellpadding="0" cellspacing="0" width="100%" id="m_-505009148846401985template_container" style="background-color:#fdfdfd;border-radius:3px!important;box-shadow: 0px 5px 7px -5px rgba(0, 0, 0, 0.24)"> <tbody> <tr> <td align="center" valign="top"> <table border="0" cellpadding="0" cellspacing="0" width="100%" id="m_-505009148846401985template_header" style="background-color:#000;border-radius:3px 3px 0 0!important;color:#ffffff;border-bottom:0;font-weight:bold;line-height:100%;vertical-align:middle;font-family:Arial,Helvetica,sans-serif"> <tbody> <tr> <td id="m_-505009148846401985header_wrapper" style="padding:22px 24px;display:block"> <h1 style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:30px;font-weight:300;line-height:150%;margin:0;text-align:left">Grey Rider Reptiles</h1> </td> </tr> </tbody> </table> </td> </tr> <tr> <td align="center" valign="top"> <table border="0" cellpadding="0" cellspacing="0" width="100%" id="m_-505009148846401985template_body"> <tbody> <tr> <td valign="top" id="m_-505009148846401985body_content" style="background-color:#fdfdfd"> <table border="0" cellpadding="20" cellspacing="0" width="100%"> <tbody> <tr> <td valign="top" style="padding:27px"> <div id="m_-505009148846401985body_content_inner" style="color:#737373;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:150%;text-align:left"> <p style="margin:0 0 16px">Hello '.$mz_first_name.', <br>Some of your tests results are available. Login to your account and go to your orders to check your results.</p> </div> </td> </tr> </tbody> </table> </td> </tr> </tbody> </table> </td> </tr> <tr> <td align="center" valign="top"> <table border="0" cellpadding="10" cellspacing="0" width="100%" id="m_-505009148846401985template_footer"> <tbody> <tr> <td valign="top" style="padding:0;font-size:14px"> <table border="0" cellpadding="10" cellspacing="0" width="100%"> <tbody> <tr> <td colspan="2" valign="middle" id="m_-505009148846401985credit" style="padding:0 36px 36px 36px;font-size:12px;border:0;color:#22134e;font-family:Arial;line-height:125%;text-align:center"> <p>Grey Rider Reptiles - All Rights Reserved 2024</p> </td> </tr> </tbody> </table> </td> </tr> </tbody> </table> </td> </tr> </tbody> </table> </td> </tr> </tbody> </table></div></body></html>';

    // $message = '<html><body>Hello '.$mz_first_name.',<br> Some of your tests results are available. Login to your account and go to your orders to check your results.</body></html>';
    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail($to, $subject, $message, $headers);

    wp_send_json_success(array('message' => __('Thanks for reporting!', 'report-a-bug')));
}
add_action('wp_ajax_nopriv_generate_emaill', 'generate_emaill');
add_action('wp_ajax_generate_emaill', 'generate_emaill');