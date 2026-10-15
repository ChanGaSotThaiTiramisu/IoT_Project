# ARS2 — Hệ thống thu thập câu trả lời trắc nghiệm

ARS2 gồm website quản lý bằng PHP/MySQL, gateway Python và thiết bị nRF52840 giao tiếp qua Bluetooth Low Energy (BLE). Hệ thống hỗ trợ quản lý sinh viên, bộ câu hỏi, phiên trả lời và kết quả theo từng tài khoản.

## Thành viên thực hiện

| Họ và tên | MSSV |
| --- | --- |
| Lưu Tấn Phát | 23134043 |
| Nguyễn Minh Nghĩa | 23134039 |
| Trương Hồng Lĩnh | 23134035 |

## Mục lục

1. [Chức năng và kiến trúc](#1-chức-năng-và-kiến-trúc)
2. [Yêu cầu môi trường](#2-yêu-cầu-môi-trường)
3. [Cấu trúc dự án](#3-cấu-trúc-dự-án)
4. [Cài đặt trên Ubuntu/Linux](#4-cài-đặt-trên-ubuntulinux)
5. [Cài đặt trên Windows với XAMPP](#5-cài-đặt-trên-windows-với-xampp)
6. [Chuẩn bị dữ liệu và chạy một phiên](#6-chuẩn-bị-dữ-liệu-và-chạy-một-phiên)
7. [Chạy gateway BLE và thiết bị thật](#7-chạy-gateway-ble-và-thiết-bị-thật)
8. [Chạy mô phỏng không cần thiết bị](#8-chạy-mô-phỏng-không-cần-thiết-bị)
9. [Kiểm thử và xác nhận hoạt động](#9-kiểm-thử-và-xác-nhận-hoạt-động)
10. [Sao lưu và chuyển sang máy khác](#10-sao-lưu-và-chuyển-sang-máy-khác)
11. [Xử lý lỗi thường gặp](#11-xử-lý-lỗi-thường-gặp)
12. [Lưu ý triển khai và giới hạn hiện tại](#12-lưu-ý-triển-khai-và-giới-hạn-hiện-tại)

## 1. Chức năng và kiến trúc

- Đăng ký, đăng nhập và phân tách dữ liệu theo tài khoản.
- Quản lý sinh viên; nhập danh sách từ CSV.
- Quản lý bộ câu hỏi; nhập câu hỏi từ CSV hoặc XLSX.
- Quản lý thiết bị, theo dõi trạng thái online/offline.
- Tạo phiên, trình chiếu câu hỏi, thu thập câu trả lời và xem thống kê.
- Gateway BLE tự chọn phiên đang chạy của tài khoản sở hữu thiết bị.
- Gateway mô phỏng phục vụ kiểm tra luồng gửi câu trả lời khi chưa có phần cứng.

```text
Thiết bị nRF52840 <-- BLE / Nordic UART --> Gateway Python
                                                |
                                             HTTP API
                                                |
Trình duyệt <---------------------------- Website PHP
                                                |
                                          MySQL / MariaDB
```

Website và gateway có thể chạy trên cùng một máy. Nếu chạy trên hai máy, gateway cần truy cập được địa chỉ HTTP của máy chủ website.

## 2. Yêu cầu môi trường

| Thành phần | Yêu cầu |
| --- | --- |
| PHP | Phiên bản 8.1 trở lên, bản 64-bit; kiểm tra tương thích bằng Composer |
| Extension PHP | `mysqli` với mysqlnd, `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `libxml`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib` |
| Cơ sở dữ liệu | MySQL/MariaDB; tệp schema đi kèm được xuất từ MariaDB 10.6 |
| Máy chủ web | Apache hoặc máy chủ tích hợp của PHP cho chạy thử cục bộ |
| Composer | Cài thư viện PHP theo `composer.lock` |
| Python | Python 3; môi trường hiện tại dùng Python 3.10 |
| Thư viện Python | Các gói trong `requirements.txt`; cài thêm `bleak` cho gateway BLE |
| Phần cứng tùy chọn | Máy có Bluetooth BLE và bo nRF52840 tương thích firmware |

Có thể chạy website và gateway mô phỏng mà không cần thiết bị nRF52840.

## 3. Cấu trúc dự án

| Đường dẫn | Vai trò |
| --- | --- |
| `index.php`, `register.php`, `logout.php` | Đăng nhập, đăng ký, đăng xuất |
| `dashboard.php` | Tổng quan và số thiết bị online |
| `students.php`, `import_students.php` | Quản lý, nhập sinh viên |
| `questions.php`, `import_questions.php` | Quản lý, nhập câu hỏi |
| `devices.php` | Danh sách thiết bị |
| `sessions.php`, `presentation.php` | Điều khiển phiên và trình chiếu |
| `responses.php`, `results.php`, `results_overview.php` | Câu trả lời và kết quả |
| `api_gateway_state.php` | Trạng thái gateway và tự tìm phiên |
| `api_response.php`, `api_next_question.php` | Nhận câu trả lời, chuyển câu hỏi |
| `api_device_presence.php`, `device_presence.php` | Tính trạng thái online/offline |
| `account.php`, `db.php` | Phạm vi tài khoản và kết nối cơ sở dữ liệu |
| `ble_web_gateway.py` | Gateway kết nối BLE với thiết bị thật |
| `mock_gateway.py` | Gateway mô phỏng |
| `css/`, `js/` | Tài nguyên giao diện |
| `composer.json`, `composer.lock`, `requirements.txt` | Khai báo thư viện |
| `ars_db_schema.sql` | Tệp tạo cấu trúc 9 bảng, không chứa dữ liệu thật |
| `migrations/account_ownership.php` | Nâng cấp quyền sở hữu cho cơ sở dữ liệu cũ |
| `tests/` | Kiểm tra API, phân tách tài khoản và gateway |

**Toàn bộ hướng dẫn dưới đây sử dụng mã nguồn trong thư mục `webserver`, nơi chứa README này.** Firmware nRF52840 nằm tại `../nrf_src/NFR_52840_v2v2.ino`.

## 4. Cài đặt trên Ubuntu/Linux

### Bước 1 — Cài môi trường

Ví dụ cho Ubuntu dùng Apache và MariaDB:

```bash
sudo apt update
sudo apt install apache2 mariadb-server php libapache2-mod-php php-cli php-mysql php-mbstring php-xml php-zip php-gd composer python3 python3-venv python3-pip
sudo systemctl enable --now apache2 mariadb
```

Chép **nội dung** thư mục `webserver` vào `/var/www/html/ars2`, sau đó mở terminal tại thư mục này:

```bash
cd /var/www/html/ars2
php -v
python3 --version
composer --version
composer install
composer check-platform-reqs
```

Nếu Composer báo thiếu extension, cài/bật extension đó rồi chạy lại. Dùng `composer install` để giữ các phiên bản thư viện trong `composer.lock`.

### Bước 2 — Tạo cơ sở dữ liệu và tài khoản ứng dụng

Mở MariaDB bằng tài khoản quản trị:

```bash
sudo mysql
```

Chạy SQL sau; thay `THAY_BANG_MAT_KHAU_RIENG` bằng mật khẩu bạn chọn và dùng cùng mật khẩu đó ở bước cấu hình PHP:

```sql
CREATE DATABASE ars_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ars_app'@'localhost' IDENTIFIED BY 'THAY_BANG_MAT_KHAU_RIENG';
GRANT ALL PRIVILEGES ON ars_db.* TO 'ars_app'@'localhost';
EXIT;
```

Nếu máy đã có database hoặc tài khoản cùng tên, kiểm tra trước khi tạo. Các lệnh trên dành cho lần cài mới.

### Bước 3 — Nhập schema

Tại thư mục `ars2`:

```bash
mysql -u ars_app -p ars_db < ars_db_schema.sql
mysql -u ars_app -p ars_db -e "SHOW TABLES;"
```

Nhập mật khẩu của `ars_app` khi được hỏi. Kết quả phải có 9 bảng, bao gồm `users`, `students`, `devices`, `question_sets`, `questions`, `sessions`, `responses` và hai bảng dữ liệu cũ `devices_legacy`, `responses_legacy`.

**Tệp SQL này chỉ tạo cấu trúc bảng, không chứa dữ liệu tài khoản hoặc sinh viên.** Hãy nhập vào database mới; nếu database đã có bảng, cần sao lưu và xử lý xung đột trước.

### Bước 4 — Cấu hình `db.php`

Cấu hình bốn biến kết nối trong `db.php` hoặc đặt `ARS_DB_HOST`, `ARS_DB_NAME`, `ARS_DB_USER`, `ARS_DB_PASSWORD` cho PHP:

```php
$host = "localhost";
$dbname = "ars_db";
$dbuser = "ars_app";
$dbpass = "THAY_BANG_MAT_KHAU_RIENG";
```

Ứng dụng đọc biến môi trường hoặc giá trị mặc định trong `db.php`; không tự nạp `.env`. Tài khoản MySQL khác với tài khoản đăng nhập website.

Kiểm tra kết nối:

```bash
php -r 'require "db.php"; echo "Ket noi database thanh cong", PHP_EOL;'
```

### Bước 5 — Mở website

Với Apache và DocumentRoot mặc định `/var/www/html`, truy cập:

```text
http://localhost/ars2/
```

Hoặc chạy thử bằng máy chủ tích hợp PHP tại thư mục dự án:

```bash
php -S 127.0.0.1:8000 -t .
```

Giữ terminal này mở và truy cập `http://127.0.0.1:8000/`. Khi dùng cách này, cần đổi `API_BASE` trong gateway BLE thành `http://127.0.0.1:8000`. Dùng terminal thứ hai để chạy gateway.

### Trường hợp nâng cấp cơ sở dữ liệu cũ

Tệp schema đi kèm đã có cấu trúc phân tách tài khoản nên **không cần chạy migration khi nhập bản này**. Chỉ với database cũ chưa có cấu trúc tương ứng, sao lưu trước rồi chạy:

```bash
php migrations/account_ownership.php TEN_TAI_KHOAN_SO_HUU
```

Tên tài khoản phải tồn tại trong bảng `users`. Script gán dữ liệu cũ chưa có chủ sở hữu cho tài khoản đó và lưu bản chụp khôi phục trong thư mục tạm. Script không tạo database từ đầu; thay đổi cấu trúc MySQL không được hoàn tác bằng transaction. Logic kiểm tra tài khoản nằm trong [account.php](account.php).

## 5. Cài đặt trên Windows với XAMPP

1. Chuẩn bị XAMPP có PHP phù hợp, Composer và Python 3. Bảo đảm lệnh `php`, `composer`, `python` dùng được trong terminal; có thể cần thêm thư mục PHP của XAMPP vào `PATH`.
2. Chép nội dung thư mục `webserver` vào `C:\xampp\htdocs\ars2`.
3. Trong XAMPP Control Panel, bật **Apache** và **MySQL**.
4. Mở `http://localhost/phpmyadmin`, tạo database `ars_db` với collation `utf8mb4_unicode_ci`.
5. Chọn database → **Import / Nhập** → chọn `ars_db_schema.sql` → thực hiện. Chỉ nhập vào database dành cho dự án vì các bảng cùng tên sẽ được thay thế.
6. Sửa `db.php` hoặc đặt biến môi trường MySQL cho Apache/PHP bằng tài khoản thực tế của máy. Không mặc định sử dụng thông tin đăng nhập của máy gửi dự án.
7. Mở PowerShell tại thư mục dự án và chạy:

```powershell
cd C:\xampp\htdocs\ars2
composer install
composer check-platform-reqs
```

Nếu thiếu extension, bật extension trong `php.ini` tương ứng; khởi động lại Apache khi thay đổi cấu hình PHP của web server. Có thể dùng `php --ini` để xem cấu hình mà PHP dòng lệnh đang sử dụng.

Truy cập `http://localhost/ars2/`. Nếu Apache dùng cổng khác, thêm cổng vào URL website và `API_BASE` của gateway, ví dụ `http://localhost:8080/ars2`.

## 6. Chuẩn bị dữ liệu và chạy một phiên

### Tài khoản và dữ liệu

1. Mở trang đăng ký và tạo tài khoản, sau đó đăng nhập.
2. Thêm sinh viên hoặc nhập danh sách CSV.
3. Tạo bộ câu hỏi, thêm câu hỏi và đáp án đúng.
4. Khai báo thiết bị với mã trùng `CLIENT_ID` trong firmware, ví dụ `NRF001`.
5. Tạo phiên và chọn bộ câu hỏi thuộc tài khoản của bạn.
6. Bắt đầu phiên; mở trang trình chiếu nếu cần hiển thị câu hỏi.
7. Chạy một trong hai gateway ở phần 7 hoặc phần 8.
8. Gửi câu trả lời và theo dõi tại trang Responses/Results.

Tệp schema đi kèm chưa có tài khoản hoặc dữ liệu. Đăng ký tài khoản mới và chuẩn bị sinh viên, thiết bị, câu hỏi, phiên; nếu nhập bản sao lưu có dữ liệu, hãy đăng nhập đúng tài khoản sở hữu.

### Mẫu nhập sinh viên

Lưu tệp CSV ở mã hóa UTF-8. Hai cột `Student Code` và `Full Name` là bắt buộc:

```csv
Student Code,Full Name,Class,Email
SV001,Nguyễn Văn An,Lop01,an@example.com
SV002,Trần Thị Bình,Lop01,binh@example.com
```

### Mẫu nhập câu hỏi

Dùng CSV UTF-8 hoặc XLSX; hàng đầu tiên chứa tiêu đề cột:

```csv
Question,A,B,C,D,Answer
2 + 2 bằng bao nhiêu?,3,4,5,6,B
Giao tiếp nào được thiết bị sử dụng?,BLE,HDMI,VGA,DVI,A
```

Chọn đúng bộ câu hỏi trước khi nhập. Dùng `A`, `B`, `C` hoặc `D` cho đáp án đúng; đặt nội dung có dấu phẩy trong dấu ngoặc kép khi soạn CSV.

### Chuyển sang phiên mới

Với gateway BLE ở chế độ tự động, kết thúc phiên cũ rồi tạo và bắt đầu phiên mới trên website. Gateway tự chuyển mà không cần sửa ID hoặc khởi động lại. Chỉ để một phiên của tài khoản ở trạng thái `running` nếu muốn gateway tự chọn rõ ràng.

## 7. Chạy gateway BLE và thiết bị thật

### Bước 1 — Chuẩn bị firmware

Nếu thiết bị đã được nạp firmware tương thích, có thể chuyển sang bước 2. Nếu chưa, mở `../nrf_src/NFR_52840_v2v2.ino` bằng Arduino IDE, chọn board/core nRF52840 tương thích và nạp firmware theo sơ đồ phần cứng của bạn.

Firmware cần tương thích với giao thức mà `ble_web_gateway.py` sử dụng:

| Thành phần | Giá trị/giao thức gateway mong đợi |
| --- | --- |
| Tên quảng bá BLE | `Quiz_NRF52840` hoặc tên được cấu hình trong `DEVICE_NAME` |
| Dịch vụ BLE | Nordic UART Service (NUS); UUID TX/RX được khai báo trong gateway |
| Nhận diện thiết bị | Gửi `ID:NRF001` và ký tự xuống dòng; mã phải trùng thiết bị trên website |
| Câu trả lời | Gửi `ANS:<question_id>:<A/B/C/D>` và ký tự xuống dòng |
| Lệnh từ gateway | Xử lý `REQUEST_ID`, `ACCEPT`, `REJECT`, `QUESTION:<id>`, `CLOSE`, `ACK`, `RETRY` |

Chọn board/core và các chân nút/LED theo firmware và sơ đồ phần cứng được bàn giao. Không suy ra chân GPIO chỉ từ tên bo nRF52840.

### Bước 2 — Tạo môi trường Python

Ubuntu/Linux, tại thư mục `ars2`:

```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements.txt
python -m pip install bleak
```

Windows PowerShell:

```powershell
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
.\.venv\Scripts\python.exe -m pip install bleak
```

`bleak` hiện chưa được liệt kê trong `requirements.txt`, nên phải cài riêng để chạy BLE. Trên Windows, dùng trực tiếp Python trong `.venv` như trên sẽ không cần thay đổi chính sách chạy script PowerShell.

### Bước 3 — Kiểm tra địa chỉ website

Trong `ble_web_gateway.py`:

```python
DEVICE_NAME = "Quiz_NRF52840"
API_BASE = "http://localhost/ars2"
```

| Cách chạy website | Giá trị `API_BASE` |
| --- | --- |
| Apache/XAMPP ở cổng 80 | `http://localhost/ars2` |
| Máy chủ PHP tích hợp | `http://127.0.0.1:8000` |
| Máy chủ khác trong mạng LAN | Địa chỉ máy chủ thực tế, ví dụ `http://192.168.1.10/ars2` |

`localhost` luôn chỉ máy đang chạy gateway. Không thêm `/api_gateway_state.php` vào `API_BASE`.

### Bước 4 — Bật thiết bị và chạy gateway

Bật Bluetooth trên máy và cấp nguồn cho nRF52840. Dừng gateway cũ bằng **Ctrl+C** trước khi chạy bản mới.

Ubuntu/Linux, sau khi kích hoạt `.venv`:

```bash
ARS_SESSION_ID=0 python ble_web_gateway.py
```

Windows PowerShell:

```powershell
$env:ARS_SESSION_ID = "0"
.\.venv\Scripts\python.exe ble_web_gateway.py
```

Chế độ `0` là mặc định và có hành vi sau:

| Trạng thái | Hành vi |
| --- | --- |
| Thiết bị hợp lệ, chưa có phiên chạy | Chấp nhận thiết bị, giữ BLE và chờ |
| Có đúng một phiên chạy thuộc tài khoản sở hữu thiết bị | Tự chọn phiên và gửi câu hỏi |
| Phiên cũ kết thúc, phiên mới bắt đầu | Tự chuyển sang phiên mới |
| Có nhiều phiên cùng chạy | Giữ kết nối, chờ kết thúc các phiên thừa |
| Mã thiết bị thuộc nhiều tài khoản | Không tự chọn tài khoản; cần chỉ định phiên |

Để chỉ định một phiên cụ thể, thay `26` bằng ID thực tế:

```bash
ARS_SESSION_ID=26 python ble_web_gateway.py
```

Trong PowerShell, đặt `$env:ARS_SESSION_ID = "26"` trước khi chạy Python. Khi đã chỉ định ID, gateway giữ phiên đó; đặt lại `0` để trở về tự động.

### Bước 5 — Xác nhận hoạt động

Log thông thường có các bước:

```text
Da ket noi BLE
Da bat Notification
Laptop -> nRF52840: REQUEST_ID
nRF52840 -> Laptop: ID:NRF001
Laptop -> nRF52840: ACCEPT
```

Khi phiên có câu hỏi đang mở, gateway gửi `QUESTION:<id>`. Chọn A/B/C/D trên thiết bị rồi nhấn nút gửi. Kiểm tra log lưu dữ liệu, phản hồi `ACK` và kết quả trên website.

## 8. Chạy mô phỏng không cần thiết bị

Cài các gói trong `requirements.txt` theo phần môi trường Python. Gateway mô phỏng không cần `bleak` hoặc Bluetooth.

Trong `mock_gateway.py`, kiểm tra danh sách `DEVICES` mặc định từ `NRF001` đến `NRF005`. Đăng ký các mã tương ứng dưới tài khoản sở hữu phiên hoặc chỉnh danh sách cho khớp dữ liệu thực tế.

Tạo và bắt đầu một phiên thử nghiệm. Gateway mô phỏng **cần ID phiên cụ thể**, không hỗ trợ chế độ `ARS_SESSION_ID=0` của gateway BLE.

Ví dụ Ubuntu/Linux với phiên `26` và Apache:

```bash
ARS_API_URL=http://localhost/ars2/api_response.php \
ARS_SESSION_ID=26 \
python mock_gateway.py
```

Windows PowerShell:

```powershell
$env:ARS_API_URL = "http://localhost/ars2/api_response.php"
$env:ARS_SESSION_ID = "26"
.\.venv\Scripts\python.exe mock_gateway.py
```

Thay `26` bằng ID thực tế. Với máy chủ PHP tích hợp, dùng `http://127.0.0.1:8000/api_response.php`. Biến `ARS_API_URL` chỉ dành cho gateway mô phỏng; gateway BLE đọc `API_BASE` trong mã nguồn.

Gateway mô phỏng ghi câu trả lời thật vào database và cập nhật trạng thái online. Dùng phiên thử nghiệm riêng; dừng bằng **Ctrl+C** sau khi kiểm tra.

## 9. Kiểm thử và xác nhận hoạt động

### Kiểm tra thủ công

- Website đăng ký/đăng nhập được và không báo lỗi kết nối MySQL.
- Tạo được bộ câu hỏi, thiết bị và phiên trong cùng tài khoản.
- Gateway nhận `ACCEPT`, đọc đúng phiên và gửi được câu trả lời.
- Trang kết quả hiển thị câu trả lời vừa gửi.
- Khi dừng gateway, thiết bị hiển thị offline sau khoảng 10 giây cộng thời gian cập nhật giao diện.
- Với chế độ tự động, tạo phiên mới và bắt đầu sau khi kết thúc phiên cũ; gateway chuyển mà không cần khởi động lại.

Trạng thái online được tính từ `status` và `last_seen` theo đồng hồ database. Quá 10 giây không nhận cập nhật thì thiết bị được hiển thị offline. Dashboard và danh sách thiết bị lấy trạng thái mỗi 2 giây khi kết nối HTTP hoạt động; online biểu thị lần liên lạc gần đây với API, không phải phép đo Bluetooth trực tiếp của trình duyệt.

### Kiểm tra tự động

Chạy từ thư mục gốc. Bài kiểm tra PHP dùng database được cấu hình trong `db.php`; nên trỏ đến bản database thử nghiệm có đầy đủ cấu trúc:

```bash
php tests/account_isolation.php
python3 -B -m unittest discover -s tests -p 'test_ble_gateway.py'
```

Trên Windows, thay `python3` bằng `python` hoặc đường dẫn Python trong `.venv`.

- Bài kiểm tra PHP tạo dữ liệu tạm, chạy máy chủ HTTP cục bộ, kiểm tra quyền truy cập, trạng thái thiết bị và chọn phiên; dọn dữ liệu tạm khi kết thúc.
- Bài kiểm tra Python mô phỏng BLE/HTTP, kiểm tra chờ phiên, chuyển phiên và ID phiên dùng khi gửi đáp án; không cần thiết bị thật.
- Kết quả thành công có `PASS` ở bài kiểm tra PHP và `OK` ở bài kiểm tra Python. Các kiểm tra mô phỏng không thay thế việc kiểm tra kết nối trên phần cứng thực tế.

## 10. Sao lưu và chuyển sang máy khác

### Xuất bản sao lưu mới

Tệp `ars_db_schema.sql` chỉ chứa cấu trúc bảng; muốn chuyển dữ liệu thật giữa hai máy, cần xuất một bản sao lưu mới từ database đang sử dụng. Để gửi dữ liệu mới nhất, xuất lại bằng terminal Linux hoặc Windows Command Prompt có `mysqldump` trong `PATH`:

```bash
mysqldump --single-transaction --quick --no-tablespaces --default-character-set=utf8mb4 -u ars_app -p ars_db > ars_db_backup.sql
```

Thay `ars_app` bằng tài khoản MySQL thực tế; nhập mật khẩu khi được hỏi. Chọn tên tệp khác nếu cần giữ bản sao lưu trước đó. Có thể dùng phpMyAdmin → chọn database → **Export / Xuất** → định dạng SQL thay cho dòng lệnh.

### Nội dung bàn giao

Gửi mã nguồn `webserver`, firmware từ `nrf_src` nếu cần dùng phần cứng, bản SQL phù hợp và README này. Giữ `composer.json`, `composer.lock`, `requirements.txt` để người nhận cài lại thư viện. Không cần gửi môi trường Python `.venv`, thư mục `.git` hay bộ nhớ đệm `__pycache__`.

Người nhận thực hiện:

1. Cài môi trường theo phần 4 hoặc 5.
2. Tạo database mới và nhập bản SQL nhận được.
3. Cấu hình `db.php` hoặc biến môi trường MySQL theo máy nhận.
4. Cài thư viện PHP/Python.
5. Chỉnh `API_BASE` hoặc `ARS_API_URL` theo URL website thực tế.
6. Đăng nhập đúng tài khoản dữ liệu hoặc tạo tài khoản mới và chuẩn bị dữ liệu riêng.
7. Bật gateway và kiểm tra một phiên thử nghiệm.

Bản sao lưu dữ liệu thật có thể chứa tài khoản ứng dụng và thông tin sinh viên; chỉ gửi cho người nhận có quyền sử dụng. Tệp schema đi kèm không tạo tài khoản MySQL `ars_app` trên máy nhận và không thay thế bước cấu hình `db.php`.

## 11. Xử lý lỗi thường gặp

| Hiện tượng | Nguyên nhân cần kiểm tra | Cách xử lý |
| --- | --- | --- |
| Website báo lỗi kết nối hoặc `Access denied` | MySQL chưa chạy, sai tài khoản hoặc mật khẩu | Khởi động dịch vụ; đối chiếu bốn biến trong `db.php`; kiểm tra đăng nhập MySQL |
| `Unknown database` / thiếu bảng | Chưa tạo database hoặc chưa nhập SQL | Tạo database và nhập tệp schema đi kèm hoặc bản sao lưu đầy đủ |
| Không thấy dữ liệu sau khi đăng ký | Tài khoản mới có phạm vi dữ liệu riêng | Chuẩn bị dữ liệu cho tài khoản mới hoặc đăng nhập tài khoản sở hữu dữ liệu cũ |
| Composer báo thiếu extension | PHP CLI chưa bật module cần thiết | Chạy `php --ini`, `php -m`, bật/cài module rồi chạy lại Composer |
| `ModuleNotFoundError: bleak` | Chưa cài `bleak` trong Python đang dùng | Dùng đúng Python của `.venv` và chạy `python -m pip install bleak` |
| Không tìm thấy `Quiz_NRF52840` | Bluetooth tắt, thiết bị chưa quảng bá hoặc sai tên | Kiểm tra nguồn, firmware, tên BLE và việc thiết bị đang bị ứng dụng khác kết nối |
| `404 Resource not found` khi dùng ID cụ thể | Phiên bị xóa hoặc ID không còn đúng | Kiểm tra ID; dùng `ARS_SESSION_ID=0` với gateway BLE để tự tìm phiên |
| `Device not found` / `REJECT` | Mã firmware không khớp hoặc thiết bị không thuộc tài khoản của phiên | Đối chiếu `CLIENT_ID`, mã thiết bị trên web và tài khoản sở hữu |
| Mã thiết bị thuộc nhiều tài khoản | Chế độ tự động không xác định được một chủ sở hữu | Dùng ID phiên cụ thể hoặc mã thiết bị phân biệt giữa các tài khoản |
| Gateway báo nhiều phiên đang chạy | Tài khoản có nhiều phiên `running` | Kết thúc các phiên thừa hoặc chỉ định `ARS_SESSION_ID` |
| BLE đã kết nối nhưng đang chờ | Chưa có phiên chạy của tài khoản sở hữu thiết bị | Tạo/chọn phiên rồi bấm bắt đầu trên website |
| API trả HTML hoặc không phải JSON | Sai URL, cổng hoặc lỗi PHP | Đối chiếu `API_BASE`; kiểm tra log Apache/PHP và database |
| Không có câu trả lời mới | Sai phiên, phiên chưa chạy hoặc câu hỏi đã chuyển | Kiểm tra log gateway, câu hỏi hiện tại và trang Responses |
| Thiết bị vẫn online sau khi tắt | Chưa hết thời gian chờ hoặc gateway mô phỏng còn chạy | Dừng các gateway không dùng; chờ hơn 10 giây và lần cập nhật giao diện tiếp theo |
| Website đúng nhưng gateway gọi sai máy | `localhost` trỏ về máy chạy gateway | Dùng IP/tên máy chủ website thực tế và kiểm tra kết nối mạng |

## 12. Lưu ý triển khai và giới hạn hiện tại

- Các API gateway hiện không yêu cầu API key. Người biết ID phiên có thể gọi API để gửi đáp án hoặc chuyển câu hỏi; chế độ tự động còn cho phép tìm phiên qua mã thiết bị chỉ thuộc một tài khoản. Phạm vi tài khoản vẫn được áp dụng, nhưng cơ chế này không thay thế xác thực gateway khi triển khai ra Internet. Logic xác định tài khoản nằm trong [account.php](account.php).
- Không để bản sao lưu SQL chứa dữ liệu thật được tải công khai qua web. Sau khi bàn giao/khôi phục, chuyển bản sao lưu ra ngoài thư mục phục vụ web hoặc cấu hình máy chủ chặn truy cập các tệp đó.
- Máy chủ `php -S` dùng cho chạy thử cục bộ. Cấu hình Apache, truy cập mạng và quyền truy cập dữ liệu cần được điều chỉnh theo môi trường triển khai thực tế.
- Gateway BLE hiện tìm một thiết bị theo `DEVICE_NAME` trong mỗi lần kết nối. Chạy nhiều thiết bị đồng thời cần thiết kế thêm cách nhận diện và quản lý nhiều kết nối.
- Gateway BLE hiện gọi API chuyển câu hỏi sau khi lưu thành công một đáp án. Cần tính đến hành vi này khi tổ chức phiên nhiều người, dù giao diện phiên có các lựa chọn chuyển câu hỏi riêng.
- Hướng dẫn cài đặt Windows là quy trình cấu hình; kết nối BLE và firmware cần được kiểm tra trên hệ điều hành, adapter Bluetooth và board thực tế của người sử dụng.
