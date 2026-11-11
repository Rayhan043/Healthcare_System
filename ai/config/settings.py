from functools import lru_cache
from pathlib import Path

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    ai_api_key: str = "change-this-local-api-key"
    llm_provider: str = "mock"
    llm_api_key: str = ""
    llm_model: str = ""
    embedding_provider: str = "local"
    embedding_model: str = "all-MiniLM-L6-v2"
    vector_db_type: str = "chroma"
    mysql_host: str = "127.0.0.1"
    mysql_port: int = 3306
    mysql_database: str = "healthcare_appointment"
    mysql_user: str = "healthcare_ai_readonly"
    mysql_password: str = ""
    allowed_origins: str = "http://127.0.0.1:8000,http://localhost:8000"
    model_config = SettingsConfigDict(env_file=".env", env_prefix="", extra="ignore")

    @property
    def origins(self) -> list[str]:
        return [origin.strip() for origin in self.allowed_origins.split(",") if origin.strip()]

    @property
    def root_dir(self) -> Path:
        return Path(__file__).resolve().parents[1]


@lru_cache
def get_settings() -> Settings:
    return Settings()

