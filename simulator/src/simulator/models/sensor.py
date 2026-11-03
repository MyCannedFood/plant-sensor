"""
Sensor definitions and validation bounds.
Copied from backend/app/Enums/ThresholdParameter.php and backend/app/Http/Requests/StoreReadingRequest.php
Keep in sync with backend - see README.md for sync instructions.
"""

from enum import Enum
from dataclasses import dataclass
from typing import ClassVar


class ThresholdParameter(str, Enum):
    """Sensor parameter types - matches backend enum exactly."""
    CO2 = "co2"
    TEMPERATURE = "temperature"
    HUMIDITY = "humidity"
    PH = "ph"
    EC = "ec"
    WATER_TEMPERATURE = "water_temperature"
    LIGHT = "light"
    WATER_LEVEL = "water_level"

    def label(self) -> str:
        labels = {
            self.CO2: "CO₂",
            self.TEMPERATURE: "Temperature",
            self.HUMIDITY: "Humidity",
            self.PH: "pH",
            self.EC: "EC",
            self.WATER_TEMPERATURE: "Water Temp",
            self.LIGHT: "Light",
            self.WATER_LEVEL: "Water Level",
        }
        return labels[self]

    def short(self) -> str:
        shorts = {
            self.CO2: "CO₂",
            self.TEMPERATURE: "Temp",
            self.HUMIDITY: "Humidity",
            self.PH: "pH",
            self.EC: "EC",
            self.WATER_TEMPERATURE: "Water T",
            self.LIGHT: "Light",
            self.WATER_LEVEL: "Level",
        }
        return shorts[self]

    def unit(self) -> str:
        units = {
            self.CO2: "ppm",
            self.TEMPERATURE: "°C",
            self.HUMIDITY: "%",
            self.PH: "",
            self.EC: "µS/cm",
            self.WATER_TEMPERATURE: "°C",
            self.LIGHT: "lux",
            self.WATER_LEVEL: "%",
        }
        return units[self]

    def decimals(self) -> int:
        if self in (self.TEMPERATURE, self.WATER_TEMPERATURE, self.PH):
            return 1
        return 0


@dataclass(frozen=True)
class SensorBounds:
    """Validation bounds for a sensor parameter."""
    min_value: float
    max_value: float
    is_integer: bool = False
    decimal_places: int = 0


# Validation bounds - matches StoreReadingRequest.php rules exactly
SENSOR_BOUNDS: dict[ThresholdParameter, SensorBounds] = {
    ThresholdParameter.CO2: SensorBounds(min_value=0, max_value=10000, is_integer=True, decimal_places=0),
    ThresholdParameter.TEMPERATURE: SensorBounds(min_value=-40, max_value=80, is_integer=False, decimal_places=1),
    ThresholdParameter.HUMIDITY: SensorBounds(min_value=0, max_value=100, is_integer=False, decimal_places=2),
    ThresholdParameter.PH: SensorBounds(min_value=0, max_value=14, is_integer=False, decimal_places=1),
    ThresholdParameter.EC: SensorBounds(min_value=0, max_value=10000, is_integer=True, decimal_places=0),
    ThresholdParameter.WATER_TEMPERATURE: SensorBounds(min_value=-40, max_value=80, is_integer=False, decimal_places=2),
    ThresholdParameter.LIGHT: SensorBounds(min_value=0, max_value=200000, is_integer=True, decimal_places=0),
    ThresholdParameter.WATER_LEVEL: SensorBounds(min_value=0, max_value=100, is_integer=True, decimal_places=0),
}

# Parameters that require at least one measurement (from StoreReadingRequest after() hook)
REQUIRED_MEASUREMENT_PARAMS: frozenset[ThresholdParameter] = frozenset({
    ThresholdParameter.CO2,
    ThresholdParameter.TEMPERATURE,
    ThresholdParameter.HUMIDITY,
})

# All parameter values for iteration
ALL_PARAMETERS: tuple[ThresholdParameter, ...] = tuple(ThresholdParameter)


def clamp_value(param: ThresholdParameter, value: float) -> float | int:
    """Clamp a value to the sensor's valid bounds."""
    bounds = SENSOR_BOUNDS[param]
    clamped = max(bounds.min_value, min(bounds.max_value, value))
    if bounds.is_integer:
        return int(round(clamped))
    return round(clamped, bounds.decimal_places)


def validate_reading_payload(payload: dict) -> list[str]:
    """
    Validate a reading payload against sensor bounds.
    Returns list of error messages (empty if valid).
    """
    errors = []

    # Check at least one required measurement present
    has_measurement = any(
        payload.get(param.value) is not None
        for param in REQUIRED_MEASUREMENT_PARAMS
    )
    if not has_measurement:
        errors.append("At least one of co2, temperature or humidity must be present.")

    # Validate each parameter
    for param in ALL_PARAMETERS:
        value = payload.get(param.value)
        if value is None:
            continue

        bounds = SENSOR_BOUNDS[param]

        if bounds.is_integer and not isinstance(value, int):
            errors.append(f"{param.value} must be an integer.")
            continue

        if value < bounds.min_value or value > bounds.max_value:
            errors.append(f"{param.value} must be between {bounds.min_value} and {bounds.max_value}.")

    # measured_at validation (required, date, before_or_equal:now+5min)
    # This is validated server-side, but we can do basic check
    if "measured_at" not in payload:
        errors.append("measured_at is required.")

    return errors


def generate_realistic_value(param: ThresholdParameter, target: float, variance: float) -> float | int:
    """
    Generate a realistic sensor reading with Gaussian drift around target.
    Respects validation bounds.
    """
    import numpy as np

    bounds = SENSOR_BOUNDS[param]

    # Gaussian drift: target ± (target * variance) with normal distribution
    std_dev = abs(target) * variance if target != 0 else variance * 10
    value = np.random.normal(target, std_dev)

    return clamp_value(param, value)


def generate_random_walk_value(param: ThresholdParameter, target: float, variance: float, previous: float | None = None) -> float | int:
    """
    Generate a value using random walk (slight change from previous).
    Used for CO2, EC, Light, Water Level.
    """
    import numpy as np

    bounds = SENSOR_BOUNDS[param]

    if previous is None:
        previous = target

    # Small random step
    step_size = abs(target) * variance * 0.5 if target != 0 else variance * 5
    value = previous + np.random.normal(0, step_size)

    return clamp_value(param, value)