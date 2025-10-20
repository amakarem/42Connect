#!/usr/bin/env python3

"""
Simple demo script that fakes an embedding vector for the provided text.
The script prints a JSON object to stdout, which the Symfony controller can parse.
"""

from __future__ import annotations

import json
import math
import sys
from datetime import datetime, timezone


def generate_embedding(text: str) -> list[float]:
    """Return a deterministic faux embedding vector derived from input text."""
    if not text:
        text = "empty"

    # Build a small vector driven by character positions to keep things reproducible.
    base = [ord(char) for char in text.encode("ascii", errors="ignore").decode()]
    if not base:
        base = [0]

    length = min(len(base), 8)
    vector = []
    for index in range(length):
        value = base[index]
        # Normalize the value into a 0..1 range with some deterministic variation.
        normalized = math.fmod(value * (index + 1), 255) / 255
        vector.append(round(normalized, 4))

    # Pad the vector to a consistent length.
    while len(vector) < 8:
        vector.append(round((len(vector) + 1) / 10, 4))

    return vector


def main() -> None:
    text = sys.argv[1] if len(sys.argv) > 1 else "Hello from Python"
    payload = {
        "text": text,
        "embedding": generate_embedding(text),
        "generated_at": datetime.now(tz=timezone.utc).isoformat(),
    }
    print(json.dumps(payload))


if __name__ == "__main__":
    main()
