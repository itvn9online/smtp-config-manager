# SMTP Config Manager - Gmail API Integration

Plugin WordPress này hiện đã hỗ trợ gửi email qua Gmail API như một alternative cho SMTP truyền thống.

## Tính năng mới

### Gmail API Email Sending

- **Độ tin cậy cao hơn**: Gmail API ít bị giới hạn hơn so với SMTP
- **Không cần password**: Sử dụng OAuth2 authentication thay vì username/password
- **Tự động retry**: Gmail API có built-in retry mechanism
- **Better deliverability**: Email được gửi qua Google servers

## Cài đặt và Cấu hình

### 1. Cài đặt Dependencies

```bash
cd wp-content/plugins/smtp-config-manager/
composer install
```

### 2. Cấu hình Google Cloud Console

1. **Tạo Google Cloud Project**:

   - Truy cập [Google Cloud Console](https://console.cloud.google.com/)
   - Tạo project mới hoặc chọn project có sẵn

2. **Enable Gmail API**:

   - Vào Library → Search "Gmail API" → Enable

3. **Tạo OAuth2 Credentials**:

   - Vào Credentials → Create Credentials → OAuth 2.0 Client IDs
   - Application type: Web application
   - Authorized redirect URIs: `https://yourdomain.com/wp-admin/tools.php?page=smtp-config-manager&action=gmail_callback`

4. **Download credentials**:
   - Download JSON file hoặc copy Client ID và Client Secret

### 3. Cấu hình Plugin

1. **Truy cập Admin**:

   - WordPress Admin → Tools → SMTP Config Manager

2. **Chọn Email Method**:

   - Chọn "Gmail API" thay vì "SMTP"

3. **Nhập Credentials**:

   - Paste Client ID và Client Secret từ Google Cloud Console
   - Click "Save Changes"

4. **Authorize với Google**:

   - Click "Authorize với Google"
   - Login với Gmail account muốn sử dụng
   - Accept permissions
   - Sẽ redirect về plugin với success message

5. **Test Connection**:
   - Click "Test Gmail API Connection"
   - Kiểm tra email test được gửi thành công

## Sử dụng

### Switching Methods

Plugin hỗ trợ chuyển đổi giữa SMTP và Gmail API dễ dàng:

```php
// Set email method via code
update_option('scm_email_method', 'gmail_api'); // or 'smtp'
```

### Email Sending

Không cần thay đổi code hiện có, plugin sẽ tự động sử dụng method đã chọn:

```php
// Vẫn sử dụng wp_mail như bình thường
wp_mail('recipient@example.com', 'Subject', 'Message content');
```

## Troubleshooting

### Common Issues

1. **"Gmail not authenticated" error**:

   - Kiểm tra đã authorize với Google chưa
   - Re-authorize nếu token hết hạn

2. **"Invalid redirect URI" error**:

   - Kiểm tra Redirect URI trong Google Console khớp với plugin
   - Format: `https://yourdomain.com/wp-admin/tools.php?page=smtp-config-manager&action=gmail_callback`

3. **"Insufficient permissions" error**:

   - Kiểm tra Gmail account có quyền gửi email
   - Đảm bảo đã enable Gmail API trong Google Cloud Console

4. **Composer autoload error**:
   - Chạy `composer install` trong plugin directory
   - Kiểm tra file `vendor/autoload.php` tồn tại

### Debug Mode

Enable debug để xem chi tiết lỗi:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs in /wp-content/debug.log
```

## Security Notes

### OAuth2 Token Storage

- Access tokens được lưu trong WordPress options (encrypted)
- Refresh tokens được lưu an toàn để tự động renew
- Tokens có expiration time và tự động refresh

### Permissions

Gmail API chỉ yêu cầu minimal permissions:

- `https://www.googleapis.com/auth/gmail.modify`: Gửi email và quản lý drafts

### Data Privacy

- Plugin không lưu trữ email content
- Chỉ lưu authentication tokens và settings
- Tuân thủ WordPress security best practices

## Technical Details

### File Structure

```
smtp-config-manager/
├── composer.json                          # Dependencies
├── includes/
│   ├── class-smtp-config-manager.php     # Main admin class with Gmail API
│   └── class-gmail-api-service.php       # Gmail API service
├── assets/
│   ├── gmail-api-admin.js                # Admin JavaScript
│   └── gmail-api-admin.css               # Admin styles
└── vendor/                               # Composer dependencies
    └── google/apiclient/                 # Google API Client Library
```

### Hooks and Filters

#### Actions

- `scm_gmail_authenticated`: Fired after successful Gmail authentication
- `scm_gmail_disconnected`: Fired when Gmail is disconnected
- `scm_gmail_send_success`: Fired after successful email send
- `scm_gmail_send_error`: Fired when email sending fails

#### Filters

- `scm_gmail_email_headers`: Modify email headers before sending
- `scm_gmail_api_scopes`: Modify Gmail API scopes
- `scm_gmail_client_config`: Modify Google Client configuration

### Database Options

- `scm_email_method`: 'smtp' or 'gmail_api'
- `scm_gmail_client_id`: OAuth2 Client ID
- `scm_gmail_client_secret`: OAuth2 Client Secret
- `scm_gmail_access_token`: Current access token (encrypted)
- `scm_gmail_refresh_token`: Refresh token for renewing access
- `scm_gmail_authenticated`: Authentication status

## API Usage Examples

### Programmatic Configuration

```php
// Set Gmail API as default method
update_option('scm_email_method', 'gmail_api');

// Configure Gmail credentials
update_option('scm_gmail_client_id', 'your-client-id');
update_option('scm_gmail_client_secret', 'your-client-secret');

// Check if Gmail is authenticated
$authenticated = get_option('scm_gmail_authenticated') === '1';

if (!$authenticated) {
    // Need to authorize - redirect to admin page
    wp_redirect(admin_url('tools.php?page=smtp-config-manager'));
}
```

### Custom Email Sending

```php
// Send via Gmail API directly
if (class_exists('Gmail_API_Service')) {
    $gmail = new Gmail_API_Service();

    $result = $gmail->send_email(
        'recipient@example.com',
        'Subject Line',
        '<h1>HTML Content</h1><p>Email body</p>',
        ['From: sender@gmail.com'],
        ['/path/to/attachment.pdf']
    );

    if ($result) {
        echo 'Email sent successfully via Gmail API';
    }
}
```

## Version History

### v1.3.0 (Current)

- ✅ Added Gmail API support
- ✅ OAuth2 authentication flow
- ✅ Admin interface for method selection
- ✅ Auto-fallback to SMTP if Gmail API fails
- ✅ Comprehensive error handling
- ✅ Security improvements

### v1.2.4 (Previous)

- SMTP configuration only
- Basic email sending
- Limited error handling

## Support

### Requirements

- PHP 7.4+
- WordPress 5.0+
- Composer for dependency management
- Valid Gmail account
- Google Cloud Console project

### Getting Help

1. Check debug logs first
2. Verify Google Cloud Console configuration
3. Test with a simple email first
4. Check plugin settings in WordPress admin

### Compatibility

- ✅ Works with all major WordPress themes
- ✅ Compatible with WooCommerce emails
- ✅ Compatible with Contact Form 7
- ✅ Compatible with other plugins using wp_mail()

---

_Tài liệu này được cập nhật cho phiên bản 1.3.0 với tính năng Gmail API integration._
