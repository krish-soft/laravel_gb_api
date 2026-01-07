<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MsApiKeyChecker
{
    use ApiResponserTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        // Local
        // $lhrtapLocalKey = env(Utils::$LHRTAP_MS_API_KEY_NAME_LOCAL);
        $lhrtapLocalKey = env('MS_SELF_API_KEY');
        if (!isset($lhrtapLocalKey)) {
            return $this->showErrorMEssage("Missing configuration MS-API key.", 404);
        }

        // $lhrtapApiKey = $request->header(Utils::$LHRTAP_MS_API_KEY_NAME_HEADER);
        $lhrtapApiKey = $request->header('X-API-KEY');
        if ($lhrtapApiKey !== $lhrtapLocalKey) {
            return $this->showErrorMessage("Invalid MS-API key header.", 401);
        }

        return $next($request);
    }
}
