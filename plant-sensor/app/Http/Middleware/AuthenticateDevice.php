<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    /**
     * Resolve the posting device from its API token.
     *
     * The token is hashed before lookup, so the database never compares
     * against a plaintext secret: api_token holds sha256(token) and the
     * device presents the 40-character original in X-Device-Token.
     *
     * The resolved device is attached to the request so the ingest request
     * and controller can read it without a second lookup.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token');

        if (! is_string($token) || $token === '') {
            abort(401, 'A device token is required.');
        }

        $device = Device::query()
            ->where('api_token', hash('sha256', $token))
            ->first();

        if ($device === null) {
            abort(401, 'The device token is not valid.');
        }

        if ($device->status !== DeviceStatus::Active) {
            abort(403, 'The device is not accepting readings.');
        }

        // Recorded even when the payload later fails validation: a device
        // whose sensors are all reporting junk is still an online device.
        // A refused device (403 above) is deliberately not marked as seen,
        // so an out-of-service node is not mistaken for one still reporting.
        $device->last_seen_at = now();
        $device->save();

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
