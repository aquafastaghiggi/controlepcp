from functools import lru_cache
from typing import Any

from sqlalchemy import text
from sqlalchemy.orm import Session


class ProgramacaoRepository:
    """Leitura da programação atual.

    Regra portada de:
    - `src/Repository/ProgramacaoRepository.php`
    - `gantt.php`
    - `gantt2.php`
    """

    def __init__(self, session: Session):
        self.session = session

    def _products_has_excel_line_column(self) -> bool:
        try:
            count = self.session.execute(
                text(
                    "SELECT COUNT(*) "
                    "FROM INFORMATION_SCHEMA.COLUMNS "
                    "WHERE TABLE_SCHEMA = DATABASE() "
                    "AND TABLE_NAME = 'prd_produtos' "
                    "AND COLUMN_NAME = 'prd_linha_excel'"
                )
            ).scalar_one()
            return int(count or 0) > 0
        except Exception:
            return False

    def _dominant_excel_line_expr(self, program_alias: str = "p", line_alias: str = "l") -> str:
        if not self._products_has_excel_line_column():
            return "NULL AS linha_excel_dominante"

        program_id_expr = f"{program_alias}.prg_id"
        fallback = f"{line_alias}.lin_codigo"

        dominant_from_items = (
            "(SELECT pp.prd_linha_excel "
            " FROM prg_itens ii"
            " JOIN prd_produtos pp ON pp.prd_sku = ii.prg_sku"
            f" WHERE ii.prg_programa_id = {program_id_expr} AND pp.prd_linha_excel <> ''"
            " GROUP BY pp.prd_linha_excel"
            " ORDER BY COUNT(*) DESC, pp.prd_linha_excel ASC"
            " LIMIT 1)"
        )

        dominant_from_schedule = (
            "(SELECT pp.prd_linha_excel "
            " FROM sch_linhas ss"
            " JOIN prd_produtos pp ON pp.prd_sku = ss.sch_sku"
            f" WHERE ss.sch_programa_id = {program_id_expr} AND ss.sch_sku IS NOT NULL AND pp.prd_linha_excel <> ''"
            f" AND ss.sch_criado_em = (SELECT MAX(s2.sch_criado_em) FROM sch_linhas s2 WHERE s2.sch_programa_id = {program_id_expr})"
            " GROUP BY pp.prd_linha_excel"
            " ORDER BY COUNT(*) DESC, pp.prd_linha_excel ASC"
            " LIMIT 1)"
        )

        return (
            f"COALESCE(NULLIF({dominant_from_items}, ''), "
            f"NULLIF({dominant_from_schedule}, ''), {fallback}) AS linha_excel_dominante"
        )

    def list_programacoes(self, limit: int = 100, offset: int = 0, line_code: str | None = None) -> list[dict[str, Any]]:
        params: dict[str, Any] = {"limit": limit, "offset": offset}
        line_filter_sql = ""
        if line_code is not None and str(line_code).strip() != "":
            normalized_line = str(line_code).strip().replace(" ", "").upper()
            line_filter_sql = "WHERE REPLACE(UPPER(l.lin_codigo), ' ', '') = :lineCode "
            params["lineCode"] = normalized_line

        inicio_base_expr = (
            "(SELECT CONCAT(ss.sch_data_inicio, ' ', ss.sch_hora_inicio)"
            " FROM sch_linhas ss"
            " WHERE ss.sch_programa_id = p.prg_id"
            " AND ss.sch_criado_em = (SELECT MAX(s2.sch_criado_em) FROM sch_linhas s2 WHERE s2.sch_programa_id = p.prg_id)"
            " AND ss.sch_data_inicio IS NOT NULL"
            " AND ss.sch_hora_inicio IS NOT NULL"
            " ORDER BY ss.sch_sequencia ASC"
            " LIMIT 1) AS inicio_base_cronograma"
        )

        programacao_criada_expr = (
            "(SELECT MIN(ss.sch_criado_em)"
            " FROM sch_linhas ss"
            " WHERE ss.sch_programa_id = p.prg_id) AS programacao_criada_em"
        )

        sql = (
            "SELECT p.prg_id, p.prg_numero_op, p.prg_linha_id, l.lin_codigo, l.lin_nome, "
            f"{self._dominant_excel_line_expr('p', 'l')}, "
            f"{inicio_base_expr}, "
            f"{programacao_criada_expr}, "
            "p.prg_base_inicio, p.prg_data_consulta, p.prg_eficiencia, p.prg_status, "
            "p.prg_criado_em, p.prg_atualizado_em, "
            "COUNT(i.prg_id_item) AS total_itens "
            "FROM prg_programas p "
            "LEFT JOIN lin_linhas l ON p.prg_linha_id = l.lin_id "
            "LEFT JOIN prg_itens i ON p.prg_id = i.prg_programa_id "
            f"{line_filter_sql}"
            "GROUP BY p.prg_id "
            "ORDER BY p.prg_criado_em DESC "
            "LIMIT :limit OFFSET :offset"
        )

        rows = self.session.execute(text(sql), params).mappings().all()
        return [dict(row) for row in rows]

    def get_programacao_by_id(self, programacao_id: int) -> dict[str, Any] | None:
        sql = (
            "SELECT p.*, l.lin_codigo, l.lin_nome, "
            "(SELECT COUNT(*) FROM prg_itens i WHERE i.prg_programa_id = p.prg_id) AS total_itens, "
            f"{self._dominant_excel_line_expr('p', 'l')} "
            "FROM prg_programas p "
            "LEFT JOIN lin_linhas l ON p.prg_linha_id = l.lin_id "
            "WHERE p.prg_id = :id "
            "LIMIT 1"
        )

        row = self.session.execute(text(sql), {"id": programacao_id}).mappings().first()
        return dict(row) if row else None

    def get_programacao_by_op(self, op: str) -> dict[str, Any] | None:
        sql = (
            "SELECT p.*, l.lin_codigo, l.lin_nome, "
            "(SELECT COUNT(*) FROM prg_itens i WHERE i.prg_programa_id = p.prg_id) AS total_itens, "
            f"{self._dominant_excel_line_expr('p', 'l')} "
            "FROM prg_programas p "
            "LEFT JOIN lin_linhas l ON p.prg_linha_id = l.lin_id "
            "WHERE p.prg_numero_op = :op "
            "ORDER BY p.prg_criado_em DESC "
            "LIMIT 1"
        )

        row = self.session.execute(text(sql), {"op": op}).mappings().first()
        return dict(row) if row else None

    def list_programacao_itens(self, programacao_id: int) -> list[dict[str, Any]]:
        rows = self.session.execute(
            text(
                "SELECT * "
                "FROM prg_itens "
                "WHERE prg_programa_id = :programId "
                "ORDER BY prg_sequencia ASC, prg_id_item ASC"
            ),
            {"programId": programacao_id},
        ).mappings().all()
        return [dict(row) for row in rows]

    def list_programacao_schedule(self, programacao_id: int) -> list[dict[str, Any]]:
        rows = self.session.execute(
            text(
                "SELECT * "
                "FROM sch_linhas "
                "WHERE sch_programa_id = :programId "
                "ORDER BY sch_data_inicio ASC, sch_sequencia ASC, sch_id ASC"
            ),
            {"programId": programacao_id},
        ).mappings().all()
        return [dict(row) for row in rows]

    def get_line_by_code(self, line_code: str) -> dict[str, Any] | None:
        normalized = str(line_code or "").strip().replace(" ", "").upper()
        if normalized == "":
            return None

        row = self.session.execute(
            text(
                "SELECT l.lin_id, l.lin_codigo, l.lin_nome "
                "FROM lin_linhas l "
                "WHERE REPLACE(UPPER(l.lin_codigo), ' ', '') = :code "
                "LIMIT 1"
            ),
            {"code": normalized},
        ).mappings().first()
        return dict(row) if row else None

    def load_work_calendar_for_line(self, line_code: str) -> dict[str, Any] | None:
        line = self.get_line_by_code(line_code)
        if not line:
            return None

        calendar = self.session.execute(
            text("SELECT * FROM cal_calendarios WHERE cal_linha_id = :lineId ORDER BY cal_id LIMIT 1"),
            {"lineId": int(line["lin_id"])},
        ).mappings().first()
        if not calendar:
            return None

        calendar_id = int(calendar["cal_id"])
        interval_rows = self.session.execute(
            text("SELECT * FROM cal_intervalos WHERE cal_calendario_id = :calId ORDER BY cal_id"),
            {"calId": calendar_id},
        ).mappings().all()
        if not interval_rows:
            return None

        weekday_rows = self.session.execute(
            text("SELECT diu_intervalo_id, diu_dia_peq FROM cal_dias_uteis WHERE diu_intervalo_id IN (SELECT cal_id FROM cal_intervalos WHERE cal_calendario_id = :calId)"),
            {"calId": calendar_id},
        ).mappings().all()
        weekdays_by_interval: dict[int, list[int]] = {}
        for row in weekday_rows:
            interval_id = int(row["diu_intervalo_id"] or 0)
            weekday = int(row["diu_dia_peq"] or 0)
            if interval_id <= 0 or weekday <= 0:
                continue
            weekdays_by_interval.setdefault(interval_id, []).append(weekday)

        holiday_rows = self.session.execute(
            text("SELECT cal_data, cal_nome FROM cal_feriados WHERE cal_calendario_id = :calId ORDER BY cal_data"),
            {"calId": calendar_id},
        ).mappings().all()

        intervals: list[dict[str, Any]] = []
        for idx, interval in enumerate(interval_rows, start=1):
            interval_id = int(interval["cal_id"] or 0)
            intervals.append(
                {
                    "start": str(interval["cal_inicio"] or "")[:5],
                    "end": str(interval["cal_fim"] or "")[:5],
                    "days": weekdays_by_interval.get(interval_id, [1, 2, 3, 4, 5]),
                    "order": idx,
                }
            )

        holidays = []
        for row in holiday_rows:
            holidays.append(
                {
                    "date": row["cal_data"],
                    "name": row["cal_nome"],
                }
            )

        return {
            "line": line["lin_codigo"],
            "working_days": [1, 2, 3, 4, 5],
            "holidays": holidays,
            "intervals": intervals,
        }
