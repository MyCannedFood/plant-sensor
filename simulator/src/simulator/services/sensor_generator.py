"""
Sensor value generation service.
Generates realistic sensor readings with Gaussian drift and random walk patterns.
"""

import numpy as np
from typing import Optional
from simulator.models.sensor import (
    ThresholdParameter,
    SENSOR_BOUNDS,
    ALL_PARAMETERS,
    clamp_value,
    generate_realistic_value,
    generate_random_walk_value,
)


class SensorGenerator:
    """
    Generates realistic sensor readings for plant monitoring devices.

    Uses two strategies:
    - Gaussian drift: for temperature, humidity, pH, water_temperature (environmental sensors)
    - Random walk: for CO2, EC, light, water_level (cumulative/absolute sensors)
    """

    # Parameters that use Gaussian drift around target
    GAUSSIAN_PARAMS: set[ThresholdParameter] = {
        ThresholdParameter.TEMPERATURE,
        ThresholdParameter.HUMIDITY,
        ThresholdParameter.PH,
        ThresholdParameter.WATER_TEMPERATURE,
    }

    # Parameters that use random walk (small steps from previous value)
    RANDOM_WALK_PARAMS: set[ThresholdParameter] = {
        ThresholdParameter.CO2,
        ThresholdParameter.EC,
        ThresholdParameter.LIGHT,
        ThresholdParameter.WATER_LEVEL,
    }

    def __init__(self, variance: float = 0.15):
        """
        Initialize the sensor generator.

        Args:
            variance: Drift factor (0.15 = ±15% of target value)
        """
        self.variance = variance
        self._previous_values: dict[ThresholdParameter, float] = {}

    def generate_reading(
        self,
        declared_sensors: list[str],
        target_conditions: dict[str, float],
    ) -> dict:
        """
        Generate a complete sensor reading payload.

        Args:
            declared_sensors: List of sensor parameter strings the device has
            target_conditions: Dict mapping parameter -> target value

        Returns:
            Dict with sensor readings (only for declared sensors)
        """
        reading = {"measured_at": self._current_timestamp()}

        for param_str in declared_sensors:
            try:
                param = ThresholdParameter(param_str)
            except ValueError:
                # Skip unknown parameters
                continue

            target = target_conditions.get(param_str)
            if target is None:
                # No target condition - skip this sensor
                continue

            value = self._generate_for_parameter(param, target)
            reading[param_str] = value

        return reading

    def _generate_for_parameter(self, param: ThresholdParameter, target: float) -> float | int:
        """Generate a value for a specific parameter using the appropriate strategy."""
        if param in self.GAUSSIAN_PARAMS:
            return generate_realistic_value(param, target, self.variance)

        if param in self.RANDOM_WALK_PARAMS:
            previous = self._previous_values.get(param)
            value = generate_random_walk_value(param, target, self.variance, previous)
            self._previous_values[param] = float(value) if isinstance(value, (int, float)) else target
            return value

        # Fallback: Gaussian
        return generate_realistic_value(param, target, self.variance)

    def _current_timestamp(self) -> str:
        """Get current UTC timestamp in ISO format."""
        from datetime import datetime, timezone
        return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")

    def reset_state(self) -> None:
        """Reset the random walk state (useful for testing)."""
        self._previous_values.clear()


def compute_target_conditions(
    readings: list[dict],
    declared_sensors: list[str],
    window_size: int = 50,
) -> dict[str, float]:
    """
    Compute target conditions from recent readings by averaging.

    Args:
        readings: List of recent reading dicts from API
        declared_sensors: List of sensor parameters the device has
        window_size: Number of recent readings to average

    Returns:
        Dict mapping parameter -> average value
    """
    if not readings:
        return {}

    # Take last N readings
    recent = readings[-window_size:]

    targets = {}
    for param_str in declared_sensors:
        try:
            param = ThresholdParameter(param_str)
        except ValueError:
            continue

        values = []
        for reading in recent:
            value = reading.get(param_str)
            if value is not None:
                values.append(value)

        if values:
            targets[param_str] = sum(values) / len(values)

    return targets