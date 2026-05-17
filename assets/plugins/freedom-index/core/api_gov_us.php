<?php if(!defined('ABSPATH')) exit;
/*
Census Bureau TIGER/Line Shapefiles via the U.S. Census API (FREE)
Best option. Most complete. Zero cost.
You can get full geometry (polygons) for: 
- U.S. Congressional Districts (116th → 118th)
- State Upper Chambers (State Senate)
- State Lower Chambers (State House/Assembly)
- Places, counties, VTDs, etc.
Formats available: GeoJSON, Shapefile, TopoJSON, KML (older releases)
Example (GeoJSON for all U.S. Congressional Districts): https://www2.census.gov/geo/tiger/TIGER2023/CD/

You can download all districts or per-state.
Cost: Free
Licensing: Public domain
Map quality: The gold standard. The same source used by nearly all civic tech projects (OpenStates, FiveThirtyEight, Ballotpedia, etc.)
Works perfectly for FI project because: 
You can store district polygons in your own DB.
You can embed them in Leaflet.
You can run intersection checks (e.g., point-in-polygon for ZIP lookups).
*/




/*
Accessing via Census API:
To retrieve current data similar to Table HH-1, you should use the American Community Survey (ACS) 1-Year Estimates, specifically table B11001: Household Type. 
https://api.census.gov/data/2024/acs/acs1?get=group(B09019)&ucgid=0100000US

I need the 9th value in this payload...but it's 2024 (2 years ago)
[["B09019_001E","B09019_001EA","B09019_001M","B09019_001MA","B09019_002E","B09019_002EA","B09019_002M","B09019_002MA","B09019_003E","B09019_003EA","B09019_003M","B09019_003MA","B09019_004E","B09019_004EA","B09019_004M","B09019_004MA","B09019_005E","B09019_005EA","B09019_005M","B09019_005MA","B09019_006E","B09019_006EA","B09019_006M","B09019_006MA","B09019_007E","B09019_007EA","B09019_007M","B09019_007MA","B09019_008E","B09019_008EA","B09019_008M","B09019_008MA","B09019_009E","B09019_009EA","B09019_009M","B09019_009MA","B09019_010E","B09019_010EA","B09019_010M","B09019_010MA","B09019_011E","B09019_011EA","B09019_011M","B09019_011MA","B09019_012E","B09019_012EA","B09019_012M","B09019_012MA","B09019_013E","B09019_013EA","B09019_013M","B09019_013MA","B09019_014E","B09019_014EA","B09019_014M","B09019_014MA","B09019_015E","B09019_015EA","B09019_015M","B09019_015MA","B09019_016E","B09019_016EA","B09019_016M","B09019_016MA","B09019_017E","B09019_017EA","B09019_017M","B09019_017MA","B09019_018E","B09019_018EA","B09019_018M","B09019_018MA","B09019_019E","B09019_019EA","B09019_019M","B09019_019MA","B09019_020E","B09019_020EA","B09019_020M","B09019_020MA","B09019_021E","B09019_021EA","B09019_021M","B09019_021MA","B09019_022E","B09019_022EA","B09019_022M","B09019_022MA","B09019_023E","B09019_023EA","B09019_023M","B09019_023MA","B09019_024E","B09019_024EA","B09019_024M","B09019_024MA","B09019_025E","B09019_025EA","B09019_025M","B09019_025MA","B09019_026E","B09019_026EA","B09019_026M","B09019_026MA","GEO_ID","NAME","ucgid"],
["340110990",null,"-555555555","*****","331722429",null,"-555555555","*****","132737146",null,"140273",null,"65273550",null,"119826",null,"17366116",null,"81071",null,"47907434",null,"115726",null,"67463596",null,"101817",null,"20949247",null,"83023",null,"46514349",null,"109308",null,"61035127",null,"182891",null,"848589",null,"18615",null,"9133169",null,"61372",null,"556688",null,"14716",null,"94090379",null,"152603",null,"88158468",null,"159217",null,"2101178",null,"37541",null,"3830733",null,"47455",null,"7875754",null,"77772",null,"3990893",null,"64807",null,"4213495",null,"58064",null,"1052125",null,"22595",null,"1391654",null,"24840",null,"4721130",null,"71477",null,"265305",null,"13654",null,"9810975",null,"127994",null,"8388561",null,"-555555555","*****","0100000US","United States","0100000US"]]

Can't find current data (most recent year) yet. Use value from spreadsheet:
https://www.census.gov/data/tables/time-series/demo/families/households.html Table HH-1. Households by Type: 1940 to Present
https://fred.stlouisfed.org/series/TTLHHM156N Household Estimates (TTLHHM156N) = 133,686 @ 12/2025
https://www.census.gov/data/developers/data-sets/govsstatefin.html
*/


