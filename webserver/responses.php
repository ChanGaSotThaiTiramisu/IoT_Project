import requests
import time
import random
import mysql.connector


# =====================================================
# CONFIG
# =====================================================

API_URL = "http://localhost/ars2/webserver/api_response.php"

SESSION_ID = 1

DEVICES = [
    "NRF001",
    "NRF002",
    "NRF003",
    "NRF004",
    "NRF005"
]

PACKET_DELAY = 1

CHECK_SESSION_DELAY = 1

# =====================================================
# MYSQL CONFIG
# =====================================================

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "ars"
}


# =====================================================
# CONNECT DATABASE
# =====================================================

def get_db_connection():

    try:

        conn = mysql.connector.connect(
            host=DB_CONFIG["host"],
            user=DB_CONFIG["user"],
            password=DB_CONFIG["password"],
            database=DB_CONFIG["database"]
        )

        return conn

    except mysql.connector.Error as e:

        print()
        print("[ERROR] Không thể kết nối MySQL:")
        print(e)

        return None


# =====================================================
# GET CURRENT QUESTION
# =====================================================

def get_session_info():

    conn = get_db_connection()

    if conn is None:
        return None


    try:

        cursor = conn.cursor(dictionary=True)

        cursor.execute(
            """
            SELECT
                id,
                status,
                current_question_id
            FROM sessions
            WHERE id = %s
            """,
            (SESSION_ID,)
        )

        session = cursor.fetchone()

        cursor.close()
        conn.close()

        return session


    except mysql.connector.Error as e:

        print(
            "[ERROR] Không đọc được session:",
            e
        )

        try:
            conn.close()
        except:
            pass

        return None


# =====================================================
# SEND PACKET
# =====================================================

def send_packet(
    packet_id,
    device_id,
    session_id,
    question_id,
    answer
):

    data = {

        "packet_id": packet_id,

        "device_id": device_id,

        "session_id": session_id,

        "question_id": question_id,

        "answer": answer

    }


    try:

        response = requests.post(

            API_URL,

            data=data,

            timeout=5

        )


        print(

            f"[GATEWAY] "
            f"Packet={packet_id} | "
            f"Device={device_id} | "
            f"Question={question_id} | "
            f"Answer={answer} | "
            f"HTTP={response.status_code}"

        )


        try:

            result = response.json()

            print(

                f"          -> "
                f"{result.get('action', 'unknown')} | "
                f"{result.get('message', '')}"

            )

        except ValueError:

            print(
                "          -> "
                "Server trả về dữ liệu không phải JSON"
            )


    except requests.RequestException as e:

        print()

        print(
            "[ERROR] Không thể kết nối API:",
            e
        )


# =====================================================
# SEND ALL DEVICES
# =====================================================

def send_question(question_id, packet_id):

    print()
    print("----------------------------------------")
    print(
        f"[QUESTION] "
        f"Đang gửi câu {question_id}"
    )
    print("----------------------------------------")


    for device_id in DEVICES:

        # =============================================
        # KIỂM TRA ADMIN CÓ ĐỔI CÂU KHÔNG
        # =============================================

        session = get_session_info()

        if session is None:

            print(
                "[GATEWAY] Không đọc được session."
            )

            return packet_id


        if session["status"] != "running":

            print()

            print(
                "[GATEWAY] Session không còn RUNNING."
            )

            return packet_id


        current_question = session[
            "current_question_id"
        ]


        if current_question != question_id:

            print()

            print(
                "[GATEWAY] Admin đã chuyển câu."
            )

            print(
                f"          Câu cũ: {question_id}"
            )

            print(
                f"          Câu mới: {current_question}"
            )

            return packet_id


        # =============================================
        # RANDOM ANSWER
        # =============================================

        answer = random.choice(
            ["A", "B", "C", "D"]
        )


        # =============================================
        # SEND
        # =============================================

        send_packet(

            packet_id,

            device_id,

            SESSION_ID,

            question_id,

            answer

        )


        packet_id += 1


        time.sleep(
            PACKET_DELAY
        )


    print()

    print(
        f"[GATEWAY] "
        f"5/5 device đã trả lời câu "
        f"{question_id}"
    )

    print()

    return packet_id


# =====================================================
# MAIN
# =====================================================

packet_id = 1000

last_question = None

print()
print("========================================")
print("          ARS MOCK GATEWAY")
print("========================================")
print()

print(
    "API      :",
    API_URL
)

print(
    "Session  :",
    SESSION_ID
)

print(
    "Devices  :",
    ", ".join(DEVICES)
)

print()

print(
    "Gateway đang chờ Admin mở câu hỏi..."
)

print(
    "Nhấn Ctrl+C để dừng."
)

print()


try:

    while True:

        # =============================================
        # ĐỌC SESSION
        # =============================================

        session = get_session_info()


        if session is None:

            print(
                "[GATEWAY] Không tìm thấy session."
            )

            time.sleep(
                CHECK_SESSION_DELAY
            )

            continue


        status = session["status"]

        current_question = (
            session["current_question_id"]
        )


        # =============================================
        # SESSION CHƯA RUNNING
        # =============================================

        if status != "running":

            if last_question is not None:

                print()

                print(
                    "[GATEWAY] Session đã dừng."
                )

                print(
                    "[GATEWAY] Đang chờ session chạy lại..."
                )

                last_question = None


            time.sleep(
                CHECK_SESSION_DELAY
            )

            continue


        # =============================================
        # KHÔNG CÓ CÂU HỎI
        # =============================================

        if not current_question:

            print(
                "[GATEWAY] Chưa có current_question_id."
            )

            time.sleep(
                CHECK_SESSION_DELAY
            )

            continue


        # =============================================
        # CÂU KHÔNG THAY ĐỔI
        # =============================================

        if current_question == last_question:

            time.sleep(
                CHECK_SESSION_DELAY
            )

            continue


        # =============================================
        # PHÁT HIỆN CÂU MỚI
        # =============================================

        print()
        print("========================================")

        print(
            f"[GATEWAY] Phát hiện câu mới: "
            f"{current_question}"
        )

        print(
            f"[GATEWAY] Session: {SESSION_ID}"
        )

        print("========================================")


        # =============================================
        # GỬI 5 DEVICE
        # =============================================

        packet_id = send_question(

            current_question,

            packet_id

        )


        # =============================================
        # ĐÁNH DẤU ĐÃ XỬ LÝ
        # =============================================

        last_question = current_question


        print()

        print(
            "[GATEWAY] Đang chờ Admin..."
        )

        print(
            "[GATEWAY] "
            "Bấm 'Câu tiếp' hoặc 'Câu trước' "
            "trên sessions.php."
        )

        print()


        time.sleep(
            CHECK_SESSION_DELAY
        )


except KeyboardInterrupt:

    print()

    print("========================================")

    print(
        "Gateway đã dừng."
    )

    print("========================================")