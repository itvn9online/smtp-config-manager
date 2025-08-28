# SMTP Config Manager Plugin

WordPress plugin để quản lý cấu hình SMTP cho hệ thống email marketing một cách dễ dàng thông qua admin interface.

## Tính năng

### Quản lý SMTP

- **Quản lý SMTP từ Admin**: Cấu hình SMTP trực tiếp từ WordPress admin
- **Hỗ trợ nhiều nhà cung cấp**: Gmail, Outlook, Yahoo, Zoho, v.v.
- **Test kết nối**: Kiểm tra SMTP settings trước khi lưu
- **Tự động đề xuất**: Tự động đề xuất settings dựa trên email domain
- **Bảo mật**: Mã hóa password và validation đầy đủ
- **Debug mode**: Hỗ trợ debug để troubleshoot
- **Responsive UI**: Giao diện thân thiện và responsive

### Quản lý Nội dung Email (Mới!)

- **Tùy chỉnh Subject**: Đặt tiêu đề email tùy chỉnh với placeholder support
- **Lựa chọn định dạng**: HTML hoặc Plain Text
- **Trình soạn thảo visual**: Rich text editor cho nội dung HTML
- **Trình soạn thảo text**: Textarea đơn giản cho nội dung plain text
- **Xem trước**: Xem trước email trước khi gửi
- **Gửi email test**: Gửi email mẫu với nội dung tùy chỉnh

### Hỗ trợ Placeholder

Các placeholder sau được hỗ trợ trong cả subject và content:

- `{SITE_NAME}` - Tên website
- `{USER_EMAIL}` - Email người nhận
- `{USER_NAME}` - Tên người nhận (nếu có)
- `{UNSUBSCRIBE_URL}` - Link hủy đăng ký

## Cài đặt

1. Copy thư mục `smtp-config-manager` vào `wp-content/plugins/`
2. Activate plugin trong WordPress admin
3. Vào **Settings → SMTP Settings** để cấu hình

## Sử dụng

### Cấu hình SMTP

1. **Vào Settings → SMTP Settings**
2. **Điền thông tin SMTP**:

   - **SMTP Host**: Server SMTP (vd: smtp.gmail.com)
   - **Port**: Cổng (587 cho TLS, 465 cho SSL)
   - **Username**: Tài khoản email
   - **Password**: Mật khẩu hoặc app password
   - **Encryption**: TLS hoặc SSL
   - **From Email**: Email gửi đi
   - **From Name**: Tên hiển thị

3. **Test Connection**: Click "Test SMTP Connection" để kiểm tra
4. **Save Settings**: Lưu cấu hình

### Cấu hình cho các nhà cung cấp phổ biến

#### Gmail

```
Host: smtp.gmail.com
Port: 587
Encryption: TLS
Username: your-email@gmail.com
Password: App Password (không phải mật khẩu thường)
```

#### Outlook/Hotmail

```
Host: smtp.live.com
Port: 587
Encryption: TLS
```

#### Yahoo

```
Host: smtp.mail.yahoo.com
Port: 587
Encryption: TLS
```

#### Zoho

```
Host: smtp.zoho.com
Port: 587
Encryption: TLS
```

## Tích hợp với Email Marketing

Plugin tự động tích hợp với hệ thống email marketing hiện có. File `mail_marketing.php` sẽ:

1. **Kiểm tra plugin SMTP** có active không
2. **Sử dụng settings từ plugin** nếu có
3. **Fallback** về settings cũ nếu plugin bị disable

## Tính năng nâng cao

### Auto-suggestion

- Plugin tự động đề xuất settings khi bạn nhập email
- Hỗ trợ các provider phổ biến

### Password visibility

- Toggle hiển thị/ẩn password để dễ kiểm tra

### Port/Encryption sync

- Tự động đồng bộ port và encryption phù hợp

### Debug mode

- 5 levels debug từ 0 (off) đến 4 (chi tiết nhất)
- Giúp troubleshoot khi có vấn đề

## API Functions

### `get_smtp_settings()`