/* TROUBLESHOOTING NOTES
I'm pulling households from here:
https://www.census.gov/quickfacts/fact/table/CO/PST045225 

Debt comes from here:
https://api.census.gov/data/timeseries/govsstatefin?get=NAME,AMOUNT&for=state:*&YEAR=2024 
Which is explained here where there's no mention of it being in thousands.
https://www.census.gov/data/developers/data-sets/govsstatefin.html

CO: $44,758,826 / 2,374,218 = $18.85
WI: $51,825,964 / 2,479,480 = $20.90
CA: $477,856,522 / 13,548,091 = $35.27
FL: $142,089,291 / 8,752,810 = $16.23
API returns millions,but should be billions...

AI:GEMINI
State	Total State Debt (Liabilities)	Number of Households	Debt per Household
California	$497.00 Billion	13,548,100	$36,684
Colorado	$43.12 Billion	2,374,220	$18,162
Wisconsin	$32.06 Billion	2,479,480	$12,930
Florida	$74.20 Billion	8,752,810	$8,477

AI:GROK
StateTotal Debt ($$   )HouseholdsDebt per Household (   $$)
Colorado 28,669,197,000  2,374,220  12,076.00
Wisconsin 25,105,675,000  2,479,480  10,126.00
California 540,936,861,000  13,548,100  39,925.00
Florida 84,708,190,000  8,752,810  9,677.00

AI:CLAUDE
State  Total State Debt (FY2023)  Households (ACS 2023)  Debt per Household
California  $497.0B  13,614,000  $36,508
Florida  ~$71.8B¹  8,834,000  ~$8,126
Colorado  $43.1B  2,268,000  $19,009
Wisconsin  ~$27.7B²  2,363,000  ~$11,720

*/
function fi_api_census_households(string $gov = 'US'): int|false {
    /*
    $cacheKey = 'api/census_households_' . strtoupper($gov);
    $cached = fi_cache($cacheKey);
    if($cached !== false){
        return $cached;
    }
    */

    if($gov == 'US'){
        return 134790000;

        /*
        //Census API endpoint for household data
        //Web: https://data.census.gov/table/ACSDT1Y2024.B09019?q=Households+by+Type&g=010XX00US
        $url = 'https://api.census.gov/data/2024/acs/acs1?get=group(B09019)&ucgid=0100000US';
        $data = fi_api_fetch($url);

        //B09019_003EA = Householder (132,737,146 @ 2024)
        if (isset($data['data'][1][8])) {
            return (int) $data['data'][1][8];
        } else {
            return false;
        }
        */
    }else{
        // https://www.census.gov/quickfacts/fact/table/CO/PST045225 ...24 for 2024
        // https://www.census.gov/quickfacts/fact/csv/CO/PST045225
        // Column 0 = Fact, Column 2 = State value (e.g. "2,374,218")
        $gov = strtoupper($gov);
        $csv_url = 'https://www.census.gov/quickfacts/fact/csv/' . $gov . '/PST045225';
        $res = wp_remote_get($csv_url, ['timeout' => 10, 'redirection' => 3]);
        if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) !== 200) {
            return false;
        }
        $body = wp_remote_retrieve_body($res);
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $cols = str_getcsv($line);
            // Exact match avoids "Households with a computer..." rows
            if (isset($cols[0], $cols[2])) {
                $fact = trim($cols[0]);
                $value = trim($cols[2]);
                if(substr($fact, 0, 12) !== 'Households, ') continue;
                $households = (int) str_replace(',', '', $value);
                if ($households > 0) {
                    return $households;
                }
            }
        }
        return false;
    }
}



