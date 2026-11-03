"""
Simulate command - generates and sends sensor readings to the backend API.
"""

import asyncio
import signal
import sys
from datetime import datetime, timezone

import typer
from rich.console import Console
from rich.progress import Progress, SpinnerColumn, TextColumn, BarColumn, TaskProgressColumn
from rich.table import Table

from simulator.config.settings import settings
from simulator.services.http_client import ApiClient, TokenManager
from simulator.services.sensor_generator import SensorGenerator
from simulator.services.ws_client import MultiDeviceWebSocketManager

console = Console()


async def _run_simulation(
    device_filter: str | None,
    interval_minutes: int,
    duration_minutes: int,
    variance: float,
    dry_run: bool,
    once: bool,
    api_url: str | None,
    token_file: str | None,
    ws_url: str | None,
) -> int:
    """
    Run the simulation loop.

    Returns:
        Exit code (0 = success, 1 = error)
    """
    # Override settings with CLI args
    if api_url:
        settings.api_url = api_url
    if token_file:
        settings.token_file = token_file
    if ws_url:
        settings.ws_url = ws_url

    # Initialize components
    token_manager = TokenManager(settings.token_file)
    api_client = ApiClient(base_url=settings.api_url, verify_ssl=settings.verify_ssl)
    sensor_generator = SensorGenerator(variance=variance)
    ws_manager = MultiDeviceWebSocketManager(ws_url=settings.ws_url) if settings.ws_url else None

    # Get devices to simulate
    all_tokens = token_manager.get_all_tokens()

    if device_filter:
        devices = [d for d in all_tokens if d["serial_number"] == device_filter]
        if not devices:
            console.print(f"[red]No device found with serial number: {device_filter}[/red]")
            return 1
    else:
        devices = all_tokens

    if not devices:
        console.print("[red]No devices to simulate. Run db:seed first.[/red]")
        return 1

    console.print(f"[green]Starting simulation for {len(devices)} device(s)[/green]")
    console.print(f"  Interval: {interval_minutes} minutes")
    console.print(f"  Duration: {'infinite' if duration_minutes == 0 else f'{duration_minutes} minutes'}")
    console.print(f"  Variance: {variance * 100:.0f}%")
    console.print(f"  API: {settings.api_url}")
    if settings.ws_url:
        console.print(f"  WebSocket: {settings.ws_url}")
    if dry_run:
        console.print("[yellow]DRY RUN MODE - no data will be sent[/yellow]")
    console.print()

    # Connect WebSocket if configured
    if ws_manager:
        for device in devices:
            await ws_manager.add_device(device["device_id"], device["token"])
        console.print("[green]WebSocket connections established[/green]")

    # Test API connectivity
    if not dry_run:
        healthy = await api_client.health_check()
        if not healthy:
            console.print("[red]API health check failed. Is the backend running?[/red]")
            return 1
        console.print("[green]API health check passed[/green]")

    # Setup progress tracking
    total_readings = 0
    failed_readings = 0
    start_time = datetime.now(timezone.utc)

    # Signal handling for graceful shutdown
    shutdown_event = asyncio.Event()

    def signal_handler():
        console.print("\n[yellow]Shutdown signal received...[/yellow]")
        shutdown_event.set()

    loop = asyncio.get_running_loop()
    for sig in (signal.SIGINT, signal.SIGTERM):
        try:
            loop.add_signal_handler(sig, signal_handler)
        except NotImplementedError:
            # Windows doesn't support add_signal_handler
            pass

    # Main simulation loop
    interval_seconds = interval_minutes * 60
    end_time = start_time.timestamp() + (duration_minutes * 60) if duration_minutes > 0 else None

    with Progress(
        SpinnerColumn(),
        TextColumn("[progress.description]{task.description}"),
        BarColumn(),
        TaskProgressColumn(),
        console=console,
    ) as progress:
        task = progress.add_task("Simulating...", total=duration_minutes if duration_minutes > 0 else None)

        cycle = 0
        while not shutdown_event.is_set():
            cycle += 1

            # Check duration limit
            if end_time and datetime.now(timezone.utc).timestamp() >= end_time:
                break

            # Generate and send readings for each device
            for device in devices:
                if shutdown_event.is_set():
                    break

                device_id = device["device_id"]
                token = device["token"]
                serial = device["serial_number"]

                # Fetch device sensors (uses device.auth middleware)
                declared_sensors = []
                sensors = await api_client.get_device_sensors(token)
                for sensor in sensors:
                    param = sensor.get("parameter")
                    if param:
                        declared_sensors.append(param)

                target_conditions = {}

                # Fallback: compute targets from recent readings
                if not target_conditions and not dry_run:
                    for param in declared_sensors:
                        readings = await api_client.get_recent_readings(device_id, token, param, "24h")
                        if readings:
                            target_conditions[param] = sum(r.get("value", 0) for r in readings) / len(readings)

                # Final fallback: use reasonable defaults
                if not target_conditions:
                    defaults = {
                        "co2": 600,
                        "temperature": 22.0,
                        "humidity": 55.0,
                        "ph": 6.5,
                        "ec": 1200,
                        "water_temperature": 22.0,
                        "light": 15000,
                        "water_level": 60,
                    }
                    for param in declared_sensors:
                        if param in defaults:
                            target_conditions[param] = defaults[param]

                # Generate reading
                reading = sensor_generator.generate_reading(declared_sensors, target_conditions)

                if dry_run:
                    # Just print the payload
                    _print_reading(serial, reading)
                    total_readings += 1
                else:
                    # Send via REST API
                    status, response = await api_client.submit_reading(token, reading)

                    if status in (200, 201):
                        total_readings += 1
                        _print_reading(serial, reading, status="OK")

                        # Also broadcast via WebSocket
                        if ws_manager:
                            await ws_manager.broadcast_reading(device_id, reading)
                    elif status == 409:
                        # Conflict - idempotent, values match
                        total_readings += 1
                        _print_reading(serial, reading, status="CONFLICT (idempotent)")
                    elif status == 429:
                        failed_readings += 1
                        console.print(f"[red]Rate limited: {serial}[/red]")
                    else:
                        failed_readings += 1
                        console.print(f"[red]Error {status} for {serial}: {response}[/red]")

            # Update progress
            if duration_minutes > 0:
                elapsed = (datetime.now(timezone.utc) - start_time).total_seconds() / 60
                progress.update(task, completed=min(elapsed, duration_minutes))
            else:
                progress.update(task, description=f"Cycle {cycle} | Sent: {total_readings} | Failed: {failed_readings}")

            # Check if we should run once and exit
            if once:
                break

            # Wait for next interval (or until shutdown)
            try:
                await asyncio.wait_for(shutdown_event.wait(), timeout=interval_seconds)
                break  # Shutdown requested
            except asyncio.TimeoutError:
                continue  # Normal interval elapsed

    # Summary
    console.print()
    _print_summary(total_readings, failed_readings, start_time)

    # Cleanup
    if ws_manager:
        await ws_manager.close_all()
    await api_client.close()

    return 0 if failed_readings == 0 else 1


