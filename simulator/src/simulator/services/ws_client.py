"""
WebSocket client for real-time communication with the backend.
Handles connection, authentication, message broadcasting, and auto-reconnection.
"""

import asyncio
import json
from typing import Any, Callable, Optional

import websockets
from websockets.exceptions import ConnectionClosed, WebSocketException

from simulator.config.settings import settings


class WebSocketClient:
    """
    WebSocket client for real-time dashboard updates.

    Features:
    - Automatic reconnection with exponential backoff
    - Device token authentication
    - Message broadcasting for sensor readings
    - Subscription to plant/device events
    """

    def __init__(
        self,
        ws_url: str | None = None,
        device_token: str | None = None,
        device_id: int | None = None,
        auto_reconnect: bool = True,
        max_reconnect_attempts: int = 10,
        reconnect_base_delay: float = 1.0,
    ):
        self.ws_url = ws_url or settings.ws_url
        self.device_token = device_token
        self.device_id = device_id
        self.auto_reconnect = auto_reconnect
        self.max_reconnect_attempts = max_reconnect_attempts
        self.reconnect_base_delay = reconnect_base_delay

        self._websocket: Optional[websockets.WebSocketClientProtocol] = None
        self._connected = False
        self._connecting = False
        self._reconnect_task: Optional[asyncio.Task] = None
        self._message_handler: Optional[Callable[[dict], None]] = None

    @property
    def is_connected(self) -> bool:
        return self._connected and self._websocket is not None

    def set_message_handler(self, handler: Callable[[dict], None]) -> None:
        """Set callback for incoming messages."""
        self._message_handler = handler

    async def connect(self) -> bool:
        """
        Connect to the WebSocket server.

        Returns:
            True if connected successfully, False otherwise
        """
        if not self.ws_url:
            return False

        if self._connecting or self.is_connected:
            return True

        self._connecting = True

        try:
            # Build connection URL with auth
            url = self._build_connection_url()

            self._websocket = await websockets.connect(
                url,
                ping_interval=20,
                ping_timeout=10,
                close_timeout=5,
            )

            self._connected = True
            self._connecting = False

            # Start message listener
            asyncio.create_task(self._listen())

            # Send authentication if we have a token
            if self.device_token and self.device_id:
                await self._authenticate()

            return True

        except Exception as e:
            self._connecting = False
            self._connected = False
            return False

    def _build_connection_url(self) -> str:
        """Build WebSocket URL with query parameters."""
        url = self.ws_url
        params = []

        if self.device_token:
            params.append(f"token={self.device_token}")
        if self.device_id:
            params.append(f"device_id={self.device_id}")

        if params:
            separator = "&" if "?" in url else "?"
            url = f"{url}{separator}{'&'.join(params)}"

        return url

    async def _authenticate(self) -> None:
        """Send authentication message after connection."""
        auth_message = {
            "type": "auth",
            "device_token": self.device_token,
            "device_id": self.device_id,
        }
        await self.send(auth_message)

    async def _listen(self) -> None:
        """Listen for incoming messages."""
        try:
            async for message in self._websocket:
                try:
                    data = json.loads(message)
                    if self._message_handler:
                        self._message_handler(data)
                except json.JSONDecodeError:
                    pass  # Ignore invalid messages
        except ConnectionClosed:
            self._connected = False
            if self.auto_reconnect:
                await self._schedule_reconnect()
        except WebSocketException:
            self._connected = False
            if self.auto_reconnect:
                await self._schedule_reconnect()
        except Exception:
            self._connected = False
            if self.auto_reconnect:
                await self._schedule_reconnect()

    async def _schedule_reconnect(self) -> None:
        """Schedule a reconnection attempt with exponential backoff."""
        if self._reconnect_task and not self._reconnect_task.done():
            return

        self._reconnect_task = asyncio.create_task(self._reconnect())

    async def _reconnect(self) -> None:
        """Attempt to reconnect with exponential backoff."""
        for attempt in range(self.max_reconnect_attempts):
            delay = self.reconnect_base_delay * (2 ** attempt)
            await asyncio.sleep(delay)

            if await self.connect():
                return

        # Max attempts reached
        pass

    async def send(self, message: dict) -> bool:
        """
        Send a message over the WebSocket.

        Args:
            message: Dictionary to send as JSON

        Returns:
            True if sent successfully, False otherwise
        """
        if not self.is_connected or not self._websocket:
            return False

        try:
            await self._websocket.send(json.dumps(message))
            return True
        except Exception:
            self._connected = False
            return False

    async def broadcast_reading(self, reading: dict) -> bool:
        """
        Broadcast a sensor reading to the WebSocket.

        Args:
            reading: Sensor reading payload (same as REST API)

        Returns:
            True if broadcast successfully, False otherwise
        """
        message = {
            "type": "reading",
            "payload": reading,
        }
        return await self.send(message)

    async def subscribe(self, channel: str) -> bool:
        """
        Subscribe to a channel.

        Args:
            channel: Channel name (e.g., "plant:1", "device:5")

        Returns:
            True if subscribed successfully, False otherwise
        """
        message = {
            "type": "subscribe",
            "channel": channel,
        }
        return await self.send(message)

    async def unsubscribe(self, channel: str) -> bool:
        """Unsubscribe from a channel."""
        message = {
            "type": "unsubscribe",
            "channel": channel,
        }
        return await self.send(message)

    async def close(self) -> None:
        """Close the WebSocket connection."""
        self.auto_reconnect = False

        if self._reconnect_task:
            self._reconnect_task.cancel()
            try:
                await self._reconnect_task
            except asyncio.CancelledError:
                pass

        if self._websocket:
            await self._websocket.close()
            self._websocket = None

        self._connected = False


class MultiDeviceWebSocketManager:
    """
    Manages WebSocket connections for multiple devices.
    Useful when simulating multiple devices simultaneously.
    """

    def __init__(self, ws_url: str | None = None):
        self.ws_url = ws_url or settings.ws_url
        self._clients: dict[int, WebSocketClient] = {}

    async def add_device(self, device_id: int, token: str) -> WebSocketClient:
        """Add a device and connect its WebSocket."""
        client = WebSocketClient(
            ws_url=self.ws_url,
            device_token=token,
            device_id=device_id,
        )
        await client.connect()
        self._clients[device_id] = client
        return client

    async def broadcast_reading(self, device_id: int, reading: dict) -> bool:
        """Broadcast a reading for a specific device."""
        client = self._clients.get(device_id)
        if client:
            return await client.broadcast_reading(reading)
        return False

    async def close_all(self) -> None:
        """Close all WebSocket connections."""
        for client in self._clients.values():
            await client.close()
        self._clients.clear()

    def get_client(self, device_id: int) -> Optional[WebSocketClient]:
        """Get a specific device's WebSocket client."""
        return self._clients.get(device_id)