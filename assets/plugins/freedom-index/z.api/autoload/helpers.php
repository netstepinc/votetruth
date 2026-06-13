<?php if(!defined('ABSPATH')) exit;

function fi_api_upper(?string $v): string { return strtoupper(trim((string)$v)); }
function fi_api_lower(?string $v): string { return strtolower(trim((string)$v)); }


function fi_api_int($v, int $default = 0): int {
	if ($v === null) return $default;
	if (is_int($v)) return $v;
	$v = trim((string)$v);
	return ctype_digit($v) ? (int)$v : $default;
}


function fi_api_clean_text(?string $v, int $max = 190): string {
	$v = trim((string)$v);
	if ($v === '') return '';
	$v = preg_replace('/[^\p{L}\p{N}\s\-\.\'\,]/u', '', $v);
	$v = preg_replace('/\s+/u', ' ', $v);
	return mb_substr($v, 0, $max);
}
