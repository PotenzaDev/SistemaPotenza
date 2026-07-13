<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdutoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cod_produto' => $this->cod_produto,
            'nome' => $this->nome,
            'grupo' => $this->grupo,
            'sub_grupo' => $this->sub_grupo,
            'empresa' => $this->empresa,
            'ativo' => (bool) $this->ativo,
            'pecas_count' => $this->whenCounted('pecas'),
            'pecas' => $this->whenLoaded('pecas', fn () => ProdutoPecaResource::collection($this->pecas)),
        ];
    }
}
