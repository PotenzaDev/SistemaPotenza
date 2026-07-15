<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponseTrait;

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($e);
        }

        return parent::render($request, $e);
    }

    private function handleApiException(Throwable $e)
    {
        if ($e instanceof ValidationException) {
            return $this->errorResponse(
                'Dados inválidos.',
                422,
                $e->errors()
            );
        }

        if ($e instanceof AuthenticationException) {
            return $this->errorResponse('Não autenticado.', 401);
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->errorResponse('Recurso não encontrado.', 404);
        }

        if ($e instanceof ConfirmacaoNecessariaException) {
            return response()->json([
                'success'              => false,
                'message'              => $e->getMessage(),
                'requiresConfirmation' => true,
                'passagensRealizadas'  => $e->passagensRealizadas,
                'passagensEsperadas'   => $e->passagensEsperadas,
            ], 409);
        }

        if ($e instanceof FinalizacaoParcialException) {
            return response()->json([
                'success'              => false,
                'message'              => $e->getMessage(),
                'requiresConfirmation' => true,
                'totalBipado'          => $e->totalBipado,
                'qtdeTotal'            => $e->qtdeTotal,
                'pendentesPorCor'      => $e->pendentesPorCor,
            ], 409);
        }

        if ($e instanceof LoteCompletoException) {
            return response()->json([
                'success'       => false,
                'message'       => $e->getMessage(),
                'loteCompleto'  => true,
                'pilhasBipadas' => $e->pilhasBipadas,
                'totalPilhas'   => $e->totalPilhas,
            ], 422);
        }

        if ($e instanceof BusinessException) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        }

        if ($e instanceof HttpException) {
            return $this->errorResponse($e->getMessage() ?: 'Erro HTTP.', $e->getStatusCode());
        }

        $message = config('app.debug')
            ? $e->getMessage()
            : 'Erro interno do servidor.';

        return $this->errorResponse($message, 500);
    }
}
