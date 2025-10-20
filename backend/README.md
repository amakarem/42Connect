## 42Connect Python Backend

This folder contains a FastAPI-powered backend plus the deterministic vibe provisioning logic that previously lived in Symfony. It exposes both HTML templates and JSON APIs, and ships with a minimal static frontend (see `../frontend`) that talks to those APIs.

### Prerequisites

- Docker & Docker Compose (for PostgreSQL/pgvector)
- Python 3.11+
- An OpenAI API key with embedding access
- 42 intra OAuth credentials (client id/secret)

### Configuration

Copy `.env.example` to `.env` and update the placeholders:

```
cp .env.example .env
```

Required values:

- `SESSION_SECRET` – random string used to sign browser sessions
- `DATABASE_URL` – Postgres connection string (defaults to the compose service)
- `OPENAI_API_KEY` – OpenAI key for embeddings
- `OAUTH_42_ID` / `OAUTH_42_SECRET` – credentials from the 42 OAuth application

Optional overrides let you customise embedding models or 42 endpoints. Environment variables are loaded automatically via `python-dotenv`.

### Running the backend

1. **Start PostgreSQL / pgvector**
   ```bash
   docker compose up -d database
   ```

2. **Create a virtual environment & install deps**
   ```bash
   python -m venv .venv
   source .venv/bin/activate
   pip install -r requirements.txt
   ```

3. **Launch FastAPI**
   ```bash
   uvicorn app.main:app --reload
   ```

   The templated UI remains available at http://127.0.0.1:8000/.

### JSON API surface

| Method | Path              | Description                               |
|-------:|-------------------|-------------------------------------------|
|  GET   | `/api/v1/vibes`   | List recent vibes (query param `limit`)   |
|  POST  | `/api/v1/vibes`   | Create/update a vibe (`uid`, `vibe_text`) |
|  POST  | `/api/v1/search`  | Semantic search (`query`, optional `top_k`) |
|  GET   | `/api/v1/me`      | Current session details (401 if none)     |
|  POST  | `/api/v1/logout`  | Clear the session                         |

All API responses are JSON and expect/return UTF-8. Cookies are used for the OAuth session, so frontends should send requests with `credentials: "include"`.

### CLI helpers

The Typer scripts still work for quick admin tasks:

```bash
python scripts/manage_vibes.py upsert alice "Loves rubber duck debugging"
python scripts/manage_vibes.py search "pair programming"
```

### Static frontend

The `../frontend` directory hosts a lightweight HTML/CSS/JS client that talks to the JSON API using `fetch`. Serve it with any static file host, for example:

```bash
cd ../frontend
python -m http.server 5173
```

Then browse to http://127.0.0.1:5173/ (the app points to `http://localhost:8000` by default).
