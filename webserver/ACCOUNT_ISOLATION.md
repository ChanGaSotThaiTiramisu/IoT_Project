# Quyền sở hữu dữ liệu và gateway

Sinh viên, bộ câu hỏi, thiết bị và phiên thuộc một tài khoản (`user_id`). Câu hỏi thuộc tài khoản sở hữu bộ câu hỏi; câu trả lời thuộc phiên và thiết bị của tài khoản tương ứng. `account.php` kiểm tra đăng nhập hoặc phạm vi tài khoản trước các truy vấn và thay đổi theo ID.

Các API gateway (`api_gateway_state.php`, `api_response.php`, `api_next_question.php`) hiện **không yêu cầu API key**. Với request không có phiên đăng nhập trình duyệt, `session_id` xác định tài khoản sở hữu. Gateway BLE có thêm chế độ tự động (`ARS_SESSION_ID=0`): request trạng thái với `auto_session=1` và `device_id`. Mã thiết bị phải thuộc duy nhất một tài khoản; mã xuất hiện ở nhiều tài khoản trả HTTP 409 và gateway cần ID phiên cụ thể. Khi tài khoản có đúng một phiên đang chạy, API trả ID phiên đó. Không có phiên hoặc có nhiều phiên đang chạy, API trả `waiting` hoặc `ambiguous` và gateway giữ kết nối nhưng không gửi đáp án.

API nhận đáp án và chuyển câu hỏi vẫn cần ID phiên cụ thể. Người truy cập được endpoint và biết ID phiên có thể đọc trạng thái, gửi câu trả lời hoặc chuyển câu hỏi; người biết mã thiết bị duy nhất có thể tìm ID phiên đang chạy. Chỉ triển khai trên phạm vi mạng phù hợp nếu chưa bổ sung xác thực gateway.

Mã sinh viên, mã thiết bị và tên bộ câu hỏi là duy nhất trong từng tài khoản. `packet_id` của câu trả lời là duy nhất trong từng phiên.

Nếu nâng cấp database cũ chưa có `user_id`, hãy sao lưu rồi chạy từ CLI:

```bash
php migrations/account_ownership.php TEN_TAI_KHOAN_SO_HUU
```

Tên tài khoản phải tồn tại. Script gán dữ liệu cũ cho tài khoản đó và lưu bản chụp khôi phục trong thư mục tạm của hệ thống. MySQL DDL không thể hoàn tác bằng transaction. Không chạy script này khi đã nhập `ars_db_schema.sql` đi kèm.

Chạy `php tests/account_isolation.php` trên database thử nghiệm để kiểm tra phân tách tài khoản và chế độ gateway; bài kiểm tra tạo rồi xóa dữ liệu tạm.
