import asyncio
import os
import time

import requests
from bleak import BleakClient, BleakScanner


DEVICE_NAME = "Quiz_NRF52840"
API_BASE = "http://localhost/ars2"
# Zero follows the device owner's running session; a positive ID pins a session.
SESSION_ID = int(os.environ.get("ARS_SESSION_ID", "0"))


def parse_device_id(message):
    # Recover repeated identical ID frames when their newline was lost.
    codes = [part.strip() for part in message[3:].split("ID:")]
    if not codes[0] or any(code != codes[0] for code in codes):
        raise ValueError("Goi ID rong hoac chua nhieu ID khac nhau; kiem tra firmware/newline.")
    if ":" in codes[0]:
        raise ValueError("Goi ID chua dau ':' khong hop le; kiem tra firmware/newline.")
    if len(codes) > 1:
        print(f"[BLE WARNING] Nhan {len(codes)} goi ID trung bi ghep; dung ID {codes[0]!r}.")
    return codes[0]

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
    url = f"{API_BASE}/api_gateway_state.php"
    diagnostics = {"url": url, "stage": "HTTP request", "http_status": None}
    try:
        response = requests.get(
            url,
            params={
                "session_id": SESSION_ID,
                "auto_session": "1" if SESSION_ID == 0 else "0",
                "device_id": device_code,
            },
            timeout=HTTP_TIMEOUT,
        )
        diagnostics.update(
            url=response.url,
            http_status=response.status_code,
            content_type=response.headers.get("Content-Type", "unknown"),
            stage="Decode JSON",
        )
        # Parse separately: requests' JSON errors also inherit RequestException.
        try:
            state = response.json()
        except ValueError:
            return {
                "success": False,
                "message": "API did not return JSON (co the la trang HTML/404/login).",
                "diagnostics": diagnostics,
            }
        if not isinstance(state, dict):
            return {
                "success": False,
                "message": "API JSON phai la object, khong phai list/null/scalar.",
                "diagnostics": diagnostics,
            }
        diagnostics["stage"] = "API validation"
        state["diagnostics"] = diagnostics
        if not response.ok:
            state["success"] = False
            state.setdefault("message", f"HTTP {response.status_code}")
        elif "success" not in state:
            state["message"] = "API JSON thieu truong success."
        return state
    except requests.RequestException as error:
        return {
            "success": False,
            "message": f"{type(error).__name__}: {error}",
            "diagnostics": diagnostics,
        }


def log_device_rejection(device_code, state):
    details = state.get("diagnostics", {})
    status = details.get("http_status")
    message = state.get("message") or "API tra success=false, khong co message."
    print("\n[REJECT] Xac thuc thiet bi that bai", flush=True)
    print(f"  Device ID : {device_code!r} | Session ID: {SESSION_ID}")
    print(f"  Buoc loi  : {details.get('stage', 'Unknown')}")
    print(f"  API       : GET {details.get('url', API_BASE)}")
    print(f"  HTTP      : {status if status is not None else 'Khong nhan duoc response'}")
    print(f"  Kieu data : {details.get('content_type', 'N/A')}")
    print(f"  Ly do     : {message}")
    if details.get("stage") == "BLE ID parsing (chua goi API)":
        hint = "Firmware can gui moi goi ID:<device_code> ket thuc bang newline."
    elif status == 401:
        hint = "Kiem tra website da cap nhat API gateway khong yeu cau key."
    elif status == 403:
        hint = "Khong co quyen truy cap API."
    elif status == 404 and message == "Resource not found.":
        hint = (
            f"Phien {SESSION_ID} khong ton tai hoac khong thuoc tai khoan dang truy cap. "
            "Lay ID phien tren web, roi chay: ARS_SESSION_ID=<ID_phien> python ble_web_gateway.py"
        )
    elif str(message).startswith("Device not found:"):
        hint = "Kiem tra device_code trong bang devices va user_id cua chu thiet bi."
    elif status == 404:
        hint = "Kiem tra API_BASE/duong dan PHP va session; doi chieu Ly do o tren."
    elif status is not None and status >= 500:
        hint = "Kiem tra PHP/web server error log va ket noi database."
    elif not device_code:
        hint = "Goi ID: rong; kiem tra CLIENT_ID trong firmware."
    elif status is None:
        hint = f"Kiem tra web server, host/port va mang (timeout={HTTP_TIMEOUT}s)."
    else:
        hint = "Kiem tra noi dung API va cac tham so device_id/session_id o tren."
    print(f"  Kiem tra  : {hint}", flush=True)

