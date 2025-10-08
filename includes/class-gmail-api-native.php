<?php

/**
 * Gmail API Native Service Class
 * Gửi email qua Gmail API không cần vendor/autoload.php
 * Sử dụng WordPress HTTP API để gọi trực tiếp REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gmail_API_Native_Service
{
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $access_token;
    private $refresh_token;

    // Gmail API endpoints
    const OAUTH_URL = 'https://accounts.google.com/o/oauth2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const GMAIL_API_URL = 'https://gmail.googleapis.com/gmail/v1';

    public function __construct()
    {
        // Lấy thông tin từ WordPress options
        $this->client_id = get_option(SCM_PLUGIN_PREFIX . 'gmail_client_id');
        $this->client_secret = get_option(SCM_PLUGIN_PREFIX . 'gmail_client_secret');
        $this->refresh_token = get_option(SCM_PLUGIN_PREFIX . 'gmail_refresh_token');
        $this->redirect_uri = admin_url('tools.php?page=smtp-config-manager&action=gmail_callback');

        // Load access token từ database hoặc file
        $this->load_access_token();
    }

    /**
     * Tạo Authorization URL để user authorize
     */
    public function get_auth_url()
    {
        if (!$this->client_id || !$this->client_secret) {
            return false;
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'https://www.googleapis.com/auth/gmail.modify',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];

        return self::OAUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Đổi authorization code thành access token và refresh token
     */
    public function handle_callback($auth_code)
    {
        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'code' => $auth_code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $this->redirect_uri,
                'grant_type' => 'authorization_code'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            error_log('Gmail OAuth Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            error_log('Gmail OAuth Error: ' . $data['error_description']);
            return false;
        }

        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];

            if (isset($data['refresh_token'])) {
                $this->refresh_token = $data['refresh_token'];
                // Lưu refresh token vào database
                update_option(SCM_PLUGIN_PREFIX . 'gmail_refresh_token', $this->refresh_token);
            }

            // Lưu access token (có thể lưu vào database hoặc transient)
            $this->save_access_token($data);

            return true;
        }

        return false;
    }

    /**
     * Refresh access token khi hết hạn
     */
    private function refresh_access_token()
    {
        if (!$this->refresh_token) {
            return false;
        }

        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'refresh_token' => $this->refresh_token,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            error_log('Gmail Token Refresh Error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $this->save_access_token($data);
            return true;
        }

        return false;
    }

    /**
     * Lưu access token (sử dụng WordPress transient - tự động expire)
     */
    private function save_access_token($token_data)
    {
        // Lưu vào transient với thời gian expire
        $expires_in = isset($token_data['expires_in']) ? intval($token_data['expires_in']) : 3600;
        set_transient(SCM_PLUGIN_PREFIX . 'gmail_access_token', $this->access_token, $expires_in - 60);
    }

    /**
     * Load access token từ transient
     */
    private function load_access_token()
    {
        $this->access_token = get_transient(SCM_PLUGIN_PREFIX . 'gmail_access_token');
    }

    /**
     * Kiểm tra có access token hợp lệ không
     */
    public function is_authenticated()
    {
        // Nếu không có access token, thử refresh
        if (!$this->access_token && $this->refresh_token) {
            $this->refresh_access_token();
        }

        return !empty($this->access_token);
    }

    /**
     * Test connection tới Gmail API bằng cách gửi email thực tế
     */
    public function test_connection($test_email = null)
    {
        if (!$this->is_authenticated()) {
            return ['success' => false, 'message' => 'Chưa authenticate với Gmail API'];
        }

        try {
            // Xác định email để gửi test
            if (!empty($test_email) && is_email($test_email)) {
                $recipient_email = $test_email;
            } else {
                $recipient_email = get_option('admin_email');
                if (!$recipient_email) {
                    return ['success' => false, 'message' => 'Không tìm thấy admin email'];
                }
            }

            // Tạo nội dung email test
            $subject = 'Gmail API Test - ' . date('Y-m-d H:i:s') . ' - ' . get_bloginfo('name');

            $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
            $message .= '<h2 style="color: #4CAF50;">🎉 Gmail API Test Email Thành Công!</h2>';
            $message .= '<p>Đây là email test từ <strong>Gmail API Native Service</strong> (không sử dụng vendor/autoload.php)</p>';

            $message .= '<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0;">';
            $message .= '<h3>📋 Thông tin Test:</h3>';
            $message .= '<ul>';
            $message .= '<li><strong>Website:</strong> ' . get_bloginfo('name') . ' (' . home_url() . ')</li>';
            $message .= '<li><strong>Recipient:</strong> ' . $recipient_email . '</li>';
            $message .= '<li><strong>Thời gian:</strong> ' . date('d/m/Y H:i:s') . '</li>';
            $message .= '<li><strong>Phương thức:</strong> Gmail API Native (REST API trực tiếp)</li>';
            $message .= '<li><strong>Server:</strong> ' . $_SERVER['HTTP_HOST'] . '</li>';
            $message .= '</ul>';
            $message .= '</div>';

            $message .= '<div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">';
            $message .= '<h3>✅ Tính năng hoạt động:</h3>';
            $message .= '<ul>';
            $message .= '<li>✅ OAuth 2.0 Authentication</li>';
            $message .= '<li>✅ Access Token Management</li>';
            $message .= '<li>✅ Auto Refresh Token</li>';
            $message .= '<li>✅ HTML Email Sending</li>';
            $message .= '<li>✅ WordPress HTTP API Integration</li>';
            $message .= '</ul>';
            $message .= '</div>';

            $message .= '<p style="color: #666; font-size: 12px; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;">';
            $message .= '📧 Email này được gửi tự động để test Gmail API. Nếu bạn nhận được email này, Gmail API đang hoạt động hoàn hảo!<br>';
            $message .= '🕒 Sent at: ' . date('c') . '<br>';
            $message .= '🔧 Plugin: SMTP Config Manager - Gmail API Native Service';
            $message .= '</p>';
            $message .= '</div>';

            // Gửi email test
            $result = $this->send_email($recipient_email, $subject, $message);

            return [
                'success' => true,
                'message' => 'Gmail API test thành công! Email đã được gửi tới ' . $recipient_email,
                'details' => 'Message ID: ' . $result,
                'email' => $recipient_email
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Gmail API test thất bại: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Gửi email qua Gmail API
     */
    public function send_email($to, $subject, $message, $headers = [], $attachments = [])
    {
        if (!$this->is_authenticated()) {
            throw new Exception('Gmail API chưa được authenticate');
        }

        // Lấy thông tin From từ settings
        $smtp_settings = the_smtp_settings();
        $from_email = $smtp_settings['from_email'];
        $from_name = $smtp_settings['from_name'];

        // Tạo raw email message
        $raw_message = $this->create_raw_message($to, $from_email, $from_name, $subject, $message, $headers);

        // Encode message
        $encoded_message = $this->base64url_encode($raw_message);

        // Gửi qua Gmail API
        $response = wp_remote_post(self::GMAIL_API_URL . '/users/me/messages/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'raw' => $encoded_message
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Lỗi gửi email: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 200) {
            $data = json_decode($body, true);
            return isset($data['id']) ? $data['id'] : true;
        } else {
            $error_data = json_decode($body, true);
            $error_msg = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            throw new Exception('Gửi email thất bại: ' . $error_msg);
        }
    }

    /**
     * Tạo raw email message theo định dạng RFC 2822
     */
    private function create_raw_message($to, $from_email, $from_name, $subject, $body, $headers = [])
    {
        $message_id = '<' . uniqid() . '@' . parse_url(home_url(), PHP_URL_HOST) . '>';

        $email_headers = [
            "Message-ID: $message_id",
            "Date: " . date('r'),
            "To: $to",
            "From: $from_name <$from_email>",
            "Subject: $subject",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=utf-8",
            "Content-Transfer-Encoding: quoted-printable"
        ];

        // Thêm custom headers nếu có
        foreach ($headers as $header) {
            if (is_array($header)) {
                $email_headers[] = $header[0] . ': ' . $header[1];
            } else {
                $email_headers[] = $header;
            }
        }

        // Encode body content
        $encoded_body = quoted_printable_encode($body);

        // Kết hợp headers và body
        $raw_message = implode("\r\n", $email_headers) . "\r\n\r\n" . $encoded_body;

        return $raw_message;
    }

    /**
     * Base64URL encoding (yêu cầu của Gmail API)
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Test gửi email tới địa chỉ tùy chọn
     */
    public function test_send_email($test_email = null)
    {
        if (!$this->is_authenticated()) {
            return ['success' => false, 'message' => 'Chưa authenticate với Gmail API'];
        }

        // Nếu không có test email, dùng admin email
        if (!$test_email) {
            $test_email = get_option('admin_email');
        }

        if (!$test_email || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email address không hợp lệ'];
        }

        try {
            $subject = '📧 Gmail API Native Test Email - ' . date('H:i:s d/m/Y');

            $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">';
            $message .= '<div style="text-align: center; margin-bottom: 30px;">';
            $message .= '<h1 style="color: #1a73e8; margin: 0;">📧 Gmail API Test</h1>';
            $message .= '<p style="color: #666; margin: 10px 0;">Native Service - Không cần vendor/autoload.php</p>';
            $message .= '</div>';

            $message .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin: 20px 0;">';
            $message .= '<h2 style="margin: 0 0 10px 0;">🎉 Test Email Thành Công!</h2>';
            $message .= '<p style="margin: 0; opacity: 0.9;">Gmail API Native Service đang hoạt động hoàn hảo!</p>';
            $message .= '</div>';

            $message .= '<div style="background: #f8f9ff; border: 1px solid #e1e5f2; border-radius: 8px; padding: 20px; margin: 20px 0;">';
            $message .= '<h3 style="color: #333; margin-top: 0;">📊 Thông tin kỹ thuật:</h3>';
            $message .= '<table style="width: 100%; border-collapse: collapse;">';
            $message .= '<tr><td style="padding: 5px 0; color: #666;"><strong>Website:</strong></td><td>' . (function_exists('get_bloginfo') ? get_bloginfo('name') : 'N/A') . '</td></tr>';
            $message .= '<tr><td style="padding: 5px 0; color: #666;"><strong>URL:</strong></td><td>' . (function_exists('home_url') ? home_url() : $_SERVER['HTTP_HOST']) . '</td></tr>';
            $message .= '<tr><td style="padding: 5px 0; color: #666;"><strong>Thời gian gửi:</strong></td><td>' . date('d/m/Y H:i:s') . '</td></tr>';
            $message .= '<tr><td style="padding: 5px 0; color: #666;"><strong>Phương thức:</strong></td><td>Gmail API REST (Native)</td></tr>';
            $message .= '<tr><td style="padding: 5px 0; color: #666;"><strong>Encoding:</strong></td><td>UTF-8 HTML</td></tr>';
            $message .= '</table>';
            $message .= '</div>';

            $message .= '<div style="background: #e8f5e8; border-left: 4px solid #4caf50; padding: 15px; margin: 20px 0;">';
            $message .= '<h3 style="color: #2e7d32; margin-top: 0;">✅ Tính năng đã test thành công:</h3>';
            $message .= '<ul style="margin: 0; padding-left: 20px;">';
            $message .= '<li>OAuth 2.0 Authentication ✅</li>';
            $message .= '<li>Access Token & Refresh Token ✅</li>';
            $message .= '<li>WordPress HTTP API Integration ✅</li>';
            $message .= '<li>HTML Email với UTF-8 ✅</li>';
            $message .= '<li>RFC 2822 Message Format ✅</li>';
            $message .= '</ul>';
            $message .= '</div>';

            // Thêm một số emoji để test unicode
            $message .= '<div style="text-align: center; padding: 20px; background: #fff9e6; border-radius: 8px; margin: 20px 0;">';
            $message .= '<p style="font-size: 24px; margin: 0;">🚀 🎯 📧 ✅ 🔧 ⚡ 💯</p>';
            $message .= '<p style="color: #666; margin: 10px 0;">Unicode & Emoji Support Test</p>';
            $message .= '</div>';

            $message .= '<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">';
            $message .= '<p style="color: #999; font-size: 12px; text-align: center; margin: 0;">';
            $message .= 'Email được gửi từ Gmail API Native Service<br>';
            $message .= 'Generated at: ' . date('c') . '<br>';
            $message .= 'Message ID sẽ được tạo tự động bởi Gmail';
            $message .= '</p>';
            $message .= '</div>';

            $result = $this->send_email($test_email, $subject, $message);

            return [
                'success' => true,
                'message' => 'Test email đã được gửi thành công!',
                'details' => [
                    'recipient' => $test_email,
                    'subject' => $subject,
                    'message_id' => $result,
                    'sent_at' => date('c')
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Gửi test email thất bại: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Revoke access token (logout)
     */
    public function revoke_access()
    {
        if ($this->access_token) {
            wp_remote_post('https://oauth2.googleapis.com/revoke', [
                'body' => ['token' => $this->access_token],
                'timeout' => 10
            ]);
        }

        // Xóa tokens
        delete_transient(SCM_PLUGIN_PREFIX . 'gmail_access_token');
        delete_option(SCM_PLUGIN_PREFIX . 'gmail_refresh_token');
        delete_option(SCM_PLUGIN_PREFIX . 'gmail_authenticated');

        $this->access_token = null;
        $this->refresh_token = null;
    }
}
