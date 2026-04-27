from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field


class _BaseSchema(BaseModel):
    model_config = ConfigDict(extra="allow")


class HealthResponse(_BaseSchema):
    status: str
    service: str


class ModuleResponse(_BaseSchema):
    status: str
    module: str
    message: str


class LinhaRelatorioSchema(_BaseSchema):
    codigo: str | None = None
    label: str | None = None
    key: str | None = None
    programacao_id: int | None = None
    total_programacoes: int | None = None
    total_ops: int | None = None


class ProgramacaoRelatorioSchema(_BaseSchema):
    id: int
    numero: str | None = None
    linha: str | None = None
    linha_codigo: str | None = None
    linha_dominante_excel: str | None = None
    base_inicio: str | None = None
    data_consulta: str | None = None
    eficiencia: float | None = None
    status: str | None = None
    total_itens: int | None = None
    total_ops: int | None = None


class SetupRelatorioSchema(_BaseSchema):
    previsto_min: float | None = None
    realizado_min: float | None = None
    eventos_previstos: int | None = None
    eventos_realizados: int | None = None
    inicio_previsto: str | None = None
    fim_previsto: str | None = None
    inicio_real: str | None = None
    fim_real: str | None = None
    status_key: str | None = None
    status_label: str | None = None
    tag_class: str | None = None
    critico: bool | None = None


class ProducaoRelatorioSchema(_BaseSchema):
    prevista: float | None = None
    realizada: float | None = None
    desvio: float | None = None


class TempoRelatorioSchema(_BaseSchema):
    previsto_min: float | None = None
    realizado_min: float | None = None
    desvio_min: float | None = None
    inicio_previsto: str | None = None
    fim_previsto: str | None = None
    inicio_real: str | None = None
    fim_real: str | None = None


class StatusOperacionalSchema(_BaseSchema):
    chave: str | None = None
    label: str | None = None
    classe: str | None = None
    critico: bool | None = None
    setup_status: str | None = None


class KpisOperacionaisSchema(_BaseSchema):
    percentual_cumprimento: float | None = None
    setup_diff_min: float | None = None
    prod_diff: float | None = None
    tempo_diff_min: float | None = None
    setup_critico: bool | None = None


class TurnosOperacionaisSchema(_BaseSchema):
    adm: float | None = None
    noite: float | None = None
    total: float | None = None


class CodiOperacionalSchema(_BaseSchema):
    disponivel: bool | None = None
    eficiencia_pct: float | None = None
    origem: str | None = None


class OperacaoRelatorioSchema(_BaseSchema):
    op: str
    programacao_id: int | None = None
    linha: LinhaRelatorioSchema | None = None
    sku: str | None = None
    descricao_produto: str | None = None
    sequence: int | None = None
    program_seq: int | None = None
    program_order: int | None = None
    setup: SetupRelatorioSchema | None = None
    producao: ProducaoRelatorioSchema | None = None
    tempo: TempoRelatorioSchema | None = None
    status_operacional: StatusOperacionalSchema | None = None
    kpis: KpisOperacionaisSchema | None = None
    turnos: TurnosOperacionaisSchema | None = None
    codi: CodiOperacionalSchema | None = None
    detalhe_operacional: dict[str, Any] = Field(default_factory=dict)
    memoria_calculo: str | None = None
    setup_memoria: str | None = None
    has_setup: bool | None = None
    setup_count: int | None = None
    setup_realizado_eventos: int | None = None
    setup_previsto_min: float | None = None
    setup_realizado_min: float | None = None
    producao_prevista: float | None = None
    producao_realizada: float | None = None
    tempo_previsto_min: float | None = None
    tempo_realizado_min: float | None = None
    start_date: str | None = None
    end_date: str | None = None
    setup_start: str | None = None
    setup_end: str | None = None
    realizado_inicio: str | None = None
    realizado_fim: str | None = None
    percentual_cumprimento: float | None = None
    status: str | None = None
    late: bool | None = None
    divergent: bool | None = None
    no_realized: bool | None = None


class ResumoOperacionalSchema(_BaseSchema):
    ops: int | None = None
    programacoes: int | None = None
    setup: dict[str, Any] = Field(default_factory=dict)
    producao: dict[str, Any] = Field(default_factory=dict)
    tempo: dict[str, Any] = Field(default_factory=dict)
    status: dict[str, Any] = Field(default_factory=dict)
    setup_status: dict[str, Any] = Field(default_factory=dict)
    turnos: dict[str, Any] = Field(default_factory=dict)
    codi: dict[str, Any] = Field(default_factory=dict)
    maior_desvio_positivo: float | None = None
    maior_desvio_negativo: float | None = None
    maior_desvio_positivo_op: str | None = None
    maior_desvio_negativo_op: str | None = None


class DetalheOperacionalSchema(_BaseSchema):
    op_foco: str | None = None
    disponivel: bool | None = None
    principal: list[dict[str, Any]] = Field(default_factory=list)
    apoio: list[dict[str, Any]] = Field(default_factory=list)
    agrupamento_paradas: list[dict[str, Any]] = Field(default_factory=list)
    turnos: dict[str, Any] = Field(default_factory=dict)
    codi: dict[str, Any] = Field(default_factory=dict)
    observacao: str | None = None
    fonte: str | None = None


class OpDetailSummarySchema(_BaseSchema):
    rows_total: int | None = None
    raw_rows_total: int | None = None
    principal_rows: int | None = None
    apoio_rows: int | None = None
    principal_events: int | None = None
    apoio_events: int | None = None
    principal_minutes: float | None = None
    apoio_minutes: float | None = None


class OpDetailResponseSchema(_BaseSchema):
    success: bool
    op: str | None = None
    period_start: str | None = None
    period_end: str | None = None
    setup_plan_min: float | None = None
    main_rule: str | None = None
    detail_source: str | None = None
    has_other_named_paradas: bool | None = None
    support_named_paradas: dict[str, Any] = Field(default_factory=dict)
    turnos: dict[str, Any] = Field(default_factory=dict)
    codi: dict[str, Any] = Field(default_factory=dict)
    summary: OpDetailSummarySchema | dict[str, Any] = Field(default_factory=dict)
    principal: list[dict[str, Any]] = Field(default_factory=list)
    apoio: list[dict[str, Any]] = Field(default_factory=list)
    paradas_agrupadas: list[dict[str, Any]] = Field(default_factory=list)


class RelatorioOperacionalResponseSchema(_BaseSchema):
    sucesso: bool
    dominio: str | None = None
    linha: LinhaRelatorioSchema | None = None
    programacao: ProgramacaoRelatorioSchema | None = None
    periodo: dict[str, Any] = Field(default_factory=dict)
    resumo: ResumoOperacionalSchema | None = None
    ops: list[OperacaoRelatorioSchema] = Field(default_factory=list)
    detalhe_operacional: DetalheOperacionalSchema | None = None
    timeline: list[dict[str, Any]] = Field(default_factory=list)
    metricas: dict[str, Any] = Field(default_factory=dict)
    itens: list[dict[str, Any]] = Field(default_factory=list)
    schedule: list[dict[str, Any]] = Field(default_factory=list)
    assignments: list[dict[str, Any]] = Field(default_factory=list)
