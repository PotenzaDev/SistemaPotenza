<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOperarioRequest;
use App\Http\Requests\UpdateOperarioRequest;
use App\Http\Resources\OperarioResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Operario;
use App\Services\OperarioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Symfony\Component\HttpFoundation\Response;

class OperarioController extends Controller
{
    use ApiResponseTrait;

    private const RELACOES = ['user', 'etapaFluxo'];

    public function __construct(private readonly OperarioService $operarioService)
    {
    }

    public function index(): JsonResponse
    {
        $operarios = Operario::with(self::RELACOES)->orderBy('matricula')->get();

        return $this->successResponse(OperarioResource::collection($operarios));
    }

    public function store(StoreOperarioRequest $request): JsonResponse
    {
        $operario = $this->operarioService->criar($request->validated());

        return $this->successResponse(
            new OperarioResource($operario->load(self::RELACOES)),
            'Operário cadastrado.',
            201
        );
    }

    public function show(Operario $operario): JsonResponse
    {
        return $this->successResponse(new OperarioResource($operario->load(self::RELACOES)));
    }

    public function update(UpdateOperarioRequest $request, Operario $operario): JsonResponse
    {
        $operario = $this->operarioService->atualizar($operario, $request->validated());

        return $this->successResponse(
            new OperarioResource($operario->load(self::RELACOES)),
            'Operário atualizado.'
        );
    }

    public function destroy(Operario $operario): JsonResponse
    {
        $operario->user->delete();

        return $this->successResponse(null, 'Operário removido.');
    }

    public function crachaPdf(Operario $operario): Response
    {
        $generator = new BarcodeGeneratorHTML();
        $barcodeHtml = $generator->getBarcode($operario->matricula, $generator::TYPE_CODE_128, 3, 90);

        $pdf = Pdf::loadView('pdf.cracha-operario', [
            'matricula'    => $operario->matricula,
            'barcodeHtml'  => $barcodeHtml,
        ]);

        return $pdf->stream("cracha-operario-{$operario->matricula}.pdf");
    }
}
