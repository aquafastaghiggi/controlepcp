from pathlib import Path

from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse
from fastapi.templating import Jinja2Templates

from ..config import settings

router = APIRouter(prefix="/gantt", tags=["gantt-ui"])

BASE_DIR = Path(__file__).resolve().parent.parent
templates = Jinja2Templates(directory=str(BASE_DIR / "templates"))


@router.get("/view", response_class=HTMLResponse)
def gantt_view(request: Request, programacao_id: int | None = None) -> HTMLResponse:
    return templates.TemplateResponse(
        request,
        "gantt.html",
        {
            "api_prefix": settings.api_prefix,
            "programacao_id": programacao_id,
            "app_name": settings.app_name,
        },
    )
