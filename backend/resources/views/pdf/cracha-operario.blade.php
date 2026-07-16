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
        padding-top: 8mm;
    }

    .barra-wrapper {
        display: inline-block;
    }

    .matricula {
        margin-top: 4px;
        font-family: 'DejaVu Sans Mono', monospace;
        font-size: 14px;
        letter-spacing: 2px;
    }
</style>
</head>
<body>
    <div class="celula">
        <div class="barra-wrapper">{!! $barcodeHtml !!}</div>
        <div class="matricula">{{ $matricula }}</div>
    </div>
</body>
</html>
