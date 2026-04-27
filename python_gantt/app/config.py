from dataclasses import dataclass
from urllib.parse import quote_plus
import os


@dataclass(frozen=True)
class Settings:
    app_name: str = os.getenv("PYTHON_GANTT_APP_NAME", "Python Gantt Paralelo")
    app_version: str = os.getenv("PYTHON_GANTT_APP_VERSION", "0.1.0")
    api_prefix: str = os.getenv("PYTHON_GANTT_API_PREFIX", "/api/v1")

    db_host: str = os.getenv("DB_HOST", "127.0.0.1")
    db_port: str = os.getenv("DB_PORT", "3306")
    db_name: str = os.getenv("DB_NAME", "controlepcp_sandbox")
    db_user: str = os.getenv("DB_USER", "root")
    db_password: str = os.getenv("DB_PASS", "")

    @property
    def sqlalchemy_url(self) -> str:
        user = quote_plus(self.db_user)
        password = quote_plus(self.db_password)
        return (
            f"mysql+pymysql://{user}:{password}"
            f"@{self.db_host}:{self.db_port}/{self.db_name}?charset=utf8mb4"
        )


settings = Settings()
