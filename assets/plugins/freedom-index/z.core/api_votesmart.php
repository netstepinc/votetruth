<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
VoteSmart API (Project VoteSmart)

Covers state + Congress + has photos.
Provides votesmart_id, plus issues positions, committees, etc.

Pros
Has data for Congress and state legislators.
Includes photo URLs.
IDs map nicely to OpenStates and Ballotpedia.

Cons
Not fully free: You must “apply” for an API key, and they historically restrict high-volume users.
API is clunky and documentation isn’t great.
Cost: Usually free for non-commercial / research use, low cost for commercial depending on usage.
API Docs: https://votesmart.org/share/api
For your FI project: This is the bridge to correlate state + Congress to Votesmart_ID.
TODO: Look up votesmart_id in VoteSmart API => Return: More detailed bio, photos, offices held
*/