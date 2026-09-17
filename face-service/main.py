import base64
import os
from pathlib import Path
from typing import List, Optional

import cv2
import numpy as np
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

MODELS_DIR = Path(__file__).parent / "models"
DETECTOR_PATH = MODELS_DIR / "face_detection_yunet.onnx"
RECOGNIZER_PATH = MODELS_DIR / "face_recognition_sface.onnx"

MATCH_THRESHOLD = float(os.environ.get("FACE_MATCH_THRESHOLD", "0.36"))
DET_SIZE = int(os.environ.get("FACE_DET_SIZE", "640"))

app = FastAPI(title="Absensi TK Face Service")

detector: Optional[cv2.FaceDetectorYN] = None
recognizer: Optional[cv2.FaceRecognizerSF] = None


@app.on_event("startup")
def load_model() -> None:
    global detector, recognizer

    if not DETECTOR_PATH.exists() or not RECOGNIZER_PATH.exists():
        raise RuntimeError(
            f"Model file tidak ditemukan di {MODELS_DIR}. "
            "Lihat README.md untuk cara mengunduhnya."
        )

    detector = cv2.FaceDetectorYN_create(
        str(DETECTOR_PATH), "", (DET_SIZE, DET_SIZE), score_threshold=0.7
    )
    recognizer = cv2.FaceRecognizerSF_create(str(RECOGNIZER_PATH), "")


class EmbedRequest(BaseModel):
    image: str


class EmbedResponse(BaseModel):
    embedding: List[float]
    confidence: float


class Candidate(BaseModel):
    id: int
    embedding: List[float]


class RecognizeRequest(BaseModel):
    image: str
    candidates: List[Candidate]


class RecognizeResponse(BaseModel):
    siswa_id: Optional[int] = None
    score: Optional[float] = None


def decode_image(data_url_or_b64: str) -> np.ndarray:
    body = data_url_or_b64
    if "," in body and body.strip().lower().startswith("data:"):
        body = body.split(",", 1)[1]

    try:
        binary = base64.b64decode(body, validate=True)
    except Exception:
        raise HTTPException(status_code=422, detail="Format gambar base64 tidak valid.")

    array = np.frombuffer(binary, dtype=np.uint8)
    image = cv2.imdecode(array, cv2.IMREAD_COLOR)
    if image is None:
        raise HTTPException(status_code=422, detail="Gambar tidak bisa dibaca.")
    return image


def largest_face(image: np.ndarray) -> Optional[np.ndarray]:
    height, width = image.shape[:2]
    detector.setInputSize((width, height))
    _, faces = detector.detect(image)
    if faces is None or len(faces) == 0:
        return None

    areas = faces[:, 2] * faces[:, 3]
    return faces[int(np.argmax(areas))]


def compute_embedding(image: np.ndarray, face_row: np.ndarray) -> np.ndarray:
    aligned = recognizer.alignCrop(image, face_row)
    feature = recognizer.feature(aligned)
    return feature.flatten()


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/embed", response_model=EmbedResponse)
def embed(payload: EmbedRequest):
    image = decode_image(payload.image)
    face = largest_face(image)
    if face is None:
        raise HTTPException(status_code=422, detail="Wajah tidak terdeteksi pada foto.")

    embedding = compute_embedding(image, face)

    return EmbedResponse(
        embedding=embedding.tolist(),
        confidence=float(face[-1]),
    )


@app.post("/recognize", response_model=RecognizeResponse)
def recognize(payload: RecognizeRequest):
    image = decode_image(payload.image)
    face = largest_face(image)
    if face is None:
        return RecognizeResponse(siswa_id=None, score=None)

    query = compute_embedding(image, face).astype(np.float32)

    best_id: Optional[int] = None
    best_score = -1.0
    for candidate in payload.candidates:
        candidate_vec = np.asarray(candidate.embedding, dtype=np.float32)
        score = float(
            recognizer.match(
                query.reshape(1, -1),
                candidate_vec.reshape(1, -1),
                cv2.FaceRecognizerSF_FR_COSINE,
            )
        )
        if score > best_score:
            best_score = score
            best_id = candidate.id

    if best_id is None or best_score < MATCH_THRESHOLD:
        return RecognizeResponse(siswa_id=None, score=best_score if best_id else None)

    return RecognizeResponse(siswa_id=best_id, score=best_score)
