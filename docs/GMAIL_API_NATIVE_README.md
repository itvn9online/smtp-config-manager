# Gmail API Native Service - Không cần vendor/autoload.php

## 📋 Tổng quan

`Gmail_API_Native_Service` là một class PHP thuần túy để gửi email qua Gmail API mà **không cần cài đặt Google Client Library** hoặc `vendor/autoload.php`. Class này chỉ sử dụng WordPress HTTP API để thực hiện các cuộc gọi REST API trực tiếp tới Gmail.

## ✅ Ưu điểm

- **Không dependency**: Không cần Google Client Library
- **Nhẹ**: Chỉ sử dụng WordPress built-in functions
- **Dễ maintain**: Code đơn giản, dễ hiểu và debug
- **Auto refresh**: Tự động refresh access token khi hết hạn
- **WordPress native**: Sử dụng WordPress Transient API để lưu tokens

## 🔧 Cách sử dụng

### 1. Khởi tạo Service

```php
require_once 'includes/class-gmail-api-native.php';
$gmail_service = new Gmail_API_Native_Service();
```

### 2. Lấy Authorization URL

```php
$auth_url = $gmail_service->get_auth_url();
if ($auth_url) {
    echo "<a href='{$auth_url}'>Authorize Gmail API</a>";
}
```

### 3. Xử lý Callback (sau khi user authorize)

```php
if (isset($_GET['code'])) {
    $success = $gmail_service->handle_callback($_GET['code']);
    if ($success) {
        echo "Authorization successful!";
    }
}
```

### 4. Kiểm tra Authentication

```php
if ($gmail_service->is_authenticated()) {
    echo "Ready to send emails!";
} else {
    echo "Need to authorize first";
}
```

### 5. Gửi Email

```php
try {
    $message_id = $gmail_service->send_email(
        'recipient@example.com',           // To
        'Test Subject',                    // Subject
        '<h1>Hello World!</h1>',          // HTML Message
        [],                               // Headers (optional)
        []                                // Attachments (optional)
    );
    echo "Email sent! Message ID: " . $message_id;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### 6. Test Connection

```php
$result = $gmail_service->test_connection();
if ($result['success']) {
    echo "Connected as: " . $result['email'];
} else {
    echo "Connection failed: " . $result['message'];
}
```

## 🔑 Cấu hình cần thiết

### WordPress Options cần set:

```php
// Client credentials từ Google Cloud Console
update_option(SCM_PLUGIN_PREFIX . 'gmail_client_id', 'your_client_id');
update_option(SCM_PLUGIN_PREFIX . 'gmail_client_secret', 'your_client_secret');

// Refresh token (được tự động lưu sau khi authorize)
update_option(SCM_PLUGIN_PREFIX . 'gmail_refresh_token', 'refresh_token');
```

### Redirect URI cần thêm vào Google Console:

```
https://yourdomain.com/wp-admin/tools.php?page=smtp-config-manager&action=gmail_callback
```

## 🔄 So sánh với Google Client Library

| Feature        | Native Service | Google Client Library        |
| -------------- | -------------- | ---------------------------- |
| Dependencies   | ❌ None        | ✅ Requires composer install |
| File size      | 🟢 ~15KB       | 🔴 ~2MB+                     |
| Performance    | 🟢 Fast        | 🟡 Slower                    |
| Maintenance    | 🟢 Easy        | 🟡 Need updates              |
| Features       | 🟡 Basic       | 🟢 Full featured             |
| Learning curve | 🟢 Simple      | 🟡 Complex                   |

## 📁 Cấu trúc Files

```
includes/
├── class-gmail-api-native.php     # Native service (NEW)
├── class-gmail-api-service.php    # Original service with vendor libs
└── class-smtp-config-manager.php  # Main plugin class

assets/
├── gmail-api-admin.js            # JavaScript cho OAuth flow
└── admin.js                      # Main admin JS

demo-gmail-native.php             # Demo file để test
```

## 🔧 Integration với Plugin hiện tại

Service được tự động detect trong `SMTP_Config_Manager`:

```php
private function get_gmail_service()
{
    // Ưu tiên Native Service (không cần vendor)
    require_once SCM_PLUGIN_PATH . 'includes/class-gmail-api-native.php';
    $native_service = new Gmail_API_Native_Service();

    if ($this->can_use_native_service()) {
        return $native_service; // Dùng Native
    }

    // Fallback sang Google Client Library
    if (file_exists(SCM_PLUGIN_PATH . 'includes/class-gmail-api-service.php')) {
        require_once SCM_PLUGIN_PATH . 'includes/class-gmail-api-service.php';
        return new Gmail_API_Service();
    }

    return $native_service;
}
```

## 🚀 Demo

Chạy file `demo-gmail-native.php` để xem demo:

```
http://yourdomain.com/wp-content/plugins/smtp-config-manager/demo-gmail-native.php
```

## 📝 Ghi chú

- Access token được lưu trong WordPress Transient (tự expire)
- Refresh token được lưu trong WordPress Options
- Tự động refresh khi access token hết hạn
- Hỗ trợ HTML email và attachments cơ bản
- Tương thích với existing JavaScript OAuth flow

## 🔐 Security

- Tokens được lưu an toàn trong WordPress database
- Sử dụng WordPress nonce cho AJAX requests
- Kiểm tra user capabilities trước khi thực hiện actions
- Access token có thời gian expire tự động

## 🐛 Troubleshooting

### Lỗi "Chưa authenticate"

- Kiểm tra Client ID và Client Secret
- Đảm bảo Redirect URI đúng trong Google Console
- Thử authorize lại

### Lỗi "Invalid grant"

- Refresh token có thể hết hạn, cần authorize lại
- Kiểm tra system time đồng bộ

### Lỗi "Insufficient permissions"

- Kiểm tra scope trong Google Console
- Đảm bảo enable Gmail API

---

**🎉 Kết quả: Có thể gửi email qua Gmail API mà không cần vendor/autoload.php!**
