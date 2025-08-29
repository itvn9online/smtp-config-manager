<?php

/**
 * Plugin Name: SMTP Config Manager
 * Plugin URI: https://echbay.com
 * Description: Quản lý cấu hình SMTP cho hệ thống email marketing
 * Version: 1.2.4
 * Author: Dao Quoc Dai
 * License: GPL v2 or later
 * Text Domain: smtp-config-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SCM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SCM_PLUGIN_VERSION', '1.2.4');
// Define plugin prefix based on the current host
define('SCM_PLUGIN_PREFIX', str_replace([
    'www.',
    '.',
    '-'
], '', explode(':', $_SERVER['HTTP_HOST'])[0]) . '_');
// die(SCM_PLUGIN_PREFIX);

// Include required files
require_once SCM_PLUGIN_PATH . 'includes/class-smtp-config-manager.php';

// Initialize the plugin
function smtp_config_manager_init()
{
    new SMTP_Config_Manager();
}
add_action('init', 'smtp_config_manager_init');

// Plugin activation hook
register_activation_hook(__FILE__, 'smtp_config_manager_activate');
function smtp_config_manager_activate()
{
    global $wpdb;

    // Create email tracking table
    $table_name = $wpdb->prefix . 'email_tracking';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        marketing_id int(11) DEFAULT NULL,
        tracking_token varchar(64) NOT NULL,
        opened_at datetime DEFAULT NULL,
        user_agent text DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tracking_token (tracking_token),
        KEY email (email),
        KEY marketing_id (marketing_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Add opened_at column to mail marketing table if not exists
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM `{$wpdb->prefix}mail_marketing` LIKE %s",
        'opened_at'
    ));

    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE `{$wpdb->prefix}mail_marketing` ADD COLUMN `opened_at` datetime DEFAULT NULL");
    }
}

// Add settings link to plugin list
if (strpos($_SERVER['REQUEST_URI'], '/plugins.php') !== false) {
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'smtp_config_manager_settings_link');
}
function smtp_config_manager_settings_link($links)
{
    $settings_link = '<a href="' . admin_url('options-general.php?page=smtp-config-manager') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Function to get SMTP settings
function get_smtp_settings()
{
    return array(
        'enabled' => get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_enabled', '1'),
        'host' => get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_host'),
        'port' => get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_port', '587'),
        'username' => get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_username'),
        'password' => base64_decode(get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_password')),
        'encryption' => get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_encryption', 'tls'),
        'from_email' => get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_from_email'),
        'from_name' => get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_from_name', get_option('blogname', $_SERVER['HTTP_HOST'])),
        'debug' => get_option(SCM_PLUGIN_PREFIX . 'scm_smtp_debug', '0')
    );
}

// Configure WordPress mail to use SMTP settings
function configure_wp_mail_smtp()
{
    $smtp_settings = get_smtp_settings();

    if ($smtp_settings['enabled'] == '1') {
        add_filter('phpmailer_init', function ($phpmailer) use ($smtp_settings) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp_settings['host'];
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $smtp_settings['username'];
            $phpmailer->Password = $smtp_settings['password'];
            $phpmailer->Port = $smtp_settings['port'];

            if ($smtp_settings['encryption'] == 'tls') {
                $phpmailer->SMTPSecure = 'tls';
            } elseif ($smtp_settings['encryption'] == 'ssl') {
                $phpmailer->SMTPSecure = 'ssl';
            }

            // Enable HTML support for all emails
            $phpmailer->isHTML(true);
            $phpmailer->CharSet = 'UTF-8';
            $phpmailer->Encoding = 'base64';

            // Set proper From address and name
            $phpmailer->From = $smtp_settings['from_email'];
            $phpmailer->FromName = $smtp_settings['from_name'];

            // Configure debug settings
            $phpmailer->SMTPDebug = intval($smtp_settings['debug']);

            // Set additional options for better compatibility
            $phpmailer->WordWrap = 80;
            $phpmailer->XMailer = 'SMTP Config Manager Plugin v' . SCM_PLUGIN_VERSION;

            // Set timeout values
            $phpmailer->Timeout = 30;
            $phpmailer->SMTPKeepAlive = false;

            // Enable SMTP options for better delivery
            $phpmailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        });

        // Ensure HTML content type is set for wp_mail
        add_filter('wp_mail_content_type', function ($content_type) {
            return 'text/html';
        });

        // Set default charset
        add_filter('wp_mail_charset', function ($charset) {
            return 'UTF-8';
        });
    }
}

// Initialize SMTP configuration
add_action('init', 'configure_wp_mail_smtp');
