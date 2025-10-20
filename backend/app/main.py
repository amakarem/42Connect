from __future__ import annotations

from typing import Any
from urllib.parse import urlencode

from fastapi import FastAPI, Form, HTTPException, Request
from fastapi.concurrency import run_in_threadpool
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from pydantic import BaseModel
from starlette.middleware.sessions import SessionMiddleware

from . import vibes
from .auth42 import FortyTwoOAuth, OAuth42Error
from .errors import DatabaseError, EmbeddingError
from .settings import get_settings
from .users import upsert_user_from_42
from .vibe_profiles import provision_placeholder_vibe

settings = get_settings()

app = FastAPI(title="42Quackform Vibes")

if not settings.session_secret:
    raise RuntimeError(
        "SESSION_SECRET must be defined to enable session management for authentication."
    )

app.add_middleware(
    SessionMiddleware,
    secret_key=settings.session_secret,
    https_only=False,
)

allowed_origins = {
    "http://localhost:5173",
    "http://127.0.0.1:5173",
    "http://localhost:8000",
    "http://127.0.0.1:8000",
}

app.add_middleware(
    CORSMiddleware,
    allow_origins=list(allowed_origins),
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

templates = Jinja2Templates(directory="app/templates")
app.mount("/static", StaticFiles(directory="app/static"), name="static")

oauth_client = FortyTwoOAuth(settings)


async def _load_vibes(limit: int = 20):
    try:
        return await run_in_threadpool(vibes.list_vibes, limit)
    except DatabaseError as exc:
        raise exc


class VibeCreatePayload(BaseModel):
    uid: str
    vibe_text: str


class SearchRequestPayload(BaseModel):
    query: str
    top_k: int = 5


def _serialize_vibe(record: vibes.Vibe) -> dict[str, Any]:
    return {
        "uid": record.uid,
        "original_vibe": record.original_vibe,
        "processed_vibe": record.processed_vibe,
        "embedding_model": record.embedding_model,
        "created_at": record.created_at,
        "updated_at": record.updated_at,
    }


def _serialize_search_result(result: vibes.SearchResult) -> dict[str, Any]:
    return {
        "uid": result.uid,
        "original_vibe": result.original_vibe,
        "processed_vibe": result.processed_vibe,
        "embedding_model": result.embedding_model,
        "distance": result.distance,
        "similarity": result.similarity,
        "lexical_overlap": result.lexical_overlap,
        "recency_decay": result.recency_decay,
        "final_score": result.final_score,
        "formatted_score": result.formatted_score,
        "overlap_terms": result.overlap_terms,
    }


def _current_user(request: Request) -> dict[str, Any] | None:
    user = request.session.get("user")
    if user and isinstance(user, dict):
        return user
    return None


@app.get("/", response_class=HTMLResponse)
async def index(request: Request, message: str | None = None, error: str | None = None):
    try:
        existing = await _load_vibes()
    except DatabaseError as exc:
        error = error or str(exc)
        existing = []

    current_user = _current_user(request)
    context = {
        "request": request,
        "vibes": existing,
        "results": None,
        "query": None,
        "message": message,
        "error": error,
        "current_user": current_user,
    }
    return templates.TemplateResponse("index.html", context)


@app.post("/vibes")
async def create_vibe(uid: str = Form(...), vibe_text: str = Form(...)):
    try:
        await run_in_threadpool(vibes.upsert_vibe, uid, vibe_text)
    except EmbeddingError as exc:
        params = urlencode({"error": str(exc)})
        return RedirectResponse(url=f"/?{params}", status_code=303)
    except DatabaseError as exc:
        params = urlencode({"error": str(exc)})
        return RedirectResponse(url=f"/?{params}", status_code=303)

    params = urlencode({"message": f"Saved vibe for {uid}."})
    return RedirectResponse(url=f"/?{params}", status_code=303)


@app.get("/api/v1/vibes")
async def api_list_vibes(limit: int = 20):
    try:
        records = await _load_vibes(limit)
    except DatabaseError as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc

    return [_serialize_vibe(record) for record in records]


@app.post("/api/v1/vibes")
async def api_create_vibe(payload: VibeCreatePayload):
    try:
        await run_in_threadpool(vibes.upsert_vibe, payload.uid, payload.vibe_text)
    except EmbeddingError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except DatabaseError as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc

    return {"status": "ok"}


@app.post("/search", response_class=HTMLResponse)
async def search_vibes(request: Request, query: str = Form(...), top_k: int = Form(5)):
    message: str | None = None
    error: str | None = None
    results = []

    try:
        results = await run_in_threadpool(vibes.search_vibes, query, top_k)
    except EmbeddingError as exc:
        error = str(exc)
    except DatabaseError as exc:
        error = str(exc)

    try:
        existing = await _load_vibes()
    except DatabaseError as exc:
        error = error or str(exc)
        existing = []

    current_user = _current_user(request)
    context = {
        "request": request,
        "vibes": existing,
        "results": results,
        "query": query,
        "message": message,
        "error": error,
        "current_user": current_user,
    }
    return templates.TemplateResponse("index.html", context)


@app.post("/api/v1/search")
async def api_search_vibes(payload: SearchRequestPayload):
    try:
        results = await run_in_threadpool(vibes.search_vibes, payload.query, payload.top_k)
    except EmbeddingError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except DatabaseError as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc

    return [_serialize_search_result(result) for result in results]


@app.get("/api/v1/me")
async def api_current_user(request: Request):
    user = _current_user(request)
    if not user:
        raise HTTPException(status_code=401, detail="Not authenticated")
    return user


@app.post("/api/v1/logout")
async def api_logout(request: Request):
    request.session.clear()
    return {"status": "ok"}


@app.get("/login")
async def login(request: Request):
    if not oauth_client.enabled:
        raise HTTPException(status_code=503, detail="42 OAuth is not configured.")

    redirect_uri = str(request.url_for("auth_callback"))
    try:
        authorization_url, state = oauth_client.build_authorization_url(redirect_uri)
    except OAuth42Error as exc:
        raise HTTPException(status_code=503, detail=str(exc)) from exc

    request.session["oauth_state"] = state
    return RedirectResponse(url=authorization_url, status_code=302)


@app.get("/auth/callback")
async def auth_callback(request: Request):
    if not oauth_client.enabled:
        raise HTTPException(status_code=503, detail="42 OAuth is not configured.")

    if request.query_params.get("error"):
        error_message = request.query_params.get("error_description") or request.query_params.get("error")
        params = urlencode({"error": error_message or "Login was cancelled."})
        return RedirectResponse(url=f"/?{params}", status_code=303)

    state = request.query_params.get("state")
    expected_state = request.session.pop("oauth_state", None)
    if not state or state != expected_state:
        params = urlencode({"error": "Invalid login state. Please try again."})
        return RedirectResponse(url=f"/?{params}", status_code=303)

    code = request.query_params.get("code")
    if not code:
        params = urlencode({"error": "No authorization code returned from 42."})
        return RedirectResponse(url=f"/?{params}", status_code=303)

    redirect_uri = str(request.url_for("auth_callback"))
    try:
        token = await oauth_client.exchange_code_for_token(code=code, redirect_uri=redirect_uri)
        profile = await oauth_client.fetch_user_profile(token.access_token)
    except OAuth42Error as exc:
        params = urlencode({"error": str(exc)})
        return RedirectResponse(url=f"/?{params}", status_code=303)

    try:
        user = await run_in_threadpool(upsert_user_from_42, profile)
    except (DatabaseError, ValueError) as exc:
        params = urlencode({"error": f"Unable to store 42 profile: {exc}"})
        return RedirectResponse(url=f"/?{params}", status_code=303)

    provisioning_error: str | None = None
    try:
        await run_in_threadpool(provision_placeholder_vibe, user)
    except DatabaseError as exc:
        provisioning_error = f"Login succeeded but provisioning vibe failed: {exc}"

    request.session["user"] = {
        "id": user.id,
        "email": user.email,
        "preferred_name": user.preferred_name,
        "image": user.image,
        "intra_login": user.intra_login,
    }

    if provisioning_error:
        params = urlencode({"error": provisioning_error})
    else:
        params = urlencode({"message": f"Welcome back {user.preferred_name}!"})

    return RedirectResponse(url=f"/?{params}", status_code=303)


@app.get("/logout")
async def logout(request: Request):
    request.session.clear()
    params = urlencode({"message": "You have been logged out."})
    return RedirectResponse(url=f"/?{params}", status_code=303)
