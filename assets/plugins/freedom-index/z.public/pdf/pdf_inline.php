<?php if(!defined('ABSPATH')) exit;

// Critical: no prior output
while (ob_get_level()) { ob_end_clean(); }

// Optional: prevent PHP output compression issues
if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
@ini_set('zlib.output_compression', 'Off');

$filename = basename($pdf_file_path);
$size     = (int) filesize($pdf_file_path);
$mtime    = (int) filemtime($pdf_file_path);

// Strong ETag (changes if file changes)
$etag = '"' . sha1($filename . '|' . $size . '|' . $mtime) . '"';

// Basic headers
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
//header('Link: <' . esc_url_raw($canonical_url) . '>; rel="canonical"', false);
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow', true);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('ETag: "' . $etag . '"');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

// 304 handling (do this BEFORE sending body headers like Content-Length)
$if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
$if_mod_since  = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : 0;

// Use ">" not ">=" to avoid edge-case false 304s when timestamps round
if (($if_none_match && $if_none_match === $etag) || ($if_mod_since && $if_mod_since > $mtime)) {
	http_response_code(304);
	exit;
}

$fp = fopen($pdf_file_path, 'rb');
if (!$fp) {
	http_response_code(500);
	exit;
}

// Range support
$start = 0;
$end   = $size - 1;

if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
	if ($m[1] !== '') { $start = max(0, (int)$m[1]); }
	if ($m[2] !== '') { $end   = min($end, (int)$m[2]); }

	if ($start > $end || $start >= $size) {
		fclose($fp);
		header('Content-Range: bytes */' . $size);
		http_response_code(416);
		exit;
	}

	http_response_code(206);
	header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
	$length = ($end - $start) + 1;
	header('Content-Length: ' . $length);

	fseek($fp, $start);
} else {
	// Full content
	header('Content-Length: ' . $size);
}

// Stream
$chunk = 8192;
$pos   = $start;

while (!feof($fp) && $pos <= $end) {
	$read = min($chunk, ($end - $pos) + 1);
	$data = fread($fp, $read);
	if ($data === false) { break; }
	echo $data;
	$pos += strlen($data);
}

fclose($fp);
exit;