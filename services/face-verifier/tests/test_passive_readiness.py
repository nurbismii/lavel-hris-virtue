from pathlib import Path
import sys
import unittest
from unittest.mock import patch

from flask import Flask

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import passive_verifier as verifier


class PassiveReadinessTest(unittest.TestCase):
    def setUp(self):
        self.app = Flask(__name__)
        self.app.register_blueprint(verifier.passive_blueprint)
        self.client = self.app.test_client()
        token = patch.object(verifier, "VERIFY_TOKEN", "test-token")
        token.start()
        self.addCleanup(token.stop)

    def ready(self):
        return self.client.post("/ready/passive", headers={"X-Verify-Token": "test-token"})

    def test_missing_dependency_is_identified_only_after_authentication(self):
        error = ModuleNotFoundError("/private/token-secret", name="torch._C")
        with patch.object(verifier, "load_engines", side_effect=error) as loader:
            self.assertEqual(401, self.client.post("/ready/passive").status_code)
            loader.assert_not_called()
            with self.assertLogs(self.app.logger, level="ERROR") as logs:
                response = self.ready()
        self.assertEqual(503, response.status_code)
        self.assertEqual("missing_dependency", response.json["error_code"])
        self.assertEqual("torch", response.json["missing_dependency"])
        self.assertEqual("pending_review", response.json["status"])
        self.assertFalse(response.json["verified"])
        self.assertNotIn("token-secret", response.get_data(as_text=True) + str(logs.output))
        self.assertIn("missing_dependency=torch", str(logs.output))

    def test_other_failures_and_invalid_module_names_do_not_expose_exception_details(self):
        for error in (RuntimeError("/private/token-secret"), ModuleNotFoundError("token-secret"),
                      ModuleNotFoundError("token-secret", name="/private/token-secret")):
            with patch.object(verifier, "load_engines", side_effect=error), self.assertLogs(self.app.logger, level="ERROR") as logs:
                response = self.ready()
            self.assertEqual(503, response.status_code)
            self.assertNotIn("missing_dependency", response.json)
            self.assertNotIn("token-secret", response.get_data(as_text=True) + str(logs.output))

    def test_attendance_failure_keeps_the_generic_response(self):
        with self.app.app_context(), self.assertLogs(self.app.logger, level="ERROR"):
            response, status = verifier.unavailable(ModuleNotFoundError("private detail", name="torch"))
        self.assertEqual(503, status)
        self.assertFalse(response.json["verified"])
        self.assertNotIn("missing_dependency", response.json)

    def test_ready_success_after_failure_releases_the_inference_lock(self):
        with patch.object(verifier, "load_engines", side_effect=ModuleNotFoundError(name="torch")), self.assertLogs(self.app.logger, level="ERROR"):
            self.assertEqual(503, self.ready().status_code)
        with patch.object(verifier, "load_engines", return_value=(None, None, None)):
            response = self.ready()
        self.assertEqual(200, response.status_code)
        self.assertTrue(response.json["runtime_ready"])


if __name__ == "__main__":
    unittest.main()
