<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/*
OpenStates API
Best free/cheap option for state legislators by name.

Pros
Has OpenStates ID, Ballotpedia ID, Votesmart ID, some Facebook/Twitter, etc.
Allows search by name, state, district, chamber.
Includes photo/headshot URLs for most current state legislators.
Very stable and widely used.

Cons
State-level only, no congressional legislators.
Bioguide is not included because that’s congressional-only.
Cost: Free tier: 10,000 requests/month, paid tiers inexpensive.
API Docs: https://docs.openstates.org/api-v3/
For your FI project: This is the source for ID correlation for state legislators.

TODO: Search name+state in OpenStates => Return: openstates_id, votesmart_id, ballotpedia_id, photo_url

VALID EXAMPLE:
curl -X 'GET' \
  'https://v3.openstates.org/people?jurisdiction=tx&name=Cole%20Hefner&page=1&per_page=10&apikey=cbfc01f3-aa7f-4c13-b209-9975f0263592' \
  -H 'accept: application/json'

VALID URL:
https://v3.openstates.org/people?jurisdiction=tx&name=Cole%20Hefner&page=1&per_page=10&apikey=cbfc01f3-aa7f-4c13-b209-9975f0263592

VALID RESPONSE:
{
  "results": [
    {
      "id": "ocd-person/43bbf34a-6bc4-41f9-9bdc-3e944c5b8495",
      "name": "Cole Hefner",
      "party": "Republican",
      "current_role": {
        "title": "Representative",
        "org_classification": "lower",
        "district": "5",
        "division_id": "ocd-division/country:us/state:tx/sldl:5"
      },
      "jurisdiction": {
        "id": "ocd-jurisdiction/country:us/state:tx/government",
        "name": "Texas",
        "classification": "state"
      },
      "given_name": "Cole",
      "family_name": "Hefner",
      "image": "https://house.texas.gov/members/photos/3505.jpg?v=88.25",
      "email": "cole.hefner@house.texas.gov",
      "gender": "Male",
      "birth_date": "1980-11-13",
      "death_date": "",
      "extras": {},
      "created_at": "2018-10-18T16:12:35.494237+00:00",
      "updated_at": "2025-07-18T02:25:06.774769+00:00",
      "openstates_url": "https://openstates.org/person/cole-hefner-23oNWdxwJrmSNzkK50DVo5/"
    }
  ],
  "pagination": {
    "per_page": 10,
    "page": 1,
    "max_page": 1,
    "total_items": 4
  }
}

DATA ASSESSMENT:
- image: Image URL is invalid. Do not use.
- email: Meta > Contact Information > Email
- gender: Meta > Biography > Gender
- birth_date: Meta > Biography > Birth Date
- death_date: Meta > Biography > Death Date
- openstates_url: Meta > url_openstates > https://openstates.org/person/cole-hefner-23oNWdxwJrmSNzkK50DVo5/

PROBLEMS:
- The openstates "ID" is a random string. We'll need to search by name and state to fetch this data.
- Free tier is limited to 500 requests per day so we only check this from the admin on command.
- If the fields we plan to use are already populated, we don't need to fetch this data.
- This is for state legislators only. We do not need to fetch this data for congressional legislators.
*/