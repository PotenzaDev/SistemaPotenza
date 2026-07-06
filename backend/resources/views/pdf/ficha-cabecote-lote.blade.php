<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
@include('pdf.partials.ficha-cabecote-estilos')
</head>
<body>
    @foreach ($fichas as $ficha)
        <div @if (! $loop->first) style="page-break-before: always;" @endif>
            @include('pdf.partials.ficha-cabecote-conteudo', $ficha)
        </div>
    @endforeach
</body>
</html>
