import os
import requests
import time
import random
from urllib.parse import urljoin


# =====================================================
# CONFIG
# =====================================================

API_URL = os.environ.get("ARS_API_URL", "http://localhost/ars2/api_response.php")


SESSION_ID = int(os.environ.get("ARS_SESSION_ID", "1"))

DEVICES = [
    "NRF001",
    "NRF002",
    "NRF003",
    "NRF004",
    "NRF005"
]

PACKET_DELAY = 1

CHECK_SESSION_DELAY = 1

# Read only the session authorized by this account's gateway key.
def get_session_info():
    try:
        response = requests.get(
            urljoin(API_URL, "api_gateway_state.php"),
            params={"session_id": SESSION_ID, "device_id": DEVICES[0]},
            timeout=5,
        )
        response.raise_for_status()
        return response.json()["session"]
    except (requests.RequestException, ValueError, KeyError) as exc:
        print("[ERROR] Unable to read the session:", exc)
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
                "The server returned a non-JSON response"
            )


    except requests.RequestException as e:

        print()

        print(
            "[ERROR] Unable to connect to the API:",
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
        f"Sending question {question_id}"
    )
    print("----------------------------------------")


    for device_id in DEVICES:

        # =============================================
        # KIỂM TRA ADMIN CÓ ĐỔI CÂU KHÔNG
        # =============================================

        session = get_session_info()

        if session is None:

            print(
                "[GATEWAY] Unable to read the session."
            )

            return packet_id


        if session["status"] != "running":

            print()

            print(
                "[GATEWAY] The session is no longer running."
            )

            return packet_id


        current_question = session[
            "current_question_id"
        ]


        if current_question != question_id:

            print()

            print(
                "[GATEWAY] The admin changed the current question."
            )

            print(
                f"          Previous question: {question_id}"
            )

            print(
                f"          Current question: {current_question}"
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
        f"All 5 devices answered question "
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
    "The gateway is waiting for the admin to open a question..."
)

print(
    "Press Ctrl+C to stop."
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
                "[GATEWAY] Session not found."
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
                    "[GATEWAY] The session has stopped."
                )

                print(
                    "[GATEWAY] Waiting for the session to resume..."
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
                "[GATEWAY] No current question is set."
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
            f"[GATEWAY] New question detected: "
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
            "[GATEWAY] Waiting for the admin..."
        )

        print(
            "[GATEWAY] "
            "Select 'Next' or 'Previous' "
            "on sessions.php."
        )

        print()


        time.sleep(
            CHECK_SESSION_DELAY
        )


except KeyboardInterrupt:

    print()

    print("========================================")

    print(
        "Gateway stopped."
    )

    print("========================================")