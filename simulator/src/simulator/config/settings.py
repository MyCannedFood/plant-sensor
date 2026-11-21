"""
Configuration settings for the simulator.
Uses Pydantic Settings for environment variable management.
"""

from pydantic_settings import BaseSettings, SettingsConfigDict
from pydantic import Field


class Settings(BaseSettings):
    """Simulator configuration loaded from environment variables and .env file."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        env_prefix="SIMULATOR_",
        case_sensitive=False,
        extra="ignore",
    )

    # Simulator defaults
    default_interval: int = Field(default=10, description="Minutes between reading batches")
    default_interval_unit: str = Field(default="minutes", description="Time unit for interval (minutes or seconds)")
    default_variance: float = Field(default=0.15, description="Drift factor (0.15 = ±15%)")

    # API Configuration
    api_url: str = Field(default="http://localhost", description="Base URL without /api (e.g., https://api.example.com)")
    verify_ssl: bool = Field(default=True, description="Verify SSL certificates for HTTPS")
    token_file: str | None = Field(default=None, description="Path to device tokens JSON file (SIMULATOR_TOKEN_FILE)")

    # Logging
    log_level: str = Field(default="INFO", description="Logging level (DEBUG, INFO, WARNING, ERROR)")

    @property
    def readings_endpoint(self) -> str:
        """Full readings API endpoint."""
        return f"{self.api_url.rstrip('/')}/api/readings"


settings = Settings()