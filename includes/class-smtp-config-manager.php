<?php

/**
 * Main SMTP Config Manager Class
 */
class SMTP_Config_Manager
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_test_smtp', array($this, 'test_smtp_connection'));
        add_action('wp_ajax_test_gmail_api', array($this, 'test_gmail_api_connection'));

        // Initialize tracking table on activation
        register_activation_hook(SCM_PLUGIN_PATH . 'smtp-config-manager.php', array($this, 'create_tracking_table'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'tools.php',
            'SMTP Config Manager',
            'SMTP Settings',
            'manage_options',
            'smtp-config-manager',
            array($this, 'admin_page')
        );

        // Add email tracking stats submenu
        add_submenu_page(
            'tools.php',
            'Email Tracking Stats',
            'Email Tracking',
            'manage_options',
            'email-tracking-stats',
            array($this, 'tracking_stats_page')
        );
    }

    /**
     * Initialize admin settings
     */
    public function admin_init()
    {
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_host');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_port');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_username');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_password');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_encryption');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_from_email');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_from_name');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_debug');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_smtp_enabled');

        // Gmail API settings
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'scm_email_method');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'gmail_client_id');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'gmail_client_secret');
        register_setting('scm_smtp_settings', SCM_PLUGIN_PREFIX . 'gmail_authenticated');

        // Xử lý Gmail OAuth callback
        if (isset($_GET['action']) && $_GET['action'] === 'gmail_callback' && isset($_GET['code'])) {
            $this->handle_gmail_callback();
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'tools_page_smtp-config-manager' && $hook !== 'tools_page_email-tracking-stats') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_script('scm-admin-js', SCM_PLUGIN_URL . 'assets/admin.js', array('jquery'), filemtime(SCM_PLUGIN_PATH . 'assets/admin.js'), true);
        wp_enqueue_style('scm-admin-css', SCM_PLUGIN_URL . 'assets/admin.css', array(), filemtime(SCM_PLUGIN_PATH . 'assets/admin.css'));

        // Gmail API assets
        if (file_exists(SCM_PLUGIN_PATH . 'assets/gmail-api-admin.js')) {
            wp_enqueue_script('scm-gmail-admin-js', SCM_PLUGIN_URL . 'assets/gmail-api-admin.js', array('jquery'), filemtime(SCM_PLUGIN_PATH . 'assets/gmail-api-admin.js'), true);
        }
        if (file_exists(SCM_PLUGIN_PATH . 'assets/gmail-api-admin.css')) {
            wp_enqueue_style('scm-gmail-admin-css', SCM_PLUGIN_URL . 'assets/gmail-api-admin.css', array(), filemtime(SCM_PLUGIN_PATH . 'assets/gmail-api-admin.css'));
        }

        wp_localize_script('scm-admin-js', 'scm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scm_test_smtp'),
            // 'plugin_prefix' => SCM_PLUGIN_PREFIX
        ));

        // Gmail API AJAX nonce
        if (wp_script_is('scm-gmail-admin-js', 'enqueued')) {
            wp_localize_script('scm-gmail-admin-js', 'scmGmailAjax', array(
                'nonce' => wp_create_nonce('scm_gmail_ajax'),
                'test_nonce' => wp_create_nonce('scm_test_gmail_api'),
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }
    }

    /**
     * Admin page HTML
     */
    public function admin_page()
    {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['scm_nonce'], 'scm_save_settings')) {
            $this->save_settings();
        }

        // Display messages
        $this->display_admin_notices();

        $smtp_settings = get_smtp_settings();
        $gmail_settings = $this->get_gmail_settings();
