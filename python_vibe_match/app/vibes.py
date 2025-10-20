from __future__ import annotations

import math
import re
from dataclasses import dataclass
from datetime import UTC, datetime
from typing import List, Optional

from .db import get_connection
from .embeddings import embed_text
from .errors import DatabaseError, EmbeddingError, NormalizationError
from .settings import get_settings
from .text_normalization import normalize_text


@dataclass
class Vibe:
    uid: str
    original_vibe: str
    processed_vibe: str
    embedding_model: str
    created_at: Optional[str] = None
    updated_at: Optional[str] = None


@dataclass
class SearchResult:
    uid: str
    original_vibe: str
    processed_vibe: str
    embedding_model: str
    distance: float
    lexical_overlap: float
    recency_decay: float
    final_score: float
    overlap_terms: List[str]

    @property
    def similarity(self) -> float:
        return max(0.0, 1.0 - self.distance)

    @property
    def formatted_score(self) -> str:
        return (
            f"{self.final_score:.3f} "
            f"(cosine {self.similarity:.3f}, "
            f"lexical {self.lexical_overlap:.3f}, "
            f"recency {self.recency_decay:.3f})"
        )

_OVERLAP_STOPWORDS = {
    "i",
    "want",
    "to",
    "like",
    "would",
    "some",
    "any",
    "the",
    "a",
    "an",
    "language",
    "speak",
    "play",
    "learn",
    "practice",
}
_TOKEN_RE = re.compile(r"[a-z0-9']+")
_MIN_FINAL_SCORE = 0.35


def _tokenize_for_overlap(text: str) -> List[str]:
    return _TOKEN_RE.findall(text.lower())


def _lexical_overlap_score(query: str, document: str) -> tuple[float, List[str]]:
    query_terms = {
        term
        for term in _tokenize_for_overlap(query)
        if term and term not in _OVERLAP_STOPWORDS
    }
    if not query_terms:
        return 0.0, []

    doc_terms = {
        term
        for term in _tokenize_for_overlap(document)
        if term and term not in _OVERLAP_STOPWORDS
    }
    if not doc_terms:
        return 0.0, []

    overlap = sorted(query_terms.intersection(doc_terms))
    return len(overlap) / len(query_terms), overlap


def _recency_decay(updated_at: Optional[datetime], created_at: Optional[datetime]) -> float:
    reference = updated_at or created_at
    if reference is None:
        return 0.0

    now = datetime.now(UTC)
    age = now - reference
    age_days = max(age.total_seconds() / 86400.0, 0.0)
    score = math.exp(-age_days / 60.0)
    return max(0.0, min(score, 1.0))


def upsert_vibe(uid: str, vibe: str) -> None:
    settings = get_settings()

    try:
        processed_vibe = normalize_text(vibe)
    except NormalizationError as exc:
        raise EmbeddingError(str(exc)) from exc

    embedding = embed_text(processed_vibe, already_normalized=True)
    original_vibe = vibe.strip()

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
                    updated_at = NOW();
                """,
                (
                    uid,
                    original_vibe,
                    processed_vibe,
                    embedding,
                    settings.embedding_model,
                ),
            )
    except EmbeddingError:
        raise
    except Exception as exc:  # pragma: no cover - DB errors
        raise DatabaseError(f"Failed to upsert vibe: {exc}") from exc


def fetch_vibe(uid: str) -> Optional[Vibe]:
    try:
        with get_connection() as conn, conn.cursor() as cur:
            cur.execute(
                """
                SELECT uid, original_vibe, vibe, embedding_model, created_at, updated_at
                FROM vibes
                WHERE uid = %s;
                """,
                (uid,),
            )
            row = cur.fetchone()
    except Exception as exc:  # pragma: no cover
        raise DatabaseError(f"Failed to fetch vibe: {exc}") from exc

    if not row:
        return None

    return Vibe(
        uid=row[0],
        original_vibe=row[1],
        processed_vibe=row[2],
        embedding_model=row[3],
        created_at=row[4].isoformat() if row[4] else None,
        updated_at=row[5].isoformat() if row[5] else None,
    )


def list_vibes(limit: int = 20) -> List[Vibe]:
    try:
        with get_connection() as conn, conn.cursor() as cur:
            cur.execute(
                """
                SELECT uid, original_vibe, vibe, embedding_model, created_at, updated_at
                FROM vibes
                ORDER BY updated_at DESC
                LIMIT %s;
                """,
                (limit,),
            )
            rows = cur.fetchall()
    except Exception as exc:  # pragma: no cover
        raise DatabaseError(f"Failed to list vibes: {exc}") from exc

    return [
        Vibe(
            uid=row[0],
            original_vibe=row[1],
            processed_vibe=row[2],
            embedding_model=row[3],
            created_at=row[4].isoformat() if row[4] else None,
            updated_at=row[5].isoformat() if row[5] else None,
        )
        for row in rows
    ]


def search_vibes(query: str, top_k: int = 5) -> List[SearchResult]:
    embedding = embed_text(query)

    try:
        with get_connection() as conn, conn.cursor() as cur:
            cur.execute(
                """
                SELECT uid,
                       original_vibe,
                       vibe,
                       embedding_model,
                       embedding <=> %s::vector AS distance,
                       updated_at,
                       created_at
                FROM vibes
                ORDER BY embedding <=> %s::vector
                LIMIT %s;
                """,
                (embedding, embedding, top_k),
            )
            rows = cur.fetchall()
    except EmbeddingError:
        raise
    except Exception as exc:  # pragma: no cover
        raise DatabaseError(f"Failed to search vibes: {exc}") from exc

    scored_results: List[SearchResult] = []
    for row in rows:
        distance = float(row[4])
        similarity = max(0.0, 1.0 - distance)
        lexical, overlap_terms = _lexical_overlap_score(query, row[1])
        recency = _recency_decay(row[5], row[6])
        final_score = (0.80 * similarity) + (0.15 * lexical) + (0.05 * recency)
        scored_results.append(
            SearchResult(
                uid=row[0],
                original_vibe=row[1],
                processed_vibe=row[2],
                embedding_model=row[3],
                distance=distance,
                lexical_overlap=lexical,
                recency_decay=recency,
                final_score=final_score,
                overlap_terms=overlap_terms,
            )
        )

    scored_results.sort(key=lambda item: item.final_score, reverse=True)
    filtered_results = [item for item in scored_results if item.final_score >= _MIN_FINAL_SCORE]
    return filtered_results[:top_k]


def wipe_vibes() -> None:
    try:
        with get_connection() as conn, conn.cursor() as cur:
            cur.execute("DELETE FROM vibes;")
    except Exception as exc:  # pragma: no cover
        raise DatabaseError(f"Failed to wipe vibes table: {exc}") from exc
