<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>{{$tituloCorreo}}</title>
    <style>
    .success {
        background-color:#1a73e8;
        color: #FFFFFF !important;
        padding: 8px 20px;
        text-decoration:none;
        font-weight:bold;
        border-radius:5px;
        cursor:pointer;
    }

    .success:hover {
        background-color:#4285f4;
        color: #FFFFFF;
    }
    </style>
</head>
<body>

    <p>Estimado(a) {{$destinatario->name}},</p>
    <p>Se le envia el reporte que corresponde al mes de {{$Mes}}. Cualquier duda o comentario, será bien recibido. Muchas gracias.</p>
    <br>
    <div style="text-align: center; margin: 15px;">
        <a href="{!! url('/api/downloadFile/'.$fileName) !!}" class="success">DESCARGAR REPORTE</a>
    </div>
    <br>
    <p><em>NOTA: La descarga del reporte estará disponible solamente por un mes.</em></p>
    <br>
    <p>Saludos,</p>
    <p>AGENTE DE PRENSA</p>
</body>
</html>
