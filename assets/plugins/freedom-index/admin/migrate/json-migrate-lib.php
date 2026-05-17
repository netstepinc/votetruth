<?php
namespace FI\Admin\MigrateJson;

if (!defined('ABSPATH')) exit;

use FI\Core\Sessions;
use FI\Core\Legislators;
use FI\Core\ApiIntegration;

/**
 * JSON migration core library.
 *
 * Major stages:
 * - Sessions (fi_sessions)
 * - Taxonomy: districts + tags (fi_taxonomy)
 * - Legislators (fi_legislators) + legislator_sessions (fi_legislator_sessions)
 * - Votes (fi_votes) + vote tags (fi_vote_tags) + rollcalls (fi_voterc) + votes.rollcall_data
 * - Reports (fi_reports) with payload normalization
 */

function fi_migrate_json_export_dir(): string {
	// wp-content/jbsfi/migrate/
	return FI_DIR_CACHE . 'migrate/';
}

function fi_migrate_json_list_exports(): array {
	$dir = fi_migrate_json_export_dir();
	$files = glob($dir . '*-fi-export.json');
	if (!is_array($files)) {
		return [];
	}
	sort($files);
	return $files;
}

function fi_migrate_json_resolve_export_path(string $basename): string {
	$basename = basename($basename);
	if ($basename === '' || strpos($basename, '..') !== false || strpos($basename, '/') !== false || strpos($basename, '\\') !== false) {
		throw new \RuntimeException('Invalid export file name.');
	}

	$path = fi_migrate_json_export_dir() . $basename;
	$real = realpath($path);
	$dir_real = realpath(fi_migrate_json_export_dir());
	if (!$real || !$dir_real || strpos($real, $dir_real) !== 0) {
		throw new \RuntimeException('Export file path is invalid or outside export directory: ' . $path);
	}
	if (!is_file($real)) {
		throw new \RuntimeException('Export file not found: ' . $real);
	}
	return $real;
}

function fi_migrate_json_print(string $line): void {
	echo esc_html($line) . "\n";
	@flush();
}

function fi_migrate_json_print_kv(string $key, $value, int $indent = 2): void {
	$pad = str_repeat(' ', max(0, $indent));
	if (is_array($value) || is_object($value)) {
		$encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		fi_migrate_json_print($pad . $key . ' = ' . ($encoded === false ? '[unencodable]' : $encoded));
		return;
	}
	fi_migrate_json_print($pad . $key . ' = ' . (string) $value);
}

/**
 * Build a deterministic, collision-proof legacy_id key.
 *
 * Summary:
 * - Multisite exports collide on numeric term_id / post ID across sites.
 * - We store legacy_id as "{GOV}-{id}" (e.g. "ND-1256") in all fi_* tables.
 */
function fi_migrate_json_legacy_key(string $gov, int|string $legacy_id): string {
	$g = strtoupper(trim((string) $gov));
	$id_raw = trim((string) $legacy_id);
	$id = preg_replace('/[^0-9]/', '', $id_raw);
	if ($g === '' || $id === '') {
		fi_migrate_json_fail('Invalid legacy key input', ['gov' => $gov, 'legacy_id' => $legacy_id]);
	}
	return $g . '-' . $id;
}

function fi_migrate_json_verbose_enabled(): bool {
	// Summary: always verbose; no UI switches.
	return true;
}

function fi_migrate_json_db_diag(): array {
	global $wpdb;
	return [
		'wpdb_last_error' => $wpdb->last_error ?? null,
		'wpdb_last_query' => $wpdb->last_query ?? null,
	];
}

