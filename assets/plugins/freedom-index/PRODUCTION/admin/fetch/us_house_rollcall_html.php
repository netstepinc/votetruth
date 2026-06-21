<?php if(!defined('ABSPATH')) exit;
/* us_house_rollcall_html fetcher
Example: https://clerk.house.gov/Votes/2025258?RollCallNum=258 = https://clerk.house.gov/evs/2025/roll258.xml
*/

$parts = explode('/', $url);
$parts = explode('?', $parts[4]);
$year = substr($parts[0], 0, 4);
$rc_parts = explode('=', $parts[1]);
$rollcall_num = $rc_parts[1];
$url = 'https://clerk.house.gov/evs/'.$year.'/roll'.$rollcall_num.'.xml';

require_once FI_DIR . 'admin/fetch/us_house_rollcall_xml.php';