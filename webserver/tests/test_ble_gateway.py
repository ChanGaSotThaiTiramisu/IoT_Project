"""Gateway regression checks without Bluetooth hardware or network access."""
import asyncio
import importlib.util
from pathlib import Path
import unittest
from unittest.mock import AsyncMock, MagicMock, patch


spec = importlib.util.spec_from_file_location(
    "gateway_under_test", Path(__file__).resolve().parents[1] / "ble_web_gateway.py"
)
gateway = importlib.util.module_from_spec(spec)
with patch.dict("sys.modules", {"bleak": MagicMock(), "requests": MagicMock()}):
    spec.loader.exec_module(gateway)


class GatewayTests(unittest.TestCase):
    def test_state_auto_and_explicit_modes(self):
        response = MagicMock(status_code=200, ok=True)
        response.json.return_value = {"success": True, "session": {"id": 52}}
        with patch.object(gateway.requests, "get", return_value=response) as get:
            for configured, auto in [(0, "1"), (26, "0")]:
                with patch.object(gateway, "SESSION_ID", configured):
                    self.assertTrue(gateway.http_get_state("NRF001")["success"])
                    self.assertEqual(get.call_args.kwargs["params"], {
                        "session_id": configured, "auto_session": auto, "device_id": "NRF001",
                    })

    def test_posts_use_the_captured_session(self):
        response = MagicMock()
        response.json.return_value = {"success": True}
        with patch.object(gateway, "SESSION_ID", 0), patch.object(
            gateway.requests, "post", return_value=response
        ) as post:
            gateway.http_save_answer("NRF001", 52, 7, "A")
            self.assertEqual(post.call_args.kwargs["data"]["session_id"], 52)
            gateway.http_advance_question(52, 7)
            self.assertEqual(post.call_args.kwargs["data"]["session_id"], 52)


class GatewayConnectionTests(unittest.IsolatedAsyncioTestCase):
    async def test_wait_and_switch_sessions_without_reconnecting(self):
        commands = []
        states = iter([
            {"id": None, "status": "waiting", "current_question_id": None},
            {"id": 51, "status": "running", "current_question_id": 7},
            {"id": None, "status": "ambiguous", "current_question_id": None},
            {"id": None, "status": "waiting", "current_question_id": None},
            {"id": 52, "status": "running", "current_question_id": 7},
        ])
        def state(_code):
            return {"success": True, "session": next(states)}

        class Client:
            is_connected = True
            async def __aenter__(self):
                return self
            async def __aexit__(self, *_args):
                self.is_connected = False
            async def start_notify(self, _uuid, callback):
                self.callback = callback
            async def write_gatt_char(self, _uuid, data, **_kwargs):
                command = data.decode().strip()
                commands.append(command)
                if command == "REQUEST_ID":
                    self.callback(None, b"ID:NRF001\n")
                if commands.count("QUESTION:7") == 2:
                    self.is_connected = False

        client = Client()
        with patch.object(gateway, "BleakClient", return_value=client) as connect, patch.object(
            gateway.BleakScanner, "find_device_by_name", new=AsyncMock(return_value=MagicMock(address="test"))
        ), patch.object(gateway, "http_get_state", side_effect=state), patch.object(
            gateway, "STATE_POLL_DELAY", 0.01
        ):
            await asyncio.wait_for(gateway.connect_once(), timeout=3)
        self.assertEqual(connect.call_count, 1)
        self.assertEqual(commands, ["REQUEST_ID", "ACCEPT", "QUESTION:7", "CLOSE", "QUESTION:7"])


if __name__ == "__main__":
    unittest.main()