/* treasury.gov API
Example: https://api.fiscaldata.treasury.gov/services/api/fiscal_service/v2/accounting/od/debt_to_penny?fields=record_date,tot_pub_debt_out_amt&sort=-record_date&format=json&page[number]=1&page[size]=1
Payload: {"data":[{"record_date":"2026-03-06","debt_held_public_amt":"31280012029379.25","intragov_hold_amt":"7589806220089.13","tot_pub_debt_out_amt":"38869818249468.38","src_line_nbr":"1","record_fiscal_year":"2026","record_fiscal_quarter":"2","record_calendar_year":"2026","record_calendar_quarter":"1","record_calendar_month":"03","record_calendar_day":"06"}],"meta":{"count":1,"labels":{"record_date":"Record Date","debt_held_public_amt":"Debt Held by the Public","intragov_hold_amt":"Intragovernmental Holdings","tot_pub_debt_out_amt":"Total Public Debt Outstanding","src_line_nbr":"Source Line Number","record_fiscal_year":"Fiscal Year","record_fiscal_quarter":"Fiscal Quarter Number","record_calendar_year":"Calendar Year","record_calendar_quarter":"Calendar Quarter Number","record_calendar_month":"Calendar Month Number","record_calendar_day":"Calendar Day Number"},"dataTypes":{"record_date":"DATE","debt_held_public_amt":"CURRENCY","intragov_hold_amt":"CURRENCY","tot_pub_debt_out_amt":"CURRENCY","src_line_nbr":"INTEGER","record_fiscal_year":"YEAR","record_fiscal_quarter":"QUARTER","record_calendar_year":"YEAR","record_calendar_quarter":"QUARTER","record_calendar_month":"MONTH","record_calendar_day":"DAY"},"dataFormats":{"record_date":"YYYY-MM-DD","debt_held_public_amt":"$10.20","intragov_hold_amt":"$10.20","tot_pub_debt_out_amt":"$10.20","src_line_nbr":"10","record_fiscal_year":"YYYY","record_fiscal_quarter":"Q","record_calendar_year":"YYYY","record_calendar_quarter":"Q","record_calendar_month":"MM","record_calendar_day":"DD"},"total-count":8259,"total-pages":8259},"links":{"self":"&page%5Bnumber%5D=1&page%5Bsize%5D=1","first":"&page%5Bnumber%5D=1&page%5Bsize%5D=1","prev":null,"next":"&page%5Bnumber%5D=2&page%5Bsize%5D=1","last":"&page%5Bnumber%5D=8259&page%5Bsize%5D=1"}}
Total Public Debt Outstanding: Get and format the latest debt data from Treasury.gov API
*/
function fi_api_treasurygov_debt_now(): array|false {
    $cacheKey = 'api/us_gov_debt';
    $data = fi_cache($cacheKey,'',1,true);
    if($data){return $data;}

    //Fetch the latest debt data from the Treasury.gov API
    $url = 'https://api.fiscaldata.treasury.gov/services/api/fiscal_service/v2/accounting/od/debt_to_penny?fields=record_date,tot_pub_debt_out_amt&sort=-record_date&format=json&page[number]=1&page[size]=1';
    $data_raw = fi_api_fetch($url);
    if(isset($data_raw['data'][0]['tot_pub_debt_out_amt'])){
        $debt_amount = $data_raw['data'][0]['tot_pub_debt_out_amt'];
        $debt_date = $data_raw['data'][0]['record_date'];
        $data = [
            'amount' => $debt_amount,
            'date' => $debt_date,
        ];
        fi_cache($cacheKey, $data);
        return $data;
    } else {
        return false; //'<div class="treasury-debt-fail">Unable to retrieve debt data.</div>';
    }
}


