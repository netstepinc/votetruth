V1/V2 Data to import
Taxonomies
- session/congress(v1) => session
- party
- fi_vote_group => tag
- state => gov (static array)
- district

Posts
- legislator
	- post_title
	- post_name [V1=bioguide_id, V2=slug]
	- post_content
	- post_excerpt
	- post_status
	- post_date
	- post_date_gmt
	- post_modified
	- post_modified_gmt
	- meta
		- legislator_chamber(v1) => role [rep, sen]
		- legislator_url => website
		- legislator_url_detail(v2) => url_profile
		- legislator_email(v2) => email
		- legislator_phone(v2) => phone
		- legislator_hometown(v2) => hometown
		- legislator_office(v2) => address_capitol
		- legislator_local(v2) => address_local
		- legislator_local2(v2) => address_local2
		- legislator_local3(v2) => address_local3
		- legislator_local4(v2) => address_local4
		- legislator_local5(v2) => address_local5
		- legislator_local6(v2) => address_local6
		- legislator_status => status
		- legislator_date_start => date_start
		- legislator_date_end => date_end
		- legislator_legislator_automated (V1: external source data packages like bioguide.congress.gov)
			legislator_govid
			legislator_lastname
			legislator_firstname
			legislator_chamber
			legislator_district
			legislator_imgsrc
			legislator_website
			legislator_phone
			legislator_website
			legislator_status
			legislator_date_start
			legislator_date_end
			legislator_state
			legislator_party
			legislator_state_rank
			legislator_prior
			legislator_formal-name
			legislator_namelist
			legislator_sortname
			legislator_suffix
			legislator_courtesy
			legislator_townname
			legislator_address
			legislator_office-building
			legislator_office-room
			legislator_office-zip
			legislator_office-zip-suffix
			legislator_chamber
			legislator_date-elected
			legislator_date-sworn
			legislator_committee
			legislator_subcommittee
			legislator_sen_class
			legislator_lisid
			legislator_fi_uid
		- legislator_legislator_meta (V2)
			legislator_govid
			legislator_lisid
			legislator_fi_uid
			legislator_state_rank
			legislator_sen_class
			legislator_lastname
			legislator_firstname
			legislator_district
			legislator_phone
			legislator_email
			legislator_prior
			legislator_name
			legislator_url_detail
			legislator_url_website
			legislator_caucus
			legislator_townname
			legislator_namelist
			legislator_sortname
			legislator_suffix
			legislator_courtesy
			legislator_address
			legislator_office
			legislator_office_building
			legislator_office_room
			legislator_office_mailing
			legislator_office_city
			legislator_office_state
			legislator_office_zip
			legislator_office-zip-suffix
			legislator_local2
			legislator_local3
			legislator_local4
			legislator_local5
			legislator_local6
			legislator_date_elected
			legislator_date_sworn
			legislator_committee
			legislator_subcommittee
			legislator_role
			legislator_status';
			legislator_date_start
			legislator_date_end
			legislator_lifescore
			legislator_score_life
			legislator_photo

- fi_vote => vote
	- post_title
	- post_name
	- post_content
	- post_excerpt
	- post_status
	- post_date
	- post_date_gmt
	- post_modified
	- post_modified_gmt
	- meta
		- vote_chamber [House, Senate] => chamber
		- vote_good [Y, N] => good
		- vote_date => vote_date
		- vote_number => vote_number
		- vote_rollcall_number => rollcall_number
		- vote_subtitle => subtitle
		- vote_url => url
		- vote_url_rollcall => url_rollcall
		- vote_cost => cost
		- vote_text_scorecard => text_scorecard
		- vote_text_scorecard_more => text_scorecard_more
		- vote_text_freedomindex => text_freedomindex
		- vote_text_rollcall => rollcall_data (JSON array of rollcall data) 
			JSON array with either bioguide_id or legiscan_id and their vote.
			Requires: vote_id, legislator_id, vote

- fi_report => report
	- post_title
	- post_name
	- post_content
	- post_excerpt
	- post_status
	- post_date
	- post_date_gmt
	- post_modified
	- post_modified_gmt
	- meta
		- report_format [scorecard, freedomindex] => format
		- report_cph [show, hide] => cph
		- report_vote_start [1, 11, 21, etc.] => vote_start
		- report_contact [back, front] => contact_info_location
		- report_constitution_qr [none, front, back] => constitution_qr_location
		- report_img_subscribe => subscribe_promo_image
		- report_fi_vote_paging [2,3,3,2] => fi_vote_paging
		- report_votes_rep => votes_rep (list of votes to include in the report in the "House" section)
		- report_votes_sen => votes_sen (list of votes to include in the report in the "Senate" section)
 

=====
Import Strategy
1. Sessions
2. Parties
3. Vote Groups
4. Districts
5. Legislators
6. Votes
7. Roll-calls: vote meta expanded to build fi_voterc table
8. Reports
9. Legislator Sessions - term_relationships => fi_legislator_sessions table
10. Legislator Images
11. Calculate scores for each legislator for each session
