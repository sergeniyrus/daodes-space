import os
import secrets
import tempfile
import threading
from pathlib import Path

from fastapi import FastAPI, File, Header, HTTPException, UploadFile
from faster_whisper import WhisperModel

MAX_AUDIO_BYTES = 25 * 1024 * 1024
ALLOWED_EXTENSIONS = {
    ".webm", ".ogg", ".wav", ".mp3", ".mp4", ".m4a",
    ".flac", ".mpeg", ".mpga",
}

MODEL_SIZE = os.getenv("WHISPER_MODEL", "small")
DEVICE = os.getenv("WHISPER_DEVICE", "cpu")
COMPUTE_TYPE = os.getenv("WHISPER_COMPUTE_TYPE", "int8")
CPU_THREADS = max(1, int(os.getenv("WHISPER_CPU_THREADS", str(os.cpu_count() or 2))))
AUTH_TOKEN_FILE = os.getenv("LOCAL_TRANSCRIPTION_TOKEN_FILE", "")
AUTH_TOKEN = os.getenv("LOCAL_TRANSCRIPTION_TOKEN", "")

app = FastAPI(
    title="DAODES Local Transcription",
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
)

_model = None
_model_lock = threading.Lock()


def get_auth_token() -> str:
    if AUTH_TOKEN:
        return AUTH_TOKEN
    if AUTH_TOKEN_FILE:
        try:
            return Path(AUTH_TOKEN_FILE).read_text(encoding="utf-8").strip()
        except OSError:
            return ""
    return ""


@app.on_event("startup")
def load_model() -> None:
    global _model

    if not get_auth_token():
        raise RuntimeError("LOCAL_TRANSCRIPTION_TOKEN or token file must be configured")

    _model = WhisperModel(
        MODEL_SIZE,
        device=DEVICE,
        compute_type=COMPUTE_TYPE,
        cpu_threads=CPU_THREADS,
        num_workers=1,
    )


@app.get("/health")
def health():
    return {"status": "ok", "model_loaded": _model is not None}


@app.post("/transcribe")
async def transcribe(
    file: UploadFile = File(...),
    authorization: str | None = Header(default=None),
):
    expected = get_auth_token()
    supplied = authorization.removeprefix("Bearer ").strip() if authorization else ""

    if not expected or not secrets.compare_digest(supplied, expected):
        raise HTTPException(status_code=401, detail="Unauthorized")

    if _model is None:
        raise HTTPException(status_code=503, detail="Transcription model is not ready")

    suffix = Path(file.filename or "").suffix.lower()
    if suffix not in ALLOWED_EXTENSIONS:
        raise HTTPException(status_code=415, detail="Unsupported audio format")

    audio_bytes = await file.read(MAX_AUDIO_BYTES + 1)
    await file.close()

    if not audio_bytes:
        raise HTTPException(status_code=422, detail="Empty audio file")
    if len(audio_bytes) > MAX_AUDIO_BYTES:
        raise HTTPException(status_code=413, detail="Audio file exceeds 25 MB")

    temp_path = None
    try:
        with tempfile.NamedTemporaryFile(prefix="daodes-voice-", suffix=suffix, delete=False) as temp:
            temp.write(audio_bytes)
            temp_path = temp.name

        # faster-whisper performs CPU-bound inference; serialize requests to
        # keep memory usage predictable on a single production server.
        def run_inference():
            with _model_lock:
                segments, _info = _model.transcribe(
                    temp_path,
                    beam_size=5,
                    vad_filter=True,
                    condition_on_previous_text=False,
                )
                return " ".join(segment.text.strip() for segment in segments).strip()

        from starlette.concurrency import run_in_threadpool

        transcript = await run_in_threadpool(run_inference)
        if not transcript:
            raise HTTPException(status_code=422, detail="No speech detected")

        return {"status": "success", "text": transcript}
    except HTTPException:
        raise
    except Exception:
        # Do not log audio, transcript, or user content.
        raise HTTPException(status_code=502, detail="Audio transcription failed")
    finally:
        if temp_path:
            try:
                os.unlink(temp_path)
            except OSError:
                pass
