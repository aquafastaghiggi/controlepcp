from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta
from typing import Any


@dataclass(frozen=True)
class CalendarInterval:
    start: str
    end: str
    days: list[int]
    order: int


class WorkCalendar:
    def __init__(
        self,
        intervals: list[dict[str, Any]],
        working_days: list[int] | None = None,
        holidays: list[dict[str, Any] | str] | None = None,
    ) -> None:
        if not intervals:
            raise ValueError("Nenhum intervalo de trabalho cadastrado.")

        self.intervals = intervals
        self.working_days = working_days or [1, 2, 3, 4, 5]
        self.holidays = holidays or []

    @staticmethod
    def _parse_datetime(value: datetime | str) -> datetime:
        if isinstance(value, datetime):
            return value

        raw = str(value or "").strip()
        if raw == "":
            raise ValueError("Data/hora invalida.")

        raw = raw.replace("T", " ")
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
                return datetime.strptime(raw, fmt)
            except ValueError:
                continue

        return datetime.fromisoformat(raw)

    @staticmethod
    def _minutes_between(start: datetime, end: datetime) -> int:
        if end <= start:
            return 0
        return int((end - start).total_seconds() // 60)

    def _holiday_date_keys(self) -> list[str]:
        keys: list[str] = []
        for holiday in self.holidays:
            if isinstance(holiday, dict):
                date_value = str(holiday.get("date") or "").strip()
            else:
                date_value = str(holiday).strip()
            if date_value:
                keys.append(date_value)
        return list(dict.fromkeys(keys))

    def _is_calendar_open_for_day(self, day: datetime) -> bool:
        return day.strftime("%Y-%m-%d") not in self._holiday_date_keys()

    def _is_interval_allowed_for_day(self, interval: dict[str, Any], day: datetime) -> bool:
        if not self._is_calendar_open_for_day(day):
            return False

        interval_days = interval.get("days") or self.working_days
        allowed_days = [int(value) for value in interval_days if 1 <= int(value) <= 7]
        return day.isoweekday() in allowed_days

    def _interval_instances_for_day(self, day: datetime) -> list[dict[str, datetime]]:
        if not self._is_calendar_open_for_day(day):
            return []

        instances: list[dict[str, datetime]] = []
        for index, interval in enumerate(self.intervals):
            if not self._is_interval_allowed_for_day(interval, day):
                continue

            start_raw = str(interval.get("start") or "").strip()
            end_raw = str(interval.get("end") or "").strip()
            if start_raw == "" or end_raw == "":
                continue

            start_hour, start_minute = [int(part) for part in start_raw.split(":")[:2]]
            end_hour, end_minute = [int(part) for part in end_raw.split(":")[:2]]

            start = day.replace(hour=start_hour, minute=start_minute, second=0, microsecond=0)
            end = day.replace(hour=end_hour, minute=end_minute, second=0, microsecond=0)

            if end <= start:
                next_day = day + timedelta(days=1)
                end = next_day.replace(hour=end_hour, minute=end_minute, second=0, microsecond=0)
                if next_day.isoweekday() == 7 or not self._is_calendar_open_for_day(next_day):
                    end = next_day.replace(hour=0, minute=0, second=0, microsecond=0)

            instances.append({"start": start, "end": end, "order": index + 1})

        instances.sort(key=lambda item: item["start"])
        return instances

    def _find_current_interval(self, date_time: datetime) -> dict[str, datetime] | None:
        previous_day = (date_time.replace(hour=0, minute=0, second=0, microsecond=0) - timedelta(days=1))
        for interval in self._interval_instances_for_day(previous_day):
            if interval["start"] <= date_time < interval["end"]:
                return interval

        current_day = date_time.replace(hour=0, minute=0, second=0, microsecond=0)
        for interval in self._interval_instances_for_day(current_day):
            if interval["start"] <= date_time < interval["end"]:
                return interval

        return None

    def next_valid_datetime(self, date_time: datetime) -> datetime:
        current_interval = self._find_current_interval(date_time)
        if current_interval is not None:
            return date_time

        for offset in range(31):
            day = date_time.replace(hour=0, minute=0, second=0, microsecond=0) + timedelta(days=offset)
            if not self._interval_instances_for_day(day):
                continue

            for interval in self._interval_instances_for_day(day):
                if date_time < interval["start"]:
                    return interval["start"]

        raise ValueError("Nao foi possivel encontrar o proximo horario valido.")

    def working_minutes_between(self, start: datetime, end: datetime) -> int:
        if end <= start:
            return 0

        minutes = 0
        cursor = start

        while cursor < end:
            valid_cursor = self.next_valid_datetime(cursor)
            if valid_cursor >= end:
                break

            interval = self._find_current_interval(valid_cursor)
            if interval is None:
                break

            segment_end = interval["end"] if interval["end"] < end else end
            minutes += self._minutes_between(valid_cursor, segment_end)
            cursor = segment_end

        return minutes

    def consolidate_intervals(self, intervals: list[dict[str, datetime]]) -> list[dict[str, datetime]]:
        if not intervals:
            return []

        ordered = sorted(intervals, key=lambda item: (item["start"], item["end"]))
        merged = [ordered[0].copy()]

        for interval in ordered[1:]:
            current = merged[-1]
            if interval["start"] <= current["end"]:
                if interval["end"] > current["end"]:
                    current["end"] = interval["end"]
                continue
            merged.append(interval.copy())

        return merged
