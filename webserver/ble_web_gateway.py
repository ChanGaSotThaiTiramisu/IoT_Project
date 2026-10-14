import asyncio
import time

import requests
from bleak import BleakClient, BleakScanner


DEVICE_NAME = "Quiz_NRF52840"
API_BASE = "http://localhost/ars2"
SESSION_ID = 1

RECONNECT_DELAY = 3
STATE_POLL_DELAY = 0.5
HTTP_TIMEOUT = 5

# Nordic UART Service (NUS)
# nRF52840 -> laptop
NUS_TX_UUID = "6e400003-b5a3-f393-e0a9-e50e24dcca9e"
# laptop -> nRF52840
NUS_RX_UUID = "6e400002-b5a3-f393-e0a9-e50e24dcca9e"

# Lấy trạng thái của thiết bị từ cơ sở dữ liệu thông qua API.
def http_get_state(device_code):
    try:
        response = requests.get(
            f"{API_BASE}/api_gateway_state.php",
            params={
                "session_id": SESSION_ID,
                "device_id": device_code,
            },
            timeout=HTTP_TIMEOUT,
        )
        return response.json()
    except requests.RequestException as error:
        return {"success": False, "message": f"HTTP error: {error}"}
    except ValueError:
        return {"success": False, "message": "API did not return JSON"}

