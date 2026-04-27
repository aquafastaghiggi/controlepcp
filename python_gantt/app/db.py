from functools import lru_cache

from sqlalchemy import create_engine
from sqlalchemy.orm import DeclarativeBase, Session, sessionmaker

from .config import settings


class Base(DeclarativeBase):
    pass


@lru_cache(maxsize=1)
def get_engine():
    return create_engine(settings.sqlalchemy_url, pool_pre_ping=True, future=True)


SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=get_engine(), future=True)


def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
