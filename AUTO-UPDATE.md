# Auto-Update System - Hướng dẫn

Plugin Echbay Mail Queue Manager đã được tích hợp hệ thống tự động cập nhật từ GitHub repository.

## Cách thức hoạt động

### 1. Kiểm tra phiên bản

- Plugin sẽ kiểm tra phiên bản mới từ: `https://github.com/itvn9online/smtp-config-manager/raw/refs/heads/main/VERSION`
- File VERSION chứa số phiên bản hiện tại (ví dụ: `1.0.7`)
- WordPress sẽ tự động kiểm tra update theo lịch định sẵn

### 2. Download và cập nhật

- Khi có phiên bản mới, plugin sẽ download từ: `https://github.com/itvn9online/smtp-config-manager/archive/refs/heads/main.zip`
- Tự động giải nén và cài đặt vào thư mục plugin
- Xử lý đúng cấu trúc thư mục plugin WordPress

### 3. Giao diện quản trị

- **Kiểm tra thủ công**: Nút "Check for Updates" trong Settings
- **Hiển thị trạng thái**: Thông báo có bản cập nhật mới
- **WordPress integration**: Hiển thị trong trang Plugins như plugin thông thường

## Sử dụng

### Kiểm tra update thủ công

1. Vào **Settings → Email Queue**
2. Tìm phần **Plugin Updates**
3. Click **"Check for Updates"**
4. Xem kết quả và làm theo hướng dẫn

### Cập nhật tự động

1. WordPress sẽ tự động kiểm tra update
2. Thông báo sẽ xuất hiện trong **Plugins** page
3. Click **"Update Now"** như plugin thông thường

## Phát triển và Release

### Để release phiên bản mới:

1. **Cập nhật VERSION file**:

   ```bash
   echo "1.0.8" > VERSION
   echo "1.0.8" > wp-content/plugins/smtp-config-manager/VERSION
   ```

2. **Cập nhật plugin header**:

   ```php
   * Version: 1.0.8
   ```

3. **Cập nhật CHANGELOG.md**:

   ```markdown
   ## [1.0.8] - 2025-08-22

   ### Added

   - New features...
   ```

4. **Commit và push**:
   ```bash
   git add .
   git commit -m "Release version 1.0.8"
   git push origin main
   ```

### Cấu trúc repository

Để auto-update hoạt động đúng, repository cần có cấu trúc:

```
/
├── VERSION                                    # File version ở root
├── wp-content/
│   └── plugins/
│       └── smtp-config-manager/
│           ├── VERSION                        # File version trong plugin
│           ├── smtp-config-manager.php  # Main plugin file
│           └── includes/
│               └── class-auto-updater.php     # Auto updater class
```

## Lưu ý kỹ thuật

### Security

- Chỉ admin mới có thể kiểm tra/cập nhật plugin
- Sử dụng WordPress nonce để bảo vệ AJAX requests
- Kiểm tra permissions trước khi thực hiện update

### Performance

- Kiểm tra version được cache bởi WordPress
- Chỉ download khi thực sự cần update
- Timeout 10 giây cho các HTTP requests

### Compatibility

- Tương thích với WordPress 5.0+
- Yêu cầu PHP 7.4+
- Hoạt động với WordPress multisite

## Troubleshooting

### Plugin không nhận update

1. Kiểm tra internet connection
2. Verify GitHub repository accessible
3. Check WordPress file permissions
4. Kiểm tra error logs

### Update failed

1. Backup plugin trước khi update
2. Kiểm tra disk space
3. Verify file permissions
4. Manual download nếu cần thiết

### Version không match

1. Clear WordPress cache
2. Kiểm tra VERSION file content
3. Force refresh plugin list
4. Manual version check

## API Reference

### Class: EMQM_Auto_Updater

#### Methods

- `check_for_update($transient)` - Kiểm tra update WordPress transient
- `get_remote_version()` - Lấy version từ GitHub
- `plugin_info($false, $action, $response)` - Thông tin plugin cho popup
- `manual_check_update()` - Kiểm tra update thủ công
- `upgrader_source_selection()` - Xử lý source directory sau download

#### Hooks

- `pre_set_site_transient_update_plugins` - WordPress update check
- `plugins_api` - Plugin information popup
- `upgrader_source_selection` - Fix directory structure

## Support

Nếu gặp vấn đề với auto-update, vui lòng:

1. Check error logs
2. Test manual download từ GitHub
3. Verify plugin permissions
4. Contact developer với log details
