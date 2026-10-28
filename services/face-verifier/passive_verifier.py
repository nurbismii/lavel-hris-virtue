"""Optional passive-liveness routes for the existing Flask verifier."""
import hmac
import inspect
import io
import math
import os
import threading
from functools import lru_cache

os.environ.setdefault("CUDA_VISIBLE_DEVICES", "-1")
os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "2")
os.environ.setdefault("OMP_NUM_THREADS", "1")

from flask import Blueprint, current_app, jsonify, request
from PIL import Image, UnidentifiedImageError

passive_blueprint = Blueprint("passive_verifier", __name__)
CONTRACT = "passive-v1"
CAMERA_FIELDS = ("selfie_image", "liveness_passive_start_image", "liveness_passive_middle_image", "liveness_passive_end_image")
VERIFY_TOKEN = os.getenv("VERIFY_TOKEN", "")
FACE_MODEL = os.getenv("FACE_MODEL", "SFace")
FACE_DETECTOR = os.getenv("FACE_DETECTOR", "opencv")
DISTANCE_METRIC = os.getenv("DISTANCE_METRIC", "cosine")
MAX_FILE_BYTES = max(1, int(os.getenv("MAX_FILE_SIZE_MB", "5"))) * 1024 * 1024
MAX_IMAGE_SIDE = max(240, min(2000, int(os.getenv("MAX_IMAGE_SIDE", "2000"))))
MIN_LIVENESS_SCORE = float(os.getenv("MIN_PASSIVE_LIVENESS_SCORE", "0.78"))
MIN_FACE_CONFIDENCE = float(os.getenv("MIN_FACE_CONFIDENCE", "0.78"))
MIN_BLUR_SCORE = float(os.getenv("MIN_BLUR_SCORE", "35"))
MIN_BRIGHTNESS = float(os.getenv("MIN_BRIGHTNESS", "35"))
MAX_BRIGHTNESS = float(os.getenv("MAX_BRIGHTNESS", "225"))
_inference_lock = threading.Lock()


class InvalidImage(ValueError):
    pass


def authorize():
    if not VERIFY_TOKEN or VERIFY_TOKEN == "change-me":
        return jsonify(status="pending_review", verified=False, message="Token verifier belum dikonfigurasi."), 503
    if not hmac.compare_digest(request.headers.get("X-Verify-Token", "").encode("utf-8"), VERIFY_TOKEN.encode("utf-8")):
        return jsonify(status="pending_review", verified=False, message="Unauthorized"), 401
    return None


@lru_cache(maxsize=1)
def load_engines():
    import cv2
    import numpy as np
    import torch
    from deepface import DeepFace

    if FACE_DETECTOR == "skip" or "anti_spoofing" not in inspect.signature(DeepFace.extract_faces).parameters:
        raise RuntimeError("Passive liveness is not supported by this detector or DeepFace version")
    if not 0 < MIN_LIVENESS_SCORE <= 1 or not 0 < MIN_FACE_CONFIDENCE <= 1:
        raise RuntimeError("Verification thresholds must be within (0, 1]")
    torch.set_num_threads(1)
    cv2.setNumThreads(1)
    DeepFace.build_model(model_name="Fasnet", task="spoofing")
    DeepFace.build_model(model_name=FACE_MODEL)
    DeepFace.build_model(model_name=FACE_DETECTOR, task="face_detector")
    return cv2, np, DeepFace


def read_image(upload, cv2, np):
    data = upload.stream.read(MAX_FILE_BYTES + 1)
    if not data or len(data) > MAX_FILE_BYTES:
        raise InvalidImage("Berkas gambar kosong atau melebihi batas ukuran.")
    try:
        with Image.open(io.BytesIO(data)) as header:
            if header.format not in ("JPEG", "PNG", "WEBP") or min(header.size) < 240 or max(header.size) > MAX_IMAGE_SIDE:
                raise InvalidImage("Format atau dimensi gambar tidak memenuhi syarat.")
            header.verify()
    except (UnidentifiedImageError, OSError, Image.DecompressionBombError) as exc:
        raise InvalidImage("Berkas gambar tidak valid.") from exc
    image = cv2.imdecode(np.frombuffer(data, dtype=np.uint8), cv2.IMREAD_COLOR)
    if image is None:
        raise InvalidImage("Berkas gambar tidak dapat dibaca.")
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    brightness = float(np.mean(gray))
    if float(cv2.Laplacian(gray, cv2.CV_64F).var()) < MIN_BLUR_SCORE or not MIN_BRIGHTNESS <= brightness <= MAX_BRIGHTNESS:
        raise InvalidImage("Gambar terlalu buram, gelap, atau terang. Ulangi pengambilan dengan pencahayaan merata.")
    return image


def single_face(image, engine, passive):
    faces = engine.extract_faces(img_path=image, detector_backend=FACE_DETECTOR,
                                 enforce_detection=True, align=True, normalize_face=False,
                                 color_face="bgr", anti_spoofing=passive)
    if len(faces) != 1:
        raise InvalidImage("Setiap gambar harus memuat tepat satu wajah.")
    return faces[0]


def embedding(face, engine):
    return engine.represent(img_path=face["face"], model_name=FACE_MODEL,
                            detector_backend="skip", enforce_detection=False, align=False)[0]["embedding"]