function fi_migrate_json_fail(string $message, array $context = []): void {
	$diag = fi_migrate_json_db_diag();
	$payload = [
		'message' => $message,
		'context' => $context,
		'db' => $diag,
	];

	// Hard-stop with maximum context (plain text; caller already wraps output in <pre>).
	fi_migrate_json_print('--- MIGRATION STOPPED ---');
	fi_migrate_json_print($message);
	fi_migrate_json_print(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

	throw new \RuntimeException($message);
}

function fi_migrate_json_load(string $file_path): array {
	$raw = file_get_contents($file_path);
	if ($raw === false) {
		fi_migrate_json_fail('Failed to read export file', ['file' => $file_path]);
	}
	$data = json_decode($raw, true);
	if (!is_array($data)) {
		fi_migrate_json_fail('Export JSON could not be decoded', [
			'file' => $file_path,
			'json_error' => json_last_error_msg(),
		]);
	}
	return $data;
}

function fi_migrate_json_session_slug(string $gov, string $legacy_slug): string {
	$legacy_slug = trim($legacy_slug);
	$legacy_slug = $legacy_slug !== '' ? $legacy_slug : 'session';
	// Summary: slugs are unique within gov, not globally.
	return sanitize_title($legacy_slug);
}

function fi_migrate_json_report_slug(string $gov, string $legacy_slug): string {
	$legacy_slug = trim($legacy_slug);
	$legacy_slug = $legacy_slug !== '' ? $legacy_slug : 'report';
	// Summary: slugs are unique within gov, not globally.
	return sanitize_title($legacy_slug);
}

function fi_migrate_json_vote_cast(string $raw): string {
	$raw = strtoupper(trim((string) $raw));
	if ($raw === 'Y') return 'Y';
	if ($raw === 'N') return 'N';
	if ($raw === 'P') return 'P';
	if ($raw === 'X') return 'X';
	// V2 exports sometimes use "--" for absent / not voting.
	if ($raw === '--' || $raw === '' || $raw === '0') return 'X';
	// Some exports use "EX" for excused/absent (treat as no-vote).
	if ($raw === 'EX') return 'X';
	fi_migrate_json_fail('Invalid rollcall cast value', ['raw' => $raw]);
	return 'X';
}

/**
 * Create a placeholder legislator for missing rollcall keys (usually LegiScan people_id).
 *
 * Summary:
 * - Some legacy sites have rollcall data referencing a people_id that was never imported as a legislator post.
 * - We create a deterministic placeholder so rollcalls can import and staff can later fill in missing data.
 */
function fi_migrate_json_get_or_create_placeholder_legislator(string $gov, string $rollcall_key, array &$maps): int {
	$rollcall_key = trim((string) $rollcall_key);
	$pid = absint($rollcall_key);
	if ($pid <= 0) {
		fi_migrate_json_fail('Cannot create placeholder legislator: rollcall key is not a numeric people_id (US rollcalls must be mapped via bioguide_id)', [
			'gov' => $gov,
			'rollcall_key' => $rollcall_key,
		]);
	}

	// If we already created one in this run, reuse it.
	$existing = (int) ($maps['legislator_by_rollcall_key'][(string) $pid] ?? 0);
	if ($existing > 0) {
		return $existing;
	}

	// Try to find an existing record by legiscan_id (idempotent reruns).
	if (class_exists('\\FI\\Core\\Legislators')) {
		$found = \FI\Core\Legislators::get(['legiscan_id' => $pid, 'per_page' => 1, 'limit' => 1]);
		if (is_array($found) && !empty($found[0]) && is_object($found[0])) {
			$id = (int) ($found[0]->id ?? 0);
			if ($id > 0) {
				$maps['legislator_by_rollcall_key'][(string) $pid] = $id;
				return $id;
			}
		}
	}

	$display = 'Missing #' . $pid;
	$meta = [
		'status' => 'draft',
		'placeholder' => true,
		'placeholder_reason' => 'missing_rollcall_legislator',
		'placeholder_rollcall_key' => (string) $pid,
		'placeholder_gov' => strtoupper($gov),
	];

	$new_id = \FI\Core\Legislators::save([
		'first_name' => 'Missing',
		'middle_name' => null,
		'last_name' => (string) $pid,
		'display_name' => $display,
		'legiscan_id' => $pid,
		'meta' => $meta,
	]);

	if (!$new_id) {
		fi_migrate_json_fail('Failed creating placeholder legislator for missing rollcall key', [
			'gov' => $gov,
			'rollcall_key' => $rollcall_key,
			'legiscan_id' => $pid,
		]);
	}

	$maps['legislator_by_rollcall_key'][(string) $pid] = (int) $new_id;

	if (fi_migrate_json_verbose_enabled()) {
		fi_migrate_json_print("ADD  placeholder_legislator: rollcall_key={$pid} -> id={$new_id} display_name=" . $display);
	}

	return (int) $new_id;
}

/**
 * US-only: create a placeholder legislator for missing Bioguide IDs referenced in legacy rollcalls.
 *
 * Summary:
 * - Some very old US rollcalls may include keys for non-voting delegates/territories or other edge cases.
 * - We create a deterministic draft placeholder so rollcalls can import and staff can later reconcile.
 */
function fi_migrate_json_get_or_create_placeholder_legislator_us_bioguide(string $bioguide_id, array &$maps): int {
	$bio = strtoupper(trim((string) $bioguide_id));
	if ($bio === '' || preg_match('/^0+$/', $bio)) {
		fi_migrate_json_fail('Cannot create placeholder legislator: invalid bioguide_id', ['bioguide_id' => $bioguide_id]);
	}

	// If we already created one in this run, reuse it.
	$existing = (int) ($maps['legislator_by_bioguide'][$bio] ?? 0);
	if ($existing > 0) {
		return $existing;
	}

	// Try to find an existing record by bioguide_id (idempotent reruns).
	if (class_exists('\\FI\\Core\\Legislators')) {
		$found = \FI\Core\Legislators::get(['bioguide_id' => $bio, 'per_page' => 1, 'limit' => 1]);
		if (is_array($found) && !empty($found[0]) && is_object($found[0])) {
			$id = (int) ($found[0]->id ?? 0);
			if ($id > 0) {
				$maps['legislator_by_bioguide'][$bio] = $id;
				$maps['legislator_by_legacy_slug'][$bio] = $id; // US post_name convention
				return $id;
			}
		}
	}

	$display = 'Missing ' . $bio;
	$meta = [
		'status' => 'draft',
		'placeholder' => true,
		'placeholder_reason' => 'missing_rollcall_legislator',
		'placeholder_bioguide_id' => $bio,
		'placeholder_gov' => 'US',
	];

	$new_id = \FI\Core\Legislators::save([
		'first_name' => 'Missing',
		'middle_name' => null,
		'last_name' => $bio,
		'display_name' => $display,
		'bioguide_id' => $bio,
		'meta' => $meta,
	]);

	if (!$new_id) {
		fi_migrate_json_fail('Failed creating placeholder legislator (US bioguide_id)', [
			'bioguide_id' => $bio,
		]);
	}

	$maps['legislator_by_bioguide'][$bio] = (int) $new_id;
	$maps['legislator_by_legacy_slug'][$bio] = (int) $new_id;
	if (fi_migrate_json_verbose_enabled()) {
		fi_migrate_json_print("ADD  placeholder_legislator_us: bioguide_id={$bio} -> id={$new_id} display_name=" . $display);
	}
	return (int) $new_id;
}

/**
 * Best-effort repair for legacy JSON strings that contain unescaped double-quotes inside values
 * (e.g., ballotpedia slugs like Thurston_"Smitty"_Smith_(California)).
 *
 * Rule: when inside a JSON string, treat a quote as "closing" only if the next non-whitespace
 * char looks like JSON structure (':', ',', '}', ']'). Otherwise escape it.
 */
function fi_migrate_json_repair_json_quotes(string $json): string {
	$len = strlen($json);
	$out = '';
	$in_string = false;
	$escaped = false;

	for ($i = 0; $i < $len; $i++) {
		$ch = $json[$i];

		if (!$in_string) {
			if ($ch === '"') {
				$in_string = true;
			}
			$out .= $ch;
			continue;
		}

		// In string
		if ($escaped) {
			$escaped = false;
			$out .= $ch;
			continue;
		}

		if ($ch === '\\') {
			$escaped = true;
			$out .= $ch;
			continue;
		}

		if ($ch === '"') {
			// Look ahead for next non-whitespace char
			$j = $i + 1;
			while ($j < $len) {
				$n = $json[$j];
				if ($n !== ' ' && $n !== "\n" && $n !== "\r" && $n !== "\t") {
					break;
				}
				$j++;
			}
			$next = $j < $len ? $json[$j] : '';

			// If this quote is followed by JSON punctuation, it's probably a closing quote.
			if ($next === ':' || $next === ',' || $next === '}' || $next === ']') {
				$in_string = false;
				$out .= '"';
			} else {
				// Embedded quote inside a value; escape it.
				$out .= '\\"';
			}
			continue;
		}

		$out .= $ch;
	}

	return $out;
}

function fi_migrate_json_run_with_output(string $file_path): void {
	// Try to remove time limits; still best-effort in shared hosting.
	@ignore_user_abort(true);
	@set_time_limit(0);

	while (ob_get_level() > 0) {
		@ob_end_flush();
	}
	@ini_set('output_buffering', 'off');
	@ini_set('zlib.output_compression', '0');

	try {
		// Track start time for time-budget decisions (shared hosting max_execution_time).
		$GLOBALS['fi_migrate_json_started_at'] = microtime(true);
		$GLOBALS['fi_migrate_json_skip_images'] = false;

		$data = fi_migrate_json_load($file_path);
		$gov = strtoupper((string) ($data['gov'] ?? ''));
		if ($gov === '') {
			fi_migrate_json_fail('Export missing gov', ['file' => $file_path]);
		}

		fi_migrate_json_print("File: {$file_path}");
		fi_migrate_json_print("Gov: {$gov}");
		fi_migrate_json_print("Generated: " . (($data['generated_at'] ?? '') ?: '(unknown)'));
		fi_migrate_json_print('Verbose: ON');
		fi_migrate_json_print('Rollcalls: OFF (summary only)');
		fi_migrate_json_print('');

		$maps = [
			// term_id => session_id
			'session_by_legacy_id' => [],
			// legacy session slug => session_id
			'session_by_legacy_slug' => [],
			// legacy session slug => legacy term_id (for deterministic junction legacy IDs)
			'legacy_session_term_id_by_slug' => [],
			// district term_id => taxonomy_id
			'district_by_legacy_id' => [],
			// district slug => taxonomy_id
			'district_by_slug' => [],
			// tag term_id => taxonomy_id
			'tag_by_legacy_id' => [],
			// tag slug => taxonomy_id
			'tag_by_slug' => [],
			// legislator legacy post ID => legislator_id
			'legislator_by_legacy_id' => [],
			// legislator legacy "post_name" => legislator_id
			'legislator_by_legacy_slug' => [],
			// US-only: Bioguide ID (uppercase) => legislator_id
			'legislator_by_bioguide' => [],
			// vote legacy post ID => vote_id
			'vote_by_legacy_id' => [],
		];

		fi_migrate_json_import_sessions($gov, $data, $maps);
		fi_migrate_json_import_taxonomy($gov, $data, $maps);
		fi_migrate_json_import_legislators($gov, $data, $maps);
		fi_migrate_json_import_votes($gov, $data, $maps);
		fi_migrate_json_import_reports($gov, $data, $maps);

		fi_migrate_json_print('');
		fi_migrate_json_print('Done.');
	} catch (\Throwable $e) {
		// If we already printed diagnostics via fi_migrate_json_fail(), don't duplicate too much.
		fi_migrate_json_print('');
		fi_migrate_json_print('Exception: ' . $e->getMessage());
		fi_migrate_json_print('File: ' . $e->getFile() . ':' . $e->getLine());
		fi_migrate_json_print('--- Stack ---');
		fi_migrate_json_print($e->getTraceAsString());
	}
}

function fi_migrate_json_import_sessions(string $gov, array $data, array &$maps): void {
	fi_migrate_json_print('== Sessions ==');

	// V2 exports: `session` taxonomy. V1 Congress exports: `congress` taxonomy.
	$terms = $data['taxonomies']['session'] ?? ($data['taxonomies']['congress'] ?? null);
	if (!is_array($terms)) {
		fi_migrate_json_fail('Export missing session taxonomy (expected taxonomies.session or taxonomies.congress)', ['gov' => $gov]);
	}

	// Pass 1: create sessions without parents.
	foreach ($terms as $term_id_str => $term) {
		$legacy_id = absint($term['term_id'] ?? $term_id_str);
		if (!$legacy_id) {
			fi_migrate_json_fail('Invalid session term_id', ['term' => $term]);
		}
		$legacy_key = fi_migrate_json_legacy_key($gov, $legacy_id);

		$existing = fi_migrate_json_db_find_by_legacy_id('fi_sessions', $legacy_key, $gov);
		if ($existing) {
			$maps['session_by_legacy_id'][$legacy_key] = (int) $existing['id'];
			$legacy_slug = (string) ($term['slug'] ?? '');
			if ($legacy_slug !== '') {
				$maps['session_by_legacy_slug'][$legacy_slug] = (int) $existing['id'];
				$maps['legacy_session_term_id_by_slug'][$legacy_slug] = (int) $legacy_id;
			}
			if (fi_migrate_json_verbose_enabled()) {
				fi_migrate_json_print("SKIP session: {$legacy_id} | " . (string) ($term['name'] ?? '') . " | slug={$legacy_slug} -> id=" . (int) $existing['id']);
			}
			continue;
		}

		$name = (string) ($term['name'] ?? '');
		$legacy_slug = (string) ($term['slug'] ?? '');
		if ($name === '' || $legacy_slug === '') {
			fi_migrate_json_fail('Session missing name/slug', ['legacy_id' => $legacy_id, 'term' => $term]);
		}

		$new_slug = fi_migrate_json_session_slug($gov, $legacy_slug);
		$session_id = Sessions::save([
			'legacy_id' => $legacy_key,
			'gov' => $gov,
			'name' => $name,
			'slug' => $new_slug,
			'parent_id' => null,
			'is_current' => 0,
			'meta' => [
				'legacy' => [
					'term_id' => $legacy_id,
					'term_taxonomy_id' => absint($term['term_taxonomy_id'] ?? 0),
					'slug' => $legacy_slug,
					'parent' => absint($term['parent'] ?? 0),
				],
			],
		]);

		if (!$session_id) {
			fi_migrate_json_fail('Failed to create session', ['legacy_id' => $legacy_id, 'term' => $term]);
		}

		$maps['session_by_legacy_id'][$legacy_key] = (int) $session_id;
		$maps['session_by_legacy_slug'][$legacy_slug] = (int) $session_id;
		$maps['legacy_session_term_id_by_slug'][$legacy_slug] = (int) $legacy_id;

		if (fi_migrate_json_verbose_enabled()) {
			fi_migrate_json_print("ADD  session: {$legacy_id} | {$name} | slug={$legacy_slug} -> id={$session_id}");
			foreach (($term ?? []) as $k => $v) {
				fi_migrate_json_print_kv('term.' . (string) $k, $v, 4);
			}
		}
	}

	// Pass 2: update parent links.
	foreach ($terms as $term_id_str => $term) {
		$legacy_id = absint($term['term_id'] ?? $term_id_str);
		$parent_legacy_id = absint($term['parent'] ?? 0);
		if (!$legacy_id || !$parent_legacy_id) {
			continue;
		}
		$legacy_key = fi_migrate_json_legacy_key($gov, $legacy_id);
		$parent_legacy_key = fi_migrate_json_legacy_key($gov, $parent_legacy_id);

		$session_id = (int) ($maps['session_by_legacy_id'][$legacy_key] ?? 0);
		$parent_id = (int) ($maps['session_by_legacy_id'][$parent_legacy_key] ?? 0);
		if (!$session_id || !$parent_id) {
			fi_migrate_json_fail('Session parent mapping missing', [
				'legacy_id' => $legacy_id,
				'parent_legacy_id' => $parent_legacy_id,
			]);
		}

		// NOTE: Don't call Sessions::update/save() here because Sessions::save() requires name+gov.
		// We only need to set parent_id, so do a direct gov-scoped update.
		global $wpdb;
		$ok = $wpdb->update(
			$wpdb->prefix . 'fi_sessions',
			['parent_id' => $parent_id],
			['id' => $session_id, 'gov' => $gov],
			['%d'],
			['%d','%s']
		);
		if ($ok === false) {
			fi_migrate_json_fail('Failed to set session parent', [
				'session_id' => $session_id,
				'parent_id' => $parent_id,
				'legacy_id' => $legacy_id,
				'parent_legacy_id' => $parent_legacy_id,
			]);
		}
	}

	fi_migrate_json_print('Sessions imported.');
	fi_migrate_json_print('');
}

function fi_migrate_json_import_taxonomy(string $gov, array $data, array &$maps): void {
	fi_migrate_json_print('== Taxonomy (Districts/Tags) ==');

	$tax = $data['taxonomies'] ?? null;
	if (!is_array($tax)) {
		fi_migrate_json_fail('Export missing taxonomies', ['gov' => $gov]);
	}

	// Districts
	$district_terms = $tax['district'] ?? [];
	if (!is_array($district_terms)) {
		$district_terms = [];
	}
	foreach ($district_terms as $term_id_str => $term) {
		$legacy_id = absint($term['term_id'] ?? $term_id_str);
		if (!$legacy_id) {
			fi_migrate_json_fail('Invalid district term_id', ['term' => $term]);
		}
		$legacy_key = fi_migrate_json_legacy_key($gov, $legacy_id);

		$existing = fi_migrate_json_db_find_taxonomy_by_legacy_id($gov, 'district', $legacy_key);
		if ($existing) {
			$maps['district_by_legacy_id'][$legacy_key] = (int) $existing;
			$slug = (string) ($term['slug'] ?? '');
			if ($slug !== '') {
				$maps['district_by_slug'][$slug] = (int) $existing;
			}
			if (fi_migrate_json_verbose_enabled()) {
				fi_migrate_json_print("SKIP district: {$legacy_id} | " . (string) ($term['name'] ?? '') . " | slug={$slug} -> id={$existing}");
			}
			continue;
		}

		$name = (string) ($term['name'] ?? '');
		$slug = (string) ($term['slug'] ?? '');
		if ($name === '' || $slug === '') {
			fi_migrate_json_fail('District missing name/slug', ['legacy_id' => $legacy_id, 'term' => $term]);
		}

		$new_id = fi_migrate_json_db_insert_taxonomy($gov, 'district', $legacy_key, $name, $slug, [
			'legacy' => [
				'term_id' => $legacy_id,
				'term_taxonomy_id' => absint($term['term_taxonomy_id'] ?? 0),
			],
		]);
		$maps['district_by_legacy_id'][$legacy_key] = (int) $new_id;
		$maps['district_by_slug'][$slug] = (int) $new_id;
		if (fi_migrate_json_verbose_enabled()) {
			fi_migrate_json_print("ADD  district: {$legacy_id} | {$name} | slug={$slug} -> id={$new_id}");
		}
	}

	// Tags: merge multiple legacy taxonomies into a single FI "tag" taxonomy.
	$tag_sources = ['post_tag', 'fi_vote_group', 'fi_report_group'];
	foreach ($tag_sources as $source) {
		$tag_terms = $tax[$source] ?? [];
		if (!is_array($tag_terms)) {
			continue;
		}
		foreach ($tag_terms as $term_id_str => $term) {
			$legacy_id = absint($term['term_id'] ?? $term_id_str);
			if (!$legacy_id) {
				fi_migrate_json_fail('Invalid tag term_id', ['source' => $source, 'term' => $term]);
			}
			$legacy_key = fi_migrate_json_legacy_key($gov, $legacy_id);

			$existing = fi_migrate_json_db_find_taxonomy_by_legacy_id($gov, 'tag', $legacy_key);
			if ($existing) {
				$maps['tag_by_legacy_id'][$legacy_key] = (int) $existing;
				$slug = (string) ($term['slug'] ?? '');
				if ($slug !== '') {
					$maps['tag_by_slug'][$slug] = (int) $existing;
				}
				if (fi_migrate_json_verbose_enabled()) {
					fi_migrate_json_print("SKIP tag: {$legacy_id} | " . (string) ($term['name'] ?? '') . " | slug={$slug} -> id={$existing} (src={$source})");
				}
				continue;
			}

			$name = (string) ($term['name'] ?? '');
			$slug = (string) ($term['slug'] ?? '');
			if ($name === '' || $slug === '') {
				fi_migrate_json_fail('Tag missing name/slug', ['source' => $source, 'legacy_id' => $legacy_id, 'term' => $term]);
			}

			$new_id = fi_migrate_json_db_insert_taxonomy($gov, 'tag', $legacy_key, $name, $slug, [
				'legacy' => [
					'term_id' => $legacy_id,
					'taxonomy' => $source,
					'term_taxonomy_id' => absint($term['term_taxonomy_id'] ?? 0),
				],
			]);

			$maps['tag_by_legacy_id'][$legacy_key] = (int) $new_id;
			$maps['tag_by_slug'][$slug] = (int) $new_id;
			if (fi_migrate_json_verbose_enabled()) {
				fi_migrate_json_print("ADD  tag: {$legacy_id} | {$name} | slug={$slug} -> id={$new_id} (src={$source})");
			}
		}
	}

	fi_migrate_json_print('Taxonomy imported.');
	fi_migrate_json_print('');
}

function fi_migrate_json_import_legislators(string $gov, array $data, array &$maps): void {
	fi_migrate_json_print('== Legislators ==');

	$posts = $data['post_types']['legislator'] ?? null;
	if (!is_array($posts)) {
		fi_migrate_json_fail('Export missing post_types.legislator', ['gov' => $gov]);
	}

	foreach ($posts as $legacy_post_id_str => $row) {
		$legacy_post_id = absint($row['fields']['ID'] ?? $legacy_post_id_str);
		if (!$legacy_post_id) {
			fi_migrate_json_fail('Invalid legislator legacy post ID', ['row' => $row]);
		}

		// Multisite exports collide on numeric post IDs across sites.
		// We store legacy_id as "{GOV}-{id}" in fi_legislators (no gov column).
		$legacy_key = fi_migrate_json_legacy_key($gov, $legacy_post_id);
		$existing = fi_migrate_json_db_find_by_legacy_id('fi_legislators', $legacy_key);
		$legislator_id = $existing ? (int) $existing['id'] : 0;

		$display_name = (string) ($row['fields']['post_title'] ?? '');
		$legacy_slug = (string) ($row['fields']['post_name'] ?? '');
		// Summary: US/V1 exports use bioguide_id as post_name; normalize to uppercase for consistent lookups.
		if (strtoupper($gov) === 'US') {
			$legacy_slug = strtoupper(trim($legacy_slug));
		}
		if ($display_name === '') {
			fi_migrate_json_fail('Legislator missing post_title (display_name)', ['legacy_post_id' => $legacy_post_id]);
		}

		// State exports: post_name is LegiScan people_id (per your AK example).
		// Summary: store it directly into fi_legislators.legiscan_id so it can be used across the system.
		$state_legiscan_id = null;
		if (strtoupper($gov) !== 'US') {
			$pid = absint($row['fields']['post_name'] ?? 0);
			if ($pid) {
				$state_legiscan_id = $pid;
			}
		}

		// US/V1: post_name is Bioguide ID (e.g., Y000064). Persist it to the dedicated bioguide_id column.
		// Summary: this keeps US legislators eligible for later LegiScan matching by bioguide_id.
		$bioguide_id = null;
		if (strtoupper($gov) === 'US') {
			$bio = strtoupper(trim((string) $legacy_slug));
			// Basic format guard: letter + 6 digits.
			if ($bio !== '' && preg_match('/^[A-Z][0-9]{6}$/', $bio)) {
				$bioguide_id = $bio;
			}
		}

		// Summary: simple name split. last token = last_name, the rest = first_name.
		$parts = preg_split('/\s+/', trim($display_name)) ?: [];
		$last_name = count($parts) ? array_pop($parts) : '';
		$first_name = trim(implode(' ', $parts));
		if ($first_name === '' || $last_name === '') {
			fi_migrate_json_fail('Failed to split display_name into first/last name', [
				'legacy_post_id' => $legacy_post_id,
				'display_name' => $display_name,
			]);
		}

		if (!$legislator_id) {
			$meta = $row['meta'] ?? [];
			if (!is_array($meta)) {
				$meta = [];
			}

			// Map freedom score (V2 exports): legislator_score_life -> fi_legislators.score
			// Summary: this is the precomputed freedom score from the source site; treat it as authoritative for initial import.
			$life_score = null;
			// V1 exports commonly use legislator_lifescore; V2 exports use legislator_score_life.
			$life_score_raw = $meta['legislator_score_life'] ?? ($meta['legislator_lifescore'] ?? null);
			if ($life_score_raw !== '' && $life_score_raw !== null) {
				$life_score = absint($life_score_raw);
			}

			// Normalize & map legacy flat fields into our structured meta groups.
			// Summary: US (V1) meta keys differ from state (V2) keys; choose mapping based on gov.
			$structured_meta = (strtoupper($gov) === 'US')
				? fi_migrate_json_map_v1_legislator_meta($meta)
				: fi_migrate_json_map_v2_legislator_meta($meta);

			// Summary: V1/US exports include legislator_townname; store it as meta.hometown for admin UI parity.
			$town = trim((string) ($meta['legislator_townname'] ?? ''));
			if ($town !== '') {
				$structured_meta['hometown'] = $town;
			}

			// Minimal, purpose-built legacy references (avoid giant meta blobs).
			$fi_meta = [
				'legacy_post' => [
					'id' => $legacy_post_id,
					'post_name' => $legacy_slug,
					'photo' => $meta['legislator_photo'] ?? null,
					'image_url' => $meta['image_url'] ?? null,
				],
			];
			$fi_meta = array_merge($fi_meta, $structured_meta);

			$legislator_id = Legislators::save([
				'legacy_id' => $legacy_key,
				'first_name' => $first_name,
				'middle_name' => null,
				'last_name' => $last_name,
				'display_name' => $display_name,
				'bioguide_id' => $bioguide_id,
				'legiscan_id' => $state_legiscan_id,
				'score' => $life_score,
				'meta' => $fi_meta,
			]);

			if (!$legislator_id) {
				fi_migrate_json_fail('Failed to create legislator', [
					'legacy_post_id' => $legacy_post_id,
					'display_name' => $display_name,
				]);
			}
			if (fi_migrate_json_verbose_enabled()) {
				fi_migrate_json_print("ADD  legislator: {$legacy_post_id} | {$display_name} | post_name={$legacy_slug} -> id={$legislator_id}");
				foreach (($row['meta'] ?? []) as $k => $v) {
					// Requirement: if meta is an array, don't display it.
					if (is_array($v) || is_object($v)) {
						continue;
					}
					fi_migrate_json_print_kv('meta.' . (string) $k, $v, 4);
				}
			}
		} else {
			if (fi_migrate_json_verbose_enabled()) {
				fi_migrate_json_print("SKIP legislator: {$legacy_post_id} | {$display_name} | post_name={$legacy_slug} -> id={$legislator_id}");
			}

			// Even when skipping base legislator row, we should still map V2 flat meta fields into structured meta
			// (so reruns can fill contact/address without requiring delete+reimport).
			$meta = $row['meta'] ?? [];
			if (is_array($meta) && !empty($meta)) {
				$structured_meta = (strtoupper($gov) === 'US')
					? fi_migrate_json_map_v1_legislator_meta($meta)
					: fi_migrate_json_map_v2_legislator_meta($meta);

				// Summary: keep hometown updated on reruns for V1/US exports.
				$town = trim((string) ($meta['legislator_townname'] ?? ''));
				if ($town !== '') {
					$structured_meta['hometown'] = $town;
				}

				// Map freedom score (V2 exports): legislator_score_life -> fi_legislators.score
				$life_score = null;
				$life_score_raw = $meta['legislator_score_life'] ?? ($meta['legislator_lifescore'] ?? null);
				if ($life_score_raw !== '' && $life_score_raw !== null) {
					$life_score = absint($life_score_raw);
				}

				$update = [];
				// Summary: only include fields when we actually have a value; avoids empty UPDATE payloads.
				if ($state_legiscan_id !== null) {
					$update['legiscan_id'] = $state_legiscan_id;
				}
				// Summary: ensure US bioguide_id is populated from legacy post_name when missing.
				if ($bioguide_id) {
					$current_bio = class_exists('\\FI\\Core\\Legislators') ? (string) (\FI\Core\Legislators::get_field_by_id((int) $legislator_id, 'bioguide_id') ?? '') : '';
					if ($current_bio === '') {
						$update['bioguide_id'] = $bioguide_id;
					}
				}
				if ($life_score !== null) {
					$update['score'] = $life_score;
				}
				if (!empty($structured_meta)) {
					$update['meta'] = $structured_meta;
				}
				if (!empty($update)) {
					$ok = Legislators::save($update, (int) $legislator_id);
					if (!$ok) {
						fi_migrate_json_fail('Failed updating legislator meta from V2 export fields', [
							'legacy_post_id' => $legacy_post_id,
							'legislator_id' => $legislator_id,
						]);
					}
				}
			}
		}

		$maps['legislator_by_legacy_id'][$legacy_key] = (int) $legislator_id;
		if ($legacy_slug !== '') {
			$maps['legislator_by_legacy_slug'][$legacy_slug] = (int) $legislator_id;
			// US/V1: rollcall keys are bioguide IDs (e.g. A000014); map them to fi_legislators.id
			if (strtoupper($gov) === 'US') {
				$maps['legislator_by_bioguide'][$legacy_slug] = (int) $legislator_id;
			}
		}

		// Rollcall key mapping:
		// - Many exports use rollcall keys that are NOT the WordPress post_name. Commonly they are LegiScan people_id.
		// - We build a lookup keyed by that rollcall key so vote rollcalls can map reliably.
		$rollcall_key = 0;
		// State exports often have post_name = LegiScan people_id; if so, prefer that.
		if (absint($row['fields']['post_name'] ?? 0)) {
			$rollcall_key = absint($row['fields']['post_name'] ?? 0);
		}
		// Otherwise attempt to extract people_id from legislator_data snapshot (if present and decodable).
		if (!$rollcall_key) {
			$raw_ld = $row['meta']['legislator_data'] ?? null;
			$ld = null;
			if (is_array($raw_ld)) {
				$ld = $raw_ld;
			} elseif (is_string($raw_ld) && $raw_ld !== '') {
				$tmp = json_decode($raw_ld, true);
				if (is_array($tmp)) $ld = $tmp;
			}
			if (is_array($ld)) {
				$p = $ld['person'] ?? $ld;
				$pid = absint($p['people_id'] ?? 0);
				if ($pid) {
					$rollcall_key = $pid;
				}
			}
		}
		if ($rollcall_key) {
			$maps['legislator_by_rollcall_key'][(string) $rollcall_key] = (int) $legislator_id;
		}

		// Session assignments (fi_legislator_sessions)
		fi_migrate_json_import_legislator_sessions($gov, (int) $legislator_id, $legacy_post_id, $row, $maps);

		// LegiScan snapshot mapping (if present in export meta)
		fi_migrate_json_import_legislator_legiscan_snapshot($gov, (int) $legislator_id, $legacy_post_id, $row);

		// Image import (best-effort; always logs what happened in verbose mode)
		fi_migrate_json_import_legislator_image($gov, (int) $legislator_id, $legacy_post_id, $row);
	}

	fi_migrate_json_print('Legislators imported.');
	fi_migrate_json_print('');
}

function fi_migrate_json_map_v2_legislator_meta(array $meta): array {
	// Summary: V2 exports have flat keys like legislator_email/phone/office/local.
	// We map these into the normalized schema used by the V3 admin UI (contact/address groups).

	if (empty($meta)) {
		return [];
	}

	$norm = fi_legislator_meta_normalize([]);
	if (!isset($norm['contact'])) $norm['contact'] = [];
	if (!isset($norm['address'])) $norm['address'] = [];

	$email = trim((string) ($meta['legislator_email'] ?? ''));
	$phone = trim((string) ($meta['legislator_phone'] ?? ''));
	if ($email !== '') $norm['contact']['email'] = $email;
	if ($phone !== '') $norm['contact']['phone'] = $phone;

	// Capitol office: single string in many exports (not parsed into city/state/zip).
	$office = trim((string) ($meta['legislator_office'] ?? ''));
	if ($office !== '') {
		$norm['address'][] = [
			'name' => 'Capitol Office',
			'type' => 'capitol',
			'address' => $office,
		];
	}

	// Local/district office: sometimes multi-line or pipe-delimited.
	$local = trim((string) ($meta['legislator_local'] ?? ''));
	if ($local !== '') {
		$norm['address'][] = [
			'name' => 'District Office',
			'type' => 'district',
			'address' => $local,
		];
	}

	// Remove empty groups
	if (empty($norm['contact'])) unset($norm['contact']);
	if (empty($norm['address'])) unset($norm['address']);

	// Return only groups we touched (so we don't overwrite unrelated meta on merge).
	$out = [];
	if (isset($norm['contact'])) $out['contact'] = $norm['contact'];
	if (isset($norm['address'])) $out['address'] = $norm['address'];
	return $out;
}

function fi_migrate_json_map_v1_legislator_meta(array $meta): array {
	// Summary: V1 (Congress) exports use keys defined in tfi/fi-export.php (legislator_phone, legislator_website, etc).
	if (empty($meta)) {
		return [];
	}

	$norm = fi_legislator_meta_normalize([]);
	if (!isset($norm['contact'])) $norm['contact'] = [];
	if (!isset($norm['address'])) $norm['address'] = [];
	if (!isset($norm['website'])) $norm['website'] = [];

	// Contact
	if (!empty($meta['legislator_phone'])) {
		$norm['contact']['phone'] = sanitize_text_field((string) $meta['legislator_phone']);
	}

	// Websites
	if (!empty($meta['legislator_website'])) {
		$url = esc_url_raw((string) $meta['legislator_website']);
		if ($url !== '') {
			$norm['website'][] = $url;
		}
	}
	if (!empty($meta['legislator_url'])) {
		$url = esc_url_raw((string) $meta['legislator_url']);
		if ($url !== '') {
			$norm['website'][] = $url;
		}
	}

	// Address (best-effort: v1 has multiple fragments; keep a single string so it diffs cleanly in admin)
	$capitol_address = '';
	if (!empty($meta['legislator_address'])) {
		$capitol_address = sanitize_text_field((string) $meta['legislator_address']);
	} else {
		$parts = [];
		if (!empty($meta['legislator_office-building'])) $parts[] = (string) $meta['legislator_office-building'];
		if (!empty($meta['legislator_office-room'])) $parts[] = (string) $meta['legislator_office-room'];
		if (!empty($meta['legislator_office-zip'])) $parts[] = (string) $meta['legislator_office-zip'];
		if (!empty($meta['legislator_office-zip-suffix'])) $parts[] = (string) $meta['legislator_office-zip-suffix'];
		$capitol_address = trim(implode(' ', array_map('trim', $parts)));
	}
	if ($capitol_address !== '') {
		// Summary: upsert a single "capitol" address entry.
		$norm['address'][] = [
			'name' => 'Capitol Office',
			'type' => 'capitol',
			'address' => $capitol_address,
		];
	}

	// De-dupe website list
	if (!empty($norm['website']) && is_array($norm['website'])) {
		$norm['website'] = array_values(array_unique(array_filter($norm['website'])));
	}

	return $norm;
}

function fi_migrate_json_import_legislator_legiscan_snapshot(string $gov, int $legislator_id, int $legacy_post_id, array $row): void {
	$meta = $row['meta'] ?? [];
	if (!is_array($meta)) {
		$meta = [];
	}

	$raw = $meta['legislator_data'] ?? null;
	if ($raw === null || $raw === '') {
		return;
	}

	$decoded = null;
	if (is_array($raw)) {
		$decoded = $raw;
	} elseif (is_string($raw)) {
		$tmp = json_decode($raw, true);
		if (is_array($tmp)) {
			$decoded = $tmp;
		}
	}

	if (!is_array($decoded)) {
		// Some V2 exports contain nearly-JSON strings with unescaped quotes inside values.
		// Attempt a safe repair; if it still fails, skip snapshot mapping (non-fatal) because
		// legislator import + session assignment should continue.
		if (is_string($raw) && function_exists('fi_migrate_json_repair_json_quotes')) {
			$repaired = fi_migrate_json_repair_json_quotes($raw);
			$tmp2 = json_decode($repaired, true);
			if (is_array($tmp2)) {
				$decoded = $tmp2;
			}
		}
	}

	if (!is_array($decoded)) {
		if (fi_migrate_json_verbose_enabled()) {
			$raw_preview = is_string($raw) ? substr($raw, 0, 180) : '';
			fi_migrate_json_print("WARN legiscan_data: legacy_post_id={$legacy_post_id} -> legislator_id={$legislator_id} | skipped (legislator_data not valid JSON)");
			if ($raw_preview !== '') {
				fi_migrate_json_print('    legislator_data_preview = ' . $raw_preview);
			}
		}
		return;
	}

	// Summary: store the raw LegiScan snapshot in meta.legiscan_data (requested),
	// and also map as much as possible into canonical FI fields using our existing LegiScan-local mapping logic.
	$save_ok = Legislators::save([
		'meta' => [
			'legiscan_data' => $decoded,
		],
	], $legislator_id);
	if (!$save_ok) {
		fi_migrate_json_fail('Failed saving legiscan_data snapshot to legislator meta', [
			'legacy_post_id' => $legacy_post_id,
			'legislator_id' => $legislator_id,
		]);
	}

	// Most exports wrap the person payload at decoded.person; fall back to decoded.
	$person = $decoded['person'] ?? $decoded;
	if (!is_array($person)) {
		return;
	}

	if (!class_exists('\\FI\\Core\\ApiIntegration')) {
		// Should always exist in this plugin, but keep it explicit.
		return;
	}

	// Build updates using the same map used by the admin "LegiScan (Local)" check.
	// only_missing=false because migration should populate fields aggressively from authoritative export data.
	$updates = ApiIntegration::build_updates_for_legislator($legislator_id, 'legiscan_local', $person, false);

	if (!empty($updates) && function_exists('fi_admin_legislators_apply_api_updates')) {
		$applied = fi_admin_legislators_apply_api_updates($legislator_id, 'legiscan_local', $updates);
		fi_migrate_json_print("MAP legiscan_data: legacy_post_id={$legacy_post_id} -> legislator_id={$legislator_id} fields_applied={$applied}");
	} elseif (!empty($updates)) {
		// Minimal fallback: at least apply top-level ID columns if admin apply function isn't loaded.
		$base_updates = [];
		foreach (['legiscan_id','bioguide_id','votesmart_id','ballotpedia_id','govtrack_id'] as $k) {
			if (isset($updates[$k]) && $updates[$k] !== '') {
				$base_updates[$k] = $updates[$k];
			}
		}
		if (!empty($base_updates)) {
			$ok = Legislators::save($base_updates, $legislator_id);
			if (!$ok) {
				fi_migrate_json_fail('Failed applying base ID updates from legiscan_data', [
					'legacy_post_id' => $legacy_post_id,
					'legislator_id' => $legislator_id,
					'base_updates' => $base_updates,
				]);
			}
		}
		fi_migrate_json_print("MAP legiscan_data: legacy_post_id={$legacy_post_id} -> legislator_id={$legislator_id} fields_applied=fallback_ids_only");
	}
}

function fi_migrate_json_import_legislator_image(string $gov, int $legislator_id, int $legacy_post_id, array $row): void {
	$meta = $row['meta'] ?? [];
	if (!is_array($meta)) $meta = [];

	// Summary: store legacy image URL into a dedicated column so a separate image importer can process it later.
	// This avoids giant meta blobs and keeps the image process resumable and inspectable.
	$image_url = trim((string) ($meta['image_url'] ?? ''));

	// V1 Congress exports usually don't have a featured image URL, only a filename like "A000055.jpg"
	// in `legislator_imgsrc`, which lives at: thefreedomindex.org/assets/freedomindex/{filename}
	if ($image_url === '' && strtoupper($gov) === 'US') {
		$imgsrc = trim((string) ($meta['legislator_imgsrc'] ?? ''));
		if ($imgsrc !== '') {
			$base = (string) apply_filters('fi_migrate_json_us_image_base_url', 'https://thefreedomindex.org/assets/freedomindex/');
			$base = rtrim($base, '/') . '/';
			$image_url = $base . basename($imgsrc);
		}
	}

	if ($image_url === '') {
		if (fi_migrate_json_verbose_enabled()) {
			fi_migrate_json_print("IMG  legislator: {$legacy_post_id} -> id={$legislator_id} | skipped (no image_url)");
		}
		return;
	}

	// Normalize: if URL doesn't end in .jpg/.jpeg/.png/.webp, append .jpg (per prior LegiScan rule).
	if (!preg_match('/\.(jpg|jpeg|png|webp)(\?.*)?$/i', $image_url)) {
		$image_url .= '.jpg';
	}

	$ok = Legislators::save([
		'legacy_image_url' => $image_url,
	], $legislator_id);
	if (!$ok) {
		fi_migrate_json_fail('Failed saving legacy_image_url for legislator', [
			'legacy_post_id' => $legacy_post_id,
			'legislator_id' => $legislator_id,
			'legacy_image_url' => $image_url,
		]);
	}
	if (fi_migrate_json_verbose_enabled()) {
		fi_migrate_json_print("IMG  legislator: {$legacy_post_id} -> id={$legislator_id} | stored legacy_image_url");
	}
}

function fi_migrate_json_sideload_image(string $url, string $preferred_filename): array {
	if (!function_exists('media_handle_sideload')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	// Summary: use wp_safe_remote_get so we can capture HTTP status + WP_Error codes reliably.
	$ua = 'FreedomIndexMigrator/1.0; ' . home_url('/');
	$res = wp_safe_remote_get($url, [
		'timeout' => 30,
		'redirection' => 5,
		'user-agent' => $ua,
	]);
	if (is_wp_error($res)) {
		return [
			'id' => 0,
			'error' => [
				'type' => 'wp_error',
				'code' => $res->get_error_code(),
				'message' => $res->get_error_message(),
				'data' => $res->get_error_data(),
			],
		];
	}
	$status = (int) wp_remote_retrieve_response_code($res);
	$body = (string) wp_remote_retrieve_body($res);
	if ($status < 200 || $status >= 300) {
		return [
			'id' => 0,
			'error' => [
				'type' => 'http_error',
				'status' => $status,
				'content_type' => wp_remote_retrieve_header($res, 'content-type'),
				'content_length' => wp_remote_retrieve_header($res, 'content-length'),
			],
		];
	}
	if ($body === '') {
		return [
			'id' => 0,
			'error' => [
				'type' => 'empty_body',
				'status' => $status,
				'content_type' => wp_remote_retrieve_header($res, 'content-type'),
				'content_length' => wp_remote_retrieve_header($res, 'content-length'),
			],
		];
	}

	$tmp = wp_tempnam($preferred_filename);
	if (!$tmp) {
		return ['id' => 0, 'error' => ['type' => 'tempfile', 'message' => 'wp_tempnam failed']];
	}
	$bytes = @file_put_contents($tmp, $body);
	if ($bytes === false) {
		@unlink($tmp);
		return ['id' => 0, 'error' => ['type' => 'tempfile', 'message' => 'file_put_contents failed']];
	}

	$file_array = [
		'name' => $preferred_filename,
		'tmp_name' => $tmp,
	];

	$att_id = media_handle_sideload($file_array, 0, null, [
		'post_title' => sanitize_text_field($preferred_filename),
	]);

	if (is_wp_error($att_id)) {
		@unlink($tmp);
		return [
			'id' => 0,
			'error' => [
				'type' => 'wp_error',
				'code' => $att_id->get_error_code(),
				'message' => $att_id->get_error_message(),
				'data' => $att_id->get_error_data(),
			],
		];
	}

	return ['id' => (int) $att_id, 'error' => null];
}

function fi_migrate_json_import_legislator_sessions(string $gov, int $legislator_id, int $legacy_post_id, array $row, array &$maps): void {
	global $wpdb;

	$tax = $row['taxonomies'] ?? [];
	if (!is_array($tax)) {
		$tax = [];
	}

	$meta = $row['meta'] ?? [];
	if (!is_array($meta)) {
		$meta = [];
	}

	$role = (string) (($row['meta']['legislator_role'] ?? '') ?: ($row['meta']['legislator_chamber'] ?? ''));
	$role = strtolower($role);
	$chamber = null;
	if ($role === 'rep' || $role === 'house') $chamber = 'H';
	if ($role === 'sen' || $role === 'senate') $chamber = 'S';
	if (!$chamber) {
		fi_migrate_json_fail('Legislator missing/invalid role for chamber mapping', [
			'legacy_post_id' => $legacy_post_id,
			'role' => $role,
		]);
	}

	$party = null;
	$party_slugs = $tax['party'] ?? [];
	if (is_array($party_slugs) && !empty($party_slugs[0])) {
		$party = strtoupper((string) $party_slugs[0]);
	}

	// District is stored as FI taxonomy ID (stringified).
	$district_tax_id = null;
	$district_slugs = $tax['district'] ?? [];
	if (is_array($district_slugs) && !empty($district_slugs[0])) {
		$district_slug = (string) $district_slugs[0];
		$district_tax_id = (int) ($maps['district_by_slug'][$district_slug] ?? 0);
		// If missing from map, attempt to create (do not fail).
		if (!$district_tax_id && $district_slug !== '') {
			$district_slug = sanitize_title($district_slug);
			$existing = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE taxonomy='district' AND gov=%s AND slug=%s LIMIT 1",
				$gov,
				$district_slug
			));
			if ($existing > 0) {
				$district_tax_id = $existing;
			} else {
				$name = strtoupper($gov) === 'US' ? ('US ' . strtoupper($district_slug)) : strtoupper($district_slug);
				$legacy_key = strtoupper($gov) . '-DISTRICT-' . $district_slug;
				$new_id = fi_migrate_json_db_insert_taxonomy($gov, 'district', $legacy_key, $name, $district_slug, [
					'created_from' => 'migrate',
				]);
				$district_tax_id = (int) $new_id;
			}
			if ($district_tax_id > 0) {
				$maps['district_by_slug'][$district_slug] = $district_tax_id;
			}
		}
	}

	// US/V1: state comes from taxonomy.state (preferred), otherwise meta. Apply to all session assignments.
	$session_state = null;
	if (strtoupper($gov) === 'US') {
		$tax_states = $tax['state'] ?? [];
		if (is_array($tax_states) && !empty($tax_states[0])) {
			$st = strtoupper(trim((string) $tax_states[0]));
			if (preg_match('/^[A-Z]{2}$/', $st)) {
				$session_state = $st;
			}
		}
		// Fallback: some exports only include meta legislator_state.
		if (!$session_state) {
			$st = strtoupper(trim((string) ($meta['legislator_state'] ?? '')));
			if (preg_match('/^[A-Z]{2}$/', $st)) {
				$session_state = $st;
			}
		}

		// Only Representatives have districts; Senators are at-large.
		if ($chamber === 'H' && !$district_tax_id && $session_state) {
			$raw_dist = trim((string) ($meta['legislator_district'] ?? ''));
			$raw_dist_lc = strtolower($raw_dist);

			$slug = '';
			$term_name = '';

			// Normalize: "5th" -> 5, "At Large" -> at-large.
			if ($raw_dist !== '' && (str_contains($raw_dist_lc, 'at') && str_contains($raw_dist_lc, 'large'))) {
				$slug = strtolower($session_state) . '-at-large';
				$term_name = $session_state . ' At Large';
			} else {
				$num = (int) preg_replace('/\D+/', '', $raw_dist);
				if ($num > 0) {
					$slug = strtolower($session_state) . '-' . (string) $num;
					$term_name = $session_state . ' ' . (function_exists('fi_format_ordinal') ? fi_format_ordinal($num) : ((string) $num . 'th'));
				}
			}

			if ($slug !== '') {
				$district_tax_id = (int) ($maps['district_by_slug'][$slug] ?? 0);
				if (!$district_tax_id) {
					$existing = (int) $wpdb->get_var($wpdb->prepare(
						"SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE taxonomy='district' AND gov=%s AND slug=%s LIMIT 1",
						$gov,
						$slug
					));
					if ($existing > 0) {
						$district_tax_id = $existing;
					} else {
						$legacy_key = 'US-DISTRICT-' . $slug;
						$new_id = fi_migrate_json_db_insert_taxonomy($gov, 'district', $legacy_key, $term_name !== '' ? $term_name : strtoupper($slug), $slug, [
							'created_from' => 'migrate',
							'state' => $session_state,
							'at_large' => (str_ends_with($slug, '-at-large') ? 1 : 0),
						]);
						$district_tax_id = (int) $new_id;
					}
					if ($district_tax_id > 0) {
						$maps['district_by_slug'][$slug] = $district_tax_id;
					}
				}
			}
		}
	}

	// V2 exports: `session` taxonomy. V1 Congress exports: `congress` taxonomy.
	$sessions = $tax['session'] ?? ($tax['congress'] ?? []);
	if (!is_array($sessions) || empty($sessions)) {
		// Some source sites may have "orphan" legislators with no session term assignments.
		// Migration should not hard-stop for this case; we can import the legislator record and skip junction rows.
		if (fi_migrate_json_verbose_enabled()) {
			fi_migrate_json_print("SKIP leg_session: legacy_post_id={$legacy_post_id} | " . (string) ($row['fields']['post_title'] ?? '') . " | no session taxonomy assignments in export");
		}
		return;
	}

	foreach ($sessions as $legacy_session_slug) {
		$legacy_session_slug = (string) $legacy_session_slug;
		$session_id = (int) ($maps['session_by_legacy_slug'][$legacy_session_slug] ?? 0);
		if (!$session_id) {
			fi_migrate_json_fail('Session mapping failed for legislator session assignment', [
				'legacy_post_id' => $legacy_post_id,
				'legacy_session_slug' => $legacy_session_slug,
			]);
		}

		// Deterministic legacy_id for the junction row.
		$legacy_session_term_id = (int) ($maps['legacy_session_term_id_by_slug'][$legacy_session_slug] ?? 0);
		if (!$legacy_session_term_id) {
			fi_migrate_json_fail('Could not resolve legacy session term_id for determinisitic legacy_id', [
				'legacy_post_id' => $legacy_post_id,
				'legacy_session_slug' => $legacy_session_slug,
			]);
		}
		// Deterministic junction legacy_id (string) to avoid multisite collisions.
		$junction_legacy_id = strtoupper((string) $gov) . '-LS-' . (string) $legacy_post_id . '-' . (string) $legacy_session_term_id;

		// Summary: if the row already exists (prior run), UPDATE it so office/district/party don't remain NULL.
		$existing_row = $wpdb->get_row($wpdb->prepare(
			"SELECT id, state, office, district, party FROM {$wpdb->prefix}fi_legislator_sessions WHERE legislator_id=%d AND session_id=%d AND gov=%s LIMIT 1",
			$legislator_id,
			$session_id,
			$gov
		));
		if ($existing_row) {
			$existing_id = (int) ($existing_row->id ?? 0);
			$existing_state = strtoupper((string) ($existing_row->state ?? ''));
			$existing_chamber = (string) ($existing_row->chamber ?? '');
			$existing_district = (string) ($existing_row->district ?? '');
			$existing_party = (string) ($existing_row->party ?? '');
			$new_district = $district_tax_id ? (string) $district_tax_id : '';
			$new_party = (string) ($party ?? '');
			$new_state = strtoupper((string) ($session_state ?? ''));

			$needs_update =
				($existing_state !== $new_state)
				|| ($existing_chamber !== (string) $chamber)
				|| ($existing_district !== $new_district)
				|| ($existing_party !== $new_party);

			if ($needs_update) {
				$ok = function_exists('fi_legislator_session_update')
					? fi_legislator_session_update($existing_id, [
						'legislator_id' => $legislator_id,
						'session_id' => $session_id,
						'gov' => $gov,
					'state' => $session_state,
						'chamber' => $chamber,
						'district' => $new_district !== '' ? $new_district : null,
						'party' => $new_party !== '' ? $new_party : null,
					])
					: ($wpdb->update(
						$wpdb->prefix . 'fi_legislator_sessions',
						[
						'state' => $session_state,
							'chamber' => $chamber,
							'district' => $new_district !== '' ? $new_district : null,
							'party' => $new_party !== '' ? $new_party : null,
						],
						['id' => $existing_id],
					['%s','%s','%s','%s'],
						['%d']
					) !== false);

				if (!$ok) {
					fi_migrate_json_fail('Failed updating existing fi_legislator_sessions row', [
						'legacy_post_id' => $legacy_post_id,
						'fi_legislator_sessions_id' => $existing_id,
						'legislator_id' => $legislator_id,
						'session_id' => $session_id,
						'gov' => $gov,
						'chamber' => $chamber,
						'district' => $new_district,
						'party' => $new_party,
					]);
				}
				if (fi_migrate_json_verbose_enabled()) {
					fi_migrate_json_print("UPD  leg_session: leg={$legislator_id} sess={$session_id} state=" . ($session_state ?? 'NULL') . " chamber={$chamber} district=" . ($district_tax_id ? (string) $district_tax_id : 'NULL') . " party=" . ($party ?? 'NULL') . " (id={$existing_id})");
				}
			} else {
				if (fi_migrate_json_verbose_enabled()) {
					fi_migrate_json_print("SKIP leg_session: leg={$legislator_id} sess={$session_id} state=" . ($session_state ?? 'NULL') . " chamber={$chamber} district=" . ($district_tax_id ? (string) $district_tax_id : 'NULL') . " party=" . ($party ?? 'NULL') . " (id={$existing_id})");
				}
			}
			continue;
		}

		$insert = [
			'legislator_id' => $legislator_id,
			'session_id' => $session_id,
			'legacy_id' => $junction_legacy_id,
			'gov' => $gov,
			'state' => $session_state,
			'chamber' => $chamber,
			'district' => $district_tax_id ? (string) $district_tax_id : null,
			'party' => $party,
		];

		$ok = $wpdb->insert(
			$wpdb->prefix . 'fi_legislator_sessions',
			$insert,
			['%d','%d','%s','%s','%s','%s','%s','%s']
		);
		if ($ok === false) {
			fi_migrate_json_fail('Failed inserting fi_legislator_sessions', [
				'legacy_post_id' => $legacy_post_id,
				'insert' => $insert,
			]);
		}
		if (fi_migrate_json_verbose_enabled()) {
			fi_migrate_json_print("ADD  leg_session: leg={$legislator_id} sess={$session_id} chamber={$chamber} district=" . ($district_tax_id ? (string) $district_tax_id : 'NULL') . " party=" . ($party ?? 'NULL'));
		}
	}
}

