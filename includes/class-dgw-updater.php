<?php
/**
 * Plugin auto-update integration.
 *
 * Hooks into WordPress's standard plugin-update mechanism so customer sites
 * see "Update available" badges in the Plugins page and can one-click upgrade
 * — same UX as a wordpress.org-hosted plugin.
 *
 * Flow:
 *   1. WordPress's update cron fires (~12h interval).
 *   2. WP populates the update_plugins transient by calling each plugin's
 *      filter hooks. Ours below merges remote metadata into the transient
 *      when remote version > current.
 *   3. WP renders the "Update available" notice and offers a click-to-update
 *      button. Clicking it downloads $package, extracts it, and replaces the
 *      plugin folder.
 *   4. Because the release zip is FLAT (no top-level dgateway-plugin/ dir),
 *      we hook upgrader_source_selection to rename the extracted folder
 *      before WP moves it into wp-content/plugins/. Without this the update
 *      would install as a separate plugin and leave the old one active.
 *
 * Remote source: dgatewayapi.desispay.com/v1/wordpress-plugin/update
 *   (Go handler at apps/api/internal/handlers/wordpress_plugin.go — proxies
 *   the GitHub Releases API with a 1-hour Redis cache.)
 *
 * @package DGateway
 */

defined('ABSPATH') || exit;

class DGW_Updater {

    /** Remote update-info endpoint. */
    const UPDATE_URL = 'https://dgatewayapi.desispay.com/v1/wordpress-plugin/update';

    /** WP transient key for the cached remote response. */
    const CACHE_KEY = 'dgw_plugin_update_info';

    /** How long to cache the remote response on the customer's site. */
    const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /** Plugin slug — must match the installed folder name AND the GitHub repo name. */
    const SLUG = 'dgateway-plugin';

    /**
     * Register the WordPress hooks. Called once from the main plugin bootstrap.
     */
    public function __construct() {
        // The transient filter is where the magic happens — WP merges our
        // entry into its update list and the Plugins page renders the
        // "Update available" notice from it.
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);

        // "View details" modal in the Plugins page calls this filter with
        // action='plugin_information'. Without it, the modal shows
        // "An unexpected error has occurred."
        add_filter('plugins_api', [$this, 'plugins_api'], 10, 3);

        // The release zip is flat — no top-level dgateway-plugin/ folder.
        // Rename the extracted source so WP installs into the right slug
        // directory rather than creating a parallel install.
        add_filter('upgrader_source_selection', [$this, 'fix_source_dir'], 10, 4);

