<?php
namespace FI\Admin {

if (!defined('ABSPATH')) exit;

/**
 * URL Generator for Freedom Index Admin
 * 
 * Generates URLs for various Freedom Index entities
 */
final class URLs {
    
    /**
     * Get legislator URL (accepts ID as string or int)
     */
    public static function get_legislator_url(string|int $id): string {
        $legislator_id = is_numeric($id) ? (int)$id : null;
        if (!$legislator_id) {
            return home_url();
        }
        if (function_exists('fi_get_legislator_url')) {
            return fi_get_legislator_url($legislator_id);
        }
        return home_url('/legislator/' . $legislator_id . '/');
    }
    
    /**
     * Get legislator URL by ID
     */
    public static function get_legislator_url_by_id(int $legislator_id): string {
        // Use ID directly as slug
        if (function_exists('fi_get_legislator_url')) {
            return fi_get_legislator_url($legislator_id);
        }
        return home_url('/legislator/' . $legislator_id . '/');
    }
    
    /**
     * Get session URL
     */
//NONSENSE: This is not a valid function
    public static function get_session_url(string $slug): string {
        return home_url("session/{$slug}/");
    }
    
    /**
     * Get report URL: https://freedomindex.us/us/report/231/
     */
    public static function get_report_url(string $gov, int $report_id): string {
		$gov = strtolower($gov);
        return home_url("{$gov}/report/{$report_id}/");
    }
    
    /**
     * Get list URL
     */
    public static function get_list_url(string $id): string {
        return home_url("list/{$id}/");
    }
    
    /**
     * Get PDF URL
     */
//NONSENSE: This is not a valid function
    public static function get_pdf_url(string $type, string $id): string {
        return home_url("pdf/{$type}/{$id}/");
    }
    
    /**
     * Get share URL
     */
//NONSENSE: This is not a valid function
    public static function get_share_url(string $type, string $id,string $gov = 'us'): string {
        switch ($type) {
            case 'legislator':
                return self::get_legislator_url($id);
            case 'session':
                return self::get_session_url($id);
            case 'report':
                return self::get_report_url($gov, $id);
            case 'list':
                return self::get_list_url($id);
            default:
                return home_url();
        }
    }
    
    /**
     * Get admin URL for a specific Freedom Index page with optional args
     */
    public static function get_admin_url(string $page = 'fi-dashboard', array $args = []): string {
        $page = $page ?: 'fi-dashboard';
        // Include current gov so each scope+page is a unique URL (bfcache safety).
        if (!isset($args['gov'])) {
            $args['gov'] = Scope::get_gov();
        }
        $url = admin_url('admin.php?page=' . rawurlencode($page));
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        return $url;
    }

    /**
     * Get edit legislator URL
     */
    public static function get_edit_legislator_url(int $legislator_id, array $args = []): string {
        $defaults = [
            'action'        => 'edit',
            'legislator_id' => $legislator_id,
        ];
        return self::get_admin_url('fi-legislators', array_merge($defaults, $args));
    }

    /**
     * Get legislator sessions management URL
     */
    public static function get_legislator_sessions_url(int $legislator_id, array $args = []): string {
        $defaults = [
            'legislator_id' => $legislator_id,
        ];
        return self::get_admin_url('fi-sessions', array_merge($defaults, $args));
    }

	/**
	 * Get edit vote URL
	 */
	public static function get_edit_vote_url(int $vote_id, array $args = []): string {
		$defaults = [
			'action' => 'edit',
			'vote_id' => $vote_id,
		];

		return self::get_admin_url('fi-votes', array_merge($defaults, $args));
	}

	/**
	 * Get roll-call editor URL
	 */
	public static function get_roll_call_edit_url(int $vote_id, array $args = []): string {
		$defaults = [
			'action' => 'rollcall',
			'vote_id' => $vote_id,
		];

		return self::get_admin_url('fi-votes', array_merge($defaults, $args));
	}

	/**
	 * Get edit session URL
	 */
	public static function get_edit_session_url(int $session_id, array $args = []): string {
		$defaults = [
			'action' => 'edit',
			'session_id' => $session_id,
		];

		return self::get_admin_url('fi-sessions', array_merge($defaults, $args));
	}

	/**
	 * Get recalculate scores URL
	 */
	public static function get_recalculate_scores_url(?string $gov = null, ?int $session_id = null): string {
		$args = ['action' => 'recalculate-scores'];

		if ($gov) {
			$args['gov'] = strtoupper($gov);
		}

		if ($session_id) {
			$args['session_id'] = $session_id;
		}

		return self::get_admin_url('fi-dashboard', $args);
	}
    
    /**
     * Get import URL
     */
    public static function get_import_url(int $blog_id = 0): string {
        $url = admin_url('admin.php?page=fi-import');
        
        if ($blog_id) {
            $url .= '&action=import&blog_id=' . $blog_id;
        }
        
        return $url;
    }
    
    /**
     * Get settings URL
     */
    public static function get_settings_url(string $gov = ''): string {
        $url = admin_url('admin.php?page=fi-settings');
        
        if ($gov) {
            $url .= '&gov=' . $gov;
        }
        
        return $url;
    }
    
    /**
     * Get API URL
     */
    public static function get_api_url(string $endpoint = ''): string {
        $url = home_url('wp-json/fi/v1/');
        
        if ($endpoint) {
            $url .= $endpoint;
        }
        
        return $url;
    }
}

}

// Global helper functions
namespace {
    /**
     * Get admin URL for a specific Freedom Index page with optional args
     */
    function fi_admin_url(string $page = 'fi-dashboard', array $args = []): string {
        return \FI\Admin\URLs::get_admin_url($page, $args);
    }

    /**
     * Get edit legislator URL
     */
    function fi_admin_edit_legislator_url(int $legislator_id, array $args = []): string {
        return \FI\Admin\URLs::get_edit_legislator_url($legislator_id, $args);
    }

    /**
     * Get legislator sessions management URL
     */
    function fi_admin_legislator_sessions_url(int $legislator_id, array $args = []): string {
        return \FI\Admin\URLs::get_legislator_sessions_url($legislator_id, $args);
    }

    /**
     * Get edit vote URL
     */
    function fi_admin_edit_vote_url(int $vote_id, array $args = []): string {
        return \FI\Admin\URLs::get_edit_vote_url($vote_id, $args);
    }

    /**
     * Get roll-call editor URL
     */
    function fi_admin_roll_call_edit_url(int $vote_id, array $args = []): string {
        return \FI\Admin\URLs::get_roll_call_edit_url($vote_id, $args);
    }

    /**
     * Get edit session URL
     */
    function fi_admin_edit_session_url(int $session_id, array $args = []): string {
        return \FI\Admin\URLs::get_edit_session_url($session_id, $args);
    }

    /**
     * Get recalculate scores URL
     */
    function fi_admin_recalculate_scores_url(?string $gov = null, ?int $session_id = null): string {
        return \FI\Admin\URLs::get_recalculate_scores_url($gov, $session_id);
    }

    /**
     * Get report URL (public URL)
     */
    function fi_report_url(string $gov, int $report_id): string {
        return \FI\Admin\URLs::get_report_url($gov,$report_id);
    }
}