?>
        <script>
            // Define SCM_PLUGIN_PREFIX for JavaScript compatibility
            let SCM_PLUGIN_PREFIX = '<?php echo SCM_PLUGIN_PREFIX; ?>';
        </script>
        <div class="wrap">
            <h1>SMTP Configuration Manager</h1>

            <div class="scm-container">
                <div class="scm-main-content">
                    <form method="post" action="">
                        <?php wp_nonce_field('scm_save_settings', 'scm_nonce'); ?>

                        <!-- Email Method Selection -->
                        <h2>📧 Email Sending Method</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">Phương thức gửi email</th>
                                <td>
                                    <label>
                                        <input type="radio" name="scm_email_method" value="smtp" <?php checked($gmail_settings['email_method'], 'smtp'); ?>>
                                        SMTP (Traditional)
                                    </label><br>
                                    <label>
                                        <input type="radio" name="scm_email_method" value="gmail_api" <?php checked($gmail_settings['email_method'], 'gmail_api'); ?>>
                                        Gmail API (Khuyên dùng - ổn định hơn)
                                    </label>
                                    <p class="description">Gmail API thường có độ tin cậy và tỷ lệ gửi thành công cao hơn SMTP truyền thống.</p>
                                </td>
                            </tr>
                        </table>

                        <!-- Gmail API Settings -->
                        <div id="gmail-api-settings" style="display: <?php echo $gmail_settings['email_method'] === 'gmail_api' ? 'block' : 'none'; ?>;">
                            <h2>🔧 Gmail API Configuration</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Google Client ID</th>
                                    <td>
                                        <input type="text" name="gmail_client_id" value="<?php echo esc_attr($gmail_settings['client_id']); ?>" class="regular-text">
                                        <p class="description">Lấy từ <a href="https://console.developers.google.com" target="_blank">Google Cloud Console</a></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Google Client Secret</th>
                                    <td>
                                        <input type="text" name="gmail_client_secret" value="<?php echo esc_attr($gmail_settings['client_secret']); ?>" class="regular-text" style="color: transparent;">
                                        <p class="description">Client secret từ Google Cloud Console</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Google Refresh Token</th>
                                    <td>
                                        <input type="text" name="gmail_refresh_token" value="<?php echo esc_attr($gmail_settings['refresh_token']); ?>" class="regular-text" style="color: transparent;">
                                        <p class="description">Refresh token từ Google OAuth (tùy chọn - sẽ được tự động tạo khi authorize)</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Trạng thái Authorization</th>
                                    <td>
                                        <?php if ($gmail_settings['authenticated'] === '1'): ?>
                                            <span style="color: #46b450;">✅ Đã được ủy quyền</span>
                                            <a href="#" id="gmail-reauth" class="button button-secondary">Ủy quyền lại</a>
                                        <?php else: ?>
                                            <span style="color: #dc3232;">❌ Chưa ủy quyền</span>
                                            <a href="#" id="gmail-authorize" class="button button-primary">Ủy quyền Gmail API</a>
                                        <?php endif; ?>
                                        <p class="description">Cần ủy quyền tài khoản Google để gửi email qua Gmail API</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Redirect URI</th>
                                    <td>
                                        <input type="text" value="<?php echo admin_url('tools.php?page=smtp-config-manager&action=gmail_callback'); ?>" class="large-text" readonly onclick="this.select();">
                                        <p class="description">Copy URL này và thêm vào Google Cloud Console project như một authorized redirect URI</p>
                                    </td>
                                </tr>
                            </table>

                            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 15px 0;">
                                <h4>🔧 Hướng dẫn setup Gmail API:</h4>
                                <ol>
                                    <li>Truy cập <a href="https://console.developers.google.com" target="_blank">Google Cloud Console</a></li>
                                    <li>Tạo project mới hoặc chọn project có sẵn</li>
                                    <li>Enable Gmail API cho project</li>
                                    <li>Tạo OAuth 2.0 credentials (Application type: Web application)</li>
                                    <li>Thêm Redirect URI ở trên vào OAuth client</li>
                                    <li>Copy Client ID và Client Secret vào form</li>
                                    <li>Lưu settings và click "Ủy quyền Gmail API"</li>
                                </ol>
                            </div>
                        </div>

                        <!-- Common Email Settings (Used by both SMTP and Gmail API) -->
                        <h2>📤 Common Email Settings</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">From Email</th>
                                <td>
                                    <input type="email" name="scm_smtp_from_email" value="<?php echo esc_attr($smtp_settings['from_email']); ?>" class="regular-text">
                                    <p class="description">Email address to send from (used by both SMTP and Gmail API)</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">From Name</th>
                                <td>
                                    <input type="text" name="scm_smtp_from_name" value="<?php echo esc_attr($smtp_settings['from_name']); ?>" class="regular-text">
                                    <p class="description">Name to display as sender (used by both SMTP and Gmail API). Using Blog Name if empty.</p>
                                </td>
                            </tr>
                        </table>

                        <!-- SMTP Settings -->
                        <div id="smtp-settings" style="display: <?php echo $gmail_settings['email_method'] !== 'gmail_api' ? 'block' : 'none'; ?>;">
                            <h2>🔧 SMTP Configuration</h2>
                            <table class="form-table smtp-setting-table">
                                <tr>
                                    <th scope="row">Enable SMTP</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="scm_smtp_enabled" value="1" <?php checked($smtp_settings['enabled'], '1'); ?>>
                                            Enable SMTP for email sending
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">SMTP Host</th>
                                    <td>
                                        <input type="text" name="scm_smtp_host" value="<?php echo esc_attr($smtp_settings['host']); ?>" class="regular-text">
                                        <p class="description">SMTP server address (e.g., smtp.gmail.com)</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">SMTP Port</th>
                                    <td>
                                        <input type="number" name="scm_smtp_port" value="<?php echo esc_attr($smtp_settings['port']); ?>" class="small-text" min="1" max="65535">
                                        <p class="description">Common ports: 25, 465 (SSL), 587 (TLS)</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Username</th>
                                    <td>
                                        <input type="text" name="scm_smtp_username" value="<?php echo esc_attr($smtp_settings['username']); ?>" class="regular-text">
                                        <p class="description">SMTP username (usually your email address)</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Password</th>
                                    <td>
                                        <input type="text" name="scm_smtp_password" value="<?php echo esc_attr($smtp_settings['password']); ?>" class="regular-text is-hidden-text">
                                        <p class="description">SMTP password or app password</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Encryption</th>
                                    <td>
                                        <select name="scm_smtp_encryption">
                                            <option value="none" <?php selected($smtp_settings['encryption'], 'none'); ?>>None</option>
                                            <option value="tls" <?php selected($smtp_settings['encryption'], 'tls'); ?>>TLS</option>
                                            <option value="ssl" <?php selected($smtp_settings['encryption'], 'ssl'); ?>>SSL</option>
                                        </select>
                                        <p class="description">Use TLS for port 587, SSL for port 465</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Debug Level</th>
                                    <td>
                                        <select name="scm_smtp_debug">
                                            <option value="0" <?php selected($smtp_settings['debug'], '0'); ?>>Disabled</option>
                                            <option value="1" <?php selected($smtp_settings['debug'], '1'); ?>>Client messages</option>
                                            <option value="2" <?php selected($smtp_settings['debug'], '2'); ?>>Client and server messages</option>
                                            <option value="3" <?php selected($smtp_settings['debug'], '3'); ?>>Connection status</option>
                                            <option value="4" <?php selected($smtp_settings['debug'], '4'); ?>>Low-level data</option>
                                        </select>
                                        <p class="description">Debug level for troubleshooting (0 = off)</p>
                                    </td>
                                </tr>
                            </table>
                        </div> <!-- Close SMTP settings div -->

                        <p class="submit">
                            <input type="submit" name="submit" class="button-primary" value="Save Settings">
                            <button type="button" id="test-smtp" class="button button-secondary" style="display: none;">Test SMTP Connection</button>
                            <button type="button" id="test-gmail-api" class="button button-secondary" style="display: none;">Test Gmail API</button>
                        </p>
                    </form>

                    <div class="scm-test-result" id="test-result" style="display: none;">
                        <h3 style="padding-top: 8px;">Test Result</h3>
                        <div id="test-output"></div>
                    </div>


                </div>

                <div class="scm-sidebar">
                    <div class="scm-info-box">
                        <h3>Email Tracking</h3>
                        <p>Track who opens your marketing emails with detailed statistics.</p>
                        <p><a href="<?php echo admin_url('tools.php?page=email-tracking-stats'); ?>" class="button button-secondary">View Email Tracking Stats</a></p>
                    </div>

                    <div class="scm-info-box">
                        <h3>Hướng dẫn cấu hình SMTP</h3>

                        <div class="smtp-provider-guide">
                            <h4>🔧 Gmail SMTP</h4>
                            <p><strong>Cài đặt:</strong><br>
                                Host: smtp.gmail.com<br>Port: 587<br>Encryption: TLS</p>
                            <p><strong>Cách tạo tài khoản:</strong></p>
                            <ol>
                                <li>Truy cập <a href="https://gmail.com" target="_blank">gmail.com</a> và tạo tài khoản Gmail</li>
                                <li>Bật xác thực 2 bước trong <a href="https://myaccount.google.com/signinoptions/twosv" target="_blank">Google Account Settings</a></li>
                                <li>Tạo <strong>App Password</strong> tại Security > <a href="https://myaccount.google.com/apppasswords" target="_blank">App passwords</a></li>
                                <li>Sử dụng App Password thay vì mật khẩu thường</li>
                            </ol>
                        </div>

                        <div class="smtp-provider-guide">
                            <h4>🔧 Outlook/Hotmail SMTP</h4>
                            <p><strong>Cài đặt:</strong><br>
                                Host: smtp.live.com<br>Port: 587<br>Encryption: TLS</p>
                            <p><strong>Cách tạo tài khoản:</strong></p>
                            <ol>
                                <li>Truy cập <a href="https://outlook.com" target="_blank">outlook.com</a> và đăng ký tài khoản</li>
                                <li>Vào Settings > Mail > Sync email</li>
                                <li>Bật <strong>IMAP</strong> và <strong>SMTP</strong></li>
                                <li>Sử dụng email và mật khẩu tài khoản</li>
                            </ol>
                        </div>

                        <div class="smtp-provider-guide">
                            <h4>🔧 Yahoo Mail SMTP</h4>
                            <p><strong>Cài đặt:</strong><br>
                                Host: smtp.mail.yahoo.com<br>Port: 587<br>Encryption: TLS</p>
                            <p><strong>Cách tạo tài khoản:</strong></p>
                            <ol>
                                <li>Tạo tài khoản tại <a href="https://yahoo.com" target="_blank">yahoo.com</a></li>
                                <li>Vào Account Info > Account Security</li>
                                <li>Bật <strong>2-step verification</strong></li>
                                <li>Tạo <strong>App Password</strong> cho ứng dụng email</li>
                            </ol>
                        </div>

                        <div class="smtp-provider-guide">
                            <h4>🔧 Zoho Mail SMTP</h4>
                            <p><strong>Cài đặt:</strong><br>
                                Host: smtp.zoho.com<br>Port: 587<br>Encryption: TLS</p>
                            <p><strong>Cách tạo tài khoản:</strong></p>
                            <ol>
                                <li>Đăng ký miễn phí tại <a href="https://zoho.com/mail" target="_blank">zoho.com/mail</a></li>
                                <li>Xác minh domain (nếu dùng domain riêng)</li>
                                <li>Tạo mailbox trong Zoho Mail Admin</li>
                                <li>Sử dụng email và mật khẩu đã tạo</li>
                            </ol>
                        </div>

                        <div class="smtp-provider-guide amazon-ses">
                            <h4>🚀 Amazon SES SMTP (Khuyên dùng cho marketing)</h4>
                            <p><strong>Cài đặt:</strong><br>
                                Host: email-smtp.{region}.amazonaws.com<br>
                                Port: 587<br>Encryption: TLS</p>
                            <p><strong>Ưu điểm:</strong> Giá rẻ ($0.10/1000 email), độ tin cậy cao, phù hợp gửi hàng loạt</p>

                            <p><strong>Hướng dẫn tạo tài khoản Amazon SES:</strong></p>
                            <ol>
                                <li><strong>Tạo tài khoản AWS:</strong>
                                    <ol>
                                        <li>Truy cập <a href="https://aws.amazon.com" target="_blank">aws.amazon.com</a></li>
                                        <li>Nhấn "Create an AWS Account"</li>
                                        <li>Điền thông tin và xác minh thẻ tín dụng</li>
                                    </ol>
                                </li>
                                <li><strong>Cấu hình SES:</strong>
                                    <ol>
                                        <li>Vào AWS Console > SES (<a href="https://us-east-2.console.aws.amazon.com/ses/home?region=us-east-2#/account" target="_blank">Simple Email Service</a>)</li>
                                        <li>Chọn region gần nhất (Ví dụ: Singapore ap-southeast-1)</li>
                                        <li>Verify email hoặc domain trong "Identities"</li>
                                        <li>Request production access (thoát sandbox mode)</li>
                                    </ol>
                                </li>
                                <li><strong>Tạo SMTP credentials:</strong>
                                    <ol>
                                        <li>Vào SES > SMTP Settings</li>
                                        <li>Nhấn "Create SMTP Credentials"</li>
                                        <li>Tạo IAM user với quyền SES</li>
                                        <li>Lưu SMTP username và password</li>
                                    </ol>
                                </li>
                                <li><strong>Cài đặt trong plugin:</strong>
                                    <ol>
                                        <li>Host: email-smtp.ap-southeast-1.amazonaws.com</li>
                                        <li>Port: 587, Encryption: TLS</li>
                                        <li>Username/Password: từ SMTP credentials</li>
                                        <li>From Email: email đã verify</li>
                                    </ol>
                                </li>
                            </ol>

                            <div style="background: #fff3cd; padding: 8px; border-radius: 4px; margin: 10px 0; font-size: 11px;">
                                <strong>💡 Lưu ý:</strong> SES mặc định ở Sandbox mode (chỉ gửi cho email đã verify).
                                Cần request production access để gửi cho mọi email.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Save SMTP settings
     */
    private function save_settings()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $prefix = SCM_PLUGIN_PREFIX;
        $fields = array(
            // Email method selection
            'scm_email_method' => isset($_POST['scm_email_method']) ? sanitize_text_field($_POST['scm_email_method']) : 'smtp',

            // SMTP settings
            'scm_smtp_enabled' => isset($_POST['scm_smtp_enabled']) ? '1' : '0',
            'scm_smtp_host' => isset($_POST['scm_smtp_host']) ? sanitize_text_field($_POST['scm_smtp_host']) : '',
            'scm_smtp_port' => isset($_POST['scm_smtp_port']) ? sanitize_text_field($_POST['scm_smtp_port']) : '587',
            'scm_smtp_username' => isset($_POST['scm_smtp_username']) ? sanitize_text_field($_POST['scm_smtp_username']) : '',
            'scm_smtp_password' => isset($_POST['scm_smtp_password']) ? $this->encode_password(sanitize_text_field($_POST['scm_smtp_password'])) : '',
            'scm_smtp_encryption' => isset($_POST['scm_smtp_encryption']) ? sanitize_text_field($_POST['scm_smtp_encryption']) : 'tls',
            'scm_smtp_from_email' => isset($_POST['scm_smtp_from_email']) ? sanitize_email($_POST['scm_smtp_from_email']) : '',
            'scm_smtp_from_name' => isset($_POST['scm_smtp_from_name']) ? sanitize_text_field($_POST['scm_smtp_from_name']) : '',
            'scm_smtp_debug' => isset($_POST['scm_smtp_debug']) ? sanitize_text_field($_POST['scm_smtp_debug']) : '0',

            // Gmail API settings
            'gmail_client_id' => isset($_POST['gmail_client_id']) ? sanitize_text_field($_POST['gmail_client_id']) : '',
            'gmail_client_secret' => isset($_POST['gmail_client_secret']) ? sanitize_text_field($_POST['gmail_client_secret']) : '',
            'gmail_refresh_token' => isset($_POST['gmail_refresh_token']) ? sanitize_text_field($_POST['gmail_refresh_token']) : ''
        );

        foreach ($fields as $field => $value) {
            update_option($prefix . $field, $value);
        }

        add_settings_error('scm_messages', 'scm_message', 'Settings saved successfully!', 'success');
    }

    /**
     * Display admin notices
     */
    private function display_admin_notices()
    {
        settings_errors('scm_messages');
    }

    /**
     * Encode password for storage
     */
    private function encode_password($password)
    {
        return base64_encode($password);
    }

    /**
     * Test SMTP connection
     */
    public function test_smtp_connection()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'scm_test_smtp')) {
            wp_die('Security check failed ' . __FUNCTION__);
        }

        if (!current_user_can('manage_options')) {
            wp_die('Security check failed ' . __FUNCTION__);
        }

        $smtp_settings = get_smtp_settings();

        // Get test email from request or use admin email as fallback
        $test_email = isset($_POST['test_email']) && !empty(trim($_POST['test_email']))
            ? sanitize_email(trim($_POST['test_email']))
            : get_option('admin_email');

        // Validate email
        if (!is_email($test_email)) {
            wp_send_json_error(array(
                'message' => '❌ Invalid email address provided.',
                'debug' => 'Email validation failed for: ' . $test_email,
                'html_test' => false
            ));
            return;
        }

        $subject = '(' . $_SERVER['HTTP_HOST'] . ') SMTP Test Email - HTML Support Test - ' . current_time('mysql');

        // Rich HTML content for testing
        $message = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP HTML Test Email</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            margin: 0; 
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: #fff; 
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 30px; 
            text-align: center; 
        }
        .content { 
            padding: 30px; 
        }
        .feature-box {
            background: #f8f9fa;
            border-left: 4px solid #007cba;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        .table-test {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .table-test th, .table-test td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .table-test th {
            background-color: #007cba;
            color: white;
        }
        .table-test tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .footer {
            background: #333;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background: #007cba;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .emoji { font-size: 1.2em; }
        .highlight { background: yellow; padding: 2px 4px; }
        ul li { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 SMTP HTML Test Email</h1>
            <p>Testing HTML email support via SMTP Config Manager</p>
        </div>
        
        <div class="content">
            <div class="success-badge">✅ HTML Support Test</div>
            
            <h2>Email Features Test</h2>
            <p>This email tests various HTML features to ensure your SMTP configuration supports rich HTML content:</p>
            
            <div class="feature-box">
                <h3>📧 Basic HTML Elements</h3>
                <ul>
                    <li><strong>Bold text</strong> and <em>italic text</em></li>
                    <li><u>Underlined text</u> and <span class="highlight">highlighted text</span></li>
                    <li>Links: <a href="https://wordpress.org" style="color: #007cba;">WordPress.org</a></li>
                    <li>Lists and nested content</li>
                </ul>
            </div>
            
            <div class="feature-box">
                <h3>🎨 CSS Styling Test</h3>
                <p>This section tests inline CSS, embedded CSS, and various styling features:</p>
                <ul>
                    <li>Background colors and gradients</li>
                    <li>Border radius and box shadows</li>
                    <li>Typography and spacing</li>
                    <li>Responsive design elements</li>
                </ul>
            </div>
            
            <h3>📊 Table Test</h3>
            <table class="table-test">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>HTML Support</td>
                        <td><span class="emoji">✅</span> Working</td>
                        <td>Rich HTML content displayed correctly</td>
                    </tr>
                    <tr>
                        <td>CSS Styling</td>
                        <td><span class="emoji">✅</span> Working</td>
                        <td>Inline and embedded CSS applied</td>
                    </tr>
                    <tr>
                        <td>Images</td>
                        <td><span class="emoji">🔗</span> External</td>
                        <td>External images may require additional setup</td>
                    </tr>
                    <tr>
                        <td>Links</td>
                        <td><span class="emoji">✅</span> Working</td>
                        <td>Clickable links functional</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="feature-box">
                <h3>⚙️ Technical Information</h3>
                <p><strong>Test Date:</strong> ' . date_i18n('F j, Y \a\t g:i A') . '</p>
                <p><strong>SMTP Host:</strong> ' . esc_html($smtp_settings['host']) . '</p>
                <p><strong>SMTP Port:</strong> ' . esc_html($smtp_settings['port']) . '</p>
                <p><strong>Encryption:</strong> ' . esc_html(strtoupper($smtp_settings['encryption'])) . '</p>
                <p><strong>From Email:</strong> ' . esc_html($smtp_settings['from_email']) . '</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . admin_url('tools.php?page=smtp-config-manager') . '" class="btn">
                    🔧 Back to SMTP Settings
                </a>
            </div>
            
            <h3>🎯 Marketing Email Features</h3>
            <p>This test also demonstrates features commonly used in marketing emails:</p>
            <ul>
                <li><span class="emoji">📧</span> Professional email layout</li>
                <li><span class="emoji">🎨</span> Custom styling and branding</li>
                <li><span class="emoji">📱</span> Responsive design elements</li>
                <li><span class="emoji">🔗</span> Call-to-action buttons</li>
                <li><span class="emoji">📊</span> Data tables and structured content</li>
                <li><span class="emoji">✨</span> Icons and emoji support</li>
            </ul>
        </div>
        
        <div class="footer">
            <p>🚀 <strong>SMTP Config Manager Plugin</strong></p>
            <p>This test email was sent successfully via your SMTP configuration!</p>
            <p style="font-size: 12px; opacity: 0.8;">
                If you can see this email with proper formatting, your SMTP setup supports HTML emails.
            </p>
        </div>
    </div>
</body>
</html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $smtp_settings['from_name'] . ' <' . $smtp_settings['from_email'] . '>',
            'Reply-To: ' . $smtp_settings['from_email']
        );

        // Capture debug output properly
        $debug_output = '';

        // Temporarily configure SMTP for test
        add_filter('phpmailer_init', function ($phpmailer) use ($smtp_settings, &$debug_output) {
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

            // Enable HTML support
            $phpmailer->isHTML(true);
            $phpmailer->CharSet = 'UTF-8';
            $phpmailer->Encoding = 'base64';

            // Set proper From address and name
            $phpmailer->From = $smtp_settings['from_email'];
            $phpmailer->FromName = $smtp_settings['from_name'];

            // SMTP Debug settings - capture to variable instead of echoing
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function ($str, $level) use (&$debug_output) {
                $debug_output .= "SMTP Debug Level $level: " . htmlspecialchars($str) . "<br>\n";
            };
        });

        $result = wp_mail($test_email, $subject, $message, $headers);

        if ($result) {
            // Check if custom email was used
            $email_note = (isset($_POST['test_email']) && !empty(trim($_POST['test_email'])))
                ? "Custom email address: " . $test_email
                : "Admin email address: " . $test_email;

            wp_send_json_success(array(
                'message' => '✅ HTML Test Email sent successfully to ' . $test_email . '! 
                
📧 <strong>Email Features Tested:</strong>
• Rich HTML content with CSS styling
• Tables, links, and formatting
• Emoji and icon support  
• Professional email layout
• Marketing email elements

📬 <strong>Email Delivery:</strong>
• ' . $email_note . '
• Please check the inbox and spam folder

🔧 <strong>SMTP Configuration:</strong>
• Host: ' . $smtp_settings['host'] . '
• Port: ' . $smtp_settings['port'] . '
• Encryption: ' . strtoupper($smtp_settings['encryption']) . '
• HTML Support: ✅ Enabled

📬 Please check your email inbox to verify that the HTML formatting is displayed correctly.',
                'debug' => $debug_output,
                'html_test' => true
            ));
        } else {
            wp_send_json_error(array(
                'message' => '❌ Failed to send HTML test email. Please check your SMTP settings.',
                'debug' => $debug_output,
                'html_test' => false
            ));
        }
    }

    /**
     * Create email tracking table on plugin activation
     */
    public function create_tracking_table()
    {
        global $wpdb;

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
    }

    /**
     * Generate tracking pixel for email
     */
    public function generate_tracking_pixel($email, $marketing_id = null)
    {
        global $wpdb;

        // Generate unique tracking token
        $tracking_token = wp_generate_password(32, false);

        // Insert tracking record
        $wpdb->insert(
            $wpdb->prefix . 'email_tracking',
            array(
                'email' => $email,
                'marketing_id' => $marketing_id,
                'tracking_token' => $tracking_token,
                'created_at' => current_time('mysql')
            )
        );

        // Generate tracking pixel URL
        $tracking_url = home_url() . '/wp-content/themes/marketing/api/v1/track/?t=' . $tracking_token;

        // Return tracking pixel HTML
        return '<img src="' . $tracking_url . '" width="1" height="1" style="display:none;" alt="">';
    }

    /**
     * Email tracking stats page
     */
    public function tracking_stats_page()
    {
        global $wpdb;

        // Handle actions
        if (isset($_GET['action']) && $_GET['action'] === 'clear_stats' && wp_verify_nonce($_GET['nonce'], 'clear_tracking_stats')) {
            $wpdb->query("DELETE FROM {$wpdb->prefix}email_tracking");
            echo '<div class="notice notice-success"><p>All tracking data has been cleared.</p></div>';
        }

        // Get tracking stats
        $tracking_table = $wpdb->prefix . 'email_tracking';
        $marketing_table = $wpdb->prefix . 'mail_marketing';

        // Overall stats
        $total_emails = $wpdb->get_var("SELECT COUNT(id) FROM $tracking_table");
        $opened_emails = $wpdb->get_var("SELECT COUNT(id) FROM $tracking_table WHERE opened_at IS NOT NULL");
        $open_rate = $total_emails > 0 ? round(($opened_emails / $total_emails) * 100, 2) : 0;

        // Recent opens (last 30 days) with pagination
        $ros_per_page = 20;
        $ros_page = isset($_GET['recent_page']) ? max(1, intval($_GET['recent_page'])) : 1;
        $ros_offset = ($ros_page - 1) * $ros_per_page;

        // Get total count for recent opens pagination
        $ros_total = $wpdb->get_var("
            SELECT COUNT(t.id)
            FROM $tracking_table t
            LEFT JOIN $marketing_table m ON t.marketing_id = m.id
            WHERE t.opened_at IS NOT NULL 
            AND t.opened_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");

        $recent_opens = $wpdb->get_results($wpdb->prepare("
            SELECT t.email, t.opened_at, t.user_agent, t.ip_address, m.name, m.phone
            FROM $tracking_table t
            LEFT JOIN $marketing_table m ON t.marketing_id = m.id
            WHERE t.opened_at IS NOT NULL 
            AND t.opened_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY t.opened_at DESC
            LIMIT %d OFFSET %d
        ", $ros_per_page, $ros_offset));

        $ros_total_pages = ceil($ros_total / $ros_per_page);

        // Top openers (limit to 50 records)
        $top_openers = $wpdb->get_results("
            SELECT t.email, COUNT(t.id) as open_count, MAX(t.opened_at) as last_opened, m.name, m.phone
            FROM $tracking_table t
            LEFT JOIN $marketing_table m ON t.marketing_id = m.id
            WHERE t.opened_at IS NOT NULL
            GROUP BY t.email
            ORDER BY open_count DESC, t.opened_at DESC
            LIMIT 50
        ");

        // Email clients stats
        $email_clients = $wpdb->get_results("
            SELECT 
                CASE 
                    WHEN user_agent LIKE '%Outlook%' THEN 'Outlook'
                    WHEN user_agent LIKE '%Gmail%' THEN 'Gmail'
                    WHEN user_agent LIKE '%Apple Mail%' THEN 'Apple Mail'
                    WHEN user_agent LIKE '%Thunderbird%' THEN 'Thunderbird'
                    WHEN user_agent LIKE '%Yahoo%' THEN 'Yahoo Mail'
                    ELSE 'Other'
                END as client,
                COUNT(id) as count
            FROM $tracking_table
            WHERE opened_at IS NOT NULL AND user_agent IS NOT NULL
            GROUP BY client
            ORDER BY count DESC
        ");

    ?>
        <script>
            // Define SCM_PLUGIN_PREFIX for JavaScript compatibility
            let SCM_PLUGIN_PREFIX = '<?php echo SCM_PLUGIN_PREFIX; ?>';
        </script>
        <div class="wrap">
            <h1>Email Tracking Statistics</h1>

            <div class="scm-stats-container">
                <!-- Overall Stats -->
                <div class="scm-stats-overview">
                    <div class="scm-stat-box">
                        <div class="scm-stat-number"><?php echo number_format($total_emails); ?></div>
                        <div class="scm-stat-label">Total Emails Sent</div>
                    </div>
                    <div class="scm-stat-box">
                        <div class="scm-stat-number"><?php echo number_format($opened_emails); ?></div>
                        <div class="scm-stat-label">Emails Opened</div>
                    </div>
                    <div class="scm-stat-box">
                        <div class="scm-stat-number"><?php echo $open_rate; ?>%</div>
                        <div class="scm-stat-label">Open Rate</div>
                    </div>
                </div>

                <!-- Recent Opens -->
                <div class="scm-stats-section">
                    <h2>Recent Opens (Last 30 Days)</h2>
                    <p><strong>Total:</strong> <?php echo number_format($ros_total); ?> opens found |
                        <strong>Showing:</strong> <?php echo number_format(($ros_page - 1) * $ros_per_page + 1); ?> -
                        <?php echo number_format(min($ros_page * $ros_per_page, $ros_total)); ?> of
                        <?php echo number_format($ros_total); ?>
                    </p>

                    <?php if (!empty($recent_opens)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Opened At</th>
                                    <th>User Agent</th>
                                    <th>IP Address</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php

                                // Include GeoLite2 database reader
                                include SCM_PLUGIN_PATH . 'geolite2/Reader.php';
                                include SCM_PLUGIN_PATH . 'geolite2/Reader/Decoder.php';
                                include SCM_PLUGIN_PATH . 'geolite2/Reader/InvalidDatabaseException.php';
                                include SCM_PLUGIN_PATH . 'geolite2/Reader/Metadata.php';
                                include SCM_PLUGIN_PATH . 'geolite2/Reader/Util.php';
                                $reader = new MaxMind\Db\Reader(SCM_PLUGIN_PATH . 'geolite2/GeoLite2-City.mmdb');

                                foreach ($recent_opens as $open) {

                                    // Get location data
                                    if (strpos($open->ip_address, '192.168.') !== false) {
                                        $r = [
                                            'continent' => [
                                                'names' => [
                                                    'en' => 'Localhost continent',
                                                ]
                                            ],
                                            'country' => [
                                                'iso_code' => 'N/A',
                                                'names' => [
                                                    'en' => 'Localhost country',
                                                ]
                                            ],
                                            'city' => [
                                                'names' => [
                                                    'en' => 'Localhost city',
                                                ]
                                            ],
                                            'location' => [
                                                'accuracy_radius' => 100,
                                                'latitude' => 0,
                                                'longitude' => 0
                                            ]
                                        ];
                                    } else {
                                        $r = $reader->get($open->ip_address);
                                        if (!isset($r['city'])) {
                                            $r['city'] = [
                                                'names' => [
                                                    'en' => 'N/A',
                                                ]
                                            ];
                                        }
                                        // var_dump($r);
                                        // print_r($r);
                                    }

                                ?>
                                    <tr>
                                        <td><?php echo esc_html($open->email); ?></td>
                                        <td><?php echo esc_html($open->name ?: 'N/A'); ?></td>
                                        <td>
                                            <?php if (!empty($open->phone)): ?>
                                                <a href="tel:<?php echo esc_attr($open->phone); ?>" class="phone-link">
                                                    <?php echo esc_html($open->phone); ?>
                                                </a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($open->opened_at); ?></td>
                                        <td><?php echo esc_html(substr($open->user_agent, 0, 50)) . (strlen($open->user_agent) > 50 ? '...' : ''); ?></td>
                                        <td><a href="https://www.iplocation.net/ip-lookup?query=<?php echo esc_html($open->ip_address); ?>" target="_blank" rel="nofollow"><?php echo esc_html($open->ip_address); ?></a></td>
                                        <td><a href="https://www.google.com/maps?q=<?php echo esc_html($r['location']['latitude']); ?>,<?php echo esc_html($r['location']['longitude']); ?>" target="_blank" rel="nofollow"><?php echo esc_html($r['city']['names']['en']); ?>, <?php echo esc_html($r['country']['iso_code']); ?></a></td>
                                    </tr>
                                <?php
                                }

                                // 
                                $reader->close();

                                ?>
                            </tbody>
                        </table>

                        <!-- Recent Opens Pagination -->
                        <?php if ($ros_total_pages > 1): ?>
                            <div class="scm-pagination">
                                <?php $base_url = admin_url('tools.php?page=email-tracking-stats'); ?>

                                <div class="tablenav-pages">
                                    <span class="displaying-num"><?php echo number_format($ros_total); ?> items</span>
                                    <span class="pagination-links">
                                        <?php if ($ros_page > 1): ?>
                                            <a href="<?php echo $base_url . '&recent_page=1'; ?>" class="first-page button">«</a>
                                            <a href="<?php echo $base_url . '&recent_page=' . ($ros_page - 1); ?>" class="prev-page button">‹</a>
                                        <?php endif; ?>

                                        <span class="paging-input">
                                            <span class="tablenav-paging-text">
                                                <?php echo $ros_page; ?> of <span class="total-pages"><?php echo $ros_total_pages; ?></span>
                                            </span>
                                        </span>

                                        <?php if ($ros_page < $ros_total_pages): ?>
                                            <a href="<?php echo $base_url . '&recent_page=' . ($ros_page + 1); ?>" class="next-page button">›</a>
                                            <a href="<?php echo $base_url . '&recent_page=' . $ros_total_pages; ?>" class="last-page button">»</a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <p>No email opens recorded in the last 30 days.</p>
                    <?php endif; ?>
                </div>

                <!-- Top Openers -->
                <div class="scm-stats-section">
                    <h2>Top 50 Email Openers</h2>

                    <?php if (!empty($top_openers)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Total Opens</th>
                                    <th>Last Opened</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_openers as $opener): ?>
                                    <tr>
                                        <td><?php echo esc_html($opener->email); ?></td>
                                        <td><?php echo esc_html($opener->name ?: 'N/A'); ?></td>
                                        <td>
                                            <?php if (!empty($opener->phone)): ?>
                                                <a href="tel:<?php echo esc_attr($opener->phone); ?>" class="phone-link">
                                                    <?php echo esc_html($opener->phone); ?>
                                                </a>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($opener->open_count); ?></td>
                                        <td><?php echo esc_html($opener->last_opened); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    <?php else: ?>
                        <p>No email opens recorded yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Email Clients -->
                <div class="scm-stats-section">
                    <h2>Email Clients Used</h2>
                    <?php if (!empty($email_clients)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Email Client</th>
                                    <th>Opens</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($email_clients as $client): ?>
                                    <tr>
                                        <td><?php echo esc_html($client->client); ?></td>
                                        <td><?php echo number_format($client->count); ?></td>
                                        <td><?php echo $opened_emails > 0 ? round(($client->count / $opened_emails) * 100, 1) : 0; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No email client data available.</p>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="scm-stats-actions">
                    <a href="<?php echo wp_nonce_url(admin_url('tools.php?page=email-tracking-stats&action=clear_stats'), 'clear_tracking_stats', 'nonce'); ?>"
                        class="button button-secondary"
                        onclick="return confirm('Are you sure you want to clear all tracking data? This action cannot be undone.');">
                        Clear All Tracking Data
                    </a>
                </div>
            </div>
        </div>
<?php
    }

    /**
     * Lấy Gmail API settings
     */
    private function get_gmail_settings()
    {
        return array(
            'email_method' => get_option(SCM_PLUGIN_PREFIX . 'scm_email_method', 'smtp'),
            'client_id' => get_option(SCM_PLUGIN_PREFIX . 'gmail_client_id'),
            'client_secret' => get_option(SCM_PLUGIN_PREFIX . 'gmail_client_secret'),
            'refresh_token' => get_option(SCM_PLUGIN_PREFIX . 'gmail_refresh_token'),
            'authenticated' => get_option(SCM_PLUGIN_PREFIX . 'gmail_authenticated', '0')
        );
    }

    /**
     * Xử lý Gmail OAuth callback
     */
    private function handle_gmail_callback()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $auth_code = sanitize_text_field($_GET['code']);

        $gmail_service = new Gmail_API_Native_Service();
        $success = $gmail_service->handle_callback($auth_code);

        if ($success) {
            update_option(SCM_PLUGIN_PREFIX . 'gmail_authenticated', '1');
            wp_redirect(admin_url('tools.php?page=smtp-config-manager&gmail_auth=success'));
        } else {
            wp_redirect(admin_url('tools.php?page=smtp-config-manager&gmail_auth=error'));
        }
        exit;
    }

    /**
     * Test Gmail API connection via AJAX
     */
    public function test_gmail_api_connection()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'scm_test_gmail_api')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // Get test email from request or use admin email as fallback
            $test_email = isset($_POST['test_email']) && !empty(trim($_POST['test_email']))
                ? sanitize_email(trim($_POST['test_email']))
                : null;

            // Validate email if provided
            if ($test_email && !is_email($test_email)) {
                wp_send_json_error('Invalid email address provided.');
                return;
            }

            $gmail_service = new Gmail_API_Native_Service();
            $result = $gmail_service->test_connection($test_email);

            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('Gmail API test failed: ' . $e->getMessage());
        }
    }
}
