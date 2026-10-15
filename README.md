# IoT Project — Hệ thống trả lời trắc nghiệm

Dự án dùng thiết bị nRF52840 kết nối BLE với gateway Python để gửi câu trả lời lên website PHP/MySQL. Website quản lý sinh viên, câu hỏi, phiên trả lời và kết quả.

## Thành viên

| Họ và tên | MSSV |
| --- | --- |
| Lưu Tấn Phát | 23134043 |
| Nguyễn Minh Nghĩa | 23134039 |
| Trương Hồng Lĩnh | 23134035 |

## Cấu trúc

- `webserver/`: website, API và gateway Python.
- `nrf_src/`: firmware cho nRF52840.

## Chạy nhanh

1. Cài PHP 8.1+, MySQL/MariaDB, Composer và Python 3.
2. Tạo database `ars_db`, nhập `webserver/ars_db_schema.sql` và cấu hình `webserver/db.php`.
3. Trong `webserver/`, chạy `composer install`, sau đó mở website bằng Apache hoặc XAMPP.
4. Cài Python: `pip install -r requirements.txt` và `pip install bleak`.
5. Bật thiết bị, rồi chạy `ARS_SESSION_ID=0 python ble_web_gateway.py`.

Xem [hướng dẫn cài đặt đầy đủ](webserver/README.md). Tệp SQL đi kèm chỉ chứa cấu trúc bảng, không chứa dữ liệu thật.
