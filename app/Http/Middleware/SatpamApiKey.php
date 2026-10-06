<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SatpamApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $given = (string) $request->header('X-Api-Key');

        if ($given !== '') {
            foreach ((array) config('satpam.pabrik') as $kode => $cfg) {
                $key = (string) ($cfg['api_key'] ?? '');

                if ($key !== '' && hash_equals($key, $given)) {
                    $request->attributes->set('satpam_pabrik', $kode);

                    return $next($request);
                }
            }
        }

        return response()->json(['message' => 'Kunci aplikasi tidak cocok dengan server'], 401);
    }
}