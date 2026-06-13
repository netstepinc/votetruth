<?php
namespace FI\Core;

if (!defined('ABSPATH')) exit;

/**
 * Yoast sitemap provider for Freedom Index custom URLs.
 *
 * Registers four sitemap types with the Yoast sitemap index:
 *   fi-govs         →  /{gov}/  (static, no DB query)
 *   fi-legislators  →  /legislator/{id}/
 *   fi-votes        →  /{gov}/vote/{id}/
 *   fi-reports      →  /{gov}/report/{id}/
 *
 * Uses an anonymous class so the `implements WPSEO_Sitemap_Provider` declaration
 * is only resolved after plugins_loaded, avoiding a fatal if Yoast loads after us.
 */
add_action('plugins_loaded', static function (): void {

	if (!interface_exists('WPSEO_Sitemap_Provider')) return;

	$provider = new class implements \WPSEO_Sitemap_Provider {

		private function types(): array {
			return [
				'fi-legislators' => [
					'table'    => TBFI_LEGISLATORS,
					'alias'    => 'l',
					'filter'   => '',
					'gov'      => false,
					'date_col' => 'date_updated',
				],
				'fi-votes' => [
					'table'    => TBFI_VOTES,
					'alias'    => 'v',
					'filter'   => "v.status = 'publish'",
					'gov'      => true,
					'date_col' => 'date_updated',
				],
				'fi-reports' => [
					'table'    => TBFI_REPORTS,
					'alias'    => 'r',
					'filter'   => "r.status = 'publish' AND (r.date_publish IS NULL OR r.date_publish <= NOW())",
					'gov'      => true,
					'date_col' => 'date_publish',
				],
			];
		}

		public function handles_type($type): bool {
			return $type === 'fi-govs' || isset($this->types()[$type]);
		}

		public function get_index_links($max_entries): array {
			$links = [];

			// Gov landing pages are static — always one page, no DB needed
			$links[] = [
				'loc'     => \WPSEO_Sitemaps_Router::get_base_url('fi-govs-sitemap1.xml'),
				'lastmod' => null,
			];

			foreach ($this->types() as $type => $c) {
				[$count, $lastmod] = $this->count_and_lastmod($c);
				if ($count === 0) continue;
				$pages = (int) ceil($count / $max_entries);
				for ($page = 1; $page <= $pages; $page++) {
					$links[] = [
						'loc'     => \WPSEO_Sitemaps_Router::get_base_url("{$type}-sitemap{$page}.xml"),
						'lastmod' => $lastmod,
					];
				}
			}
			return $links;
		}

		public function get_sitemap_links($type, $max_entries, $current_page): array {
			if ($type === 'fi-govs') {
				if ($current_page > 1) {
					throw new \OutOfBoundsException("Invalid sitemap page: {$type} page {$current_page}");
				}
				$links = [];
				foreach (FI_GOVERNMENTS as $abbr => $name) {
					$links[] = ['loc' => home_url('/' . strtolower($abbr) . '/')];
				}
				return $links;
			}

			$types = $this->types();
			if (!isset($types[$type])) return [];

			$c      = $types[$type];
			$offset = ($current_page - 1) * $max_entries;
			$rows   = $this->paginated_rows($c, $max_entries, $offset);

			if (empty($rows) && $current_page > 1) {
				throw new \OutOfBoundsException("Invalid sitemap page: {$type} page {$current_page}");
			}

			$links = [];
			foreach ($rows as $row) {
				$url = $this->row_url($type, $row);
				if (!$url) continue;
				$link = ['loc' => $url];
				if (!empty($row->date_updated)) {
					$link['mod'] = $row->date_updated;
				}
				$links[] = $link;
			}
			return $links;
		}

		private function count_and_lastmod(array $c): array {
			global $wpdb;
			$a     = $c['alias'];
			$where = $c['filter'] ? "WHERE {$c['filter']}" : '';
			$d   = $c['date_col'];
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row("SELECT COUNT(*) AS cnt, MAX({$a}.{$d}) AS lastmod FROM {$c['table']} {$a} {$where}");
			return [(int) ($row->cnt ?? 0), $row->lastmod ?? null];
		}

		private function paginated_rows(array $c, int $limit, int $offset): array {
			global $wpdb;
			$a     = $c['alias'];
			$d     = $c['date_col'];
			$cols  = $c['gov'] ? "{$a}.id, {$a}.gov, {$a}.{$d} AS date_updated" : "{$a}.id, {$a}.{$d} AS date_updated";
			$where = $c['filter'] ? "WHERE {$c['filter']}" : '';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$sql = $wpdb->prepare(
				"SELECT {$cols} FROM {$c['table']} {$a} {$where} ORDER BY {$a}.id ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results($sql) ?: [];
		}

		private function row_url(string $type, object $row): string {
			return match ($type) {
				'fi-legislators' => \FI\Public\Rewrite::get_legislator_url((int) $row->id),
				'fi-votes'       => home_url('/' . strtolower($row->gov) . '/vote/' . $row->id . '/'),
				'fi-reports'     => home_url('/' . strtolower($row->gov) . '/report/' . $row->id . '/'),
				default          => '',
			};
		}
	};

	add_filter('wpseo_sitemaps_providers', static function (array $providers) use ($provider): array {
		$providers[] = $provider;
		return $providers;
	});

}, 20);
