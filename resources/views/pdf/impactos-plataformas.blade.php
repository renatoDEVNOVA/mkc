<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMPACTOS POR PLATAFORMAS</title>
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
    <header>
        <div class="header_adp">
            <div class="title">
                <p style="color: #FF3333;"><strong>IMPACTOS POR PLATAFORMAS</strong></p>
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
    <main class="content">
        @php 
        $dateInicio = strtotime($fechaInicio);
        $Inicio = date('Y-m-01', $dateInicio);
        $dateInicio = strtotime($Inicio);
        $dateFin = strtotime($fechaFin);
        $Fin = date('Y-m-01', $dateFin);
        $dateFin = strtotime($Fin);
        @endphp
        <section class="first-page">
            <div class="container-table">
                <p><strong>Duración:</strong> del {{date_format(date_create($fechaInicio), 'd-m-Y')}} al {{date_format(date_create($fechaFin), 'd-m-Y')}}</p>
            </div>
            <div class="container-table">
                <table class="statistics-table">
                    <thead>
                        <tr>
                            <th style="text-align: center;">AÑO</th>
                            <th style="text-align: center;">MES</th>
                            @foreach($plataformas as $plataforma)
                            <th style="text-align: center;">{{$plataforma->descripcion}}</th>
                            @endforeach
                            <th style="text-align: center;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php 
                    $dateCurrent = $dateInicio;
                    $countPlat = array();
                    $countTotal = 0;
                    $mesDesc = array();
                    $mesDesc[1] = 'Enero';
                    $mesDesc[2] = 'Febrero';
                    $mesDesc[3] = 'Marzo';
                    $mesDesc[4] = 'Abril';
                    $mesDesc[5] = 'Mayo';
                    $mesDesc[6] = 'Junio';
                    $mesDesc[7] = 'Julio';
                    $mesDesc[8] = 'Agosto';
                    $mesDesc[9] = 'Setiembre';
                    $mesDesc[10] = 'Octubre';
                    $mesDesc[11] = 'Noviembre';
                    $mesDesc[12] = 'Diciembre';
                    @endphp
                    @while ($dateCurrent <= $dateFin)
                        @php 
                        $M = date('n', $dateCurrent);
                        $Y = date('Y', $dateCurrent);
                        $countFecha = 0;
                        @endphp
                        <tr>
                            <td style="text-align: center;">{{$Y}}</td>
                            <td style="text-align: center;">{{$mesDesc[$M]}}</td>
                            @foreach($plataformas as $plataforma)
                            @php 
                            $item = $data->where('year', $Y)->where('month', $M)->where('idPlataforma', $plataforma->id)->first();
                            $countFecha += (isset($item) ? $item->cantidad : 0);
                            if(!isset($countPlat[$plataforma->id])){
                                $countPlat[$plataforma->id] = 0;
                            }
                            $countPlat[$plataforma->id] += (isset($item) ? $item->cantidad : 0);;
                            @endphp
                            <td style="text-align: center;">{{isset($item) ? $item->cantidad : '-'}}</td>
                            @endforeach
                            <td style="text-align: center; font-weight: bold;">{{$countFecha}}</td>
                        </tr>
                        @php 
                        $countTotal += $countFecha;
                        $dateCurrent = strtotime("+1 month", $dateCurrent);
                        @endphp
                    @endwhile
                        <tr>
                            <td style="text-align: center; font-weight: bold;">-</td>
                            <td style="text-align: center; font-weight: bold;">TOTAL</td>
                            @foreach($plataformas as $plataforma)
                            <td style="text-align: center; font-weight: bold;">{{($countPlat[$plataforma->id] > 0) ? $countPlat[$plataforma->id] : '-'}}</td>
                            @endforeach
                            <td style="text-align: center; font-weight: bold;">{{$countTotal}}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>

</html>