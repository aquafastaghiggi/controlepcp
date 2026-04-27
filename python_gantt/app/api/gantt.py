from fastapi import APIRouter, Depends, HTTPException, Query
from sqlalchemy.orm import Session

from ..db import get_db
from ..repositories.programacao_repository import ProgramacaoRepository
from ..repositories.realizado_repository import RealizadoRepository
from ..services.gantt_service import GanttService
from ..services.mapping_service import MappingService
from ..schemas import OpDetailResponseSchema

router = APIRouter(prefix="/gantt", tags=["gantt"])


def _build_service(db: Session) -> GanttService:
    return GanttService(
        programacao_repository=ProgramacaoRepository(db),
        realizado_repository=RealizadoRepository(db),
        mapping_service=MappingService(),
    )


@router.get("/")
def gantt_root() -> dict[str, str]:
    return {
        "status": "ready",
        "module": "python_gantt",
        "message": "Base paralela do gantt pronta para leitura de dados.",
    }


@router.get("/programacoes")
def list_programacoes(
    limit: int = Query(100, ge=1, le=500),
    offset: int = Query(0, ge=0),
    linha: str | None = Query(default=None),
    db: Session = Depends(get_db),
) -> dict[str, object]:
    service = _build_service(db)
    return {
        "sucesso": True,
        "data": service.list_programacoes(limit=limit, offset=offset, line_code=linha),
        "limit": limit,
        "offset": offset,
        "linha": linha,
    }


@router.get("/programacoes/{programacao_id}")
def get_programacao_timeline(
    programacao_id: int,
    data_inicio: str | None = None,
    data_fim: str | None = None,
    db: Session = Depends(get_db),
) -> dict[str, object]:
    service = _build_service(db)
    try:
        return service.build_programacao_payload(
            programacao_id=programacao_id,
            data_inicio=data_inicio,
            data_fim=data_fim,
        )
    except LookupError as exc:
        raise HTTPException(status_code=404, detail=str(exc)) from exc


@router.get("/ops/{op}/detalhe", response_model=OpDetailResponseSchema)
def get_op_detail(
    op: str,
    period_start: str | None = Query(default=None),
    period_end: str | None = Query(default=None),
    setup_plan_min: float | None = Query(default=None),
    db: Session = Depends(get_db),
) -> dict[str, object]:
    if str(op or "").strip() == "":
        raise HTTPException(status_code=400, detail="OP invalida para o detalhe.")
    if str(period_start or "").strip() == "" or str(period_end or "").strip() == "":
        raise HTTPException(status_code=400, detail="Parâmetros inválidos para o detalhe da OP.")

    service = _build_service(db)
    try:
        return service.build_op_detail_payload(
            op=op,
            period_start=period_start or "",
            period_end=period_end or "",
            setup_plan_min=setup_plan_min,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