// Intentionally no "find term_id by slug" helpers: we build slug->taxonomy_id maps during taxonomy import
// to keep mapping deterministic and avoid scanning large export arrays repeatedly.

function fi_migrate_json_import_votes(string $gov, array $data, array &$maps): void {
	fi_migrate_json_print('== Votes + Rollcalls ==');

	$posts = $data['post_types']['fi_vote'] ?? null;
	if (!is_array($posts)) {
		fi_migrate_json_fail('Export missing post_types.fi_vote', ['gov' => $gov]);
	}

	foreach ($posts as $legacy_post_id_str => $row) {
		$legacy_vote_id = absint($row['fields']['ID'] ?? $legacy_post_id_str);
		if (!$legacy_vote_id) {
			fi_migrate_json_fail('Invalid vote legacy post ID', ['row' => $row]);
		}
		$legacy_vote_key = fi_migrate_json_legacy_key($gov, $legacy_vote_id);

		$existing = fi_migrate_json_db_find_by_legacy_id('fi_votes', $legacy_vote_key, $gov);
		$vote_id = $existing ? (int) $existing['id'] : 0;

		// V2 exports: `session` taxonomy. V1 Congress exports: `congress` taxonomy.
		$session_slugs = $row['taxonomies']['session'] ?? ($row['taxonomies']['congress'] ?? []);
		if (!is_array($session_slugs) || empty($session_slugs[0])) {
			// Summary: some exports contain orphan/draft vote posts with no session/congress taxonomy.
			// We skip non-published votes deterministically to avoid blocking migration of reports.
			$post_status = (string) ($row['fields']['post_status'] ?? '');
			if ($post_status !== 'publish') {
				if (fi_migrate_json_verbose_enabled()) {
					$title = (string) ($row['fields']['post_title'] ?? '');
					fi_migrate_json_print("SKIP vote: {$legacy_vote_id} | {$title} | missing session taxonomy (status={$post_status})");
				}
				continue;
			}
			fi_migrate_json_fail('Vote missing session taxonomy assignment', [
				'legacy_vote_id' => $legacy_vote_id,
				'post_status' => $post_status,
				'post_title' => $row['fields']['post_title'] ?? null,
				'post_name' => $row['fields']['post_name'] ?? null,
			]);
		}
		$legacy_session_slug = (string) $session_slugs[0];
		$session_id = (int) ($maps['session_by_legacy_slug'][$legacy_session_slug] ?? 0);
		if (!$session_id) {
			fi_migrate_json_fail('Vote session mapping failed', [
				'legacy_vote_id' => $legacy_vote_id,
				'legacy_session_slug' => $legacy_session_slug,
			]);
		}

		$meta = $row['meta'] ?? [];
		if (!is_array($meta)) $meta = [];

		// V2 exports: `vote_role` (rep/sen). Some state exports use shorthand (H/S/U/L).
		// V1 exports: `vote_chamber` (House/Senate).
		$role_raw = (string) (($meta['vote_role'] ?? '') ?: ($meta['vote_chamber'] ?? ''));
		$role = strtolower(trim($role_raw));
		$chamber = null;
		if (in_array($role, ['rep','r','house','h','lower','assembly','a'], true)) $chamber = 'H';
		if (in_array($role, ['sen','s','senate','upper','u'], true)) $chamber = 'S';
		if (!$chamber) {
			fi_migrate_json_fail('Vote missing/invalid chamber field for office mapping (vote_role or vote_chamber)', [
				'legacy_vote_id' => $legacy_vote_id,
				'vote_role' => $meta['vote_role'] ?? null,
				'vote_chamber' => $meta['vote_chamber'] ?? null,
				'vote_role_normalized' => $role,
			]);
		}

		// Summary: some exports omit vote_good; treat as Unknown/TBD ("U") instead of hard-stopping migration.
		$constitutional_raw = $meta['vote_good'] ?? null;
		$constitutional = strtoupper(trim((string) ($constitutional_raw ?? '')));
		if ($constitutional === '') {
			$constitutional = 'U';
		}
		if (!in_array($constitutional, ['Y','N','U'], true)) {
			fi_migrate_json_fail('Vote missing/invalid vote_good for constitutional mapping', [
				'legacy_vote_id' => $legacy_vote_id,
				'vote_good' => $constitutional_raw,
				'constitutional_normalized' => $constitutional,
			]);
		}

		$title = (string) ($row['fields']['post_title'] ?? '');
		$slug = (string) ($row['fields']['post_name'] ?? '');
		if ($title === '' || $slug === '') {
			fi_migrate_json_fail('Vote missing title/slug', ['legacy_vote_id' => $legacy_vote_id]);
		}

		$date_voted = (string) ($meta['vote_date'] ?? ($row['fields']['post_date'] ?? ''));
		$ts = strtotime($date_voted);
		if ($ts === false) {
			$ts = strtotime((string) ($row['fields']['post_date'] ?? ''));
		}
		if ($ts === false) {
			fi_migrate_json_fail('Vote missing/invalid date', [
				'legacy_vote_id' => $legacy_vote_id,
				'vote_date' => $date_voted,
			]);
		}
		$date_voted_dt = date('Y-m-d H:i:s', $ts);

		$vote_meta = [
			'url_bill' => (string) ($meta['vote_url'] ?? ''),
			'url_rollcall' => (string) ($meta['vote_url_rollcall'] ?? ''),
			'description_short' => (string) ($meta['vote_text_scorecard'] ?? ''),
			'description_medium' => (string) ($meta['vote_text_scorecard_more'] ?? ''),
			'description_long' => (string) ($meta['vote_text_freedomindex'] ?? ''),
			'text_xl' => (string) ($row['fields']['post_content'] ?? ''),
			'cost' => (string) ($meta['vote_cost'] ?? ''),
			'legacy' => $row['fields'],
		];

		if (!$vote_id) {
			global $wpdb;
			$insert = [
				'legacy_id' => $legacy_vote_key,
				'session_id' => $session_id,
				'gov' => $gov,
				'chamber' => $chamber,
				'title' => $title,
				'slug' => $slug,
				// V2 exports: vote_bill_number. V1 exports: vote_number.
				'bill_number' => (string) (($meta['vote_bill_number'] ?? '') ?: ($meta['vote_number'] ?? $slug)),
				'constitutional' => $constitutional,
				'rollcall_number' => (string) ($meta['vote_rollcall_number'] ?? ''),
				'rollcall_data' => null,
				'status' => in_array(($row['fields']['post_status'] ?? 'publish'), ['publish','draft','pending','trash'], true) ? $row['fields']['post_status'] : 'publish',
				'date_voted' => $date_voted_dt,
				'meta' => json_encode($vote_meta),
			];
			$ok = $wpdb->insert(
				$wpdb->prefix . 'fi_votes',
				$insert,
				['%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']
			);
			if ($ok === false) {
				fi_migrate_json_fail('Failed inserting fi_votes', ['legacy_vote_id' => $legacy_vote_id, 'insert' => $insert]);
			}
			$vote_id = (int) $wpdb->insert_id;
			if (fi_migrate_json_verbose_enabled()) {
				fi_migrate_json_print("ADD  vote: {$legacy_vote_id} | {$title} | slug={$slug} sess={$legacy_session_slug} office={$office} -> id={$vote_id}");
				foreach (($row['meta'] ?? []) as $k => $v) {
					// Requirement: if meta is an array, don't display it.
					if (is_array($v) || is_object($v)) {
						continue;
					}
					fi_migrate_json_print_kv('meta.' . (string) $k, $v, 4);
				}
			}
		} else {
			if (fi_migrate_json_verbose_enabled()) {
				fi_migrate_json_print("SKIP vote: {$legacy_vote_id} | {$title} | slug={$slug} -> id={$vote_id}");
			}
		}

		$maps['vote_by_legacy_id'][$legacy_vote_key] = (int) $vote_id;

		// Tags (fi_vote_tags) from fi_vote_group and post_tag slugs.
		fi_migrate_json_import_vote_tags($gov, $vote_id, $row, $maps);

		// Rollcall (fi_voterc + votes.rollcall_data)
		fi_migrate_json_import_vote_rollcall($gov, $vote_id, $legacy_vote_id, $row, $maps);
	}

	fi_migrate_json_print('Votes imported.');
	fi_migrate_json_print('');
}

