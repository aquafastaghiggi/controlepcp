from pathlib import Path

from fastapi import FastAPI
from fastapi.staticfiles import StaticFiles

from .api.gantt import router as gantt_router
from .api.health import router as health_router
from .api.ui import router as ui_router
from .config import settings

BASE_DIR = Path(__file__).resolve().parent

app = FastAPI(
    title=settings.app_name,
    version=settings.app_version,
    description="Base paralela do gantt em Python.",
)

app.mount("/static", StaticFiles(directory=BASE_DIR / "static"), name="static")

app.include_router(health_router, prefix=settings.api_prefix)
app.include_router(gantt_router, prefix=settings.api_prefix)
app.include_router(ui_router, prefix=settings.api_prefix)


@app.get("/")
def root() -> dict[str, str]:
    return {
        "app": settings.app_name,
        "status": "running",
        "api_prefix": settings.api_prefix,
    }
