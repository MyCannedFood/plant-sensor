"""
HTTP client for communicating with the backend API.
Handles REST API calls with authentication, retries, and error handling.
"""

import asyncio
import json
from pathlib import Path

import httpx

from simulator.config.settings import settings
from simulator.models.sensor import validate_reading_payload


class TokenManager:
    """Manages device tokens loaded from JSON file."""

    def __init__(self, token_file: str | None = None):
        self.token_file = token_file or settings.token_file
        self._tokens: dict[str, dict] = {}
        self._load_tokens()

    def _load_tokens(self) -> None:
        """Load tokens from JSON file."""
        path = Path(self.token_file)
        if not path.exists():
            raise FileNotFoundError(
                f"Token file not found: {self.token_file}. "
                "Run `cd backend && ./vendor/bin/sail artisan db:seed` first."
            )

        with open(path) as f:
            data = json.load(f)

        # Support both formats: dict keyed by plant name or list
        if isinstance(data, dict):
            # Convert to serial_number -> token mapping for easier lookup
            for plant_name, info in data.items():
                if isinstance(info, dict) and "serial_number" in info and "token" in info:
                    self._tokens[info["serial_number"]] = {
                        "device_id": info.get("device_id"),
                        "serial_number": info["serial_number"],
                        "token": info["token"],
                        "owner": info.get("owner"),
                        "plant_name": plant_name,
                    }
        elif isinstance(data, list):
            for info in data:
                if isinstance(info, dict) and "serial_number" in info and "token" in info:
                    self._tokens[info["serial_number"]] = info

    def get_token(self, serial_number: str) -> dict | None:
        """Get token info for a device by serial number."""
        return self._tokens.get(serial_number)

    def get_all_tokens(self) -> list[dict]:
        """Get all token entries."""
        return list(self._tokens.values())

    def filter_by_serial(self, serial_number: str) -> list[dict]:
        """Filter tokens by serial number (exact match)."""
        if serial_number in self._tokens:
            return [self._tokens[serial_number]]
        return []


class ApiClient:
    """
    HTTP client for the plant sensor backend API.

    Handles:
    - Device authentication via X-Device-Token header
    - Reading submission with retry logic
    - Rate limit handling (429)
    - Conflict resolution (409)
    """

    def __init__(
        self,
        base_url: str | None = None,
        verify_ssl: bool | None = None,
        timeout: float = 30.0,
    ):
        self.base_url = base_url or settings.api_url
        self.verify_ssl = verify_ssl if verify_ssl is not None else settings.verify_ssl
        self.timeout = timeout
        self._client: httpx.AsyncClient | None = None
        self._token_manager = TokenManager()

    @property
    def client(self) -> httpx.AsyncClient:
        """Get or create the HTTP client."""
        if self._client is None:
            self._client = httpx.AsyncClient(
                base_url=self.base_url,
                verify=self.verify_ssl,
                timeout=self.timeout,
                headers={"User-Agent": "plant-sensor-simulator/0.1.0"},
            )
        return self._client

    async def close(self) -> None:
        """Close the HTTP client."""
        if self._client:
            await self._client.aclose()
            self._client = None

    def _get_headers(self, token: str) -> dict[str, str]:
        """Get headers with device authentication."""
        return {"X-Device-Token": token}

    async def submit_reading(
        self,
        token: str,
        payload: dict,
        max_retries: int = 3,
    ) -> tuple[int, dict]:
        """
        Submit a sensor reading to the API.

        Args:
            token: Device token (plaintext)
            payload: Reading payload with measured_at and sensor values
            max_retries: Maximum number of retry attempts

        Returns:
            Tuple of (status_code, response_json)

        Raises:
            httpx.HTTPError: On network or unrecoverable errors
        """
        # Validate payload locally first
        errors = validate_reading_payload(payload)
        if errors:
            raise ValueError(f"Invalid payload: {errors}")

        headers = self._get_headers(token)

        for attempt in range(max_retries + 1):
            try:
                response = await self.client.post(
                    "/api/readings",
                    json=payload,
                    headers=headers,
                )

                # Success cases
                if response.status_code in (200, 201):
                    return response.status_code, response.json()

                # Rate limited - respect Retry-After header
                if response.status_code == 429:
                    retry_after = response.headers.get("Retry-After")
                    wait_time = int(retry_after) if retry_after and retry_after.isdigit() else 60
                    if attempt < max_retries:
                        await asyncio.sleep(min(wait_time, 60))
                        continue
                    return response.status_code, {"error": "Rate limited", "retry_after": wait_time}

                # Conflict - idempotent, values match existing
                if response.status_code == 409:
                    return response.status_code, response.json()

                # Validation error - shouldn't happen if we validated locally
                if response.status_code == 422:
                    return response.status_code, response.json()

                # Other errors
                response.raise_for_status()

            except httpx.TimeoutException:
                if attempt < max_retries:
                    await asyncio.sleep(2 ** attempt)  # Exponential backoff
                    continue
                raise

            except httpx.RequestError:
                if attempt < max_retries:
                    await asyncio.sleep(2 ** attempt)
                    continue
                raise

        raise RuntimeError("Max retries exceeded")

    async def get_plant(self, plant_id: int, token: str) -> dict | None:
        """Get plant details including metrics (declared sensors)."""
        headers = self._get_headers(token)
        try:
            response = await self.client.get(f"/api/plants/{plant_id}", headers=headers)
            if response.status_code == 200:
                return response.json()
            return None
        except httpx.RequestError:
            return None

    async def get_recent_readings(
        self,
        plant_id: int,
        token: str,
        metric: str,
        range_: str = "24h",
    ) -> list[dict] | None:
        """Get recent readings for a specific metric."""
        headers = self._get_headers(token)
        try:
            response = await self.client.get(
                f"/api/plants/{plant_id}/readings",
                params={"metric": metric, "range": range_},
                headers=headers,
            )
            if response.status_code == 200:
                data = response.json()
                return data.get("points", [])
            return None
        except httpx.RequestError:
            return None

    async def health_check(self) -> bool:
        """Check if API is reachable."""
        try:
            response = await self.client.get("/api/ping")
            return response.status_code == 200
        except Exception:
            return False

    async def get_device_sensors(self, token: str) -> list[dict]:
        """Get the sensors declared for the authenticated device."""
        headers = self._get_headers(token)
        try:
            response = await self.client.get("/api/device/sensors", headers=headers)
            if response.status_code == 200:
                data = response.json()
                return data.get("sensors", [])
            return []
        except httpx.RequestError:
            return []


def load_tokens(token_file: str | None = None) -> dict[str, dict]:
    """Convenience function to load tokens synchronously."""
    manager = TokenManager(token_file)
    return manager._tokens