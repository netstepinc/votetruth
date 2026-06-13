<?php
/**
 * Freedom Index FluentCRM Integration Helpers
 *
 * Straight function version of the former FICore\Fluent class file.
 *
 * Centralizes FluentCRM references and provides safe helper functions for
 * subscribers, tags, and WordPress user integrations.
 */

if (!defined('ABSPATH')) exit;

/**
 * Check if FluentCRM model classes are available.
 *
 * Important: FluentCRM classes must be checked with class_exists(), not function_exists().
 *
 * @return bool True if the required FluentCRM classes are available.
 */
function fi_fluent_is_active(): bool {
	return class_exists('\FluentCrm\App\Models\Subscriber')
		&& class_exists('\FluentCrm\App\Models\Tag');
}

/**
 * Log FluentCRM integration errors.
 *
 * @param string $message Error message.
 * @param string $file File path.
 * @param int $line Line number.
 * @return void
 */
function fi_fluent_log_error(string $message, string $file = '', int $line = 0): void {
	if (function_exists('fi_log')) {
		fi_log('[FluentCRM] ' . $message, $file, $line, 'error');
	}
}

/**
 * Normalize tag input into a clean array.
 *
 * @param string|array $tags Tag slug(s).
 * @return array Sanitized tag slugs.
 */
function fi_fluent_normalize_tags($tags): array {
	$tags = is_array($tags) ? $tags : [$tags];

	return array_values(array_filter(array_map(static function($tag) {
		return sanitize_text_field((string) $tag);
	}, $tags)));
}

/**
 * Get a WordPress user's email address.
 *
 * @param int $user_id WordPress user ID.
 * @return string User email or empty string.
 */
function fi_fluent_get_user_email(int $user_id): string {
	$user = get_userdata($user_id);

	return ($user && !empty($user->user_email)) ? (string) $user->user_email : '';
}

/**
 * Get FluentCRM subscriber by email.
 *
 * @param string $email Subscriber email.
 * @return mixed FluentCRM Subscriber model or null.
 */
function fi_fluent_get_subscriber(string $email) {
	if (!fi_fluent_is_active()) {
		return null;
	}

	$email = sanitize_email($email);
	if ($email === '') {
		return null;
	}

	try {
		return \FluentCrm\App\Models\Subscriber::where('email', $email)->first();
	} catch (\Throwable $e) {
		fi_fluent_log_error('Error getting subscriber: ' . $e->getMessage(), __FILE__, __LINE__);
		return null;
	}
}

/**
 * Get FluentCRM subscriber by WordPress user ID.
 *
 * @param int $user_id WordPress user ID.
 * @return mixed FluentCRM Subscriber model or null.
 */
function fi_fluent_get_subscriber_by_user_id(int $user_id) {
	$email = fi_fluent_get_user_email($user_id);

	return $email !== '' ? fi_fluent_get_subscriber($email) : null;
}

/**
 * Add FluentCRM tag(s) to subscriber.
 *
 * @param string $email Subscriber email.
 * @param string|array $tags Tag slug(s) to add.
 * @return bool Success.
 */
function fi_fluent_add_tag(string $email, $tags): bool {
	$subscriber = fi_fluent_get_subscriber($email);
	if (!$subscriber) {
		return false;
	}

	$tags = fi_fluent_normalize_tags($tags);
	if (empty($tags)) {
		return false;
	}

	try {
		$subscriber->attachTags($tags);
		return true;
	} catch (\Throwable $e) {
		fi_fluent_log_error('Error adding tag: ' . $e->getMessage(), __FILE__, __LINE__);
		return false;
	}
}

/**
 * Remove FluentCRM tag(s) from subscriber.
 *
 * @param string $email Subscriber email.
 * @param string|array $tags Tag slug(s) to remove.
 * @return bool Success.
 */
function fi_fluent_remove_tag(string $email, $tags): bool {
	$subscriber = fi_fluent_get_subscriber($email);
	if (!$subscriber) {
		return false;
	}

	$tags = fi_fluent_normalize_tags($tags);
	if (empty($tags)) {
		return false;
	}

	try {
		$subscriber->detachTags($tags);
		return true;
	} catch (\Throwable $e) {
		fi_fluent_log_error('Error removing tag: ' . $e->getMessage(), __FILE__, __LINE__);
		return false;
	}
}

/**
 * Check if subscriber has FluentCRM tag.
 *
 * @param string $email Subscriber email.
 * @param string $tag Tag slug.
 * @return bool
 */
function fi_fluent_has_tag(string $email, string $tag): bool {
	$subscriber = fi_fluent_get_subscriber($email);
	if (!$subscriber) {
		return false;
	}

	$tag = sanitize_text_field($tag);
	if ($tag === '') {
		return false;
	}

	try {
		return (bool) $subscriber->hasTag($tag);
	} catch (\Throwable $e) {
		return false;
	}
}

/**
 * Get FluentCRM tag subscriber count.
 *
 * @param string $tag_slug Tag slug.
 * @return int Count of subscribers with this tag.
 */
function fi_fluent_get_tag_count(string $tag_slug): int {
	if (!fi_fluent_is_active()) {
		return 0;
	}

	$tag_slug = sanitize_text_field($tag_slug);
	if ($tag_slug === '') {
		return 0;
	}

	try {
		$tag = \FluentCrm\App\Models\Tag::where('slug', $tag_slug)->first();
		return $tag && isset($tag->subscribers_count) ? (int) $tag->subscribers_count : 0;
	} catch (\Throwable $e) {
		return 0;
	}
}

/**
 * Add FluentCRM tag(s) to WordPress user.
 *
 * @param int $user_id WordPress user ID.
 * @param string|array $tags Tag slug(s) to add.
 * @return bool Success.
 */
function fi_fluent_add_tag_to_user(int $user_id, $tags): bool {
	$email = fi_fluent_get_user_email($user_id);

	return $email !== '' ? fi_fluent_add_tag($email, $tags) : false;
}

/**
 * Remove FluentCRM tag(s) from WordPress user.
 *
 * @param int $user_id WordPress user ID.
 * @param string|array $tags Tag slug(s) to remove.
 * @return bool Success.
 */
function fi_fluent_remove_tag_from_user(int $user_id, $tags): bool {
	$email = fi_fluent_get_user_email($user_id);

	return $email !== '' ? fi_fluent_remove_tag($email, $tags) : false;
}

/**
 * Check if WordPress user has FluentCRM tag.
 *
 * @param int $user_id WordPress user ID.
 * @param string $tag Tag slug.
 * @return bool
 */
function fi_fluent_user_has_tag(int $user_id, string $tag): bool {
	$email = fi_fluent_get_user_email($user_id);

	return $email !== '' ? fi_fluent_has_tag($email, $tag) : false;
}