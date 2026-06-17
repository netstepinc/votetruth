<?php if(!defined('ABSPATH')) exit;
//Don't cache for me so I can keep testing the PDF generation.


/*
if( get_current_user_id() != 1 && file_exists($pdf_file_path) && ( time() - (MONTH_IN_SECONDS) < filemtime($pdf_file_path) ) ):

	fi_log('PDF-STREAM: '.$pdf_filename, __FILE__, __LINE__);
	require_once FI_DIR . 'public/pdf/pdf_inline.php';

else: //Generate a new PDF
*/
	//fi_log('PDF-GEN: '.$pdf_filename, __FILE__, __LINE__);
	//Instantiate Dompdf
require_once FI_DIR . 'dompdf/dompdf.php';
if( file_exists($format_file) ){
	ob_start();
	require_once $format_file;
	$html = ob_get_clean();
	if(isset($_GET['pbug']) && $_GET['pbug'] == '1'){
		echo $html;exit;
	}else{
		fi_dompdf($pdf_filename, $html, $orientation);
	}
}else{
	fi_log('PDF-GEN: FORMAT FILE NOT FOUND: '.$format_file, __FILE__, __LINE__);
	status_header(404);
	echo '<h1>Format File Not Found</h1>';
	exit;
}
//endif;