from __future__ import annotations

import json
from datetime import datetime, timedelta
from typing import Any

from ..repositories.programacao_repository import ProgramacaoRepository
from ..repositories.realizado_repository import RealizadoRepository
from .mapping_service import MappingService
from .work_calendar import WorkCalendar


class GanttService:
    """Monta um contrato operacional inspirado em `relgantt.php`."""

    def __init__(
        self,
        programacao_repository: ProgramacaoRepository,
        realizado_repository: RealizadoRepository,
        mapping_service: MappingService,
    ) -> None:
        self.programacao_repository = programacao_repository
        self.realizado_repository = realizado_repository
        self.mapping_service = mapping_service

    @staticmethod
    def _parse_date(value: str | None) -> datetime | None:
        raw = str(value or "").strip()
        if raw == "":
            return None

        normalized = raw.replace("T", " ")
        formats = [
            "%Y-%m-%d %H:%M:%S",
            "%Y-%m-%d %H:%M",
            "%Y-%m-%d",
            "%d/%m/%Y %H:%M:%S",
            "%d/%m/%Y %H:%M",
            "%d/%m/%Y",
        ]

        for fmt in formats:
            try:
                return datetime.strptime(normalized, fmt)
            except ValueError:
                continue

        try:
            return datetime.fromisoformat(normalized)
        except ValueError:
            return None

    @staticmethod
    def _format_task_date(value: str | datetime | None) -> str:
        if value is None:
            return ""
        if isinstance(value, datetime):
            return value.strftime("%d-%m-%Y %H:%M")
        parsed = GanttService._parse_date(str(value))
        if parsed is None:
            return str(value)
        return parsed.strftime("%d-%m-%Y %H:%M")

    @staticmethod
    def _to_float(value: Any, default: float = 0.0) -> float:
        try:
            return float(value)
        except (TypeError, ValueError):
            return default

    @staticmethod
    def _to_int(value: Any, default: int = 0) -> int:
        try:
            return int(value)
        except (TypeError, ValueError):
            return default

    @staticmethod
    def _normalize_line_key(value: str | None) -> str:
        raw = str(value or "").strip().lower()
        if raw == "":
            return ""
        return (
            raw.replace(" ", "")
            .replace("/", "")
            .replace(".", "")
            .replace("-", "")
        )

    @staticmethod
    def _line_label(value: str | None) -> str:
        raw = str(value or "").strip()
        if raw == "":
            return "S/linha"

        normalized = GanttService._normalize_line_key(raw)
        if normalized.startswith("ln") and normalized[2:].isdigit():
            return f"Linha {int(normalized[2:]):02d}"
        if normalized.startswith("linha") and normalized[5:].isdigit():
            return f"Linha {int(normalized[5:]):02d}"

        return raw

    @staticmethod
    def _first_non_empty(*values: str | None) -> str:
        for value in values:
            raw = str(value or "").strip()
            if raw:
                return raw
        return ""

    def _resolve_row_window(self, row: dict[str, Any]) -> tuple[str, str]:
        start = self._first_non_empty(
            row.get("sch_inicio_producao"),
            f"{str(row.get('sch_data_inicio') or '').strip()} {str(row.get('sch_hora_inicio') or '').strip()}".strip(),
        )
        end = self._first_non_empty(
            row.get("sch_fim_producao"),
            f"{str(row.get('sch_data_inicio') or '').strip()} {str(row.get('sch_hora_fim') or '').strip()}".strip(),
        )
        return start, end

    @staticmethod
    def _resolve_screen_period(
        schedule_rows: list[dict[str, Any]],
        data_inicio: str | None = None,
        data_fim: str | None = None,
    ) -> tuple[str, str]:
        start = GanttService._parse_date(data_inicio)
        end = GanttService._parse_date(data_fim)

        if start and end and start > end:
            start, end = end, start

        if start and end:
            return start.strftime("%Y-%m-%d"), end.strftime("%Y-%m-%d")

        start_candidates: list[str] = []
        end_candidates: list[str] = []
        for row in schedule_rows:
            start_candidate = str(row.get("sch_inicio_producao") or "").strip()
            end_candidate = str(row.get("sch_fim_producao") or "").strip()
            if start_candidate:
                parsed = GanttService._parse_date(start_candidate)
                if parsed:
                    start_candidates.append(parsed.strftime("%Y-%m-%d"))
            if end_candidate:
                parsed = GanttService._parse_date(end_candidate)
                if parsed:
                    end_candidates.append(parsed.strftime("%Y-%m-%d"))

        if start_candidates and end_candidates:
            start_value = min(start_candidates)
            end_value = max(end_candidates)
        else:
            today = datetime.today().strftime("%Y-%m-%d")
            start_value = today
            end_value = today

        if start_value > end_value:
            start_value, end_value = end_value, start_value

        return start_value, end_value

    def build_programacao_payload(
        self,
        programacao_id: int,
        data_inicio: str | None = None,
        data_fim: str | None = None,
    ) -> dict[str, Any]:
        programacao = self.programacao_repository.get_programacao_by_id(programacao_id)
        if not programacao:
            raise LookupError("Programacao nao encontrada")

        items = self.programacao_repository.list_programacao_itens(programacao_id)
        schedule = self.programacao_repository.list_programacao_schedule(programacao_id)
        if not schedule:
            raise LookupError("Programacao sem schedule")

        screen_period_start, screen_period_end = self._resolve_screen_period(schedule, data_inicio, data_fim)
        assignments = self.mapping_service.resolve_assignments(schedule, items)
        line_code = str(programacao.get("lin_codigo") or programacao.get("linha_excel_dominante") or "").strip()
        work_calendar = self._build_work_calendar(line_code)
        operations = self._build_operacoes_relatorio(
            programacao=programacao,
            schedule_rows=schedule,
            assignments=assignments,
            screen_period_start=screen_period_start,
            screen_period_end=screen_period_end,
            work_calendar=work_calendar,
        )
        resumo = self._build_resumo_relatorio(operations)
        detalhe_operacional = self._build_detalhe_operacional(operations, programacao)
        timeline = self._build_timeline_from_operacoes(operations)

        payload = {
            "sucesso": True,
            "dominio": "relgantt",
            "linha": self._build_line_info(programacao, len(operations)),
            "programacao": self._serialize_programacao(programacao, len(operations)),
            "periodo": {
                "inicio": screen_period_start,
                "fim": screen_period_end,
            },
            "resumo": resumo,
            "ops": operations,
            "detalhe_operacional": detalhe_operacional,
            "timeline": timeline,
            "metricas": self._build_legacy_metricas_alias(resumo),
            "itens": items,
            "schedule": schedule,
            "assignments": self._serialize_assignments(schedule, assignments),
        }

        return payload

    def list_programacoes(
        self,
        limit: int = 100,
        offset: int = 0,
        line_code: str | None = None,
    ) -> list[dict[str, Any]]:
        return self.programacao_repository.list_programacoes(limit=limit, offset=offset, line_code=line_code)

    def _build_line_info(self, programacao: dict[str, Any], total_ops: int) -> dict[str, Any]:
        line_code = str(programacao.get("lin_codigo") or programacao.get("linha_excel_dominante") or "").strip()
        line_label = self._line_label(line_code)
        return {
            "codigo": line_code or "S/linha",
            "label": line_label,
            "key": self._normalize_line_key(line_code) or self._normalize_line_key(line_label),
            "programacao_id": self._to_int(programacao.get("prg_id")),
            "total_programacoes": 1,
            "total_ops": total_ops,
        }

    def _build_work_calendar(self, line_code: str) -> WorkCalendar | None:
        if line_code.strip() == "":
            return None

        calendar_data = self.programacao_repository.load_work_calendar_for_line(line_code)
        if not calendar_data:
            return None

        intervals = calendar_data.get("intervals") or []
        if not intervals:
            return None

        try:
            return WorkCalendar(
                intervals=[dict(interval) for interval in intervals],
                working_days=[int(value) for value in (calendar_data.get("working_days") or [1, 2, 3, 4, 5])],
                holidays=list(calendar_data.get("holidays") or []),
            )
        except Exception:
            return None

    @staticmethod
    def _format_minutes_clock(minutes: float) -> str:
        total = max(0, int(round(minutes)))
        hours, mins = divmod(total, 60)
        return f"{hours:02d}:{mins:02d}"

    @staticmethod
    def _format_nullable_event_text(value: str | None) -> str:
        raw = str(value or "").strip()
        return raw if raw else "Sem classificacao"

    @staticmethod
    def _normalize_parada_label(value: str | None) -> str:
        raw = str(value or "").strip()
        return raw if raw else "Sem classificacao"

    @staticmethod
    def _parada_group_key(value: str | None) -> str:
        raw = str(value or "").strip()
        return raw.upper() if raw else "__NULL__"

    @staticmethod
    def _is_setup_target_parada(value: str | None) -> bool:
        upper = str(value or "").strip().upper()
        return upper in {"TROCA DE KIT", "TROCA DE LIQUIDO"}

    @staticmethod
    def _is_visible_complementary_parada(value: str | None) -> bool:
        label = str(value or "").strip()
        if label == "":
            return False
        upper_label = label.upper()
        return upper_label != "DESCONEXAO"

    def _working_minutes_by_shift(
        self,
        work_calendar: WorkCalendar,
        start: datetime,
        end: datetime,
    ) -> dict[str, int]:
        adm = 0
        noite = 0

        start_ts = start.timestamp()
        end_ts = end.timestamp()
        current = start.replace(hour=0, minute=0, second=0, microsecond=0)
        end_day = end.replace(hour=0, minute=0, second=0, microsecond=0)
        end_day_ts = end_day.timestamp()

        while current.timestamp() <= end_day_ts:
            windows = [
                {"bucket": "adm", "start": current.replace(hour=7, minute=5, second=0, microsecond=0), "end": current.replace(hour=11, minute=30, second=0, microsecond=0)},
                {"bucket": "adm", "start": current.replace(hour=13, minute=27, second=0, microsecond=0), "end": current.replace(hour=17, minute=45, second=0, microsecond=0)},
                {"bucket": "noite", "start": current.replace(hour=17, minute=45, second=0, microsecond=0), "end": current.replace(hour=22, minute=0, second=0, microsecond=0)},
                {"bucket": "noite", "start": current.replace(hour=23, minute=0, second=0, microsecond=0), "end": (current + timedelta(days=1)).replace(hour=3, minute=0, second=0, microsecond=0)},
            ]

            for window in windows:
                win_start = window["start"]
                win_end = window["end"]
                seg_start = start if start_ts > win_start.timestamp() else win_start
                seg_end = end if end_ts < win_end.timestamp() else win_end
                if seg_end <= seg_start:
                    continue

                minutes = work_calendar.working_minutes_between(seg_start, seg_end)
                if window["bucket"] == "adm":
                    adm += minutes
                else:
                    noite += minutes

            current = current + timedelta(days=1)

        return {"adm": max(0, adm), "noite": max(0, noite)}

    @staticmethod
    def _parse_payload_json(value: Any) -> dict[str, Any]:
        if isinstance(value, dict):
            return value
        raw = str(value or "").strip()
        if raw == "":
            return {}
        try:
            parsed = json.loads(raw)
            return parsed if isinstance(parsed, dict) else {}
        except Exception:
            return {}

    def _infer_line_code_from_raw_rows(self, raw_rows: list[dict[str, Any]]) -> str:
        for row in raw_rows:
            payload = self._parse_payload_json(row.get("payload_json"))
            grandeza = payload.get("grandeza") if isinstance(payload.get("grandeza"), dict) else {}
            recurso = grandeza.get("recurso") if isinstance(grandeza.get("recurso"), dict) else {}
            resource_name = str(recurso.get("nomeRecurso") or recurso.get("descricao") or "").strip()
            if resource_name == "":
                continue

            normalized = self._normalize_line_key(resource_name)
            if normalized.startswith("ln") and normalized[2:].isdigit():
                return f"Ln {int(normalized[2:]):02d}"
            if normalized.startswith("linha") and normalized[5:].isdigit():
                return f"Ln {int(normalized[5:]):02d}"
            digits = "".join(ch for ch in resource_name if ch.isdigit())
            if digits:
                return f"Ln {int(digits):02d}"

        return ""

    def _build_codi_efficiency_from_raw_rows(self, raw_rows: list[dict[str, Any]]) -> dict[str, Any]:
        prod_minutes = 0.0
        weighted_sum = 0.0

        for row in raw_rows:
            if str(row.get("estado_evento") or "").strip().upper() != "PRODUCAO":
                continue

            duration = self._to_float(row.get("duracao_evento_minutos"))
            if duration <= 0.0:
                continue

            payload = self._parse_payload_json(row.get("payload_json"))
            perf = payload.get("performancePeriodo")
            try:
                perf_value = float(perf)
            except (TypeError, ValueError):
                continue

            prod_minutes += duration
            weighted_sum += perf_value * duration

        if prod_minutes <= 0.0:
            return {
                "disponivel": False,
                "eficiencia_pct": None,
                "origem": "realizado_2026_eventos",
            }

        return {
            "disponivel": True,
            "eficiencia_pct": round((weighted_sum / prod_minutes) * 100.0, 2),
            "origem": "realizado_2026_eventos",
        }

    def _split_turnos_from_intervals(
        self,
        work_calendar: WorkCalendar,
        intervals: list[dict[str, Any]],
    ) -> dict[str, Any]:
        adm_qty = 0.0
        noite_qty = 0.0
        total_qty = 0.0
        has_data = False

        for row in intervals:
            start = self._parse_date(str(row.get("inicio_evento") or ""))
            end = self._parse_date(str(row.get("fim_evento") or ""))
            if start is None or end is None or end <= start:
                continue

            qty = self._to_float(row.get("quantidade"))
            if qty <= 0.0:
                continue

            shift_minutes = self._working_minutes_by_shift(work_calendar, start, end)
            adm_minutes = int(shift_minutes.get("adm") or 0)
            noite_minutes = int(shift_minutes.get("noite") or 0)
            denom = adm_minutes + noite_minutes
            if denom <= 0:
                continue

            has_data = True
            adm_qty += qty * (adm_minutes / denom)
            noite_qty += qty * (noite_minutes / denom)
            total_qty += qty

        return {
            "disponivel": has_data,
            "adm": round(adm_qty, 2),
            "noite": round(noite_qty, 2),
            "total": round(total_qty, 2),
            "origem": "realizado_2026_excel",
        }

    def _classify_setup_status(self, setup_planned_min: float, setup_realized_min: float, setup_events: int) -> dict[str, Any]:
        setup_diff = setup_realized_min - setup_planned_min
        abs_diff = abs(setup_diff)
        critical = setup_planned_min > 0.01 and abs_diff >= 30.0

        if setup_events > 0:
            return {
                "status_key": "no_prazo" if abs_diff <= 1.0 else ("acima" if setup_diff > 1.0 else "abaixo"),
                "status_label": "Setup realizado",
                "tag_class": "tag-success",
                "critical": critical,
            }

        if setup_planned_min > 0.01:
            return {
                "status_key": "sem_evento",
                "status_label": "Sem evento",
                "tag_class": "tag-warning",
                "critical": critical,
            }

        return {
            "status_key": "sem_setup",
            "status_label": "Sem setup",
            "tag_class": "tag-muted",
            "critical": False,
        }

    def _classify_analytic_badge(
        self,
        prod_plan: float,
        prod_diff: float,
        tempo_prev_min: float,
        tempo_real_min: float,
        setup_plan_min: float,
        setup_events: int,
        setup_critical: bool,
    ) -> dict[str, str]:
        prod_ratio = (prod_diff / prod_plan) if prod_plan > 0.0001 else 0.0
        tempo_diff = tempo_real_min - tempo_prev_min
        tempo_ratio = (tempo_diff / tempo_prev_min) if tempo_prev_min > 0.0001 else 0.0

        is_critical = (
            setup_critical
            or (prod_plan > 0.0001 and prod_ratio <= -0.10)
            or (tempo_prev_min > 0.0001 and tempo_diff >= 60.0 and tempo_ratio >= 0.25)
        )

        if is_critical:
            return {"label": "Crítico", "class": "tag-danger", "key": "critico"}

        is_warning = (
            (setup_plan_min > 0.01 and setup_events <= 0)
            or (prod_plan > 0.0001 and prod_ratio <= -0.03)
            or (tempo_prev_min > 0.0001 and tempo_diff >= 20.0 and tempo_ratio >= 0.10)
        )

        if is_warning:
            return {"label": "Atenção", "class": "tag-warning", "key": "atencao"}

        return {"label": "OK", "class": "tag-ok", "key": "ok"}

    def _build_operacoes_relatorio(
        self,
        programacao: dict[str, Any],
        schedule_rows: list[dict[str, Any]],
        assignments: dict[int, str],
        screen_period_start: str,
        screen_period_end: str,
        work_calendar: WorkCalendar | None = None,
    ) -> list[dict[str, Any]]:
        line_code = str(programacao.get("lin_codigo") or programacao.get("linha_excel_dominante") or "").strip()
        line_label = self._line_label(line_code)
        programacao_id = self._to_int(programacao.get("prg_id"))
        line_info = {
            "codigo": line_code or "S/linha",
            "label": line_label,
            "key": self._normalize_line_key(line_code) or self._normalize_line_key(line_label),
            "programacao_id": programacao_id,
        }

        operations: dict[str, dict[str, Any]] = {}
        pending_setup = {
            "minutes": 0.0,
            "events": 0,
            "start": "",
            "end": "",
        }
        program_order = 0

        for row in schedule_rows:
            schedule_type = str(row.get("sch_tipo") or "").strip().lower()
            schedule_id = self._to_int(row.get("sch_id"))
            sku = str(row.get("sch_sku") or "").strip()
            planned_qty = self._to_float(row.get("sch_quantidade"))
            duration_minutes = self._to_float(row.get("sch_duracao_minutos"))
            description = str(row.get("sch_descricao") or "").strip()
            row_start, row_end = self._resolve_row_window(row)

            if schedule_type == "setup":
                pending_setup["minutes"] += max(0.0, duration_minutes)
                pending_setup["events"] += 1
                if row_start and (pending_setup["start"] == "" or row_start < pending_setup["start"]):
                    pending_setup["start"] = row_start
                if row_end and (pending_setup["end"] == "" or row_end > pending_setup["end"]):
                    pending_setup["end"] = row_end
                continue

            op = str(assignments.get(schedule_id, "S/OP") or "S/OP").strip() or "S/OP"
            if op == "S/OP":
                continue

            if op not in operations:
                operations[op] = {
                    "op": op,
                    "programacao_id": programacao_id,
                    "linha": line_info,
                    "sku": sku,
                    "descricao_produto": description,
                    "sequence": self._to_int(row.get("sch_sequencia")),
                    "program_seq": self._to_int(row.get("sch_sequencia")),
                    "program_order": program_order,
                    "has_setup": False,
                    "setup_count": 0,
                    "setup_realizado_eventos": 0,
                    "setup_previsto_min": 0.0,
                    "setup_realizado_min": 0.0,
                    "producao_prevista": 0.0,
                    "producao_realizada": 0.0,
                    "producao_realizada_adm": 0.0,
                    "producao_realizada_noite": 0.0,
                    "tempo_previsto_min": 0.0,
                    "tempo_realizado_min": 0.0,
                    "start_date": "",
                    "end_date": "",
                    "setup_start": "",
                    "setup_end": "",
                    "realizado_inicio": None,
                    "realizado_fim": None,
                    "memoria_calculo": str(row.get("sch_memoria_calculo") or ""),
                    "setup_memoria": "",
                    "status": "planned",
                    "late": False,
                    "divergent": False,
                    "no_realized": False,
                    "turnos": {
                        "disponivel": False,
                        "adm": 0.0,
                        "noite": 0.0,
                        "total": 0.0,
                        "origem": "indisponivel",
                    },
                    "codi": {
                        "disponivel": False,
                        "eficiencia_pct": None,
                        "origem": "indisponivel",
                    },
                    "detalhe_operacional": {
                        "disponivel": False,
                        "fonte": "relgantt_base",
                    },
                }
                program_order += 1

            record = operations[op]
            if not record["descricao_produto"]:
                record["descricao_produto"] = description or sku or op
            if not record["sku"]:
                record["sku"] = sku

            if row_start and (record["start_date"] == "" or row_start < record["start_date"]):
                record["start_date"] = row_start
            if row_end and (record["end_date"] == "" or row_end > record["end_date"]):
                record["end_date"] = row_end

            if pending_setup["minutes"] > 0 or pending_setup["events"] > 0:
                record["setup_previsto_min"] += pending_setup["minutes"]
                record["setup_count"] += 1
                record["has_setup"] = True
                if pending_setup["start"] and (record["setup_start"] == "" or pending_setup["start"] < record["setup_start"]):
                    record["setup_start"] = pending_setup["start"]
                if pending_setup["end"] and (record["setup_end"] == "" or pending_setup["end"] > record["setup_end"]):
                    record["setup_end"] = pending_setup["end"]
                if not record["setup_memoria"]:
                    record["setup_memoria"] = str(row.get("sch_memoria_calculo") or "")

            record["producao_prevista"] += max(0.0, planned_qty)
            record["tempo_previsto_min"] += max(0.0, duration_minutes)

            pending_setup = {
                "minutes": 0.0,
                "events": 0,
                "start": "",
                "end": "",
            }

        ops_list = list(operations.values())
        for record in ops_list:
            op = str(record.get("op") or "S/OP")
            if op == "S/OP":
                continue

            realized = self.realizado_repository.aggregate_by_op_period(op, screen_period_start, screen_period_end)
            realized_intervals: list[dict[str, Any]] = []
            detail_rows: dict[str, Any] = {
                "detail_source": "realizado_2026_excel",
                "raw_rows": [],
                "raw_rows_total": 0,
                "raw_table_available": False,
            }
            if work_calendar is not None:
                realized_intervals = self.realizado_repository.list_intervals_by_op_period(op, screen_period_start, screen_period_end)
                calendar_minutes = self._calc_realized_minutes_with_calendar(work_calendar, realized_intervals)
                if calendar_minutes is not None:
                    realized["tempo_realizado_min"] = calendar_minutes
                turnos = self._split_turnos_from_intervals(work_calendar, realized_intervals)
            else:
                turnos = {
                    "disponivel": False,
                    "adm": 0.0,
                    "noite": 0.0,
                    "total": 0.0,
                    "origem": "sem_calendario",
                }

            detail_rows = self.realizado_repository.list_detail_rows_by_op_period(op, screen_period_start, screen_period_end)
            codi = self._build_codi_efficiency_from_raw_rows(list(detail_rows.get("raw_rows") or []))
            realized_total = self._to_float(realized.get("total"))
            realized_tempo = self._to_float(realized.get("tempo_realizado_min"))
            realized_setup = self._to_float(realized.get("setup_realizado_min"))
            realized_setup_events = self._to_int(realized.get("setup_realizado_eventos"))
            realized_start = realized.get("inicio_real")
            realized_end = realized.get("fim_real")

            prod_prev = self._to_float(record.get("producao_prevista"))
            setup_prev = self._to_float(record.get("setup_previsto_min"))
            tempo_prev = self._to_float(record.get("tempo_previsto_min"))
            prod_diff = realized_total - prod_prev
            tempo_diff = realized_tempo - tempo_prev
            setup_diff = realized_setup - setup_prev
            setup_critical = setup_prev > 0.01 and abs(setup_diff) >= 30.0
            setup_status = self._classify_setup_status(setup_prev, realized_setup, realized_setup_events)
            analytic_badge = self._classify_analytic_badge(
                prod_prev,
                prod_diff,
                tempo_prev,
                realized_tempo,
                setup_prev,
                realized_setup_events,
                setup_critical,
            )

            planned_end = self._parse_date(str(record.get("end_date") or ""))
            realized_end_dt = self._parse_date(str(realized_end or ""))
            has_realized = realized_total > 0.0001
            late = False
            if not has_realized:
                late = planned_end is not None and planned_end < datetime.today()
            elif realized_end_dt is not None and planned_end is not None and realized_end_dt > planned_end:
                late = True

            record["setup_realizado_min"] = round(realized_setup, 2)
            record["setup_realizado_eventos"] = realized_setup_events
            record["producao_realizada"] = round(realized_total, 2)
            record["producao_realizada_adm"] = round(self._to_float(turnos.get("adm")), 2)
            record["producao_realizada_noite"] = round(self._to_float(turnos.get("noite")), 2)
            record["tempo_realizado_min"] = round(realized_tempo, 2)
            record["realizado_inicio"] = realized_start
            record["realizado_fim"] = realized_end
            record["setup"] = {
                "previsto_min": round(setup_prev, 2),
                "realizado_min": round(realized_setup, 2),
                "eventos_previstos": self._to_int(record.get("setup_count")),
                "eventos_realizados": realized_setup_events,
                "inicio_previsto": record.get("setup_start") or None,
                "fim_previsto": record.get("setup_end") or None,
                "inicio_real": None,
                "fim_real": None,
                "status_key": setup_status["status_key"],
                "status_label": setup_status["status_label"],
                "tag_class": setup_status["tag_class"],
                "critico": setup_status["critical"],
            }
            record["producao"] = {
                "prevista": round(prod_prev, 2),
                "realizada": round(realized_total, 2),
                "desvio": round(prod_diff, 2),
            }
            record["tempo"] = {
                "previsto_min": round(tempo_prev, 2),
                "realizado_min": round(realized_tempo, 2),
                "desvio_min": round(tempo_diff, 2),
                "inicio_previsto": record.get("start_date") or None,
                "fim_previsto": record.get("end_date") or None,
                "inicio_real": realized_start,
                "fim_real": realized_end,
            }
            record["status_operacional"] = {
                "chave": analytic_badge["key"],
                "label": analytic_badge["label"],
                "classe": analytic_badge["class"],
                "critico": analytic_badge["key"] == "critico",
                "setup_status": setup_status["status_key"],
            }
            percentual = round((realized_total / prod_prev) * 100, 1) if prod_prev > 0 else 0.0
            record["kpis"] = {
                "percentual_cumprimento": percentual,
                "setup_diff_min": round(setup_diff, 2),
                "prod_diff": round(prod_diff, 2),
                "tempo_diff_min": round(tempo_diff, 2),
                "setup_critico": setup_critical,
            }
            record["percentual_cumprimento"] = percentual
            record["status"] = analytic_badge["key"] if analytic_badge["key"] != "atencao" else "running"
            record["late"] = late
            record["divergent"] = prod_prev > 0.0001 and abs(prod_diff) > 0.01
            record["no_realized"] = not has_realized and prod_prev > 0.0001
            record["turnos"] = turnos
            record["codi"] = codi
            record["detalhe_operacional"] = {
                "disponivel": bool(detail_rows.get("raw_table_available")),
                "fonte": str(detail_rows.get("detail_source") or "realizado_2026_excel"),
                "op_foco": op,
                "principal": [],
                "apoio": [],
                "agrupamento_paradas": [],
                "turnos": turnos,
                "codi": codi,
                "observacao": "Detalhe operacional completo ainda nao portado.",
            }
            record["status_setup"] = setup_status
            record["status_analitico"] = analytic_badge

        ops_list.sort(
            key=lambda item: (
                self._to_int(item.get("program_order"), 10**9),
                self._to_int(item.get("program_seq"), 10**9),
                str(item.get("start_date") or ""),
                str(item.get("op") or ""),
            )
        )

        return ops_list

    def _calc_realized_minutes_with_calendar(
        self,
        work_calendar: WorkCalendar,
        intervals: list[dict[str, Any]],
    ) -> float | None:
        parsed_intervals: list[dict[str, Any]] = []
        for row in intervals:
            start = self._parse_date(str(row.get("inicio_evento") or ""))
            end = self._parse_date(str(row.get("fim_evento") or ""))
            if start is None or end is None or end <= start:
                continue
            parsed_intervals.append({"start": start, "end": end})

        if not parsed_intervals:
            return None

        merged = work_calendar.consolidate_intervals(parsed_intervals)
        minutes = 0
        for segment in merged:
            start = segment.get("start")
            end = segment.get("end")
            if start is None or end is None or end <= start:
                continue
            minutes += work_calendar.working_minutes_between(start, end)

        return float(max(0, minutes))

    def _build_resumo_relatorio(self, operations: list[dict[str, Any]]) -> dict[str, Any]:
        setup_previsto = sum(self._to_float(item.get("setup", {}).get("previsto_min")) for item in operations)
        setup_realizado = sum(self._to_float(item.get("setup", {}).get("realizado_min")) for item in operations)
        producao_prevista = sum(self._to_float(item.get("producao", {}).get("prevista")) for item in operations)
        producao_realizada = sum(self._to_float(item.get("producao", {}).get("realizada")) for item in operations)
        tempo_previsto = sum(self._to_float(item.get("tempo", {}).get("previsto_min")) for item in operations)
        tempo_realizado = sum(self._to_float(item.get("tempo", {}).get("realizado_min")) for item in operations)

        status_counts = {"ok": 0, "atencao": 0, "critico": 0, "sem_evento": 0, "sem_setup": 0}
        setup_status_counts = {"no_prazo": 0, "acima": 0, "abaixo": 0, "sem_evento": 0, "sem_setup": 0}
        turnos_summary = {"adm": 0.0, "noite": 0.0, "total": 0.0}
        codi_available_ops = 0
        codi_sum_pct = 0.0

        maior_desvio_positivo = 0.0
        maior_desvio_negativo = 0.0
        maior_desvio_positivo_op = ""
        maior_desvio_negativo_op = ""

        for item in operations:
            status_key = str((item.get("status_operacional") or {}).get("chave") or "ok")
            if status_key not in status_counts:
                status_counts[status_key] = 0
            status_counts[status_key] += 1

            setup_status_key = str((item.get("setup") or {}).get("status_key") or "sem_setup")
            if setup_status_key not in setup_status_counts:
                setup_status_counts[setup_status_key] = 0
            setup_status_counts[setup_status_key] += 1

            turnos = item.get("turnos") if isinstance(item.get("turnos"), dict) else {}
            turnos_summary["adm"] += self._to_float(turnos.get("adm"))
            turnos_summary["noite"] += self._to_float(turnos.get("noite"))
            turnos_summary["total"] += self._to_float(turnos.get("total"))

            codi = item.get("codi") if isinstance(item.get("codi"), dict) else {}
            codi_pct = codi.get("eficiencia_pct")
            if codi.get("disponivel") and codi_pct is not None:
                codi_available_ops += 1
                codi_sum_pct += self._to_float(codi_pct)

            setup_diff = self._to_float((item.get("kpis") or {}).get("setup_diff_min"))
            op = str(item.get("op") or "")
            if setup_diff > maior_desvio_positivo:
                maior_desvio_positivo = setup_diff
                maior_desvio_positivo_op = op
            if setup_diff < maior_desvio_negativo:
                maior_desvio_negativo = setup_diff
                maior_desvio_negativo_op = op

        setup_pendente = max(0.0, setup_previsto - setup_realizado)
        percentual = (producao_realizada / producao_prevista) * 100 if producao_prevista > 0 else 0.0

        return {
            "ops": len(operations),
            "programacoes": 1,
            "setup": {
                "previsto_min": round(setup_previsto, 2),
                "realizado_min": round(setup_realizado, 2),
                "pendente_min": round(setup_pendente, 2),
            },
            "producao": {
                "prevista": round(producao_prevista, 2),
                "realizada": round(producao_realizada, 2),
                "desvio": round(producao_realizada - producao_prevista, 2),
                "percentual": round(percentual, 1),
            },
            "tempo": {
                "previsto_min": round(tempo_previsto, 2),
                "realizado_min": round(tempo_realizado, 2),
                "desvio_min": round(tempo_realizado - tempo_previsto, 2),
            },
            "status": status_counts,
            "setup_status": setup_status_counts,
            "turnos": {
                "adm": round(turnos_summary["adm"], 2),
                "noite": round(turnos_summary["noite"], 2),
                "total": round(turnos_summary["total"], 2),
            },
            "codi": {
                "ops_com_codi": codi_available_ops,
                "eficiencia_media_pct": round((codi_sum_pct / codi_available_ops), 2) if codi_available_ops > 0 else None,
            },
            "maior_desvio_positivo": round(maior_desvio_positivo, 2),
            "maior_desvio_negativo": round(maior_desvio_negativo, 2),
            "maior_desvio_positivo_op": maior_desvio_positivo_op,
            "maior_desvio_negativo_op": maior_desvio_negativo_op,
        }

    def build_op_detail_payload(
        self,
        op: str,
        period_start: str,
        period_end: str,
        setup_plan_min: float | None = None,
    ) -> dict[str, Any]:
        op_value = str(op or "").strip()
        start = self._parse_date(period_start)
        end = self._parse_date(period_end)

        if op_value == "" or start is None or end is None:
            raise ValueError("Parametros invalidos para o detalhe da OP.")

        if start > end:
            start, end = end, start

        start_date = start.strftime("%Y-%m-%d")
        end_date = end.strftime("%Y-%m-%d")

        loaded = self.realizado_repository.list_detail_rows_by_op_period(op_value, start_date, end_date)
        excel_rows = list(loaded.get("excel_rows") or [])
        raw_rows = list(loaded.get("raw_rows") or [])
        detail_source = str(loaded.get("detail_source") or "realizado_2026_excel")
        raw_table_available = bool(loaded.get("raw_table_available"))
        programacao = self.programacao_repository.get_programacao_by_op(op_value)
        detail_line_code = str((programacao or {}).get("lin_codigo") or (programacao or {}).get("linha_excel_dominante") or "").strip()
        detail_calendar = self._build_work_calendar(detail_line_code)

        setup_reference_minutes = self._to_float(setup_plan_min) if setup_plan_min is not None else None
        principal: list[dict[str, Any]] = []
        apoio: list[dict[str, Any]] = []
        grouped_paradas: dict[str, dict[str, Any]] = {}
        support_named_counts: dict[str, int] = {}
        has_other_named_paradas = False
        turnos = {
            "disponivel": False,
            "adm": 0.0,
            "noite": 0.0,
            "total": 0.0,
            "origem": "sem_calendario" if detail_calendar is None else "realizado_2026_excel",
        }
        if detail_calendar is not None:
            turnos = self._split_turnos_from_intervals(
                detail_calendar,
                self.realizado_repository.list_intervals_by_op_period(op_value, start_date, end_date),
            )
        elif raw_rows:
            inferred_line_code = self._infer_line_code_from_raw_rows(raw_rows)
            if inferred_line_code:
                detail_calendar = self._build_work_calendar(inferred_line_code)
                if detail_calendar is not None:
                    turnos = self._split_turnos_from_intervals(
                        detail_calendar,
                        self.realizado_repository.list_intervals_by_op_period(op_value, start_date, end_date),
                    )
        codi = self._build_codi_efficiency_from_raw_rows(raw_rows if raw_table_available else [])

        summary = {
            "rows_total": 0,
            "raw_rows_total": int(loaded.get("raw_rows_total") or 0),
            "principal_rows": 0,
            "apoio_rows": 0,
            "principal_events": 0,
            "apoio_events": 0,
            "principal_minutes": 0.0,
            "apoio_minutes": 0.0,
            "turnos": turnos,
            "codi": codi,
        }

        for row in excel_rows:
            nome_parada = str(row.get("parada_nomeParada") or "")
            if not self._is_setup_target_parada(nome_parada):
                continue

            duration = self._to_float(row.get("setup_duracao_minutos") or row.get("duracao_evento_minutos"))
            events = self._to_int(row.get("setup_eventos_count"))
            data_evento = str(row.get("data_evento") or "")

            principal.append(
                {
                    "data_evento": data_evento,
                    "inicio_evento": str(row.get("inicio_evento") or ""),
                    "fim_evento": str(row.get("fim_evento") or ""),
                    "parada_nomeParada": self._format_nullable_event_text(nome_parada),
                    "parada_tipo_nome": "Setup principal",
                    "setup_referencia": self._format_minutes_clock(setup_reference_minutes if setup_reference_minutes is not None else duration),
                    "setup_referencia_detail": "Setup previsto",
                    "setup_duracao_minutos": duration,
                    "setup_eventos_count": events,
                    "quantidade": self._to_float(row.get("quantidade")),
                    "tipo_bloco": "setup_principal",
                    "origem": "agregado",
                }
            )
            summary["principal_rows"] += 1
            summary["principal_events"] += events if events > 0 else 1
            summary["principal_minutes"] += duration

            group_key = self._parada_group_key(nome_parada)
            if group_key not in grouped_paradas:
                grouped_paradas[group_key] = {
                    "parada_nomeParada": self._normalize_parada_label(nome_parada),
                    "eventos_count": 0,
                    "duracao_total_minutos": 0.0,
                    "is_principal": True,
                    "is_null": str(nome_parada).strip() == "",
                    "categoria_label": "Setup principal",
                }
            grouped_paradas[group_key]["eventos_count"] += 1
            grouped_paradas[group_key]["duracao_total_minutos"] += duration

        if raw_table_available:
            for row in raw_rows:
                nome_parada = str(row.get("parada_nomeParada") or "")
                if self._is_setup_target_parada(nome_parada):
                    continue

                if not self._is_visible_complementary_parada(nome_parada):
                    continue

                duration = self._to_float(row.get("duracao_evento_minutos") or row.get("setup_duracao_minutos"))
                apoio.append(
                    {
                        "data_evento": str(row.get("data_evento") or ""),
                        "inicio_evento": str(row.get("inicio_evento") or ""),
                        "fim_evento": str(row.get("fim_evento") or ""),
                        "parada_nomeParada": self._format_nullable_event_text(nome_parada),
                        "parada_tipo_nome": self._format_nullable_event_text(str(row.get("parada_tipo_nome") or "")),
                        "setup_referencia": "Contexto complementar",
                        "setup_referencia_detail": "Somente contexto complementar",
                        "setup_duracao_minutos": 0.0,
                        "duracao_evento_minutos": duration,
                        "setup_eventos_count": self._to_int(row.get("setup_eventos_count")),
                        "quantidade": self._to_float(row.get("quantidade")),
                        "tipo_bloco": "apoio",
                        "origem": "bruto",
                        "codigo_evento": str(row.get("evt_codigo_evento") or ""),
                    }
                )
                summary["apoio_rows"] += 1
                summary["apoio_events"] += 1
                summary["apoio_minutes"] += duration

                group_key = self._parada_group_key(nome_parada)
                if group_key not in grouped_paradas:
                    grouped_paradas[group_key] = {
                        "parada_nomeParada": self._normalize_parada_label(nome_parada),
                        "eventos_count": 0,
                        "duracao_total_minutos": 0.0,
                        "is_principal": False,
                        "is_null": str(nome_parada).strip() == "",
                        "categoria_label": "Sem classificacao" if str(nome_parada).strip() == "" else "Paradas complementares",
                    }
                grouped_paradas[group_key]["eventos_count"] += 1
                grouped_paradas[group_key]["duracao_total_minutos"] += duration

                if str(nome_parada).strip() != "":
                    has_other_named_paradas = True
                    support_named_counts[nome_parada] = support_named_counts.get(nome_parada, 0) + 1
        else:
            for row in excel_rows:
                nome_parada = str(row.get("parada_nomeParada") or "")
                if self._is_setup_target_parada(nome_parada):
                    continue

                if not self._is_visible_complementary_parada(nome_parada):
                    continue

                duration = self._to_float(row.get("setup_duracao_minutos") or row.get("duracao_evento_minutos"))
                events = self._to_int(row.get("setup_eventos_count"))
                apoio.append(
                    {
                        "data_evento": str(row.get("data_evento") or ""),
                        "inicio_evento": str(row.get("inicio_evento") or ""),
                        "fim_evento": str(row.get("fim_evento") or ""),
                        "parada_nomeParada": self._format_nullable_event_text(nome_parada),
                        "parada_tipo_nome": "Sem detalhe bruto",
                        "setup_referencia": "Contexto complementar",
                        "setup_referencia_detail": "Somente contexto complementar",
                        "setup_duracao_minutos": duration,
                        "setup_eventos_count": events,
                        "quantidade": self._to_float(row.get("quantidade")),
                        "tipo_bloco": "apoio",
                        "origem": "agregado",
                    }
                )
                summary["apoio_rows"] += 1
                summary["apoio_events"] += events if events > 0 else 0
                summary["apoio_minutes"] += duration

                group_key = self._parada_group_key(nome_parada)
                if group_key not in grouped_paradas:
                    grouped_paradas[group_key] = {
                        "parada_nomeParada": self._normalize_parada_label(nome_parada),
                        "eventos_count": 0,
                        "duracao_total_minutos": 0.0,
                        "is_principal": False,
                        "is_null": str(nome_parada).strip() == "",
                        "categoria_label": "Sem classificacao" if str(nome_parada).strip() == "" else "Paradas complementares",
                    }
                grouped_paradas[group_key]["eventos_count"] += 1
                grouped_paradas[group_key]["duracao_total_minutos"] += duration

                if str(nome_parada).strip() != "":
                    has_other_named_paradas = True
                    support_named_counts[nome_parada] = support_named_counts.get(nome_parada, 0) + 1

        summary["rows_total"] = summary["principal_rows"] + summary["apoio_rows"]

        grouped_rows = list(grouped_paradas.values())
        grouped_rows.sort(
            key=lambda item: (
                -self._to_float(item.get("duracao_total_minutos")),
                -self._to_int(item.get("eventos_count")),
                str(item.get("parada_nomeParada") or ""),
            )
        )

        return {
            "success": True,
            "op": op_value,
            "period_start": start_date,
            "period_end": end_date,
            "setup_plan_min": setup_reference_minutes,
            "main_rule": "TROCA DE KIT / TROCA DE LIQUIDO",
            "detail_source": detail_source,
            "has_other_named_paradas": has_other_named_paradas,
            "support_named_paradas": support_named_counts,
            "turnos": turnos,
            "codi": codi,
            "summary": summary,
            "principal": principal,
            "apoio": apoio,
            "paradas_agrupadas": grouped_rows,
        }

    def _build_detalhe_operacional(
        self,
        operations: list[dict[str, Any]],
        programacao: dict[str, Any],
    ) -> dict[str, Any]:
        focus = operations[0] if operations else {}
        op_focus = str(focus.get("op") or programacao.get("prg_numero_op") or "")
        turnos = {
            "adm": round(sum(self._to_float(item.get("turnos", {}).get("adm")) for item in operations), 2),
            "noite": round(sum(self._to_float(item.get("turnos", {}).get("noite")) for item in operations), 2),
            "total": round(sum(self._to_float(item.get("turnos", {}).get("total")) for item in operations), 2),
        }
        codi_values = [
            self._to_float((item.get("codi") or {}).get("eficiencia_pct"))
            for item in operations
            if isinstance(item.get("codi"), dict) and (item.get("codi") or {}).get("disponivel")
        ]
        codi = {
            "ops_com_codi": len(codi_values),
            "eficiencia_media_pct": round(sum(codi_values) / len(codi_values), 2) if codi_values else None,
        }
        return {
            "op_foco": op_focus or None,
            "disponivel": False,
            "principal": [],
            "apoio": [],
            "agrupamento_paradas": [],
            "turnos": turnos,
            "codi": codi,
            "observacao": "Detalhe operacional completo ainda nao portado nesta etapa.",
            "fonte": "relgantt_base",
        }

    def _build_timeline_from_operacoes(self, operations: list[dict[str, Any]]) -> list[dict[str, Any]]:
        timeline: list[dict[str, Any]] = []
        for item in operations:
            op = str(item.get("op") or "S/OP")
            if op == "S/OP":
                continue

            prod_info = item.get("producao") or {}
            tempo_info = item.get("tempo") or {}
            setup_info = item.get("setup") or {}
            status_info = item.get("status_operacional") or {}
            line_info = item.get("linha") or {}
            kpis_info = item.get("kpis") or {}

            planned_start = str(item.get("start_date") or "")
            planned_end = str(item.get("end_date") or "")
            setup_start = str(item.get("setup_start") or "")
            setup_end = str(item.get("setup_end") or "")
            realized_start = item.get("realizado_inicio")
            realized_end = item.get("realizado_fim")

            timeline.append(
                {
                    "id": f"{item.get('programacao_id')}:{op}",
                    "text": f"OP {op}\n{str(item.get('descricao_produto') or '-').strip()}",
                    "descricao_produto": str(item.get("descricao_produto") or "-").strip(),
                    "start_date": self._format_task_date(planned_start),
                    "end_date": self._format_task_date(planned_end),
                    "color": "#3b82f6",
                    "progress": 1,
                    "open": True,
                    "sku": str(item.get("sku") or "-").strip() or "-",
                    "tipo": "producao",
                    "tipo_original": "operacao",
                    "op": op,
                    "programacao_id": item.get("programacao_id"),
                    "line_label": line_info.get("label") or "S/linha",
                    "line_key": line_info.get("key") or "",
                    "memoria_calculo": str(item.get("memoria_calculo") or ""),
                    "quantidade_prevista": self._to_float(prod_info.get("prevista")),
                    "quantidade_realizada": self._to_float(prod_info.get("realizada")),
                    "realizado_inicio": self._format_task_date(realized_start) if realized_start else "",
                    "realizado_fim": self._format_task_date(realized_end) if realized_end else "",
                    "setup_start": self._format_task_date(setup_start) if setup_start else "",
                    "setup_end": self._format_task_date(setup_end) if setup_end else "",
                    "percentual_cumprimento": self._to_float(kpis_info.get("percentual_cumprimento")),
                    "status": "done"
                    if self._to_float(prod_info.get("realizada")) >= self._to_float(prod_info.get("prevista"))
                    and self._to_float(prod_info.get("realizada")) > 0
                    else ("running" if self._to_float(prod_info.get("realizada")) > 0 else "planned"),
                    "setup": setup_info,
                    "producao": prod_info,
                    "tempo": tempo_info,
                    "status_operacional": status_info,
                    "kpis": kpis_info,
                    "has_setup": bool(item.get("has_setup")),
                    "setup_count": self._to_int(item.get("setup_count")),
                    "setup_realizado_eventos": self._to_int(item.get("setup_realizado_eventos")),
                    "setup_memoria": str(item.get("setup_memoria") or ""),
                    "detalhe_operacional": item.get("detalhe_operacional") or {},
                    "late": bool(item.get("late")),
                    "divergent": bool(item.get("divergent")),
                    "no_realized": bool(item.get("no_realized")),
                }
            )

        return timeline

    @staticmethod
    def _build_legacy_metricas_alias(resumo: dict[str, Any]) -> dict[str, Any]:
        producao = resumo.get("producao") if isinstance(resumo.get("producao"), dict) else {}
        setup = resumo.get("setup") if isinstance(resumo.get("setup"), dict) else {}

        return {
            "total_linhas": resumo.get("ops", 0),
            "producoes": resumo.get("ops", 0),
            "setups": resumo.get("ops", 0) - (resumo.get("setup_status", {}).get("sem_setup", 0) if isinstance(resumo.get("setup_status"), dict) else 0),
            "total_previsto": producao.get("prevista", 0),
            "total_realizado": producao.get("realizada", 0),
            "diferenca": producao.get("desvio", 0),
            "percentual": producao.get("percentual", 0),
            "setup_previsto": setup.get("previsto_min", 0),
            "setup_realizado": setup.get("realizado_min", 0),
        }

    @staticmethod
    def _serialize_programacao(programacao: dict[str, Any], total_ops: int) -> dict[str, Any]:
        return {
            "id": int(programacao.get("prg_id") or 0),
            "numero": programacao.get("prg_numero_op"),
            "linha": programacao.get("lin_codigo") or "N/A",
            "linha_codigo": programacao.get("lin_codigo"),
            "linha_dominante_excel": programacao.get("linha_excel_dominante"),
            "base_inicio": programacao.get("prg_base_inicio"),
            "data_consulta": programacao.get("prg_data_consulta"),
            "eficiencia": programacao.get("prg_eficiencia"),
            "status": programacao.get("prg_status"),
            "total_itens": programacao.get("total_itens"),
            "total_ops": total_ops,
        }

    @staticmethod
    def _serialize_assignments(
        schedule_rows: list[dict[str, Any]],
        assignments: dict[int, str],
    ) -> list[dict[str, Any]]:
        serialized = []
        for row in schedule_rows:
            schedule_id = int(row.get("sch_id") or 0)
            serialized.append(
                {
                    "sch_id": schedule_id,
                    "sku": row.get("sch_sku"),
                    "op": assignments.get(schedule_id, "S/OP"),
                    "tipo": row.get("sch_tipo"),
                }
            )
        return serialized
