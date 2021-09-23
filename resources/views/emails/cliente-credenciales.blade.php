<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Credenciales de acceso</title>
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
    <h1 style="text-align: center;">Tu cuenta Clipping</h1>
    <p>Hola, {{ $usuario->name }}</p>
    <p>¡Felicidades! Ya eres parte del Clipping de AGENTE DE PRENSA. Accede a tu cuenta y disfruta de nuestros beneficios.</p>
    <br>
    <div style="margin: 15px;">
        <table style="margin-left: auto; margin-right: auto; background-color: #f1f1f1; border: 1px solid #bdc1c6;">
            <tbody>
                <tr>
                    <td style="font-weight: bold; padding: 5px;">Correo electrónico</td>
                    <td style="text-align: right; padding: 5px;">{{ $usuario->email }}</td>
                </tr>
                <tr>
                    <td style="font-weight: bold; padding: 5px;">Contraseña</td>
                    <td style="text-align: right; padding: 5px;">{{ $password }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <br>
    <div style="text-align: center; margin: 15px;">
        <a href="https://clippingclientes.pruebasgt.com" class="success">ENTRAR AL CLIPPING</a>
    </div>
    <br>
    <p><em style="font-weight: bold;">NOTA: Por su seguridad, se le solicitara realizar el cambio de contraseña.</em></p>
    <br>
    <p>Saludos,</p>
    <p>AGENTE DE PRENSA</p>
</body>
</html>
