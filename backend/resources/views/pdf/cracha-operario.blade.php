<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @page { size: 80mm 40mm; margin: 0; }

    body {
        margin: 0;
        padding: 0;
        width: 80mm;
        height: 40mm;
    }

    .celula {
        text-align: center;
        padding-top: 3mm;
    }

    .nome {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 13px;
        font-weight: bold;
        margin-bottom: 3mm;
    }

    .barra-wrapper {
        display: inline-block;
    }

    .matricula {
        margin-top: 4px;
        font-family: 'DejaVu Sans Mono', monospace;
        font-size: 12px;
        letter-spacing: 2px;
    }
</style>
</head>
<body>
    <div class="celula">
        <div class="nome">{{ $nome }}</div>
        <div class="barra-wrapper">{!! $barcodeHtml !!}</div>
        <div class="matricula">{{ $matricula }}</div>
    </div>
</body>
</html>
