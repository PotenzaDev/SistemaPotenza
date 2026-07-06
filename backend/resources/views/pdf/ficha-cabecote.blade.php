<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 18px; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10px;
        color: #1a1a1a;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    .titulo table { border: 1px solid #1f3a5f; }

    .titulo td {
        background-color: #1f3a5f;
        color: #ffffff;
        text-align: center;
        padding: 6px;
    }

    .titulo .nome-produto { font-size: 13px; font-weight: bold; }
    .titulo .subtitulo { font-size: 9px; }

    .identificacao table {
        border: 1px solid #1f3a5f;
        border-top: none;
        margin-bottom: 10px;
    }

    .identificacao td {
        border: 1px solid #cbd5e1;
        padding: 5px 6px;
        vertical-align: middle;
    }

    .identificacao .rotulo {
        background-color: #eef2f7;
        font-weight: bold;
        width: 18%;
    }

    .rotulo.destaque { background-color: #f5c518; }

    .secao-titulo {
        background-color: #1f3a5f;
        color: #ffffff;
        text-align: center;
        font-weight: bold;
        padding: 4px;
        font-size: 9px;
    }

    .secao-titulo.verde { background-color: #3f7a4e; }

    .tabela-dados { margin-bottom: 12px; }

    .tabela-dados th {
        background-color: #dbe4f0;
        border: 1px solid #cbd5e1;
        padding: 4px;
        font-size: 8px;
        text-transform: uppercase;
    }

    .tabela-dados.brocas th { background-color: #dcecdf; }

    .tabela-dados td {
        border: 1px solid #cbd5e1;
        padding: 5px 4px;
        font-size: 9px;
        height: 16px;
    }
</style>
</head>
<body>

    <div class="titulo">
        <table>
            <tr><td class="nome-produto">{{ strtoupper($produtoNome ?? '') }}</td></tr>
            <tr><td class="subtitulo">FICHA DE APONTAMENTO DE SETUP</td></tr>
        </table>
    </div>

    <div class="identificacao">
        <table>
            <tr>
                <td class="rotulo destaque">PEÇA</td>
                <td>{{ $pecaNumero }} {{ strtoupper($pecaNome) }}</td>
                <td class="rotulo">CÓDIGO</td>
                <td>{{ $pecaNumero }}</td>
                <td class="rotulo">DIMENSÃO</td>
                <td>{{ $pecaDimensao ?? '—' }}</td>
            </tr>
            <tr>
                <td class="rotulo">DATA</td>
                <td colspan="3">{{ $data ?? '' }}</td>
                <td class="rotulo">MÁQUINA</td>
                <td>{{ $maquinaNome ?? '' }}</td>
            </tr>
            <tr>
                <td class="rotulo">OPERADOR</td>
                <td colspan="3">{{ $operadorNome ?? '' }}</td>
                <td class="rotulo">QUANT. PEÇAS POR VEZ</td>
                <td>{{ $quantidadePecasVez ?? '' }}</td>
            </tr>
            <tr>
                <td class="rotulo">TOP ESQUERDO (MM)</td>
                <td colspan="3">{{ $topEsquerdoMm ?? '' }}</td>
                <td class="rotulo">TOP DIREITO (MM)</td>
                <td>{{ $topDireitoMm ?? '' }}</td>
            </tr>
            <tr>
                <td class="rotulo">VELOCIDADE DE TRABALHO</td>
                <td colspan="5">{{ $velocidadeTrabalho ?? '' }}</td>
            </tr>
            <tr>
                <td class="rotulo">OBSERVAÇÃO</td>
                <td colspan="5">{{ $observacao ?? '' }}</td>
            </tr>
        </table>
    </div>

    <div class="secao-titulo">LEVANTAMENTO DE POSIÇÕES DOS CABEÇOTES</div>
    <table class="tabela-dados">
        <thead>
            <tr>
                <th>Cabeçote</th>
                <th>Sentido</th>
                <th>Largura (X)</th>
                <th>Deslocamento (Y)</th>
                <th>Altura Cabeçote (Z)</th>
                <th>Obs</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($posicoesCabecote as $linha)
                <tr>
                    <td>{{ $linha['cabecote'] }}</td>
                    <td>{{ $linha['sentido'] }}</td>
                    <td>{{ $linha['largura_mm'] }}</td>
                    <td>{{ $linha['deslocamento_mm'] }}</td>
                    <td>{{ $linha['altura_cabecote_mm'] }}</td>
                    <td>{{ $linha['obs'] }}</td>
                </tr>
            @empty
                @for ($i = 0; $i < $linhasBrancoCabecote; $i++)
                    <tr>
                        <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                    </tr>
                @endfor
            @endforelse
        </tbody>
    </table>

    <div class="secao-titulo verde">POSIÇÃO DAS BROCAS</div>
    <table class="tabela-dados brocas">
        <thead>
            <tr>
                <th>Cabeçote</th>
                <th>Sentido</th>
                <th>Posição</th>
                <th>Broca (mm)</th>
                <th>Passante (Sim ou Prof)</th>
                <th>Agregado</th>
                <th>Obs</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($posicoesBroca as $linha)
                <tr>
                    <td>{{ $linha['cabecote'] }}</td>
                    <td>{{ $linha['sentido'] }}</td>
                    <td>{{ $linha['posicao'] }}</td>
                    <td>{{ $linha['broca_codigo'] }}</td>
                    <td>{{ $linha['passante_label'] }}</td>
                    <td>{{ $linha['agregado'] }}</td>
                    <td>{{ $linha['obs'] }}</td>
                </tr>
            @empty
                @for ($i = 0; $i < $linhasBrancoBroca; $i++)
                    <tr>
                        <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                    </tr>
                @endfor
            @endforelse
        </tbody>
    </table>

</body>
</html>
