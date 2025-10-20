from __future__ import annotations

import hashlib
import math
import re

from .db import get_connection
from .errors import DatabaseError
from .users import User

EMBEDDING_DIMENSION = 1536
EMBEDDING_MODEL = "fallback-php-1536"

_NON_ALNUM_RE = re.compile(r"[^a-z0-9\s]+")
_WHITESPACE_RE = re.compile(r"\s+")


def provision_placeholder_vibe(user: User) -> None:
    """Ensure a deterministic placeholder vibe exists for the user."""
    uid = _resolve_uid(user)
    if uid is None:
        return

    original = _build_narrative(user)
    processed = _normalize_text(original)
    embedding_literal = _generate_embedding_literal(processed)

    try:
        with get_connection() as conn, conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO vibes (uid, original_vibe, vibe, embedding, embedding_model)
                VALUES (%s, %s, %s, %s, %s)
                ON CONFLICT (uid) DO UPDATE
                SET original_vibe = EXCLUDED.original_vibe,
                    vibe = EXCLUDED.vibe,
                    embedding = EXCLUDED.embedding,
                    embedding_model = EXCLUDED.embedding_model,
                    updated_at = NOW()
                """,
                (uid, original, processed, embedding_literal, EMBEDDING_MODEL),
            )
    except Exception as exc:  # pragma: no cover - DB errors bubble up
        raise DatabaseError(f"Failed to provision placeholder vibe: {exc}") from exc


def _resolve_uid(user: User) -> str | None:
    candidate = user.intra_login or user.email
    if not candidate:
        return None
    return candidate[:255]


def _build_narrative(user: User) -> str:
    segments: list[str] = []

    display = user.display_name or user.usual_full_name or user.intra_login
    if display:
        segments.append(f"{display} just joined 42Connect")
    else:
        segments.append("A new 42 student just joined 42Connect")

    if user.kind:
        segments.append(f"profile type {user.kind}")

    if user.location:
        segments.append(f"based in {user.location}")

    campus = user.campus or []
    campus_names = [
        entry.get("name")
        for entry in campus
        if isinstance(entry, dict) and entry.get("name")
    ]
    if campus_names:
        segments.append(f"campus {', '.join(campus_names[:3])}")

    projects = user.projects or []
    highlights: list[str] = []
    for project in projects:
        if not isinstance(project, dict):
            continue
        name = project.get("name")
        status = project.get("status")
        mark = project.get("final_mark")
        if not name or not status:
            continue

        snippet = f"{name} ({status})"
        if isinstance(mark, (int, float)):
            snippet += f" mark {mark}"
        highlights.append(snippet)
        if len(highlights) >= 5:
            break

    if highlights:
        segments.append(f"projects {'; '.join(highlights)}")

    narrative = ". ".join(segments).strip()
    if not narrative:
        narrative = "New 42 student on 42Connect"

    return _truncate(narrative, 1000)


def _normalize_text(text: str) -> str:
    lowered = text.lower()
    replaced = _NON_ALNUM_RE.sub(" ", lowered)
    collapsed = _WHITESPACE_RE.sub(" ", replaced).strip()
    return collapsed or "new 42 student on 42connect"


def _generate_embedding_literal(text: str) -> str:
    vector = _generate_deterministic_vector(text)
    formatted = ", ".join(f"{value:.6f}" for value in vector)
    return f"[{formatted}]"


def _generate_deterministic_vector(text: str) -> list[float]:
    seed = hashlib.sha512(text.encode("utf-8") if text else b"42connect-vibes").digest()
    vector: list[float] = []
    counter = 0

    while len(vector) < EMBEDDING_DIMENSION:
        digest = hashlib.sha512(seed + counter.to_bytes(4, "big")).digest()
        for offset in range(0, len(digest), 4):
            chunk = digest[offset : offset + 4]
            if len(chunk) < 4:
                chunk = chunk.ljust(4, b"\0")
            integer = int.from_bytes(chunk, "big", signed=False)
            scaled = ((integer % 2_000_000) / 1_000_000.0) - 1.0
            vector.append(scaled)
            if len(vector) >= EMBEDDING_DIMENSION:
                break
        counter += 1

    norm = math.sqrt(sum(value * value for value in vector))
    if norm <= 0:
        vector = [0.0] * EMBEDDING_DIMENSION
        vector[0] = 1.0
        return vector

    return [value / norm for value in vector]


def _truncate(value: str, limit: int) -> str:
    if len(value) <= limit:
        return value
    trimmed = value[: limit - 1].rstrip()
    return f"{trimmed}…"
