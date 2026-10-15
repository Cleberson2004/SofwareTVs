<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return response()->json(['message' => 'Credencial do dispositivo ausente.'], 401);
        }

        $device = Device::query()
            ->where('credential_hash', hash('sha256', $token))
            ->where('is_revoked', false)
            ->first();

        if ($device === null) {
            return response()->json(['message' => 'Credencial do dispositivo inválida ou revogada.'], 401);
        }

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
