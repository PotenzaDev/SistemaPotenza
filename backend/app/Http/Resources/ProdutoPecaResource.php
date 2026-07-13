<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdutoPecaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'produto_id' => $this->produto_id,
            'numero' => $this->numero,
            'nome' => $this->nome,
            'sub_grupo' => $this->sub_grupo,
            'dimensao' => $this->dimensao,
            'material' => $this->material,
            'ordem' => $this->ordem,
            'produto' => $this->whenLoaded('produto', fn () => new ProdutoResource($this->produto)),
            'ultima_ficha_cabecote' => $this->whenLoaded('ultimaFichaCabecote', fn () => $this->ultimaFichaCabecote ? new FichaCabecoteResource($this->ultimaFichaCabecote) : null),
            'fichas_cabecote_count' => $this->whenCounted('fichasCabecote'),
        ];
    }
}