function fi_migrate_json_import_vote_tags(string $gov, int $vote_id, array $row, array &$maps): void {
	$tax = $row['taxonomies'] ?? [];
	if (!is_array($tax)) return;

	$tag_slugs = [];
	foreach (['fi_vote_group', 'post_tag'] as $k) {
		$slugs = $tax[$k] ?? [];
		if (is_array($slugs)) {
			foreach ($slugs as $s) {
				$s = (string) $s;
				if ($s !== '') $tag_slugs[] = $s;
			}
		}
	}
	$tag_slugs = array_values(array_unique($tag_slugs));
	if (empty($tag_slugs)) {
		return;
	}

	$tag_ids = [];
	foreach ($tag_slugs as $slug) {
		$tag_id = (int) ($maps['tag_by_slug'][$slug] ?? 0);
		if (!$tag_id) {
			fi_migrate_json_fail('Vote tag slug not found in imported tags', [
				'vote_id' => $vote_id,
				'tag_slug' => $slug,
			]);
		}
		$tag_ids[] = $tag_id;
	}

	if (function_exists('fi_vote_tags_set_tags')) {
		\fi_vote_tags_set_tags($vote_id, $tag_ids);
	}
}

function fi_migrate_json_import_vote_rollcall(string $gov, int $vote_id, int $legacy_vote_id, array $row, array &$maps): void {
	global $wpdb;

	// Summary: import is idempotent. We will replace any existing fi_voterc rows for this vote_id.

	$meta = $row['meta'] ?? [];
	if (!is_array($meta)) $meta = [];

	$rollcall_raw = $meta['vote_rollcall'] ?? null;
	if ($rollcall_raw === null || $rollcall_raw === '') {
		// No rollcall in export.
		return;
	}

	$rollcall_map = null;
	if (is_string($rollcall_raw)) {
		$tmp = json_decode($rollcall_raw, true);
		if (is_array($tmp)) $rollcall_map = $tmp;
	}
	if (!is_array($rollcall_map)) {
		fi_migrate_json_fail('vote_rollcall is not valid JSON map', [
			'legacy_vote_id' => $legacy_vote_id,
			'vote_id' => $vote_id,
		]);
	}

	// Transaction: ensure vote rollcall_data and fi_voterc rows stay consistent.
	$wpdb->query('START TRANSACTION');
	try {
		$compact = [];
		$rows_by_leg = [];
		$total = 0;
		foreach ($rollcall_map as $legacy_leg_slug => $cast_raw) {
			$total++;
			$legacy_leg_slug = (string) $legacy_leg_slug;

			$legislator_id = 0;

			// US/V1: rollcall keys are Bioguide IDs (e.g. A000014). Convert to fi_legislators.id via prebuilt map.
			if (strtoupper($gov) === 'US') {
				$bio = strtoupper(trim($legacy_leg_slug));
				// Some exports include placeholder/invalid keys like "0000000". Skip these.
				if ($bio === '' || preg_match('/^0+$/', $bio)) {
					if (fi_migrate_json_verbose_enabled()) {
						fi_migrate_json_print("SKIP rollcall_legislator: vote_legacy={$legacy_vote_id} -> vote_id={$vote_id} invalid_bioguide_id=" . ($bio !== '' ? $bio : '(empty)'));
					}
					continue;
				}
				if ($bio !== '') {
					$legislator_id = (int) ($maps['legislator_by_bioguide'][$bio] ?? 0);
				}
				if (!$legislator_id) {
					// Summary: US-only safety valve for edge-case legacy rollcalls (e.g., old territory delegates).
					$legislator_id = fi_migrate_json_get_or_create_placeholder_legislator_us_bioguide($bio, $maps);
				}
			} else {
				// V2/state exports: rollcall keys are usually LegiScan people_id; fall back to legacy post_name when needed.
				$legislator_id = (int) (
					($maps['legislator_by_rollcall_key'][$legacy_leg_slug] ?? 0)
					?: ($maps['legislator_by_legacy_slug'][$legacy_leg_slug] ?? 0)
				);
			}
			if (!$legislator_id) {
				// User-approved policy: create a placeholder legislator so rollcalls can import,
				// and staff can later populate missing people from LegiScan.
				$legislator_id = fi_migrate_json_get_or_create_placeholder_legislator($gov, $legacy_leg_slug, $maps);
			}

			$cast = fi_migrate_json_vote_cast((string) $cast_raw);
			// Summary: rollcall maps can contain multiple keys that resolve to the same legislator_id.
			// Keep the last observed cast and ensure we insert only one row per (vote_id, legislator_id).
			$compact[(string) $legislator_id] = $cast;
			$rows_by_leg[(int) $legislator_id] = $cast;
		}

		// Replace any pre-existing rows (partial prior run, manual edits, etc.)
		$del = $wpdb->delete($wpdb->prefix . 'fi_voterc', ['vote_id' => $vote_id], ['%d']);
		if ($del === false) {
			fi_migrate_json_fail('Failed clearing existing fi_voterc rows for vote', [
				'legacy_vote_id' => $legacy_vote_id,
				'vote_id' => $vote_id,
				'wpdb_error' => $wpdb->last_error,
			]);
		}

		if (!empty($rows_by_leg)) {
			$values = [];
			foreach ($rows_by_leg as $leg_id => $cast) {
				$values[] = $wpdb->prepare("(%d,%d,%s,%d)", $vote_id, (int) $leg_id, (string) $cast, 0);
			}
			$sql = "INSERT INTO {$wpdb->prefix}fi_voterc (vote_id, legislator_id, cast, is_override) VALUES " . implode(',', $values);
			$ok = $wpdb->query($sql);
			if ($ok === false) {
				fi_migrate_json_fail('Failed inserting fi_voterc rows', [
					'legacy_vote_id' => $legacy_vote_id,
					'vote_id' => $vote_id,
					'wpdb_error' => $wpdb->last_error,
				]);
			}
		}

		$ok2 = $wpdb->update(
			$wpdb->prefix . 'fi_votes',
			['rollcall_data' => json_encode($compact)],
			['id' => $vote_id],
			['%s'],
			['%d']
		);
		if ($ok2 === false) {
			fi_migrate_json_fail('Failed updating fi_votes.rollcall_data', [
				'legacy_vote_id' => $legacy_vote_id,
				'vote_id' => $vote_id,
				'wpdb_error' => $wpdb->last_error,
			]);
		}

		$wpdb->query('COMMIT');
		if (fi_migrate_json_verbose_enabled()) {
			fi_migrate_json_print("ADD  rollcall: vote_legacy={$legacy_vote_id} -> vote_id={$vote_id} rows_in_export={$total} unique_legislators=" . count($rows_by_leg));
		}
	} catch (\Throwable $e) {
		$wpdb->query('ROLLBACK');
		throw $e;
	}
}