        // Bust our own cache when an update completes so the next page load
        // doesn't keep offering the just-installed version.
        add_action('upgrader_process_complete', [$this, 'after_update'], 10, 2);
    }

    /**
     * Inject our update info into the update_plugins transient when a newer
     * remote version is available.
     *
     * @param  object $transient The WP-built transient object.
     * @return object            Possibly augmented with our plugin entry.
     */
    public function inject_update($transient) {
        // 'checked' is the dictionary WP filled in before invoking us. Empty
        // means WP hasn't decided to run an update check yet — bail rather
        // than risk a phantom entry.
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_info();
        if (!$remote || empty($remote->version)) {
            return $transient;
        }

        if (version_compare(DGW_VERSION, $remote->version, '>=')) {
            // Already on the latest — nothing to inject.
            return $transient;
        }

        $plugin_file = DGW_PLUGIN_BASENAME; // e.g. "dgateway-plugin/dgateway.php"

        $transient->response[$plugin_file] = (object) [
            'id'           => self::SLUG . '/' . self::SLUG,
            'slug'         => self::SLUG,
            'plugin'       => $plugin_file,
            'new_version'  => $remote->version,
            'url'          => isset($remote->homepage) ? $remote->homepage : '',
            'package'      => $remote->download_url,
            'tested'       => isset($remote->tested) ? $remote->tested : '',
            'requires'     => isset($remote->requires) ? $remote->requires : '',
            'requires_php' => isset($remote->requires_php) ? $remote->requires_php : '',
            'icons'        => [
                '1x' => DGW_PLUGIN_URL . 'assets/images/logo.png',
                '2x' => DGW_PLUGIN_URL . 'assets/images/logo.png',
            ],
        ];

        return $transient;
    }

    /**
     * Provide the data WP needs to render the "View details" modal that
     * appears when the user clicks "View version X details" on the Plugins
     * page.
     *
     * @param  false|object|array $result Default plugins_api result (false unless we override).
     * @param  string             $action The plugins_api action.
     * @param  object             $args   Request args (slug, etc.).
     * @return false|object|array         Plugin info object or the passed-through default.
     */
    public function plugins_api($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        if (empty($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }

        $remote = $this->get_remote_info();
        if (!$remote || empty($remote->version)) {
            return $result;
        }

        $sections = isset($remote->sections) ? (array) $remote->sections : [];

        return (object) [
            'name'           => isset($remote->name) ? $remote->name : 'DGateway Payment Gateway',
            'slug'           => self::SLUG,
            'version'        => $remote->version,
            'requires'       => isset($remote->requires) ? $remote->requires : '',
            'requires_php'   => isset($remote->requires_php) ? $remote->requires_php : '',
            'tested'         => isset($remote->tested) ? $remote->tested : '',
            'author'         => isset($remote->author) ? $remote->author : 'DesisPay',
            'last_updated'   => isset($remote->last_updated) ? $remote->last_updated : '',
            'homepage'       => isset($remote->homepage) ? $remote->homepage : '',
            'download_link'  => $remote->download_url,
            'sections'       => $sections,
            'banners'        => [
                'low'  => DGW_PLUGIN_URL . 'assets/images/logo.png',
                'high' => DGW_PLUGIN_URL . 'assets/images/logo.png',
            ],
        ];
    }

    /**
     * The release zip published on GitHub is flat (assets/, includes/,
     * dgateway.php at the root) rather than nested inside a top-level
     * dgateway-plugin/ folder. WP's installer takes whatever folder it
     * finds after extraction and moves it to wp-content/plugins/<that-name>
     * — which for a flat zip would be a random temp folder name, resulting
     * in a broken parallel install.
     *
     * This filter runs after extraction but before the move. We rename the
     * source directory to the correct slug so WP overwrites the existing
     * plugin folder cleanly.
     *
     * @param  string       $source        Extracted source directory path.
     * @param  string       $remote_source Original remote source path.
     * @param  WP_Upgrader  $upgrader      The upgrader instance.
     * @param  array        $hook_extra    Extra context (plugin slug etc.).
     * @return string|WP_Error             The (possibly renamed) source path.
     */
    public function fix_source_dir($source, $remote_source, $upgrader, $hook_extra = null) {
        // Only act on this plugin's update path. Hook_extra carries the
        // plugin file when an actual update is running; skip if it's a
        // generic install or a different plugin's update.
        if (!is_array($hook_extra) || empty($hook_extra['plugin'])) {
            return $source;
        }
        if ($hook_extra['plugin'] !== DGW_PLUGIN_BASENAME) {
            return $source;
        }

        // If the extracted folder already has the correct slug, nothing to do.
        $current_basename = basename(untrailingslashit($source));
        if ($current_basename === self::SLUG) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            return $source; // filesystem unavailable — let WP fail gracefully
        }

        $new_source = trailingslashit(dirname($source)) . self::SLUG . '/';

        // If the target name already exists from a previous attempt, clear it.
        if ($wp_filesystem->exists($new_source)) {
            $wp_filesystem->delete($new_source, true);
        }

        if ($wp_filesystem->move($source, $new_source)) {
            return $new_source;
        }

        return new WP_Error(
            'dgw_rename_failed',
            __('DGateway updater: could not rename extracted folder.', 'dgateway')
        );
    }

    /**
     * Bust the cached remote-info transient after any plugin update so the
     * next admin page-load doesn't immediately re-offer the version that
     * was just installed.
     */
    public function after_update($upgrader, $options) {
        if (!is_array($options)) {
            return;
        }
        if (!isset($options['type']) || $options['type'] !== 'plugin') {
            return;
        }
        if (!isset($options['plugins']) || !in_array(DGW_PLUGIN_BASENAME, (array) $options['plugins'], true)) {
            return;
        }
        delete_transient(self::CACHE_KEY);
    }

    /**
     * Fetch remote update info, caching the result for CACHE_TTL.
     *
     * Returns an object on success, null on any error (parsing, HTTP, etc.).
     * Errors are intentionally swallowed — a failed update check should NEVER
     * surface as a WP admin warning. The user will just continue running
     * the version they have, which is the safe degraded behaviour.
     *
     * @return object|null
     */
    private function get_remote_info() {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_object($cached) && isset($cached->version)) {
            return $cached;
        }

        $response = wp_remote_get(self::UPDATE_URL, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/json',
                'User-Agent' => 'dgateway-wp-plugin/' . DGW_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $info = json_decode($body);
        if (!$info || empty($info->version)) {
            return null;
        }

        set_transient(self::CACHE_KEY, $info, self::CACHE_TTL);
        return $info;
    }
}
