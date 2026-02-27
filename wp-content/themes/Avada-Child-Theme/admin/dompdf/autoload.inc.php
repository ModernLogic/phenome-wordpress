<?php require (__DIR__ . '/vendor/autoload.php');
include_once get_stylesheet_directory() . '/dompdf/autoload.inc.php';
if (file_exists(get_stylesheet_directory() . '/admin/dompdf/autoload.inc.php')) {
    include_once get_stylesheet_directory() . '/admin/dompdf/autoload.inc.php';
    error_log('Dompdf autoload.inc.php loaded successfully.');
} else {
    error_log('Dompdf autoload.inc.php file not found.');
}
