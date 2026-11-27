# DAODES LiveKit deployment

LiveKit is the separate SFU for group calls. Laravel issues short-lived participant JWTs.
The API key and secret are server-only and must never be committed to Git or sent to the browser.

## Network

- Public signaling: `wss://rtc.daodes.space`
- Local HTTP/WebSocket listener behind Nginx: `127.0.0.1:7880`
- Direct ICE fallback: `7881/tcp`
- Direct WebRTC media: `50000-60000/udp`
- Docker data root is already configured on the host as
  `/home/sergeniyrus/www/docker-data/docker`.

The Compose service uses host networking, as recommended by LiveKit for Dockerized
deployments. Do not expose TCP 7880 directly to the Internet; it is accessed through
the local Nginx TLS reverse proxy.

## Secrets

Generate a dedicated key and secret on the server:

```bash
openssl rand -hex 8
openssl rand -hex 32
```

Create a private env file outside the repository:

```bash
sudo install -d -m 750 /home/sergeniyrus/www/docker-data/livekit
sudo sh -c 'umask 077; cat > /home/sergeniyrus/www/docker-data/livekit/.env' <<'EOF'
LIVEKIT_API_KEY=PASTE_FIRST_GENERATED_VALUE
LIVEKIT_API_SECRET=PASTE_SECOND_GENERATED_VALUE
EOF
```

Use exactly the same values in the Laravel `.env`:

```dotenv
LIVEKIT_URL=wss://rtc.daodes.space
LIVEKIT_API_KEY=PASTE_FIRST_GENERATED_VALUE
LIVEKIT_API_SECRET=PASTE_SECOND_GENERATED_VALUE
SESSION_DOMAIN=.daodes.space
MESSENGER_HOST=deschat.daodes.space
MESSENGER_SCHEME=https
```

## Start

From the project root:

```bash
docker compose --env-file /home/sergeniyrus/www/docker-data/livekit/.env \
  -f deploy/livekit/compose.yml up -d
```

Inspect status and logs:

```bash
docker compose --env-file /home/sergeniyrus/www/docker-data/livekit/.env \
  -f deploy/livekit/compose.yml ps
docker compose --env-file /home/sergeniyrus/www/docker-data/livekit/.env \
  -f deploy/livekit/compose.yml logs --tail=100 livekit
```

Do not start the service until `rtc.daodes.space` resolves to the server, a trusted
certificate is available, and the Nginx reverse proxy is configured.

## Nginx requirements

- `deschat.daodes.space`: same Laravel public root and PHP-FPM socket as
  `daodes.space`; proxy `/app/` and `/apps/` to Reverb at `127.0.0.1:8090`.
- `rtc.daodes.space`: proxy all HTTPS paths to `http://127.0.0.1:7880` using
  HTTP/1.1 WebSocket upgrade headers.
- Keep the existing `daodes.space` virtual host unchanged.
- Confirm certificate SANs and exact Nginx paths on the server before editing/reloading.

The application redirects messenger page GET requests to the dedicated messenger host.
Existing Laravel routes remain unchanged. The shared `.daodes.space` session cookie keeps
the user authenticated across the main site and messenger. LiveKit media/signaling is
served independently through `rtc.daodes.space`.