function fi_migrate_json_import_reports(string $gov, array $data, array &$maps): void {
	fi_migrate_json_print('== Reports ==');

	$posts = $data['post_types']['fi_report'] ?? null;
	if (!is_array($posts)) {
		// Reports are optional in some exports.
		fi_migrate_json_print('No reports section in export.');
		fi_migrate_json_print('');
		return;
	}

	foreach ($posts as $legacy_post_id_str => $row) {
		$legacy_report_id = absint($row['fields']['ID'] ?? $legacy_post_id_str);
		if (!$legacy_report_id) {
			fi_migrate_json_fail('Invalid report legacy post ID', ['row' => $row]);
		}
		$legacy_report_key = fi_migrate_json_legacy_key($gov, $legacy_report_id);

		$existing = fi_migrate_json_db_find_by_legacy_id('fi_reports', $legacy_report_key, $gov);
		if ($existing) {
			continue;
		}

		// V2 exports: `session` taxonomy. V1 Congress exports: `congress` taxonomy.
		$session_slugs = $row['taxonomies']['session'] ?? ($row['taxonomies']['congress'] ?? []);
		if (!is_array($session_slugs) || empty($session_slugs[0])) {
			fi_migrate_json_fail('Report missing session taxonomy assignment', ['legacy_report_id' => $legacy_report_id]);
		}
		$legacy_session_slug = (string) $session_slugs[0];
		$session_id = (int) ($maps['session_by_legacy_slug'][$legacy_session_slug] ?? 0);
		if (!$session_id) {
			fi_migrate_json_fail('Report session mapping failed', [
				'legacy_report_id' => $legacy_report_id,
				'legacy_session_slug' => $legacy_session_slug,
			]);
		}

		$title = (string) ($row['fields']['post_title'] ?? '');
		$legacy_slug = (string) ($row['fields']['post_name'] ?? '');
		if ($title === '') {
			fi_migrate_json_fail('Report missing title', ['legacy_report_id' => $legacy_report_id]);
		}
		$slug = fi_migrate_json_report_slug($gov, $legacy_slug);

		$meta = $row['meta'] ?? [];
		if (!is_array($meta)) $meta = [];

		// Summary: start from report_* meta keys (strip prefix) to preserve all report settings,
		// then normalize into the V3 payload structure.
		$payload = [
			'content' => (string) ($row['fields']['post_content'] ?? ''),
			'votes_r' => [],
			'votes_s' => [],
		];
		foreach ($meta as $k => $v) {
			if (!is_string($k) || strpos($k, 'report_') !== 0) {
				continue;
			}
			$key = substr($k, strlen('report_'));
			$payload[$key] = $v;
		}

		// Convert legacy vote IDs to new vote IDs.
		$legacy_r = $meta['report_votes_rep'] ?? [];
		$legacy_s = $meta['report_votes_sen'] ?? [];
		if (is_array($legacy_r)) {
			$payload['legacy_votes_r'] = array_map('intval', $legacy_r);
			foreach ($legacy_r as $legacy_vote_id) {
				$legacy_vote_id = absint($legacy_vote_id);
				$legacy_vote_key = fi_migrate_json_legacy_key($gov, $legacy_vote_id);
				$new_vote_id = (int) ($maps['vote_by_legacy_id'][$legacy_vote_key] ?? 0);
				if (!$new_vote_id) {
					fi_migrate_json_fail('Report references vote that was not imported', [
						'legacy_report_id' => $legacy_report_id,
						'legacy_vote_id' => $legacy_vote_id,
					]);
				}
				$payload['votes_h'][] = $new_vote_id;
			}
		}
		if (is_array($legacy_s)) {
			$payload['legacy_votes_s'] = array_map('intval', $legacy_s);
			foreach ($legacy_s as $legacy_vote_id) {
				$legacy_vote_id = absint($legacy_vote_id);
				$legacy_vote_key = fi_migrate_json_legacy_key($gov, $legacy_vote_id);
				$new_vote_id = (int) ($maps['vote_by_legacy_id'][$legacy_vote_key] ?? 0);
				if (!$new_vote_id) {
					fi_migrate_json_fail('Report references vote that was not imported', [
						'legacy_report_id' => $legacy_report_id,
						'legacy_vote_id' => $legacy_vote_id,
					]);
				}
				$payload['votes_s'][] = $new_vote_id;
			}
		}
		// Remove legacy export keys to avoid confusion; we keep V3 canonical + legacy_votes_* only.
		unset($payload['votes_rep'], $payload['votes_sen']);

		if (function_exists('fi_report_payload_normalize')) {
			$payload = \fi_report_payload_normalize($payload);
		}

		global $wpdb;
		$status = (string) ($row['fields']['post_status'] ?? 'draft');
		if (!in_array($status, ['publish','draft','pending','trash'], true)) {
			$status = 'draft';
		}

		$date_publish = null;
		if ($status === 'publish') {
			$dp = (string) ($row['fields']['post_date'] ?? '');
			$ts = strtotime($dp);
			$date_publish = $ts ? date('Y-m-d H:i:s', $ts) : null;
		}

		$insert = [
			'legacy_id' => $legacy_report_key,
			'title' => $title,
			'slug' => $slug,
			'gov' => $gov,
			'session_id' => $session_id,
			'owner_user_id' => absint($row['fields']['post_author'] ?? 0) ?: null,
			'payload_json' => json_encode($payload),
			'status' => $status,
			'date_publish' => $date_publish,
			'meta' => json_encode([
				'legacy' => [
					'post_id' => $legacy_report_id,
					'post_name' => $legacy_slug,
				],
			]),
		];

		$ok = $wpdb->insert(
			$wpdb->prefix . 'fi_reports',
			$insert,
			['%s','%s','%s','%s','%d','%d','%s','%s','%s','%s']
		);
		if ($ok === false) {
			fi_migrate_json_fail('Failed inserting fi_reports', ['legacy_report_id' => $legacy_report_id, 'insert' => $insert]);
		}
	}

	fi_migrate_json_print('Reports imported.');
	fi_migrate_json_print('');
}