def _print_reading(serial: str, reading: dict, status: str = "DRY") -> None:
    """Print a sensor reading to console."""
    timestamp = reading.get("measured_at", "")
    sensors = {k: v for k, v in reading.items() if k != "measured_at"}

    if sensors:
        sensor_str = ", ".join(f"{k}: {v}" for k, v in sensors.items())
    else:
        sensor_str = "(no sensors)"

    color = "green" if status == "OK" else "yellow" if status == "DRY" else "cyan" if status == "CONFLICT" else "red"
    console.print(f"[{color}]{status}[/{color}] {serial} | {timestamp} | {sensor_str}")


def _print_summary(total: int, failed: int, start_time: datetime) -> None:
    """Print simulation summary."""
    from datetime import timezone
    duration = datetime.now(timezone.utc) - start_time

    table = Table(title="Simulation Summary")
    table.add_column("Metric", style="cyan")
    table.add_column("Value", style="green")

    table.add_row("Total Readings", str(total))
    table.add_row("Failed", str(failed))
    table.add_row("Success Rate", f"{(total / (total + failed) * 100):.1f}%" if (total + failed) > 0 else "N/A")
    table.add_row("Duration", str(duration).split(".")[0])
    table.add_row("Avg Rate", f"{total / max(duration.total_seconds() / 60, 1):.1f} readings/min")

    console.print(table)


simulate_app = typer.Typer(help="Generate and send sensor readings")

@simulate_app.callback(invoke_without_command=True)
def simulate(
    ctx: typer.Context,
    device: str | None = typer.Option(None, "--device", "-d", help="Filter by device serial number"),
    interval: int = typer.Option(10, "--interval", "-i", help="Minutes between reading batches"),
    duration: int = typer.Option(0, "--duration", help="Total minutes to run (0 = infinite)"),
    variance: float = typer.Option(0.15, "--variance", "-v", help="Drift factor (0.15 = ±15%)"),
    dry_run: bool = typer.Option(False, "--dry-run", help="Print payloads without sending"),
    once: bool = typer.Option(False, "--once", help="Send one batch then exit"),
    api_url: str | None = typer.Option(None, "--api-url", help="Override API base URL"),
    token_file: str | None = typer.Option(None, "--token-file", help="Override token file path"),
    ws_url: str | None = typer.Option(None, "--ws-url", help="WebSocket URL for real-time updates"),
) -> None:
    """
    Generate and send sensor readings to the backend API.

    Examples:

    # Dry run to verify payloads
    simulator simulate --once --dry-run

    # Run for 1 hour with 5-minute intervals
    simulator simulate --interval 5 --duration 60

    # Run specific device with WebSocket
    simulator simulate --device ABC123DEF456 --ws-url ws://localhost

    # Staging environment
    SIMULATOR_API_URL=https://staging-api.example.com simulator simulate --once
    """
    if ctx.invoked_subcommand is None:
        # Run the async simulation
        exit_code = asyncio.run(_run_simulation(
            device_filter=device,
            interval_minutes=interval,
            duration_minutes=duration,
            variance=variance,
            dry_run=dry_run,
            once=once,
            api_url=api_url,
            token_file=token_file,
            ws_url=ws_url,
        ))
        sys.exit(exit_code)