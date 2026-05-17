<?php 
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
* Parties for the Freedom Index plugin
* 
* This class contains the parties for the Freedom Index plugin.
* 
* @package Freedom Index
* @subpackage Parties
* @since 4.0.0
* @author Sam Mittelstaedt <smittelstaedt@jbs.org>
* @copyright 2025 The New American
* RULE: All uses of party abbreviations must be uppercase EXCEPT when in the URL/rewrite. As soon as the query var is set it must be uppercase.
* RULE: $party_abbr refers to uppdercase and $party_slug refers to lowercase. DO NOT interchange them.
* 
*/

function fi_parties(): array {
	return FI_PARTIES;
}
/**
* Get party full name from abbreviation
*/
function fi_party_name(?string $abbreviation): string {
	if (empty($abbreviation)) {
		return '';
	}
	$party = FI_PARTIES[$abbreviation] ?? null;
	return $party ? $party['name'] : strtoupper($abbreviation);
}

/**
* Get party background CSS class
*/
function fi_party_bg_class(?string $abbreviation): string {
	if (empty($abbreviation)) {
		return 'bg-light text-dark';
	}
	$party = FI_PARTIES[$abbreviation] ?? null;
	return $party ? $party['bg_class'] : 'bg-light text-dark';
}

/**
* Get party background color (hex)
*/
function fi_party_bg_color(?string $abbreviation): string {
	if (empty($abbreviation)) {
		return '#888';
	}
	$party = FI_PARTIES[$abbreviation] ?? null;
	return $party ? $party['bg_color'] : '#888';
}

/**
* Get party text CSS class
*/
function fi_party_text_class(?string $abbreviation): string {
	if (empty($abbreviation)) {
		return '';
	}
	$party = FI_PARTIES[$abbreviation] ?? null;
	return $party ? $party['text_class'] : '';
}

/**
* Get party text color (hex)
*/
function fi_party_text_color(?string $abbreviation): string {
	if (empty($abbreviation)) {
		return '#000';
	}
	$party = FI_PARTIES[$abbreviation] ?? null;
	return $party ? $party['text_color'] : '#000';
}

/**
* Validate party abbreviation
* 
* @param string $abbreviation Party abbreviation (e.g., 'R', 'D', 'L')
* @return bool True if valid party abbreviation
*/
function fi_party_validate(string $abbreviation): bool {
	$abbreviation = strtoupper($abbreviation);
	return FI_PARTIES[$abbreviation] !== null;
}

function fi_party_abbr(string $abbreviation): string {
	$abbreviation = strtoupper($abbreviation);
	return FI_PARTIES[$abbreviation]['abbr'] ?? $abbreviation;
}