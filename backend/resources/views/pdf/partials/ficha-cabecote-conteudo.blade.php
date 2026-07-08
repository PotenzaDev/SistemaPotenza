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
                <td>{{ strtoupper($pecaNome) }}</td>
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
                <th>Posição (X)</th>
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
                <th>N Pino</th>
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
