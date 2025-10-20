from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from typing import Any, Iterable, Optional

from .db import get_connection
from .errors import DatabaseError


@dataclass(frozen=True)
class User:
    id: int
    email: str
    roles: list[str]
    intra_login: str | None
    usual_full_name: str | None
    display_name: str | None
    kind: str | None
    image: str | None
    location: str | None
    projects: list[dict[str, Any]] | None
    campus: list[dict[str, Any]] | None
    ready_to_help: bool
    created_at: datetime
    updated_at: datetime

    @property
    def preferred_name(self) -> str:
        return (
            self.display_name
            or self.usual_full_name
            or self.intra_login
            or self.email
        )


def upsert_user_from_42(payload: dict[str, Any]) -> User:
    """Insert or update a user record based on data from the 42 API."""
    email = payload.get("email")
    if not email:
        raise ValueError("42 profile did not include an email address.")

    projects = _normalize_projects(payload.get("projects_users"))
    campus = _normalize_campus(payload.get("campus"))

    user_values = {
        "email": email,
        "intra_login": payload.get("login"),
        "usual_full_name": payload.get("usual_full_name"),
        "display_name": payload.get("displayname"),
        "kind": payload.get("kind"),
        "image": _extract_image_url(payload.get("image")),
        "location": payload.get("location"),
        "projects": projects if projects else None,
        "campus": campus if campus else None,
    }

    try:
        with get_connection() as conn, conn.cursor() as cur:
            cur.execute(
                'SELECT id, roles, ready_to_help FROM "user" WHERE email = %s',
                (email,),
            )
            existing = cur.fetchone()

            if existing is None:
                cur.execute(
                    '''
                    INSERT INTO "user" (
                        email,
                        roles,
                        intra_login,
                        usual_full_name,
                        display_name,
                        kind,
                        image,
                        location,
                        projects,
                        campus
                    )
                    VALUES (%(email)s, %(roles)s, %(intra_login)s, %(usual_full_name)s,
                            %(display_name)s, %(kind)s, %(image)s, %(location)s,
                            %(projects)s, %(campus)s)
                    RETURNING id, email, roles, intra_login, usual_full_name,
                              display_name, kind, image, location, projects,
                              campus, ready_to_help, created_at, updated_at
                    ''',
                    {**user_values, "roles": ["ROLE_USER"]},
                )
            else:
                user_id = existing[0]
                cur.execute(
                    '''
                    UPDATE "user"
                    SET intra_login = %(intra_login)s,
                        usual_full_name = %(usual_full_name)s,
                        display_name = %(display_name)s,
                        kind = %(kind)s,
                        image = %(image)s,
                        location = %(location)s,
                        projects = %(projects)s,
                        campus = %(campus)s,
                        updated_at = NOW()
                    WHERE id = %(user_id)s
                    RETURNING id, email, roles, intra_login, usual_full_name,
                              display_name, kind, image, location, projects,
                              campus, ready_to_help, created_at, updated_at
                    ''',
                    {**user_values, "user_id": user_id},
                )

            row = cur.fetchone()
    except Exception as exc:  # pragma: no cover - DB errors propagate
        raise DatabaseError(f"Failed to upsert user: {exc}") from exc

    if row is None:
        raise DatabaseError("User upsert did not return a record.")

    return _user_from_row(row)


def _user_from_row(row: Iterable[Any]) -> User:
    (
        user_id,
        email,
        roles,
        intra_login,
        usual_full_name,
        display_name,
        kind,
        image,
        location,
        projects,
        campus,
        ready_to_help,
        created_at,
        updated_at,
    ) = row

    return User(
        id=int(user_id),
        email=str(email),
        roles=list(roles or []),
        intra_login=intra_login,
        usual_full_name=usual_full_name,
        display_name=display_name,
        kind=kind,
        image=image,
        location=location,
        projects=list(projects) if projects else None,
        campus=list(campus) if campus else None,
        ready_to_help=bool(ready_to_help),
        created_at=created_at,
        updated_at=updated_at,
    )


def _normalize_projects(projects: Any) -> list[dict[str, Any]]:
    if not isinstance(projects, list):
        return []

    normalised: list[dict[str, Any]] = []
    for item in projects:
        if not isinstance(item, dict):
            continue

        project_info = item.get("project")
        name = None
        if isinstance(project_info, dict):
            name = project_info.get("name")

        status = item.get("status")
        if item.get("validated?") == 1 and status == "in_progress":
            status = "finished"

        if not name or not status:
            continue

        normalised.append(
            {
                "name": name,
                "status": status,
                "updated_at": item.get("updated_at"),
                "final_mark": item.get("final_mark"),
            }
        )

    return normalised


def _normalize_campus(campus: Any) -> list[dict[str, Any]]:
    if not isinstance(campus, list):
        return []

    results: list[dict[str, Any]] = []
    for entry in campus:
        if isinstance(entry, dict):
            results.append(
                {key: entry.get(key) for key in ("id", "name", "time_zone", "language")}
            )
    return results


def _extract_image_url(image_field: Optional[Any]) -> Optional[str]:
    if isinstance(image_field, str):
        return image_field
    if isinstance(image_field, dict):
        link = image_field.get("link")
        if isinstance(link, str):
            return link
    return None