function fi_api_census_state_debt($state_name): string|int|float {
    $cacheKey = 'api/census_state_debt_'.$state_name;
	$debt_amount = fi_cache($cacheKey,'',7);
    if($debt_amount){
		return $debt_amount;
	}

    //Census API endpoint for state debt data - falls back to prior year inside helper
    $data = fi_api_census_state_debt_csv();

	//Return matching state from API
	if( is_array($data) && isset($data[0]) ){
		//fi_log('fi_api_census_state_debt: API data good: '.$state_name, __FILE__, __LINE__);
		if($state_name){
			//Reorganize data by state
			foreach($data as $row){
				$name = $row[0];
				if($name == $state_name){
					$debt_amount = $row[1] * 1000; //Convert from thousands to dollars
					fi_cache($cacheKey, $debt_amount,7,true);
					return $debt_amount;
				}
			}
		}
	}else{
		//fi_log('fi_api_census_state_debt: API data fail', __FILE__, __LINE__);
	}
    return '';
}

//Fetch state debt array from Census API, falling back one year if needed
function fi_api_census_state_debt_csv(): array|false {
	//avoid excessive API calls by caching for 1 week
	$cacheKey = 'api/census_state_debt_csv';
	$data = fi_cache($cacheKey,'',7,true);
	//I manually fetched the data and saved as the cache file but it may not be the correct format
	if(!is_array($data)){
		//fix and re-cache if needed
		//$data = json_decode(file_get_contents(FI_DIR_CACHE . $cacheKey), true);
		$data = json_decode($data, true);
		if(is_array($data)){
			fi_cache($cacheKey, $data, 7,true);
		}
	}
	if (is_array($data) && isset($data[0])) {
		//fi_log('census_state_debt_csv: cache data good', __FILE__, __LINE__);
		return $data;
	}else{
		fi_log('census_state_debt_csv: cache fail: API HIT', __FILE__, __LINE__);		
	}

    $base_url = 'https://api.census.gov/data/timeseries/govsstatefin?get=NAME,AMOUNT&for=state:*&YEAR=';
    foreach ([date('Y') - 1, date('Y') - 2] as $year) {
        $res = wp_remote_get($base_url . $year, ['timeout' => 10, 'redirection' => 3]);
        if (is_wp_error($res)) {
            fi_log('fi_api_census_state_debt_csv ['.$year.']: ' . $res->get_error_message(), __FILE__, __LINE__);
            continue;
        }
        if ((int) wp_remote_retrieve_response_code($res) !== 200) {
            fi_log('fi_api_census_state_debt_csv ['.$year.']: HTTP ' . wp_remote_retrieve_response_code($res) . ' ' . wp_remote_retrieve_response_message($res), __FILE__, __LINE__);
            continue;
        }
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (is_array($data) && isset($data[0])) {
			fi_cache($cacheKey, $data, 7,true);
            return $data;
        }
    }
	return false;
}


function fi_api_gov_debt($gov): string|int|float {
    if($gov == 'US'){
        $debt_data = fi_api_treasurygov_debt_now();
        if($debt_data){
            return $debt_data['amount'];
        }else{
            return '';
        }
    }else{
        $gov_name = FI_GOVERNMENTS[$gov] ?? null;
        if(!$gov_name){return '';}
        return fi_api_census_state_debt($gov_name);
    }
}


