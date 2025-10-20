from __future__ import annotations

import os
from dataclasses import dataclass
from functools import lru_cache

from dotenv import load_dotenv

# Load variables from a local .env file if present.
load_dotenv()


@dataclass(frozen=True)
class Settings:
    database_url: str = os.getenv(
        "DATABASE_URL", "postgresql://quack:quack@localhost:5433/vibes"
    )
    embedding_model: str = os.getenv("EMBEDDING_MODEL", "text-embedding-3-small")
    embedding_dimension: int = int(os.getenv("EMBEDDING_DIMENSION", "1536"))
    openai_api_key: str | None = os.getenv("OPENAI_API_KEY")
    openai_base_url: str | None = os.getenv("OPENAI_BASE_URL")
    ivfflat_probes: int = int(os.getenv("IVFFLAT_PROBES", "100"))
    forty_two_client_id: str | None = os.getenv("OAUTH_42_ID")
    forty_two_client_secret: str | None = os.getenv("OAUTH_42_SECRET")
    forty_two_authorize_url: str = os.getenv(
        "OAUTH_42_AUTHORIZE_URL", "https://api.intra.42.fr/oauth/authorize"
    )
    forty_two_token_url: str = os.getenv(
        "OAUTH_42_TOKEN_URL", "https://api.intra.42.fr/oauth/token"
    )
    forty_two_resource_url: str = os.getenv(
        "OAUTH_42_RESOURCE_URL", "https://api.intra.42.fr/v2/me"
    )
    forty_two_scope: str = os.getenv("OAUTH_42_SCOPE", "public")
    session_secret: str | None = os.getenv("SESSION_SECRET")


@lru_cache()
def get_settings() -> Settings:
    return Settings()