/**
 * DB helpers
 */
function fi_migrate_json_db_find_by_legacy_id(string $table, string $legacy_id, ?string $gov = null): ?array {
	global $wpdb;

	// Summary: sessions/votes/reports are siloed by gov, so legacy_id lookups must be gov-scoped.
	// Legislators are career-spanning and may be shared; call-sites omit gov in that case.
	if (is_string($gov) && $gov !== '') {
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT id, legacy_id FROM {$wpdb->prefix}{$table} WHERE legacy_id = %s AND gov = %s LIMIT 1",
			$legacy_id,
			$gov
		), ARRAY_A);
	} else {
		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT id, legacy_id FROM {$wpdb->prefix}{$table} WHERE legacy_id = %s LIMIT 1",
			$legacy_id
		), ARRAY_A);
	}

	return is_array($row) ? $row : null;
}

function fi_migrate_json_db_find_taxonomy_by_legacy_id(string $gov, string $taxonomy, string $legacy_id): int {
	global $wpdb;
	$id = $wpdb->get_var($wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}fi_taxonomy WHERE gov=%s AND taxonomy=%s AND legacy_id=%s LIMIT 1",
		$gov,
		$taxonomy,
		$legacy_id
	));
	return $id ? (int) $id : 0;
}

function fi_migrate_json_db_insert_taxonomy(string $gov, string $taxonomy, string $legacy_id, string $name, string $slug, array $meta): int {
	global $wpdb;
	$ok = $wpdb->insert(
		$wpdb->prefix . 'fi_taxonomy',
		[
			'legacy_id' => $legacy_id,
			'gov' => $gov,
			'taxonomy' => $taxonomy,
			'name' => $name,
			'slug' => $slug,
			'meta' => json_encode($meta),
		],
		['%s','%s','%s','%s','%s','%s']
	);
	if ($ok === false) {
		fi_migrate_json_fail('Failed inserting fi_taxonomy', [
			'gov' => $gov,
			'taxonomy' => $taxonomy,
			'legacy_id' => $legacy_id,
			'name' => $name,
			'slug' => $slug,
		]);
	}
	return (int) $wpdb->insert_id;
}

