from typing import Any

from sqlalchemy import text
from sqlalchemy.orm import Session


class RealizadoRepository:
    """Leitura do realizado local.

    Regra portada de:
    - `gantt.php`
    - `gantt2.php`
    """

    def __init__(self, session: Session):
        self.session = session
        self._real_cols_cache: dict[str, list[str]] = {}

    def table_exists(self, table_name: str) -> bool:
        try:
            row = self.session.execute(
                text(
                    "SELECT COUNT(*) "
                    "FROM INFORMATION_SCHEMA.TABLES "
                    "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName"
                ),
                {"tableName": table_name},
            ).scalar_one()
            return int(row or 0) > 0
        except Exception:
            return False

    def _discover_columns(self, table_name: str = "realizado_2026_excel") -> list[str]:
        if table_name in self._real_cols_cache:
            return self._real_cols_cache[table_name]

        try:
            rows = self.session.execute(text(f"SHOW COLUMNS FROM `{table_name}`")).all()
            self._real_cols_cache[table_name] = [str(row[0]) for row in rows]
        except Exception:
            self._real_cols_cache[table_name] = []

        return self._real_cols_cache[table_name]

    @staticmethod
    def _pick_first_existing(columns: list[str], candidates: list[str]) -> str | None:
        available = set(columns)
        for candidate in candidates:
            if candidate in available:
                return candidate
        return None

    @staticmethod
    def _wrap_column(column_name: str) -> str:
        return f"`{column_name}`"

    def _row_duration_expr(self, col_duracao: str | None, col_inicio: str | None, col_fim: str | None) -> str:
        if col_duracao and col_inicio and col_fim:
            return (
                f"COALESCE({self._wrap_column(col_duracao)}, "
                f"CASE WHEN {self._wrap_column(col_inicio)} IS NOT NULL "
                f"AND {self._wrap_column(col_fim)} IS NOT NULL "
                f"AND LENGTH(TRIM({self._wrap_column(col_inicio)})) > 0 "
                f"AND LENGTH(TRIM({self._wrap_column(col_fim)})) > 0 "
                f"THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, {self._wrap_column(col_inicio)}, {self._wrap_column(col_fim)})) "
                f"ELSE 0 END)"
            )

        if col_duracao:
            return f"COALESCE({self._wrap_column(col_duracao)}, 0)"

        if col_inicio and col_fim:
            return (
                f"CASE WHEN {self._wrap_column(col_inicio)} IS NOT NULL "
                f"AND {self._wrap_column(col_fim)} IS NOT NULL "
                f"AND LENGTH(TRIM({self._wrap_column(col_inicio)})) > 0 "
                f"AND LENGTH(TRIM({self._wrap_column(col_fim)})) > 0 "
                f"THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, {self._wrap_column(col_inicio)}, {self._wrap_column(col_fim)})) "
                f"ELSE 0 END"
            )

        return "0"

    def _setup_condition(self, col_parada: str | None, col_estado: str | None, col_setup_duracao: str | None) -> str:
        if col_parada:
            return (
                f"UPPER(TRIM(COALESCE({self._wrap_column(col_parada)}, ''))) IN "
                "('TROCA DE KIT', 'TROCA DE LIQUIDO')"
            )

        if col_estado:
            return f"UPPER(TRIM(COALESCE({self._wrap_column(col_estado)}, ''))) = 'SETUP'"

        if col_setup_duracao:
            return f"COALESCE({self._wrap_column(col_setup_duracao)}, 0) > 0"

        return "0 = 1"

    def aggregate_by_op_period(self, op: str, start_date: str, end_date: str) -> dict[str, Any]:
        columns = self._discover_columns()
        col_qty = self._pick_first_existing(columns, ["quantidade", "qtd", "qtde", "quantidade_produzida"])
        col_op = self._pick_first_existing(columns, ["ordem_op", "op", "ordem"])
        col_date = self._pick_first_existing(columns, ["data_evento", "data", "data_apontamento", "data_hora"])
        col_inicio = self._pick_first_existing(columns, ["inicio_evento", "inicio", "data_inicio", "inicio_apontamento", "dt_inicio", "inicio_real"])
        col_fim = self._pick_first_existing(columns, ["fim_evento", "fim", "data_fim", "fim_apontamento", "dt_fim", "fim_real"])
        col_duracao = self._pick_first_existing(columns, ["duracao_evento_minutos", "duracao_minutos", "tempo_minutos", "duracao"])
        col_setup_duracao = self._pick_first_existing(columns, ["setup_duracao_minutos", "setup_minutos"])
        col_setup_eventos = self._pick_first_existing(columns, ["setup_eventos_count", "setup_eventos"])
        col_parada = self._pick_first_existing(columns, ["parada_nomeParada", "nome_parada", "parada"])
        col_estado = self._pick_first_existing(columns, ["estado_evento", "estado", "tipo_evento"])

        if not (col_qty and col_op and col_date):
            return {
                "total": 0.0,
                "tempo_realizado_min": 0.0,
                "setup_realizado_min": 0.0,
                "setup_realizado_eventos": 0,
                "inicio_real": None,
                "fim_real": None,
            }

        row_duration_expr = self._row_duration_expr(col_duracao, col_inicio, col_fim)
        setup_condition = self._setup_condition(col_parada, col_estado, col_setup_duracao)

        expr_inicio = (
            f"MIN(CASE WHEN {self._wrap_column(col_inicio)} IS NOT NULL "
            f"AND LENGTH(TRIM({self._wrap_column(col_inicio)})) > 0 THEN {self._wrap_column(col_inicio)} ELSE NULL END)"
            if col_inicio
            else f"MIN(CASE WHEN {self._wrap_column(col_date)} IS NOT NULL THEN {self._wrap_column(col_date)} ELSE NULL END)"
        )
        expr_fim = (
            f"MAX(CASE WHEN {self._wrap_column(col_fim)} IS NOT NULL "
            f"AND LENGTH(TRIM({self._wrap_column(col_fim)})) > 0 THEN {self._wrap_column(col_fim)} ELSE NULL END)"
            if col_fim
            else f"MAX(CASE WHEN {self._wrap_column(col_date)} IS NOT NULL THEN {self._wrap_column(col_date)} ELSE NULL END)"
        )

        setup_duration_expr = (
            f"SUM(CASE WHEN {setup_condition} THEN COALESCE({self._wrap_column(col_setup_duracao)}, {row_duration_expr}) ELSE 0 END)"
            if col_setup_duracao
            else f"SUM(CASE WHEN {setup_condition} THEN {row_duration_expr} ELSE 0 END)"
        )

        setup_events_expr = (
            f"SUM(CASE WHEN {setup_condition} THEN COALESCE({self._wrap_column(col_setup_eventos)}, 1) ELSE 0 END)"
            if col_setup_eventos
            else f"SUM(CASE WHEN {setup_condition} THEN 1 ELSE 0 END)"
        )

        sql = (
            f"SELECT "
            f"SUM(COALESCE({self._wrap_column(col_qty)}, 0)) AS total, "
            f"SUM({row_duration_expr}) AS tempo_realizado_min, "
            f"{setup_duration_expr} AS setup_realizado_min, "
            f"{setup_events_expr} AS setup_realizado_eventos, "
            f"{expr_inicio} AS inicio_real, "
            f"{expr_fim} AS fim_real "
            f"FROM `realizado_2026_excel` "
            f"WHERE {self._wrap_column(col_op)} = :op "
            f"AND {self._wrap_column(col_date)} >= :start_date "
            f"AND {self._wrap_column(col_date)} <= :end_date"
        )

        row = self.session.execute(
            text(sql),
            {"op": op, "start_date": start_date, "end_date": end_date},
        ).mappings().first()

        if not row:
            return {
                "total": 0.0,
                "tempo_realizado_min": 0.0,
                "setup_realizado_min": 0.0,
                "setup_realizado_eventos": 0,
                "inicio_real": None,
                "fim_real": None,
            }

        return {
            "total": float(row["total"] or 0.0),
            "tempo_realizado_min": float(row["tempo_realizado_min"] or 0.0),
            "setup_realizado_min": float(row["setup_realizado_min"] or 0.0),
            "setup_realizado_eventos": int(row["setup_realizado_eventos"] or 0),
            "inicio_real": row["inicio_real"],
            "fim_real": row["fim_real"],
        }

    def list_intervals_by_op_period(self, op: str, start_date: str, end_date: str) -> list[dict[str, Any]]:
        columns = self._discover_columns()
        col_op = self._pick_first_existing(columns, ["ordem_op", "op", "ordem"])
        col_date = self._pick_first_existing(columns, ["data_evento", "data", "data_apontamento", "data_hora"])
        col_inicio = self._pick_first_existing(columns, ["inicio_evento", "inicio", "data_inicio", "inicio_apontamento", "dt_inicio", "inicio_real"])
        col_fim = self._pick_first_existing(columns, ["fim_evento", "fim", "data_fim", "fim_apontamento", "dt_fim", "fim_real"])
        col_qty = self._pick_first_existing(columns, ["quantidade", "qtd", "qtde", "quantidade_produzida"])

        if not (col_op and col_date and col_inicio and col_fim):
            return []

        sql = (
            f"SELECT {self._wrap_column(col_inicio)} AS inicio_evento, "
            f"{self._wrap_column(col_fim)} AS fim_evento, "
            f"COALESCE({self._wrap_column(col_qty)}, 0) AS quantidade "
            f"FROM `realizado_2026_excel` "
            f"WHERE {self._wrap_column(col_op)} = :op "
            f"AND {self._wrap_column(col_date)} >= :start_date "
            f"AND {self._wrap_column(col_date)} <= :end_date "
            f"AND {self._wrap_column(col_inicio)} IS NOT NULL "
            f"AND {self._wrap_column(col_fim)} IS NOT NULL "
            f"AND LENGTH(TRIM({self._wrap_column(col_inicio)})) > 0 "
            f"AND LENGTH(TRIM({self._wrap_column(col_fim)})) > 0 "
            f"ORDER BY {self._wrap_column(col_date)} ASC, {self._wrap_column(col_inicio)} ASC, {self._wrap_column(col_fim)} ASC"
        )

        rows = self.session.execute(
            text(sql),
            {"op": op, "start_date": start_date, "end_date": end_date},
        ).mappings().all()
        return [dict(row) for row in rows]

    def list_detail_rows_by_op_period(self, op: str, start_date: str, end_date: str) -> dict[str, Any]:
        excel_rows = self._list_rows_by_op_period("realizado_2026_excel", op, start_date, end_date)
        raw_rows: list[dict[str, Any]] = []
        raw_table_available = self.table_exists("realizado_2026_eventos")
        detail_source = "realizado_2026_eventos" if raw_table_available else "realizado_2026_excel"

        if raw_table_available:
            raw_rows = self._list_rows_by_op_period("realizado_2026_eventos", op, start_date, end_date)

        return {
            "detail_source": detail_source,
            "raw_table_available": raw_table_available,
            "excel_rows": excel_rows,
            "raw_rows": raw_rows,
            "raw_rows_total": len(raw_rows),
        }

    def _list_rows_by_op_period(self, table_name: str, op: str, start_date: str, end_date: str) -> list[dict[str, Any]]:
        columns = self._discover_columns(table_name)
        col_op = self._pick_first_existing(columns, ["ordem_op", "op", "ordem"])
        col_date = self._pick_first_existing(columns, ["data_evento", "data", "data_apontamento", "data_hora"])
        col_inicio = self._pick_first_existing(columns, ["inicio_evento", "inicio", "data_inicio", "inicio_apontamento", "dt_inicio", "inicio_real"])
        col_fim = self._pick_first_existing(columns, ["fim_evento", "fim", "data_fim", "fim_apontamento", "dt_fim", "fim_real"])

        if not (col_op and col_date):
            return []

        order_by = [self._wrap_column(col_date)]
        if col_inicio:
            order_by.append(self._wrap_column(col_inicio))
        if col_fim:
            order_by.append(self._wrap_column(col_fim))

        sql = (
            f"SELECT * FROM `{table_name}` "
            f"WHERE {self._wrap_column(col_op)} = :op "
            f"AND {self._wrap_column(col_date)} BETWEEN :start_date AND :end_date "
            f"ORDER BY {', '.join(order_by)}"
        )

        rows = self.session.execute(
            text(sql),
            {"op": op, "start_date": start_date, "end_date": end_date},
        ).mappings().all()
        return [dict(row) for row in rows]
