<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
GovTrack API (Congress)
Best free source for Bioguide + images + name searches for Congress.

Pros
Provides bioguide_id, GovTrack ID, ICPSR, FEC IDs, Wikipedia slug, etc.
Allows search by name.
Offers official photos (free to use).
Extremely stable and 100% free.

Cons
Congress only, no state legislators.
Does not include Votesmart or Ballotpedia IDs.
API Docs: https://www.govtrack.us/developers/api
For your FI project: This is your authoritative Bioguide source.

TODO: Search name in GovTrack => Return: bioguide, govtrack_id, wikipedia, photo
*/