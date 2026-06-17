<?php

namespace FI\Core {

	if (!defined('ABSPATH')) exit;

	/**
	* FluentCRM Integration Helper Class
	* 
	* Consolidates all FluentCRM references and provides helper functions
	* for managing tags, subscribers, and push notifications.
	* 
	* @author Sam Mittelstaedt <smittelstaedt@jbs.org>
	*/
	final class Fluent {
		
		/**
		* Check if FluentCRM is active
		*/
		public static function is_active(): bool {
			return function_exists('FluentCrm\App\Models\Subscriber');
		}
		
		/**
		* Get subscriber by email
		* 
		* @param string $email Subscriber email
		* @return \FluentCrm\App\Models\Subscriber|null
		*/
		public static function get_subscriber(string $email) {
			if (!self::is_active()) {
				return null;
			}
			
			try {
				return \FluentCrm\App\Models\Subscriber::where('email', $email)->first();
			} catch (\Exception $e) {
				fi_log('[FluentCRM] Error getting subscriber: ' . $e->getMessage(), __FILE__, __LINE__, 'error');
				return null;
			}
		}
		
		/**
		* Get subscriber by WordPress user ID
		* 
		* @param int $user_id WordPress user ID
		* @return \FluentCrm\App\Models\Subscriber|null
		*/
		public static function get_subscriber_by_user_id(int $user_id) {
			$user = get_userdata($user_id);
			if (!$user || empty($user->user_email)) {
				return null;
			}
			
			return self::get_subscriber($user->user_email);
		}
		
		/**
		* Add tag to subscriber
		* 
		* @param string $email Subscriber email
		* @param string|array $tags Tag slug(s) to add
		* @return bool Success
		*/
		public static function add_tag(string $email, $tags): bool {
			$subscriber = self::get_subscriber($email);
			if (!$subscriber) {
				return false;
			}
			
			$tags = is_array($tags) ? $tags : [$tags];
			$tags = array_filter(array_map('sanitize_text_field', $tags));
			
			if (empty($tags)) {
				return false;
			}
			
			try {
				$subscriber->attachTags($tags);
				return true;
			} catch (\Exception $e) {
				fi_log('[FluentCRM] Error adding tag: ' . $e->getMessage(), __FILE__, __LINE__, 'error');
				return false;
			}
		}
		
		/**
		* Remove tag from subscriber
		* 
		* @param string $email Subscriber email
		* @param string|array $tags Tag slug(s) to remove
		* @return bool Success
		*/
		public static function remove_tag(string $email, $tags): bool {
			$subscriber = self::get_subscriber($email);
			if (!$subscriber) {
				return false;
			}
			
			$tags = is_array($tags) ? $tags : [$tags];
			$tags = array_filter(array_map('sanitize_text_field', $tags));
			
			if (empty($tags)) {
				return false;
			}
			
			try {
				$subscriber->detachTags($tags);
				return true;
			} catch (\Exception $e) {
				fi_log('[FluentCRM] Error removing tag: ' . $e->getMessage(), __FILE__, __LINE__, 'error');
				return false;
			}
		}
		
		/**
		* Check if subscriber has tag
		* 
		* @param string $email Subscriber email
		* @param string $tag Tag slug
		* @return bool
		*/
		public static function has_tag(string $email, string $tag): bool {
			$subscriber = self::get_subscriber($email);
			if (!$subscriber) {
				return false;
			}
			
			try {
				return $subscriber->hasTag($tag);
			} catch (\Exception $e) {
				return false;
			}
		}
		
		/**
		* Get tag count
		* 
		* @param string $tag_slug Tag slug
		* @return int Count of subscribers with this tag
		*/
		public static function get_tag_count(string $tag_slug): int {
			if (!self::is_active()) {
				return 0;
			}
			
			try {
				$tag = \FluentCrm\App\Models\Tag::where('slug', $tag_slug)->first();
				return $tag && isset($tag->subscribers_count) ? (int) $tag->subscribers_count : 0;
			} catch (\Exception $e) {
				return 0;
			}
		}
		
		/**
		* Add tag to WordPress user by email
		* 
		* @param int $user_id WordPress user ID
		* @param string|array $tags Tag slug(s) to add
		* @return bool Success
		*/
		public static function add_tag_to_user(int $user_id, $tags): bool {
			$user = get_userdata($user_id);
			if (!$user || empty($user->user_email)) {
				return false;
			}
			
			return self::add_tag($user->user_email, $tags);
		}
		
		/**
		* Remove tag from WordPress user by email
		* 
		* @param int $user_id WordPress user ID
		* @param string|array $tags Tag slug(s) to remove
		* @return bool Success
		*/
		public static function remove_tag_from_user(int $user_id, $tags): bool {
			$user = get_userdata($user_id);
			if (!$user || empty($user->user_email)) {
				return false;
			}
			
			return self::remove_tag($user->user_email, $tags);
		}
		
		/**
		* Check if WordPress user has tag
		* 
		* @param int $user_id WordPress user ID
		* @param string $tag Tag slug
		* @return bool
		*/
		public static function user_has_tag(int $user_id, string $tag): bool {
			$user = get_userdata($user_id);
			if (!$user || empty($user->user_email)) {
				return false;
			}
			
			return self::has_tag($user->user_email, $tag);
		}
	}
}

// Global namespace functions
namespace {
	
	/**
	 * Check if FluentCRM is active
	 */
	function fi_fluent_is_active(): bool {
		return \FI\Core\Fluent::is_active();
	}
	
	/**
	 * Get FluentCRM subscriber by email
	 */
	function fi_fluent_get_subscriber(string $email) {
		return \FI\Core\Fluent::get_subscriber($email);
	}
	
	/**
	 * Get FluentCRM subscriber by WordPress user ID
	 */
	function fi_fluent_get_subscriber_by_user_id(int $user_id) {
		return \FI\Core\Fluent::get_subscriber_by_user_id($user_id);
	}
	
	/**
	 * Add FluentCRM tag to subscriber
	 */
	function fi_fluent_add_tag(string $email, $tags): bool {
		return \FI\Core\Fluent::add_tag($email, $tags);
	}
	
	/**
	 * Remove FluentCRM tag from subscriber
	 */
	function fi_fluent_remove_tag(string $email, $tags): bool {
		return \FI\Core\Fluent::remove_tag($email, $tags);
	}
	
	/**
	 * Check if subscriber has FluentCRM tag
	 */
	function fi_fluent_has_tag(string $email, string $tag): bool {
		return \FI\Core\Fluent::has_tag($email, $tag);
	}
	
	/**
	 * Get FluentCRM tag count
	 */
	function fi_fluent_get_tag_count(string $tag_slug): int {
		return \FI\Core\Fluent::get_tag_count($tag_slug);
	}
	
	/**
	 * Add FluentCRM tag to WordPress user
	 */
	function fi_fluent_add_tag_to_user(int $user_id, $tags): bool {
		return \FI\Core\Fluent::add_tag_to_user($user_id, $tags);
	}
	
	/**
	 * Remove FluentCRM tag from WordPress user
	 */
	function fi_fluent_remove_tag_from_user(int $user_id, $tags): bool {
		return \FI\Core\Fluent::remove_tag_from_user($user_id, $tags);
	}
	
	/**
	 * Check if WordPress user has FluentCRM tag
	 */
	function fi_fluent_user_has_tag(int $user_id, string $tag): bool {
		return \FI\Core\Fluent::user_has_tag($user_id, $tag);
	}
}