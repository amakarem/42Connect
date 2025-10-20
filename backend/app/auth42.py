from __future__ import annotations

import secrets
from dataclasses import dataclass, field
from typing import Any
from urllib.parse import urlencode

import httpx

from .settings import Settings


class OAuth42Error(RuntimeError):
    """Raised when the 42 OAuth flow fails."""


@dataclass(frozen=True)
class OAuthToken:
    access_token: str
    expires_in: int | None = None
    refresh_token: str | None = None
    raw: dict[str, Any] = field(default_factory=dict)


class FortyTwoOAuth:
    """Lightweight client for the 42 intra OAuth2 flow."""

    def __init__(self, settings: Settings, *, timeout: float = 10.0) -> None:
        self._settings = settings
        self._timeout = timeout

    @property
    def enabled(self) -> bool:
        return bool(
            self._settings.forty_two_client_id
            and self._settings.forty_two_client_secret
        )

    def build_authorization_url(self, redirect_uri: str) -> tuple[str, str]:
        if not self.enabled:
            raise OAuth42Error("42 OAuth is not configured.")

        state = secrets.token_urlsafe(32)
        params = {
            "client_id": self._settings.forty_two_client_id,
            "redirect_uri": redirect_uri,
            "response_type": "code",
            "scope": self._settings.forty_two_scope,
            "state": state,
        }
        url = f"{self._settings.forty_two_authorize_url}?{urlencode(params)}"
        return url, state

    async def exchange_code_for_token(self, *, code: str, redirect_uri: str) -> OAuthToken:
        if not self.enabled:
            raise OAuth42Error("42 OAuth is not configured.")

        data = {
            "grant_type": "authorization_code",
            "client_id": self._settings.forty_two_client_id,
            "client_secret": self._settings.forty_two_client_secret,
            "code": code,
            "redirect_uri": redirect_uri,
        }

        async with httpx.AsyncClient(timeout=self._timeout) as client:
            response = await client.post(
                self._settings.forty_two_token_url,
                data=data,
                headers={"Accept": "application/json"},
            )

        if response.status_code != 200:
            raise OAuth42Error(
                f"Token exchange failed with status {response.status_code}."
            )

        try:
            payload = response.json()
        except ValueError as exc:  # pragma: no cover - unexpected server response
            raise OAuth42Error("Token response was not valid JSON.") from exc

        access_token = payload.get("access_token")
        if not access_token:
            raise OAuth42Error("Token response did not include an access token.")

        return OAuthToken(
            access_token=access_token,
            expires_in=payload.get("expires_in"),
            refresh_token=payload.get("refresh_token"),
            raw=payload,
        )

    async def fetch_user_profile(self, access_token: str) -> dict[str, Any]:
        if not self.enabled:
            raise OAuth42Error("42 OAuth is not configured.")

        headers = {
            "Authorization": f"Bearer {access_token}",
            "Accept": "application/json",
        }

        async with httpx.AsyncClient(timeout=self._timeout) as client:
            response = await client.get(
                self._settings.forty_two_resource_url, headers=headers
            )

        if response.status_code != 200:
            raise OAuth42Error(
                f"Failed to fetch user profile (status {response.status_code})."
            )

        try:
            payload = response.json()
        except ValueError as exc:  # pragma: no cover - unexpected server response
            raise OAuth42Error("User profile response was not valid JSON.") from exc

        if isinstance(payload, dict) and payload.get("error"):
            description = payload.get("error_description") or payload.get("message")
            raise OAuth42Error(f"42 API error: {description or payload['error']}")

        if not isinstance(payload, dict):
            raise OAuth42Error("Malformed user profile payload.")

        return payload
