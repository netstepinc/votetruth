# Import production data into local dev

Updated production DB with new values.
ALTER TABLE `jbsw_5_fi_legislators` ADD `session_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `image_url`, ADD `gov` VARCHAR(2) NULL DEFAULT NULL AFTER `session_id`, ADD `state` VARCHAR(2) NULL DEFAULT NULL AFTER `gov`, ADD `chamber` ENUM('S','H') NULL DEFAULT NULL AFTER `state`, ADD `district` VARCHAR(32) NULL DEFAULT NULL AFTER `chamber`, ADD `party` VARCHAR(64) NULL DEFAULT NULL AFTER `district`;


cd /var/www/html/votetruth



DROP TABLE `jbsw_5_fi_legacy_redirects`, `jbsw_5_fi_legislators`, `jbsw_5_fi_legislator_sessions`, `jbsw_5_fi_log`, `jbsw_5_fi_reports`, `jbsw_5_fi_sessions`, `jbsw_5_fi_taxonomy`, `jbsw_5_fi_user_lists`, `jbsw_5_fi_voterc`, `jbsw_5_fi_votes`, `jbsw_5_fi_vote_tags`;

mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_sessions.sql
mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_taxonomy.sql
mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_legislators.sql
mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_legislator_sessions.sql
mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_votes.sql
mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_voterc.sql
mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_vote_tags.sql
mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_reports.sql
mysql -u root -p9MMGlock19  vttrth_scorecard  < /home/sbmitt/Dropbox/WEB.JBS/jbs.org/FreedomIndex.us/VoteData/jbsw_5_fi_user_lists.sql

DROP TABLE `vtus_fi_legacy_redirects`, `vtus_fi_legislators`, `vtus_fi_legislator_sessions`, `vtus_fi_reports`, `vtus_fi_sessions`, `vtus_fi_taxonomy`, `vtus_fi_user_lists`, `vtus_fi_voterc`, `vtus_fi_votes`, `vtus_fi_vote_tags`;



# Activation
- Export local DB
- Purge production DB
- Import to production DB

UPDATE `vtus_options` set option_value = 'https://votetruth.us' where option_name = 'siteurl';
UPDATE `vtus_options` set option_value = 'https://votetruth.us' where option_name = 'home';