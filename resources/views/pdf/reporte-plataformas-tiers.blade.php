<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REPORTE DETALLADO POR PLATAFORMAS</title>
    <style>

        * {
            margin: 0;
            padding: 0;
        }

        @font-face {
            font-family : 'Poppins';
            src: url('{{storage_path("fonts/Poppins-Regular.ttf")}}');
        }

        @font-face {
            font-family : 'Poppins';
            src: url('{{storage_path("fonts/Poppins-Bold.ttf")}}');
            font-weight: bold;
        }

        body {
            margin: 150px 50px 50px 50px;
            font-family: 'Poppins', Courier, Helvetica, sans-serif;
            font-size: 13px;
            color: #454545;
        }

        /* header */

        header {
            /*text-align: center;*/
            position: fixed;
            /*left: 0;
            top: 0;
            right: 0;*/
        }

        header .header_adp {
            margin: 50px 50px 20px 50px;
            /*overflow: auto;*/
        }

        header .header_adp .logo,
        header .header_adp .title {
            width: 50%;
            display: inline-block;
        }

        header .header_adp .logo {
            text-align: right;
        }

        header .header_adp .title {
            text-align: left;
        }

        header .header_cliente {
            margin: 20px 50px;
            overflow: auto;
        }

        header .header_cliente .client {
            text-align: left;
            /*width: 200px;
            display: inline-block;*/
        }

        .content table {
            width: 100%;
        }

        .content .first-page table thead {
            background-color: #FF3333;
            color: #fff;
        }

        .content .first-page table,
        .content .first-page th,
        .content .first-page td {
            border: 1px solid #FFFFFF;
            border-collapse: collapse;
        }

        .content .first-page th,
        .content .first-page td {
            padding: 5px;
        }

        .content .first-page .container-table {
            margin-bottom: 20px;
        }

        .content .first-page .statistics-table tbody tr:nth-child(even){
            background-color: #F9F9F9;
        }

        .content .first-page .statistics-table tbody tr:nth-child(odd){
            background-color: #FDFDFD;
        }

        .content .second-page table thead {
            background-color: #FFFFFF;
            color: #454545;
        }

        .content .second-page table,
        .content .second-page th,
        .content .second-page td {
            border-bottom: 1px solid #C4C4C4;
            border-collapse: collapse;
        }

        .content .second-page th,
        .content .second-page td {
            padding: 5px;
        }

        .content .second-page .container-table {
            margin-bottom: 20px;
        }

        footer {
            background-color: #FF3333;
            height: 50px;
            position: fixed;
            left: 0;
            bottom: 0;
            right: 0;
        }

        .page-break {
            page-break-after: always;
        }
        
        
    </style>
</head>

