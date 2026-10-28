import io
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

import cv2
import numpy as np
from flask import Flask
from PIL import Image
from werkzeug.datastructures import FileStorage

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import passive_verifier as verifier


class FakeDeepFace:
    def __init__(self):
        self.antispoof_calls = []
        self.comparisons = 0
        self.spoof_frame = None
        self.score = 0.99
        self.face_mismatch_frame = None
        self.incomplete_pad = False

    def extract_faces(self, img_path, anti_spoofing=False, **kwargs):
        self.antispoof_calls.append(anti_spoofing)
        face = {"face": img_path}
        if anti_spoofing and not self.incomplete_pad:
            index = self.antispoof_calls.count(True)
            face.update(is_real=index != self.spoof_frame, antispoof_score=self.score)
        return [face]

    def represent(self, **kwargs):
        return [{"embedding": [0.1, 0.2, 0.3]}]

    def verify(self, **kwargs):
        self.comparisons += 1
        matches = self.comparisons != self.face_mismatch_frame
        return dict(verified=matches, confidence=99.0, distance=0.1 if matches else 0.9, threshold=0.5)


class PassiveVerifierTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        pixels = np.random.default_rng(42).integers(40, 210, (240, 320, 3), dtype=np.uint8)
        image = io.BytesIO()
        Image.fromarray(pixels).save(image, format="JPEG")
        cls.jpeg = image.getvalue()

    def setUp(self):
        self.app = Flask(__name__)
        self.app.register_blueprint(verifier.passive_blueprint)
        self.client = self.app.test_client()
        self.token = patch.object(verifier, "VERIFY_TOKEN", "unit-test-token")
        self.token.start()
        self.addCleanup(self.token.stop)
        self.engine = FakeDeepFace()

    def payload(self):
        data = dict(verification_contract="passive-v1", liveness_mode="passive", presensi_challenge_id="capture-1")
        for name in ("reference_image",) + verifier.CAMERA_FIELDS:
            data[name] = (io.BytesIO(self.jpeg), "test.jpg")
        return data

    def post(self, data=None):
        return self.client.post("/verify-passive", data=self.payload() if data is None else data,
                                headers={"X-Verify-Token": "unit-test-token"})

    def engines(self):
        return patch.object(verifier, "load_engines", return_value=(cv2, np, self.engine))

    def test_authentication_and_missing_configuration_do_not_run_models(self):
        with self.engines() as loader:
            self.assertEqual(401, self.client.post("/verify-passive").status_code)
            with patch.object(verifier, "VERIFY_TOKEN", ""):
                self.assertEqual(503, self.post().status_code)
            loader.assert_not_called()

    def test_health_reports_runtime_separately_from_service_availability(self):
        response = self.client.get("/health/passive")
        self.assertEqual(200, response.status_code)
        self.assertFalse(response.json["runtime_ready"])
        self.assertEqual("passive-v1", response.json["verification_contract"])
        self.assertNotIn("weights_path", response.json)

    def test_contract_missing_frame_and_duplicate_upload_are_rejected_before_inference(self):
        with self.engines() as loader:
            for mutation in ("contract", "missing", "duplicate"):
                data = self.payload()
                if mutation == "contract":
                    data["liveness_mode"] = "active"
                elif mutation == "missing":
                    del data["liveness_passive_end_image"]
                else:
                    data["selfie_image"] = [(io.BytesIO(self.jpeg), "one.jpg"), (io.BytesIO(self.jpeg), "two.jpg")]
                self.assertEqual(422, self.post(data).status_code)
            loader.assert_not_called()

    def test_every_camera_frame_is_checked_for_spoofing_and_identity(self):
        with self.engines():
            response = self.post()
        self.assertEqual(200, response.status_code)
        self.assertEqual("verified", response.json["status"])
        self.assertEqual("capture-1", response.json["presensi_challenge_id"])
        self.assertEqual([False, True, True, True, True], self.engine.antispoof_calls)
        self.assertEqual(4, self.engine.comparisons)
        self.assertEqual(0.99, response.json["confidence"])
        self.assertTrue(response.json["passive_liveness_passed"])

    def test_one_spoofed_frame_cannot_pass_even_when_identity_matches(self):
        self.engine.spoof_frame = 2
        with self.engines():
            response = self.post()
        self.assertEqual("rejected", response.json["status"])
        self.assertTrue(response.json["screen_attack_detected"])
        self.assertFalse(response.json["passive_liveness_passed"])
        self.assertAlmostEqual(0.01, response.json["liveness_score"])

    def test_low_pad_score_and_a_different_face_in_one_frame_require_review(self):
        self.engine.score = 0.5
        with self.engines():
            self.assertEqual("pending_review", self.post().json["status"])
        self.engine = FakeDeepFace()
        self.engine.face_mismatch_frame = 3
        with self.engines():
            result = self.post().json
        self.assertFalse(result["face_matched"])
        self.assertEqual("pending_review", result["status"])

    def test_missing_antispoof_model_output_never_falls_back_to_face_matching(self):
        self.engine.incomplete_pad = True
        with self.engines():
            response = self.post()
        self.assertEqual(503, response.status_code)
        self.assertFalse(response.json["verified"])

    def test_invalid_and_oversized_images_are_rejected(self):
        with self.engines():
            data = self.payload()
            data["reference_image"] = (io.BytesIO(b"not-a-jpeg"), "reference.jpg")
            self.assertEqual(422, self.post(data).status_code)
        with self.assertRaises(verifier.InvalidImage):
            verifier.read_image(FileStorage(stream=io.BytesIO(b"x" * (verifier.MAX_FILE_BYTES + 1))), cv2, np)
        self.assertEqual([], self.engine.antispoof_calls)

    def test_second_request_cannot_start_parallel_inference_in_the_same_process(self):
        verifier._inference_lock.acquire()
        try:
            with self.engines() as loader:
                self.assertEqual(503, self.post().status_code)
                loader.assert_not_called()
        finally:
            verifier._inference_lock.release()

    def test_model_failure_returns_friendly_error_without_paths_or_traceback(self):
        with patch.object(verifier, "load_engines", side_effect=RuntimeError("/private/model/token-secret")):
            response = self.post()
        self.assertEqual(503, response.status_code)
        self.assertNotIn("token-secret", response.get_data(as_text=True))
        self.assertNotIn("trace", response.get_data(as_text=True))


if __name__ == "__main__":
    unittest.main()