def analyze_uploads(files, cv2, np, engine):
    reference = single_face(read_image(files["reference_image"], cv2, np), engine, False)
    reference_embedding = embedding(reference, engine)
    scores, confidences, distances = [], [], []
    matched = True
    spoof = False
    for field in CAMERA_FIELDS:
        face = single_face(read_image(files[field], cv2, np), engine, True)
        is_real = face.get("is_real")
        score = face.get("antispoof_score")
        if not isinstance(is_real, (bool, np.bool_)) or not isinstance(score, (int, float, np.floating)):
            raise RuntimeError("The anti-spoofing model returned incomplete results")
        score = float(score)
        if not math.isfinite(score) or not 0 <= score <= 1:
            raise RuntimeError("The anti-spoofing model returned an invalid score")
        spoof = spoof or not bool(is_real)
        # DeepFace's score belongs to the predicted class, including the fake class.
        scores.append(score if is_real else 1 - score)
        result = engine.verify(img1_path=reference_embedding, img2_path=embedding(face, engine),
                               model_name=FACE_MODEL, distance_metric=DISTANCE_METRIC, silent=True)
        confidence = result.get("confidence")
        distance, threshold = result.get("distance"), result.get("threshold")
        values = (confidence, distance, threshold)
        if any(not isinstance(value, (int, float, np.floating)) or not math.isfinite(float(value)) for value in values):
            raise RuntimeError("DeepFace must return confidence, distance and threshold")
        if not 0 <= confidence <= 100 or distance < 0 or threshold <= 0:
            raise RuntimeError("DeepFace returned invalid matching metrics")
        confidences.append(float(confidence) / 100)
        distances.append(float(distance))
        matched = matched and bool(result.get("verified")) and distance <= threshold
    confidence = min(confidences)
    liveness_score = min(scores)
    face_matched = bool(matched and confidence >= MIN_FACE_CONFIDENCE)
    passed = bool(not spoof and liveness_score >= MIN_LIVENESS_SCORE)
    status = "verified" if passed and face_matched else ("rejected" if spoof else "pending_review")
    return dict(status=status, verified=status == "verified", face_matched=face_matched,
                passive_liveness_passed=passed, liveness_score=round(liveness_score, 6),
                confidence=round(confidence, 6), screen_attack_detected=bool(spoof),
                distance=round(max(distances), 6), model=FACE_MODEL, detector=FACE_DETECTOR,
                message="Verifikasi selesai." if status == "verified" else "Hasil verifikasi membutuhkan pemeriksaan.")


def unavailable(exc):
    current_app.logger.error("Passive verifier unavailable: %s", type(exc).__name__)
    return jsonify(status="pending_review", verified=False,
                   message="Verifier belum siap atau gagal memproses. Perlu review HR."), 503


@passive_blueprint.route("/health/passive", methods=["GET"])
def passive_health():
    return jsonify(status="ok", service="presensi-face-verifier", verification_contract=CONTRACT,
                   supported_modes=["passive"], model=FACE_MODEL, detector=FACE_DETECTOR,
                   runtime_ready=load_engines.cache_info().currsize > 0,
                   token_configured=bool(VERIFY_TOKEN and VERIFY_TOKEN != "change-me"))


@passive_blueprint.route("/ready/passive", methods=["POST"])
def passive_ready():
    denied = authorize()
    if denied is not None:
        return denied
    if not _inference_lock.acquire(blocking=False):
        return jsonify(status="pending_review", message="Verifier sedang memproses permintaan lain."), 503
    try:
        load_engines()
        return jsonify(status="ok", verification_contract=CONTRACT, runtime_ready=True)
    except Exception as exc:
        return unavailable(exc)
    finally:
        _inference_lock.release()


@passive_blueprint.route("/verify-passive", methods=["POST"])
def verify_passive():
    denied = authorize()
    if denied is not None:
        return denied
    if request.content_length is None or request.content_length > MAX_FILE_BYTES * 5 + 65536:
        return jsonify(status="pending_review", message="Ukuran permintaan tidak valid."), 413
    if request.form.get("verification_contract") != CONTRACT or request.form.get("liveness_mode") != "passive":
        return jsonify(status="pending_review", message="Kontrak passive liveness tidak sesuai."), 422
    challenge_id = request.form.get("presensi_challenge_id", "")
    if not challenge_id or len(challenge_id) > 64:
        return jsonify(status="pending_review", message="Sesi verifikasi tidak valid."), 422
    required = ("reference_image",) + CAMERA_FIELDS
    if any(len(request.files.getlist(field)) != 1 for field in required):
        return jsonify(status="pending_review", message="Foto referensi, selfie dan tiga frame pasif wajib dikirim."), 422
    if not _inference_lock.acquire(blocking=False):
        return jsonify(status="pending_review", message="Verifier sedang memproses permintaan lain."), 503
    try:
        cv2, np, engine = load_engines()
        result = analyze_uploads(request.files, cv2, np, engine)
        result.update(verification_contract=CONTRACT, liveness_mode="passive", presensi_challenge_id=challenge_id)
        return jsonify(result)
    except InvalidImage as exc:
        return jsonify(status="pending_review", verified=False, message=str(exc)), 422
    except Exception as exc:
        return unavailable(exc)
    finally:
        _inference_lock.release()