# Lưu đáp án của thiết bị vào cơ sở dữ liệu thông qua API.
def http_save_answer(device_code, question_id, answer):
    packet_id = int(time.time_ns() // 1_000_000)

    try:
        response = requests.post(
            f"{API_BASE}/api_response.php",
            data={
                "packet_id": packet_id,
                "device_id": device_code,
                "session_id": SESSION_ID,
                "question_id": question_id,
                "answer": answer,
            },
            timeout=HTTP_TIMEOUT,
        )
        return response.json()
    except requests.RequestException as error:
        return {"success": False, "message": f"HTTP error: {error}"}
    except ValueError:
        return {"success": False, "message": "API did not return JSON"}

# Lấy câu hỏi tiếp theo từ cơ sở dữ liệu thông qua API.
def http_advance_question(question_id):
    try:
        response = requests.post(
            f"{API_BASE}/api_next_question.php",
            data={
                "session_id": SESSION_ID,
                "current_question_id": question_id,
            },
            timeout=HTTP_TIMEOUT,
        )

        return response.json()

    except requests.RequestException as error:
        return {
            "success": False,
            "message": f"HTTP error: {error}",
        }

    except ValueError:
        return {
            "success": False,
            "message": "API did not return JSON",
        }

# Kết nối một lần với thiết bị BLE.
async def connect_once():

    print(f"Dang tim {DEVICE_NAME}...")
    device = await BleakScanner.find_device_by_name(DEVICE_NAME, timeout=15)

    if device is None:
        print("Khong tim thay thiet bi")
        return

    print(f"Da tim thay: {device.address}")
# Kết nối với thiết bị BLE
    async with BleakClient(device) as client:
        print("Da ket noi BLE")

        # Khởi tạo các biến và khóa để quản lý trạng thái và đồng bộ hóa

        receive_buffer = bytearray()
        send_lock = asyncio.Lock()
        answer_lock = asyncio.Lock()
        message_tasks = set()

        accepted = False
        device_code = None
        opened_question_id = None

        # Gửi lệnh đến thiết bị BLE

        async def send_command(command):
            async with send_lock:
                await client.write_gatt_char(
                    NUS_RX_UUID,
                    f"{command}\n".encode("utf-8"),
                    response=False,
                )
            print(f"Laptop -> nRF52840: {command}")

        # Xử lý tin nhắn nhận được từ thiết bị BLE
        
        async def process_message(message):
            nonlocal accepted, device_code, opened_question_id

            print(f"nRF52840 -> Laptop: {message}")

            if message.startswith("ID:"):
                candidate = message.split(":", 1)[1].strip()
                state = await asyncio.to_thread(http_get_state, candidate)

                if state.get("success"):
                    device_code = candidate
                    accepted = True
                    opened_question_id = None
                    await send_command("ACCEPT")
                    print(f"Da xac thuc thiet bi: {device_code}")
                else:
                    accepted = False
                    device_code = None
                    await send_command("REJECT")
                    print("Tu choi thiet bi:", state.get("message"))
                return

            if not message.startswith("ANS:"):
                print("Bo qua goi tin khong ho tro")
                return

            if not accepted or device_code is None:
                print("Bo qua dap an: thiet bi chua duoc ACCEPT")
                return

            # Ho tro ca goi moi ANS:<question_id>:<answer>
            # va goi cu ANS:<answer>.
            parts = message.split(":")
            if len(parts) == 3:
                try:
                    question_id = int(parts[1])
                except ValueError:
                    print("Question ID khong hop le")
                    return
                answer = parts[2].strip().upper()
            elif len(parts) == 2 and opened_question_id is not None:
                question_id = opened_question_id
                answer = parts[1].strip().upper()
            else:
                print("Sai dinh dang dap an")
                return

            if answer not in ("A", "B", "C", "D"):
                print("Dap an khong hop le:", answer)
                return

            async with answer_lock:
                state = await asyncio.to_thread(http_get_state, device_code)
                session = state.get("session", {})

                if (
                    not state.get("success")
                    or session.get("status") != "running"
                    or session.get("current_question_id") != question_id
                ):
                    print("Khong luu: cau hoi khong con mo")
                    await send_command("CLOSE")
                    opened_question_id = None
                    return

                result = await asyncio.to_thread(
                    http_save_answer,
                    device_code,
                    question_id,
                    answer,
                )

                if result.get("success"):
                    print(
                        f"Da luu DB: device={device_code}, "
                        f"question={question_id}, answer={answer}"
                    )
                    await send_command("ACK")
                    
                    next_result = await asyncio.to_thread(
                        http_advance_question,
                        question_id,
                    )

                    if next_result.get("success"):
                        next_question_id = next_result.get(
                            "next_question_id"
                        )

                        if next_question_id is None:
                            print("Da het cau hoi")
                            print("Session da ket thuc")
                        else:
                            print(
                                f"Web chuyen sang cau "
                                f"{next_question_id}"
                            )
                    else:
                        print(
                            "Khong chuyen duoc cau tiep theo:",
                            next_result.get("message"),
                        )
                else:
                    print("Luu DB that bai:", result.get("message"))
                    await send_command("RETRY")

        def notification_handler(_sender, data):
            receive_buffer.extend(data)

            while True:
                newline_position = receive_buffer.find(b"\n")
                if newline_position == -1:
                    break

                line = bytes(receive_buffer[:newline_position])
                del receive_buffer[: newline_position + 1]

                message = line.decode("utf-8", errors="ignore").strip()
                if message:
                    task = asyncio.create_task(process_message(message))
                    message_tasks.add(task)
                    task.add_done_callback(message_tasks.discard)

        async def poll_web_state():
            nonlocal opened_question_id

            while client.is_connected:
                if not accepted or device_code is None:
                    await asyncio.sleep(STATE_POLL_DELAY)
                    continue

                state = await asyncio.to_thread(http_get_state, device_code)
                session = state.get("session", {})

                if not state.get("success"):
                    print("Khong doc duoc trang thai web:", state.get("message"))
                    await asyncio.sleep(STATE_POLL_DELAY)
                    continue

                is_running = session.get("status") == "running"
                current_question_id = session.get("current_question_id")

                if is_running and current_question_id is not None:
                    if current_question_id != opened_question_id:
                        await send_command(f"QUESTION:{current_question_id}")
                        opened_question_id = current_question_id
                        print(f"Da mo cau hoi {current_question_id} tu web")
                        
                elif opened_question_id is not None:
                    await send_command("CLOSE")
                    opened_question_id = None
                    print("Web da dong cau hoi")

                await asyncio.sleep(STATE_POLL_DELAY)

        await client.start_notify(NUS_TX_UUID, notification_handler)
        print("Da bat Notification")

        # Chu dong hoi ID de khong bi loi ket noi xong nhung khong nhan duoc ID.
        await asyncio.sleep(0.5)
        await send_command("REQUEST_ID")

        poll_task = asyncio.create_task(poll_web_state())

        try:
            while client.is_connected:
                await asyncio.sleep(1)
        finally:
            poll_task.cancel()
            await asyncio.gather(poll_task, return_exceptions=True)

            for task in message_tasks:
                task.cancel()
            if message_tasks:
                await asyncio.gather(*message_tasks, return_exceptions=True)


async def run_forever():
    while True:
        try:
            await connect_once()
        except asyncio.CancelledError:
            raise
        except Exception as error:
            print(f"Loi BLE: {error}")

        print(f"Mat ket noi. Thu lai sau {RECONNECT_DELAY} giay...")
        await asyncio.sleep(RECONNECT_DELAY)


try:
    asyncio.run(run_forever())
except KeyboardInterrupt:
    print("\nDa dung BLE web gateway")