function fi_debt_clock($args = []): string|int|false {
    $defaults = [
        'gov' => 'US',
        'view' => 'household',
        'format' => 'stacked', // 'stacked' or 'inline'
    ];
    $args = array_merge($defaults, $args);

    $gov_name = FI_GOVERNMENTS[$args['gov']] ?? null;

	//if($args['gov'] == 'CA'){fi_log('fi_debt_clock '.$args['gov'].': ' . $gov_name . ' | ' . $args['view'] . ' | ' . $args['format'],__FILE__,__LINE__);}

	if(!$gov_name){return '';}

    $args['gov_name'] = $gov_name;
    if($args['gov'] == 'US'){
        $args['gov_name'] = 'U.S.';
    }

    $cacheKey = 'html/debt_clock_'.$args['gov'].'-'.$args['view'].'-'.$args['format'];
    $debt_html = ''; //TEST fi_cache($cacheKey);
	if($debt_html){return $debt_html;}
    //Get the latest debt data
    if($args['gov'] == 'US'){
        $debt_data = fi_api_treasurygov_debt_now();
        if(!$debt_data){return '';}

        $zone = 'U.S.';
        $zone_text = 'U.S. National';

        //Format the debt amount as currency
        $debt_amount = $debt_data['amount'];
        $debt_formatted = '$' . number_format($debt_amount, 0);

        //Format the date
        $debt_date = $debt_data['date'];
        $date_formatted = date('F j, Y', strtotime($debt_date)); // Example: March 6, 2026
        //$date_formatted = date('m/d/Y', strtotime($debt_date)); // Example: 3/6/2026

    }else{
if($args['gov'] == 'CA'){fi_log('fi_debt_clock '.$args['gov_name'].': before state debt API call',__FILE__,__LINE__);}
		$debt_amount = fi_api_census_state_debt($args['gov_name']);
        $zone = $args['gov_name'];
        $zone_text = 'Estimated ' . $args['gov_name'] . ' State';
if($args['gov'] == 'CA'){fi_log('fi_debt_clock CA: Debt amount: ' . $debt_amount,__FILE__,__LINE__);}
		if(!$debt_amount){return '';}
        $date_formatted = ''; //No date available for state debt. Use whatever recent is return thus 'estimated'.
    }

    //Create the HTML output from source agnostic data
    if($debt_amount){
        switch($args['view']):
            case 'household':
                $household_count = fi_api_census_households($args['gov']);
                if($household_count){
                    $debt_per_household = round($debt_amount / $household_count, 2);
                    //Show cents for state debt. National debt > 250K.
                    if($debt_per_household < 100000){
                        $debt_per_household_formatted = '$' . number_format($debt_per_household, 2);
                    }else{
                        $debt_per_household_formatted = '$' . number_format($debt_per_household, 0);
                    }
                    if($args['format'] == 'stacked'){
                        $debt_html = '<div class="text-danger treasury-debt-amount">' . $debt_per_household_formatted . '</div>';
                        $debt_html .= '<div class="treasury-debt-date">'.$zone_text.' Debt Per Household';
                        if($date_formatted){
                            $debt_html .= ' as of ' . $date_formatted;
                        }
                        $debt_html .= '</div>';
                    }else{
                        $debt_html = '<div class="treasury-debt-inline">'.$zone_text.' Debt Per Household';
                        if($date_formatted){
                            $debt_html .= ' as of ' . $date_formatted;
                        }
                        $debt_html .= ': <span class="text-danger treasury-debt-amount">' . $debt_per_household_formatted . '</span></div>';
                    }
                    // $debt_html .= '<!-- '.$debt_amount.'|'.$household_count.' -->';
                }else{
                    return false; //'<div class="treasury-debt-fail">Unable to retrieve household data.</div>';
                }
                break;
            case 'raw':
                $debt_html =  $debt_amount;
                break;
            default:
                if($args['format'] == 'stacked'){
                    $debt_html = '<div class="text-danger treasury-debt-amount">' . $debt_formatted . '</div>';
                    $debt_html .= '<div class="treasury-debt-date">'.$zone_text.' Debt';
                    if($date_formatted){
                        $debt_html .= ' as of ' . $date_formatted;
                    }
                    $debt_html .= '</div>';
                }else{
                    $debt_html = '<div class="treasury-debt-inline">'.$zone_text.' Debt';
                    if($date_formatted){
                        $debt_html .= ' as of ' . $date_formatted;
                    }
                    $debt_html .= ': <span class="text-danger treasury-debt-amount">' . $debt_formatted . '</span></div>';
                }
                break;
        endswitch;

        //TESTING fi_cache($cacheKey,$debt_html);
        return $debt_html;
    }else{
        return false;
    }
}