# Lưu đáp án của thiết bị vào cơ sở dữ liệu thông qua API.
def http_save_answer(device_code, session_id, question_id, answer):
    packet_id = int(time.time_ns() // 1_000_000)

    try:
        response = requests.post(
            f"{API_BASE}/api_response.php",
            data={
                "packet_id": packet_id,
                "device_id": device_code,
                "session_id": session_id,
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
def http_advance_question(session_id, question_id):
    try:
        response = requests.post(
            f"{API_BASE}/api_next_question.php",
            data={
                "session_id": session_id,
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
        opened_session_id = None

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
            nonlocal accepted, device_code, opened_question_id, opened_session_id

            # Capture the session before yielding so queued answers cannot move
            # into another session that happens to use the same question.
            answer_session_id = opened_session_id

            print(f"nRF52840 -> Laptop: {message}")

            if message.startswith("ID:"):
                try:
                    candidate = parse_device_id(message)
                except ValueError as error:
                    accepted = False
                    device_code = None
                    opened_question_id = None
                    log_device_rejection(message, {
                        "message": str(error),
                        "diagnostics": {"stage": "BLE ID parsing (chua goi API)"},
                    })
                    await send_command("REJECT")
                    return
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
                    log_device_rejection(candidate, state)
                    await send_command("REJECT")
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
                    or answer_session_id is None
                    or session.get("id") != answer_session_id
                    or opened_session_id != answer_session_id
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
                    answer_session_id,
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
                        answer_session_id,
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
            nonlocal opened_question_id, opened_session_id
            last_session_state = None

            while client.is_connected:
                if not accepted or device_code is None:
                    await asyncio.sleep(STATE_POLL_DELAY)
                    continue

                async with answer_lock:
                    state = await asyncio.to_thread(http_get_state, device_code)
                    session = state.get("session", {})
                    if not state.get("success"):
                        print("Khong doc duoc trang thai web:", state.get("message"))
                        if opened_question_id is not None:
                            await send_command("CLOSE")
                        opened_question_id = None
                        opened_session_id = None
                    else:
                        session_state = (session.get("id"), session.get("status"))
                        if session_state != last_session_state:
                            if session.get("status") == "waiting":
                                print("Dang cho: hay bat dau mot phien tren website. BLE van ket noi.")
                            elif session.get("status") == "ambiguous":
                                print("Co nhieu phien dang chay. Hay ket thuc phien cu hoac dat ARS_SESSION_ID.")
                            else:
                                print(f"Phien hien tai: {session.get('id')} ({session.get('status')})")
                            last_session_state = session_state

                        next_session_id = session.get("id")
                        next_question_id = session.get("current_question_id") if session.get("status") == "running" else None
                        if (next_session_id, next_question_id) != (opened_session_id, opened_question_id):
                            if opened_question_id is not None:
                                await send_command("CLOSE")
                            opened_session_id = next_session_id
                            opened_question_id = next_question_id
                            if next_question_id is not None:
                                await send_command(f"QUESTION:{next_question_id}")
                                print(f"Da mo cau hoi {next_question_id} cua phien {next_session_id}")

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
    print("Che do phien: " + ("tu dong" if SESSION_ID == 0 else str(SESSION_ID)))
    while True:
        try:
            await connect_once()
        except asyncio.CancelledError:
            raise
        except Exception as error:
            print(f"Loi BLE: {error}")

        print(f"Mat ket noi. Thu lai sau {RECONNECT_DELAY} giay...")
        await asyncio.sleep(RECONNECT_DELAY)


if __name__ == "__main__":
    try:
        asyncio.run(run_forever())
    except KeyboardInterrupt:
        print("\nDa dung BLE web gateway")

