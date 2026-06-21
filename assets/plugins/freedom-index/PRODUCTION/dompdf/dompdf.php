<?php if(!defined('ABSPATH')) exit;
require_once __DIR__ . '/vendor/autoload.php';
// reference the Dompdf namespace
use Dompdf\Dompdf;
use Dompdf\Options;

function fi_dompdf($filename, $html, $orientation = 'L') {
    $file_path = FI_DIR_CACHE . 'pdf/' . $filename . '.pdf';
    $file_name = $filename . '.pdf';
    
    $orientation = ($orientation == 'L') ? 'landscape' : 'portrait';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'sans-serif');
	$options->set('fontHeightRatio', 1.1);
	$options->set('fontFamily', 'Arial, sans-serif');
	$options->set('fontSize', 12); // 12pt (Dompdf expects points, not px)
	$options->set('dpi', 72); //DPI: 72,96,150,300

    $pdf = new \Dompdf\Dompdf($options);
    $pdf->loadHtml($html);
    $pdf->setPaper('A4', $orientation);

    // 1. You MUST render the PDF first
    $pdf->render();

    // 2. Access the Canvas to set Metadata
    // This controls the Browser Tab Title
    $canvas = $pdf->getCanvas();
    $canvas->add_info('Author', 'The John Birch Society');
	$canvas->add_info('CreationDate', date('m/d/Y h:i A')); // USA date format

    // Check if your constants exist before applying them
    if (defined('PDF_PAGE_TITLE')) {
        $canvas->add_info('Title', PDF_PAGE_TITLE);
    }
	if(defined('PDF_KEYWORDS')){
		$canvas->add_info('Keywords', PDF_KEYWORDS);
	}
	if(defined('PDF_DESCRIPTION')){
		$canvas->add_info('Description', PDF_DESCRIPTION);
	}

    // 3. Now get the output and stream
    $output = $pdf->output();
    // No cache: file_put_contents($file_path, $output);

	if ( ! headers_sent() ) {

		// Prevent indexing
		header('X-Robots-Tag: noindex, noarchive, nosnippet', true);

		// Security
		header('X-Content-Type-Options: nosniff');

		// Optional canonical target
		if ( defined('PDF_CANONICAL_URL') ) {
			header(
				'Link: <' . esc_url_raw(PDF_CANONICAL_URL) . '>; rel="canonical"',
				false
			);
		}
	}

    $pdf->stream($file_name, ['Attachment' => false]);
}