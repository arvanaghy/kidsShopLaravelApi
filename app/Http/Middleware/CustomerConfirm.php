<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\CustomerModel;
use Symfony\Component\HttpFoundation\Response;

class CustomerConfirm
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $result = CustomerModel::where('UToken', $token)->first();
        if ($token and $result) {
            return $next($request);
        } else {
            return response()->json(['message' => 'دسترسی غیر مجاز', 'result' => null], 401);
        }
    }
}
