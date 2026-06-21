<?php
/*
File extractor for Legiscan data

key= timestamp(today as 00:00:01)  {Unix time stamp for 12:00:01 AM today}
gov=US
session=2025-2026_119th_Congress
file=HB1{add .json in this page}

The purpose of this file is to extract the relevant data from each Legiscan file and output a JSON string.

Extract these values from the bill file:
$billData = [
    "bill" => [
        "bill_id" => 2000358,
        "session_id" => 2199,
        "url" => "https://legiscan.com/US/bill/HB2056/2025",
        "state_link" => "https://www.congress.gov/bill/119th-congress/house-bill/2056/all-info",
        "state" => "US",
        "bill_number" => "HB2056",
        "bill_type" => "B",
        "body" => "H",
        "title" => "District of Columbia Federal Immigration Compliance Act of 2025",
        "description" => "To require the District of Columbia to comply with federal immigration laws.",
        "votes" => [
            [
                "roll_call_id" => 1590237,
                "state_link" => "https://clerk.house.gov/Votes/2025170",
            ],
        ],
    ]
];

https://freedomindex.us/wp-content/cache/jbsfi/legiscan/extractor.php?key=aba1fcf8e23f67cd261842c7a8f30012&gov=US&session=2025-2026_119th_Congress&file=HB1
*/

$extractor_auth_key = md5(strtotime(date('Y-m-d') . ' 00:00:01'));

$key = isset($_GET['key']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['key']) : '';
if ($key != $extractor_auth_key) {
	header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid key']);
    exit;
}

$gov = isset($_GET['gov']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['gov']) : '';
$session = isset($_GET['session']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['session']) : '';
$file = isset($_GET['file']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['file']) : '';

// Construct data directory path
$data_dir = rtrim(__DIR__, '/') . '/' . $gov . '/' . $session . '/';
$bills_dir = rtrim($data_dir, '/') . '/bill/';
$votes_dir = rtrim($data_dir, '/') . '/vote/';

//Process bills and votes and save processed bills WITH votes individually in separate folder
$fi_dir = rtrim($data_dir, '/') . '/fi/';
if (!is_dir($fi_dir)) {
	mkdir($fi_dir, 0755, true);
}

$file_path = $bills_dir . $file . '.json';

//echo '<div style="font-family: monospace;">' . $file_path . '</div>';


if (!file_exists($file_path)) {
	header('Content-Type: application/json');
	echo json_encode(['error' => 'File not found']);
	exit;
}

$file_content = file_get_contents($file_path);

$billData = json_decode($file_content, true);
$data = [
	"bill_id" => $billData['bill']['bill_id'],
	"session_id" => $billData['bill']['session_id'],
	"url" => $billData['bill']['url'],
	"state_link" => $billData['bill']['state_link'],
	"state" => $billData['bill']['state'],
	"bill_number" => $billData['bill']['bill_number'],
	"bill_type" => $billData['bill']['bill_type'],
	"body" => $billData['bill']['body'],
	"title" => $billData['bill']['title'],
	"description" => $billData['bill']['description'],
	"votes" => [],
];

//Fetch votes for bills on the fly in one pass
$votes = [];
foreach($billData['bill']['votes'] as $vote){
	$rc_id = $vote['roll_call_id'];
	$state_link = $vote['state_link'];
	$vote_file_path = __DIR__ . '/' . $gov . '/' . $session . '/vote/' . $rc_id . '.json';
	if (!file_exists($vote_file_path)) {
		error_log("Vote file not found: " . $vote_file_path);
		header('Content-Type: application/json');
		echo json_encode([
			'error' => 'Vote file not found',
			'js_console_error' => "console.error('Vote file not found: " . addslashes($vote_file_path) . "');"
		]);
		exit;
	}
	$vote_file_content = file_get_contents($vote_file_path);
	$vote_data = json_decode($vote_file_content, true);
	if ($vote_data) {
		$rollcall = $vote_data['roll_call'];
		$rollcall['state_link'] = $state_link;
		$votes[$rc_id] = $rollcall;
	}
}
$data['votes'] = $votes;

header('Content-Type: application/json');

$votes_count = count($votes);
if($votes_count > 0){
	//Save the processed bill with votes to the FI folder
	$fi_file_path = $fi_dir . $file . '.json';
	file_put_contents($fi_file_path, json_encode($data));
	echo json_encode(['status' => 'processed', 'file' => $file, 'saved' => true, 'votes_count' => $votes_count]);
} else {
	echo json_encode(['status' => 'processed', 'file' => $file, 'saved' => false, 'votes_count' => 0, 'message' => 'No votes found for bill: ' . $file]);
}