Lấy toàn bộ SMTP settings:

```php
$settings = get_smtp_settings();
echo $settings['host']; // smtp.gmail.com
```

### `configure_wp_mail_smtp()`

Tự động cấu hình WordPress mail:

```php
configure_wp_mail_smtp(); // Đã được gọi tự động
```

## Troubleshooting

### Lỗi thường gặp

1. **"Failed to authenticate"**

   - Kiểm tra username/password
   - Với Gmail: sử dụng App Password thay vì mật khẩu thường
   - Bật 2-factor authentication

2. **"Connection timeout"**

   - Kiểm tra host và port
   - Kiểm tra firewall/hosting provider

3. **"SSL/TLS errors"**
   - Thử thay đổi encryption (TLS ↔ SSL)
   - Kiểm tra port (587 cho TLS, 465 cho SSL)

### Debug Steps

1. **Bật debug mode** (level 2)
2. **Test connection** và xem output
3. **Kiểm tra WordPress debug log**
4. **Thử settings khác nhau**

### Gmail Setup

1. **Bật 2-factor authentication**
2. **Tạo App Password**:
   - Google Account → Security → App passwords
   - Chọn "Mail" và "Other"
   - Copy password 16 ký tự
3. **Sử dụng App Password** thay vì mật khẩu thường

## Security

- **Password encryption**: Passwords được lưu an toàn
- **Nonce validation**: Bảo vệ CSRF
- **Capability checks**: Chỉ admin mới được truy cập
- **Input sanitization**: Validate toàn bộ input

## Compatibility

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **Tích hợp**: Mail Marketing Importer plugin
- **Email providers**: Gmail, Outlook, Yahoo, Zoho, custom SMTP

## File Structure

```
smtp-config-manager/
├── smtp-config-manager.php     # Main plugin file
├── includes/
│   └── class-smtp-config-manager.php  # Main class
├── assets/
│   ├── admin.css              # Admin styles
│   └── admin.js               # Admin JavaScript
└── README.md                  # Documentation
```

## Changelog

### Version 1.1.0 (Hiện tại)

- **[MỚI]** Thêm tính năng quản lý nội dung email
- **[MỚI]** Hỗ trợ định dạng HTML và Plain Text
- **[MỚI]** Trình soạn thảo visual cho HTML content
- **[MỚI]** Chức năng xem trước email
- **[MỚI]** Gửi email test với nội dung tùy chỉnh
- **[MỚI]** Hỗ trợ placeholder trong subject và content
- **[CẢI TIẾN]** Giao diện admin được cải thiện
- **[CẢI TIẾN]** Tích hợp với hệ thống mail marketing

### Version 1.0.2

- Phiên bản ban đầu với cấu hình SMTP cơ bản
- Chức năng test kết nối
- Giao diện admin cơ bản

## Hướng dẫn sử dụng nâng cao

### Tùy chỉnh nội dung email

1. Truy cập **Settings → SMTP Settings**
2. Cuộn xuống phần **Email Content Settings**
3. Nhập subject line tùy chỉnh
4. Chọn loại nội dung (HTML hoặc Plain Text)
5. Nhập nội dung email sử dụng trình soạn thảo
6. Sử dụng chức năng preview để xem trước
7. Gửi email test để kiểm tra
8. Lưu cài đặt

### Sử dụng Placeholder

Placeholder sẽ được thay thế tự động khi gửi email:

- `{SITE_NAME}` → Tên website của bạn
- `{USER_EMAIL}` → Email người nhận
- `{USER_NAME}` → Tên người nhận
- `{UNSUBSCRIBE_URL}` → Link hủy đăng ký an toàn

## Tích hợp với Mail Marketing

Plugin tự động tích hợp với hệ thống Mail Marketing. Tất cả email gửi qua hệ thống marketing sẽ sử dụng:

- Cài đặt SMTP đã cấu hình
- Nội dung email tùy chỉnh
- Placeholder được thay thế tự động
- Định dạng email đã chọn (HTML/Text)
