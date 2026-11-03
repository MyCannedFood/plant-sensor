"""
Configuration settings for the simulator.
Uses Pydantic Settings for environment variable management.
"""

from pydantic_settings import BaseSettings, SettingsConfigDict
from pydantic import Field
from typing import Optional


class Settings(BaseSettings):
    """Simulator configuration loaded from environment variables and .env file."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore",
    )

    # Simulator defaults
    default_interval: int = Field(default=10, description="Minutes between reading batches")
    default_variance: float = Field(default=0.15, description="Drift factor (0.15 = ±15%)")

    # API Configuration
    api_url: str = Field(default="http://localhost", description="Base URL without /api (e.g., https://api.example.com)")
    token_file: str = Field(default="../backend/storage/app/private/device-tokens.json", description="Path to device tokens JSON file")
    verify_ssl: bool = Field(default=True, description="Verify SSL certificates for HTTPS")

    # WebSocket (optional, for real-time updates)
    ws_url: Optional[str] = Field(default=None, description="WebSocket URL for real-time updates (e.g., wss://api.example.com/ws)")

    # Logging
    log_level: str = Field(default="INFO", description="Logging level (DEBUG, INFO, WARNING, ERROR)")

    @property
    def readings_endpoint(self) -> str:
        """Full readings API endpoint."""
        return f"{self.api_url.rstrip('/')}/api/readings"


settings = Settings()