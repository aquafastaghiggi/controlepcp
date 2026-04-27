from collections import defaultdict
from typing import Any


class MappingService:
    """Resolve a relação SKU -> OP.

    Regra portada de:
    - `gantt.php`
    - `gantt2.php`
    - `api/sequenciamento_gantt.php`
    """

    def resolve_assignments(
        self,
        schedule_rows: list[dict[str, Any]],
        program_items: list[dict[str, Any]],
    ) -> dict[int, str]:
        buckets: dict[str, list[dict[str, Any]]] = defaultdict(list)

        for item in program_items:
            program_id = int(item.get("prg_programa_id") or 0)
            sku = str(item.get("prg_sku") or "").strip()
            if program_id <= 0 or sku == "":
                continue

            buckets[f"{program_id}|{sku}"].append(
                {
                    "op": str(item.get("prg_itens_op") or "S/OP"),
                    "quantidade": float(item.get("prg_quantidade") or 0),
                    "used": False,
                }
            )

        assignments: dict[int, str] = {}

        for row in schedule_rows:
            schedule_id = int(row.get("sch_id") or 0)
            program_id = int(row.get("sch_programa_id") or 0)
            sku = str(row.get("sch_sku") or "").strip()
            tipo = str(row.get("sch_tipo") or "").strip().lower()

            assignments[schedule_id] = "S/OP"
            if schedule_id <= 0 or tipo == "setup" or sku == "" or program_id <= 0:
                continue

            bucket = buckets.get(f"{program_id}|{sku}")
            if not bucket:
                continue

            quantidade_prevista = float(row.get("sch_quantidade") or 0)
            picked_idx = None

            for idx, item in enumerate(bucket):
                if item["used"]:
                    continue
                if abs(float(item["quantidade"]) - quantidade_prevista) < 0.0001:
                    picked_idx = idx
                    break

            if picked_idx is None:
                for idx, item in enumerate(bucket):
                    if not item["used"]:
                        picked_idx = idx
                        break

            if picked_idx is not None:
                assignments[schedule_id] = str(bucket[picked_idx]["op"] or "S/OP")
                bucket[picked_idx]["used"] = True

        return assignments
