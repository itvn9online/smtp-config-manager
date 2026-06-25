<?php

/**
 * Auto Updater for SMTP Config Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCM_Auto_Updater
{
    private $plugin_slug;
    private $plugin_file;
    private $version;
    private $github_user;
    private $github_repo;
    private $github_version_url;
    private $github_download_url;

    public function __construct($plugin_file)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = SCM_PLUGIN_VERSION;
        $this->github_user = 'itvn9online';
        $this->github_repo = 'smtp-config-manager';
        $this->github_version_url = 'https://raw.githubusercontent.com/' . $this->github_user . '/' . $this->github_repo . '/refs/heads/main/VERSION';
        $this->github_download_url = 'https://github.com/' . $this->github_user . '/' . $this->github_repo . '/archive/refs/heads/main.zip';

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'upgrader_post_install'), 10, 3);
    }

    /**
     * Canonical plugin directory name (without GitHub archive -main suffix).
     */
    private function get_canonical_plugin_dir()
    {
        $dir = dirname($this->plugin_slug);

        if (substr($dir, -5) === '-main') {
            $dir = substr($dir, 0, -5);
        }

        return $dir !== '.' ? $dir : $this->github_repo;
    }

    /**
     * Check for plugin updates
     */
    public function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get remote version
        $remote_version = $this->get_remote_version();

        if (!$remote_version) {
            return $transient;
        }

        // Compare versions
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
                'package' => $this->github_download_url,
                'tested' => get_bloginfo('version'),
                'compatibility' => array()
            );
        } else {
            // Plugin is up to date — clear stale update response and mark as no_update
            if (isset($transient->response[$this->plugin_slug])) {
                unset($transient->response[$this->plugin_slug]);
            }
            $transient->no_update[$this->plugin_slug] = (object) array(
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_version,
                'url' => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
                'package' => '',
                'tested' => get_bloginfo('version'),
                'compatibility' => array()
            );
        }

        return $transient;
    }

    /**
     * Get remote version from GitHub (VERSION or version.txt).
     */
    private function get_remote_version()
    {
        $version_urls = array(
            $this->github_version_url,
            'https://raw.githubusercontent.com/' . $this->github_user . '/' . $this->github_repo . '/refs/heads/main/version.txt',
        );

        foreach ($version_urls as $url) {
            $request = wp_remote_get($url, array(
                'timeout' => 10,
                'sslverify' => false,
            ));

            if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
                $version = trim(wp_remote_retrieve_body($request));
                if ($version !== '') {
                    return $version;
                }
            }
        }

        return false;
    }

    /**
     * Plugin information for update popup
     */
    public function plugin_info($false, $action, $response)
    {
        if ($action !== 'plugin_information') {
            return $false;
        }

        if ($response->slug !== dirname($this->plugin_slug)) {
            return $false;
        }

        $remote_version = $this->get_remote_version();

        $response = (object) array(
            'name' => 'SMTP Config Manager',
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_version,
            'author' => 'Dao Quoc Dai',
            'homepage' => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
            'short_description' => 'Quản lý cấu hình SMTP cho hệ thống email marketing.',
            'sections' => array(
                'Description' => 'Plugin quản lý cấu hình SMTP cho hệ thống email marketing.',
                'Installation' => 'Upload plugin và activate. Cấu hình cronjob server để xử lý hàng đợi email.',
                'Changelog' => $this->get_changelog()
            ),
            'download_link' => $this->github_download_url,
            'trunk' => $this->github_download_url,
            'requires' => '5.0',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
            'last_updated' => date_i18n('Y-m-d H:i:s'),
            'upgrade_notice' => 'Phiên bản mới có sẵn. Vui lòng cập nhật để nhận các tính năng và sửa lỗi mới nhất.'
        );

        return $response;
    }

    /**
     * Get changelog from GitHub
     */
    private function get_changelog()
    {
        $changelog_url = 'https://github.com/' . $this->github_user . '/' . $this->github_repo . '/raw/refs/heads/main/CHANGELOG.md';

        $request = wp_remote_get($changelog_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            // Convert markdown to HTML (basic)
            $body = nl2br(esc_html($body));
            return $body;
        }

        return 'Không thể tải changelog.';
    }

    /**
     * Fix source directory after download (strip -main from GitHub archive folder name).
     */
    public function upgrader_source_selection($source, $remote_source, $upgrader)
    {
        global $wp_filesystem;

        if (!isset($upgrader->skin->plugin) || $upgrader->skin->plugin !== $this->plugin_slug) {
            return $source;
        }

        $canonical_dir = $this->get_canonical_plugin_dir();
        $desired_destination = trailingslashit($remote_source) . $canonical_dir;

        if (trailingslashit($source) === trailingslashit($desired_destination)) {
            return $source;
        }

        // GitHub archive extracts as {repo}-main/ — rename to canonical plugin directory
        if ($wp_filesystem->move($source, $desired_destination)) {
            return $desired_destination;
        }

        return $source;
    }

    /**
     * Rename plugin folder after install if it still has the -main suffix.
     */
    public function upgrader_post_install($response, $hook_extra, $result)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $response;
        }

        $current_dir = dirname($this->plugin_slug);
        $canonical_dir = $this->get_canonical_plugin_dir();

        if ($current_dir === $canonical_dir) {
            return $response;
        }

        global $wp_filesystem;

        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $from = trailingslashit(WP_PLUGIN_DIR) . $current_dir;
        $to = trailingslashit(WP_PLUGIN_DIR) . $canonical_dir;

        if (!$wp_filesystem->exists($from) || $wp_filesystem->exists($to)) {
            return $response;
        }

        if ($wp_filesystem->move($from, $to)) {
            $this->repoint_active_plugin($current_dir, $canonical_dir);
        }

        return $response;
    }

    /**
     * Update active_plugins after directory rename.
     */
    private function repoint_active_plugin($old_dir, $new_dir)
    {
        $plugin_file = basename($this->plugin_file);
        $old_slug = $old_dir . '/' . $plugin_file;
        $new_slug = $new_dir . '/' . $plugin_file;

        $active = get_option('active_plugins', array());
        if (is_array($active)) {
            $key = array_search($old_slug, $active, true);
            if ($key !== false) {
                $active[$key] = $new_slug;
                update_option('active_plugins', $active);
            }
        }

        if (!is_multisite()) {
            return;
        }

        $network_active = get_site_option('active_sitewide_plugins', array());
        if (isset($network_active[$old_slug])) {
            $network_active[$new_slug] = $network_active[$old_slug];
            unset($network_active[$old_slug]);
            update_site_option('active_sitewide_plugins', $network_active);
        }
    }

    /**
     * Manual check for updates (for admin page)
     */
    public function manual_check_update()
    {
        $remote_version = $this->get_remote_version();

        if (!$remote_version) {
            return array(
                'success' => false,
                'message' => 'Không thể kiểm tra phiên bản mới từ GitHub.'
            );
        }

        $current_version = $this->version;
        $has_update = version_compare($current_version, $remote_version, '<');

        return array(
            'success' => true,
            'current_version' => $current_version,
            'remote_version' => $remote_version,
            'has_update' => $has_update,
            'message' => $has_update
                ? "Có phiên bản mới: {$remote_version} (hiện tại: {$current_version})"
                : "Plugin đang ở phiên bản mới nhất: {$current_version}"
        );
    }
}