/**
 * Import vote rollcall overrides from JSON export.
 * 
 * Summary:
 * - Scans votes for meta.vote_rollcall_control
 * - Maps legacy vote IDs to fi_votes.id
 * - Maps legiscan_id to fi_legislators.id
 * - Updates/inserts fi_votesrc rows with is_override=1
 */
function fi_migrate_json_import_vote_overrides(string $file_path): void {
	global $wpdb;
	
	@ignore_user_abort(true);
	@set_time_limit(0);
	
	try {
		$data = fi_migrate_json_load($file_path);
		$gov = strtoupper((string) ($data['gov'] ?? ''));
		if ($gov === '') {
			fi_migrate_json_fail('Export missing gov', ['file' => $file_path]);
		}
		
		fi_migrate_json_print("=== Vote Override Import ===");
		fi_migrate_json_print("File: {$file_path}");
		fi_migrate_json_print("Gov: {$gov}");
		fi_migrate_json_print('');
		
		// Build vote reference array: legacy_id -> vote_id
		fi_migrate_json_print("Building vote reference array...");
		$vote_refs = [];
		$votes = $data['post_types']['fi_vote'] ?? [];
		if (!is_array($votes)) {
			fi_migrate_json_fail('Export missing post_types.fi_vote', ['gov' => $gov]);
		}
		
		foreach ($votes as $legacy_post_id_str => $row) {
			$legacy_vote_id = absint($row['fields']['ID'] ?? $legacy_post_id_str);
			if (!$legacy_vote_id) {
				continue;
			}
			$legacy_key = fi_migrate_json_legacy_key($gov, $legacy_vote_id);
			$existing = fi_migrate_json_db_find_by_legacy_id('fi_votes', $legacy_key, $gov);
			if (!$existing) {
				fi_migrate_json_fail('Vote not found in database', [
					'legacy_vote_id' => $legacy_vote_id,
					'legacy_key' => $legacy_key,
					'gov' => $gov,
				]);
			}
			$vote_refs[$legacy_vote_id] = (int) $existing['id'];
		}
		fi_migrate_json_print("Vote references: " . count($vote_refs));
		
		// Build legislator reference array: legiscan_id -> legislator_id
		// Query all legislators in DB (not just export) since overrides may reference any legislator
		fi_migrate_json_print("Building legislator reference array...");
		$legislator_refs = [];
		$all_legislators = $wpdb->get_results(
			"SELECT id, legiscan_id FROM {$wpdb->prefix}fi_legislators WHERE legiscan_id IS NOT NULL AND legiscan_id > 0",
			ARRAY_A
		);
		foreach ($all_legislators as $leg) {
			$legiscan_id = absint($leg['legiscan_id'] ?? 0);
			$legislator_id = absint($leg['id'] ?? 0);
			if ($legiscan_id > 0 && $legislator_id > 0) {
				$legislator_refs[$legiscan_id] = $legislator_id;
			}
		}
		fi_migrate_json_print("Legislator references: " . count($legislator_refs));
		fi_migrate_json_print('');
		
		// Scan votes for vote_rollcall_control
		$total_overrides = 0;
		$total_updated = 0;
		$total_inserted = 0;
		
		foreach ($votes as $legacy_post_id_str => $row) {
			$legacy_vote_id = absint($row['fields']['ID'] ?? $legacy_post_id_str);
			if (!$legacy_vote_id) {
				continue;
			}
			
			$meta = $row['meta'] ?? [];
			if (!is_array($meta)) {
				$meta = [];
			}
			
			$overrides = $meta['vote_rollcall_control'] ?? null;
			if (!is_array($overrides) || empty($overrides)) {
				continue;
			}
			
			$vote_id = $vote_refs[$legacy_vote_id] ?? 0;
			if (!$vote_id) {
				fi_migrate_json_fail('Vote ID not found in reference array', [
					'legacy_vote_id' => $legacy_vote_id,
					'gov' => $gov,
				]);
			}
			
			fi_migrate_json_print("Processing vote: legacy_id={$legacy_vote_id} -> vote_id={$vote_id} (overrides: " . count($overrides) . ")");
			
			foreach ($overrides as $key => $cast_value) {
				$total_overrides++;
				
				// Extract legiscan_id from key (format: "cast18978")
				if (!is_string($key) || strpos($key, 'cast') !== 0) {
					fi_migrate_json_fail('Invalid override key format', [
						'key' => $key,
						'legacy_vote_id' => $legacy_vote_id,
					]);
				}
				$legiscan_id = absint(substr($key, 4)); // Remove "cast" prefix
				if (!$legiscan_id) {
					fi_migrate_json_fail('Invalid legiscan_id in override key', [
						'key' => $key,
						'legacy_vote_id' => $legacy_vote_id,
					]);
				}
				
				$legislator_id = $legislator_refs[$legiscan_id] ?? 0;
				if (!$legislator_id) {
					fi_migrate_json_fail('Legislator not found for legiscan_id', [
						'legiscan_id' => $legiscan_id,
						'legacy_vote_id' => $legacy_vote_id,
						'gov' => $gov,
					]);
				}
				
				// Normalize cast value
				$cast = fi_migrate_json_vote_cast((string) $cast_value);
				
				// Check if row exists
				$existing = $wpdb->get_row($wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}fi_voterc WHERE vote_id = %d AND legislator_id = %d LIMIT 1",
					$vote_id,
					$legislator_id
				));
				
				if ($existing) {
					// Update existing row
					$ok = $wpdb->update(
						$wpdb->prefix . 'fi_voterc',
						[
							'cast' => $cast,
							'is_override' => 1,
						],
						[
							'vote_id' => $vote_id,
							'legislator_id' => $legislator_id,
						],
						['%s', '%d'],
						['%d', '%d']
					);
					if ($ok === false) {
						fi_migrate_json_fail('Failed updating fi_voterc override', [
							'vote_id' => $vote_id,
							'legislator_id' => $legislator_id,
							'cast' => $cast,
							'wpdb_error' => $wpdb->last_error,
						]);
					}
					$total_updated++;
				} else {
					// Insert new row
					$ok = $wpdb->insert(
						$wpdb->prefix . 'fi_voterc',
						[
							'vote_id' => $vote_id,
							'legislator_id' => $legislator_id,
							'cast' => $cast,
							'is_override' => 1,
						],
						['%d', '%d', '%s', '%d']
					);
					if ($ok === false) {
						fi_migrate_json_fail('Failed inserting fi_voterc override', [
							'vote_id' => $vote_id,
							'legislator_id' => $legislator_id,
							'cast' => $cast,
							'wpdb_error' => $wpdb->last_error,
						]);
					}
					$total_inserted++;
				}
			}
		}
		
		fi_migrate_json_print('');
		fi_migrate_json_print("=== Summary ===");
		fi_migrate_json_print("Total overrides processed: {$total_overrides}");
		fi_migrate_json_print("Rows updated: {$total_updated}");
		fi_migrate_json_print("Rows inserted: {$total_inserted}");
		fi_migrate_json_print("Done.");
		
	} catch (\Throwable $e) {
		fi_migrate_json_print('');
		fi_migrate_json_print('Exception: ' . $e->getMessage());
		fi_migrate_json_print('File: ' . $e->getFile() . ':' . $e->getLine());
		fi_migrate_json_print('--- Stack ---');
		fi_migrate_json_print($e->getTraceAsString());
		throw $e;
	}
}