<body>
    @if (!$lastPage)        
    <header>
        <div class="header_adp">
            <div class="title">
                <p style="color: #FF3333;"><strong>REPORTE DETALLADO POR PLATAFORMAS</strong></p>
            </div>
            <div class="logo">
            @if (!file_exists(storage_path('app/clientes/logo.png')))
                <img src="" width="100" alt="">
            @else
                <img src="../storage/app/clientes/logo.png" width="100" alt="">
            @endif
            </div>
        </div>
        <div class="header_cliente">
            <div class="cliente">
            @if (empty($cliente->logo))
                <img src="" width="100" alt="">
            @elseif (!file_exists(storage_path('app/clientes/').$cliente->alias."/".$cliente->logo))
                <img src="" width="100" alt="">
            @else
                <img src="../storage/app/clientes/{{$cliente->alias}}/{{$cliente->logo}}" style="max-height: 50px; max-width: 150px;" alt="">   
            @endif
            </div>
        </div>
    </header>
    @endif
    <main class="content">
    @if (!$lastPage) 
        @php $printPageBreak = true; @endphp
        @if ($printFirstPage)
        <section class="first-page">
            <div class="container-table">
                <p><strong>Duración:</strong> del {{date_format(date_create($fechaInicio), 'd-m-Y')}} al {{date_format(date_create($fechaFin), 'd-m-Y')}}</p>
                <p><strong>Publicaciones por plataformas:</strong></p>
            </div>
            @if ($isVal || $isDet)
            <div class="container-table">
                <table class="summary-table">
                    <thead>
                        <tr>
                            @if ($isDet)
                            <th style="text-align: center;">Alcance Total</th>
                            @endif
                            @if ($isVal)
                            <th style="text-align: center;">Valorización Total</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            @if ($isDet)
                            <td style="text-align: center;">{{number_format($alcanceTotal)}}</td>
                            @endif
                            @if ($isVal)
                            <td style="text-align: center;">S/. {{number_format(($valorizadoTotal), 2, '.', ',')}}</td>
                            @endif
                        </tr>
                    </tbody>
                </table>
            </div>
            @endif
            <div class="container-table">
                <table class="statistics-table">
                    <thead>
                        <tr>
                            <th style="text-align: center;">Plataformas</th>
                            @foreach($tipoTiersTotales as $tipoTierTotal)
                            @switch($tipoTierTotal)
                            @case(1)
                                <th style="text-align: center;">Tier 1</th>
                                @break
                            @case(2)
                                <th style="text-align: center;">Tier 2</th>
                                @break
                            @case(3)
                                <th style="text-align: center;">Tier 3</th>
                                @break
                            @default
                                <th style="text-align: center;">-</th>
                                @break
                            @endswitch
                            @endforeach
                            <th style="text-align: center;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($plataformasTotales as $plataformaTotal)
                        <tr>
                            <td style="text-align: center;">{{$plataformaTotal->descripcion}}</td>
                            @foreach($tipoTiersTotales as $tipoTierTotal)
                            <td style="text-align: center;">{{($count[$plataformaTotal->id][$tipoTierTotal] > 0) ? $count[$plataformaTotal->id][$tipoTierTotal] : '-'}}</td>
                            @endforeach
                            <td style="text-align: center; font-weight: bold;">{{($count[$plataformaTotal->id]['total'] > 0) ? $count[$plataformaTotal->id]['total'] : '-'}}</td>
                        </tr>
                    @endforeach
                        <tr>
                            <td style="text-align: center; font-weight: bold;">TOTAL</td>
                            @foreach($tipoTiersTotales as $tipoTierTotal)
                            <td style="text-align: center; font-weight: bold;">{{($count['total'][$tipoTierTotal] > 0) ? $count['total'][$tipoTierTotal] : '-'}}</td>
                            @endforeach
                            <td style="text-align: center; font-weight: bold;">{{($count['total']['total'] > 0) ? $count['total']['total'] : '-'}}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        @else
        @php $printPageBreak = false; @endphp
        @endif
        <section class="second-page">
            <div class="container-table">
                @if ($printPageBreak)
                <div class="page-break"></div>
                @endif
                <table>
                    <thead>
                        <tr>
                            <th style="text-align: left; text-transform: uppercase; font-weight: bold;" colspan="2">{{$plataforma->descripcion}}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($dataPlatTipoTier as $item)
                        @if ($tipoTier === $item->tipoTier)
                        <tr>
                            <td style="width: 50%;">
                                <ul style="list-style: none;">
                                <li>
                                @switch($item->tipoTier)
                                    @case(1)
                                        <div style="color: #FFFFFF; text-transform: uppercase; font-size: 10px; font-weight: bold; background-color: #02704B; margin: 10px 0px; height: 20px; line-height: 10px; width: 80px; text-align: center; border-radius: 2px;">Tier 1</div>
                                        @break
                                    @case(2)
                                        <div style="color: #FFFFFF; text-transform: uppercase; font-size: 10px; font-weight: bold; background-color: #FE6D34; margin: 10px 0px; height: 20px; line-height: 10px; width: 80px; text-align: center; border-radius: 2px;">Tier 2</div>
                                        @break
                                    @case(3)
                                        <div style="color: #FFFFFF; text-transform: uppercase; font-size: 10px; font-weight: bold; background-color: #FFD731; margin: 10px 0px; height: 20px; line-height: 10px; width: 80px; text-align: center; border-radius: 2px;">Tier 3</div>
                                        @break
                                    @default
                                        <div style="color: #FFFFFF; text-transform: uppercase; font-size: 10px; font-weight: bold; background-color: #FF3333; margin: 10px 0px; height: 20px; line-height: 10px; width: 80px; text-align: center; border-radius: 2px;">-</div>
                                        @break
                                @endswitch
                                </li>
                                    <li><strong>CAMPAÑA:</strong> {{$item->Campaign}}</li>
                                    <li><strong>MEDIO:</strong> {{$item->Medio}}</li>
                                    @if (!empty($item->Programa))
                                    <li><strong>PROGRAMA:</strong> {{$item->Programa}}</li>
                                    @endif
                                    @if (isset($item->voceros) && !empty($item->voceros))
                                    <li><strong>VOCERO(S):</strong> {{$item->voceros}}</li>
                                    @endif
                                    @if (isset($item->Vocero) && !empty($item->Vocero))
                                    <li><strong>VOCERO:</strong> {{$item->Vocero}}</li>
                                    @endif
                                    <li><strong>FECHA:</strong> {{date_format(date_create($item->fechaPublicacion), 'd-m-Y')}}</li>
                                    @if (!empty($item->url))
                                    <li><strong>LINK:</strong> <a href="{{$item->url}}">{{implode(PHP_EOL, str_split($item->url, 40))}}</a></li>
                                    @endif
                                    @if ($item->idPlataforma === 9)
                                    <li><strong>RED:</strong> {{$item->Clasificacion}}</li>
                                    @endif
                                    @if ($item->idPlataforma === 5)
                                    <li><strong>SECCIÓN:</strong> {{$item->MedioPlataforma}}</li>
                                    @endif
                                    @if ($isDet && $item->idPlataforma === 5)
                                    <li><strong>CM<sup>2</sup>:</strong> {{is_null($item->cm2) ? '0.00' : $item->cm2}}</li>
                                    @endif
                                    @if ($isDet && ($item->idPlataforma === 2 || $item->idPlataforma === 3))
                                    <li><strong>DURACIÓN:</strong> {{is_null($item->segundos) ? '0' : $item->segundos}} segundos</li>
                                    @endif
                                    @if ($isVal)
                                    <li><strong>VALOR:</strong> S/. {{number_format(($item->valorizado), 2, '.', ',')}}</li>
                                    @endif
                                    @if ($isDet && !empty($item->Alcance))
                                    <li><strong>ALCANCE:</strong> {{number_format($item->Alcance)}}</li>
                                    @endif
                                </ul>
                            </td>
                            <td style="width: 50%; text-align: center; padding: 15px">
                            @if (empty($item->foto))
                                <img src="../storage/app/clientes/reporte_img_default.jpg" width="250" alt="">
                            @elseif (!file_exists(storage_path('app/clientes/').$item->ruta_foto."/".$item->foto))
                                <img src="../storage/app/clientes/reporte_img_default.jpg" width="250" alt="">
                            @else
                                <a href="https://clippingclientes.pruebasgt.com/page/campaign/{{$item->idEncriptado}}"><img src="../storage/app/clientes/{{$item->ruta_foto}}/{{$item->foto}}" style="max-height: 250px; max-width: 250px;" alt=""></a>
                            @endif
                            </td>
                        </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    @if ($lastPage) 
        <section class="third-page">
            <div style="margin-top: 350px; text-align: center;">
            @if (!file_exists(storage_path('app/clientes/logo.png')))
                <img src="" width="100" alt="">
            @else
                <img src="../storage/app/clientes/logo.png" width="100" alt="">
            @endif
                <p>REPORTE DETALLADO POR PLATAFORMAS</p>
            </div>
        </section>
    @endif    
    </main>
</body>

</html>