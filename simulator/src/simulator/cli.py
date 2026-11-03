"""
Main CLI entry point for the plant sensor simulator.
Uses Typer for command-line interface.
"""

import asyncio
import sys
from pathlib import Path

import typer
from rich.console import Console
from rich.logging import RichHandler

import logging

from simulator.commands.simulate import simulate_app
from simulator.config.settings import settings

app = typer.Typer(
    name="plant-sensor-simulator",
    help="IoT Plant Sensor Simulator - generates realistic sensor readings for ESP8266/ESP32 devices",
    add_completion=False,
    rich_markup_mode="rich",
)

console = Console()

# Configure logging
logging.basicConfig(
    level=settings.log_level,
    format="%(message)s",
    datefmt="[%X]",
    handlers=[RichHandler(console=console, rich_tracebacks=True)],
)

logger = logging.getLogger("simulator")


@app.callback()
def main(
    ctx: typer.Context,
    verbose: bool = typer.Option(False, "--verbose", "-v", help="Enable verbose logging"),
    quiet: bool = typer.Option(False, "--quiet", "-q", help="Suppress non-error output"),
) -> None:
    """
    Plant Sensor Simulator - generates realistic sensor readings for IoT devices.
    """
    if verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    elif quiet:
        logging.getLogger().setLevel(logging.WARNING)

    # Ensure token file exists
    token_path = Path(settings.token_file)
    if not token_path.exists():
        logger.warning(f"Token file not found: {token_path}")
        logger.warning("Run 'cd backend && ./vendor/bin/sail artisan db:seed' to generate tokens")


app.add_typer(simulate_app, name="simulate")


def run() -> None:
    """Entry point for console script."""
    app()


if __name__ == "__main__":
    run()