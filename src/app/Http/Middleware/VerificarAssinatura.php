<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarAssinatura
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = auth()->user()?->empresa;
        $status  = $empresa?->status_assinatura;

        $destino = match ($status) {
            'pendente'  => 'assinatura.pendente',
            'suspenso'  => 'assinatura.suspensa',
            'cancelado' => 'assinatura.cancelada',
            default     => null,
        };

        if ($destino && ! $request->routeIs('assinatura.*', 'logout')) {
            return redirect()->route($destino);
        }

        return $next($request);
    }
}
