<?php
namespace FI\Admin;

use wpdb;

final class Installer {
    private string $option_key = 'fi_admin_db_version';

    public function activate(): void {
        global $wpdb;
        $current = get_option($this->option_key, '');
        $target  = FI_VERSION;

        // Fast activation mode:
        // - On existing installs with large FI tables, dbDelta/ALTER operations can exceed proxy timeouts (e.g., Cloudflare 120s).
        // - Schema upgrade can be run later (manually / via admin_init on FI pages if enabled).
        // - On fresh installs (tables missing), we still create schema immediately.
        $table = $wpdb->prefix . 'fi_legislators';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$exists) {
            fi_schema_ensure();
            update_option('fi_schema_version', $target, false);
        } else {
            // Mark schema as "current" to avoid automatic schema runs during activation.
            // If an upgrade is needed, staff can explicitly run it from FI admin tools.
            update_option('fi_schema_version', $target, false);
            set_transient('fi_schema_deferred_notice', 1, DAY_IN_SECONDS);
        }

        // Persist version
        update_option($this->option_key, $target, true);
    }
}
