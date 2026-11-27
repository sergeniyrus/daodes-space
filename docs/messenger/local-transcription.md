# DAODES local voice transcription

Voice transcription is self-hosted with faster-whisper. No OpenAI key or external transcription API is used. Audio is uploaded to Laravel only after the user explicitly chooses **Open as text**. Laravel forwards it to a loopback-only Python service. The service uses a temporary file, removes it after inference, and does not persist audio or log transcripts. Laravel returns the recognized plaintext only to the requesting browser. The browser displays it in the current page session only. The transcript is never encrypted for storage, sent to another participant, written to logs, or saved in the database. Closing or reloading the page clears the displayed transcript.

## Runtime

- Python 3.11 or newer
- FFmpeg available on PATH (required by faster-whisper for decoding)
- CPU inference with CTranslate2 INT8 by default
- `small` multilingual model by default; the first start downloads model weights from Hugging Face
- FastAPI service bound only to `127.0.0.1:8765`
- Supervisor keeps the service running

The model is free/open source. The first model download requires internet access; subsequent transcription runs locally. CPU speed and memory use depend on the selected model and hardware.

## Install on the production host

Run from the DAODES project root as the deployment user:

```bash
sudo apt-get update
sudo apt-get install -y python3 python3-venv python3-pip ffmpeg

sudo mkdir -p /opt/daodes-transcription
sudo python3 -m venv /opt/daodes-transcription/venv
sudo /opt/daodes-transcription/venv/bin/pip install --upgrade pip
sudo /opt/daodes-transcription/venv/bin/pip install -r services/transcription/requirements.txt
```

Create one random shared token. Put the same value in Laravel's `.env` and in the Python service token file:

```bash
TOKEN="$(openssl rand -hex 32)"
printf '%s' "$TOKEN" | sudo tee /etc/daodes-transcription.token >/dev/null
sudo chown root:sergeniyrus /etc/daodes-transcription.token
sudo chmod 640 /etc/daodes-transcription.token
```

Add the following to Laravel `.env` (use the generated token value):

```dotenv
LOCAL_TRANSCRIPTION_URL=http://127.0.0.1:8765
LOCAL_TRANSCRIPTION_TOKEN=PASTE_THE_GENERATED_TOKEN_HERE
```

Copy the Supervisor example into the active Supervisor configuration directory:

```bash
sudo cp deploy/supervisor/daodes-transcription.conf.example /etc/supervisor/conf.d/daodes-transcription.conf
sudo service supervisor reread
sudo service supervisor update
sudo supervisorctl start daodes-transcription
sudo supervisorctl status daodes-transcription
```

The first service start downloads the `small` model and may take several minutes. The service is not ready until the model has loaded. Confirm loopback health:

```bash
curl -fsS http://127.0.0.1:8765/health
```

Then refresh Laravel's cached configuration using the deployment's normal procedure. Do not expose port 8765 through Nginx or a public firewall rule.

## Tuning

Environment variables in the Supervisor example:

- `WHISPER_MODEL=small`: multilingual model; use `base` for lower RAM/faster inference or `medium` for better accuracy with substantially higher RAM/CPU cost.
- `WHISPER_DEVICE=cpu`
- `WHISPER_COMPUTE_TYPE=int8`
- `WHISPER_CPU_THREADS`: defaults to detected CPU count.

Only one inference runs at a time to keep memory predictable. The HTTP request timeout in Laravel is five minutes. The endpoint accepts audio files up to 25 MiB and the supported formats validated by the Laravel controller.

## Privacy and security

- Python binds to loopback only.
- Requests require the shared bearer token.
- The token file is readable by the service account but not by other users.
- Temporary audio files are deleted in a `finally` block.
- No transcript or audio content is written to application logs.
- No transcription table is read or written by the application. The legacy `message_transcriptions` migration is retained as a no-op for compatibility; existing historical rows, if any, are not read or modified.
