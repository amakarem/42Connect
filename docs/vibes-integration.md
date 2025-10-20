# Vibe Profile Integration

This project now ships with the `python_vibe_match` data layer so every newly on-boarded user gets a row inside a shared `vibes` table. The table is compatible with the pgvector-powered Python tooling, which means you can immediately plug real embeddings in when you are ready.

## Database setup

1. Ensure Docker containers are recreated so the database image with pgvector is used:
   ```bash
   docker compose up -d --build database
   ```
   The base `compose.yaml` now relies on the `pgvector/pgvector` Postgres image, providing the `vector` extension out of the box.

2. Apply migrations:
   ```bash
   php bin/console doctrine:migrations:migrate
   ```
   This installs the `vector` extension (if not already available) and creates the `vibes` table plus supporting trigger/index definitions.

## Placeholder embeddings for new users

During OAuth sign-in a small PHP service (`App\Service\VibeProfileManager`) assembles a short narrative from the available 42 API fields and generates a deterministic, normalized fallback embedding. The record is stored in the `vibes` table via an upsert, so:

- returning users get their text refreshed,
- first time users always have a row waiting for the Python tooling,
- failures are logged but never break the login flow.

The placeholder embedding keeps the table ready for semantic search even before OpenAI access is configured.

## Upgrading to real embeddings

When you are ready to lean on the Python tooling:

1. Provide credentials to `python_vibe_match` (e.g. create `python_vibe_match/.env` with `OPENAI_API_KEY` and `DATABASE_URL` that points to the Symfony database).
2. Use the CLI to re-upsert users with genuine OpenAI vectors:
   ```bash
   cd python_vibe_match
   python scripts/manage_vibes.py upsert alice "Looking for late-night pair programming partners"
   ```
3. The CLI writes into the same `vibes` table, replacing the fallback embedding that Symfony creates.

No additional schema work is required—both stacks operate on the exact same data.
