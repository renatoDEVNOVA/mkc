<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;

use Validator;
use DB;

class GoogleGmailController extends Controller
{
    //
    private $gmail;

    public function __construct() 
    {
        $this->middleware(function ($request, $next){

            $authUser = auth()->user();

            if (is_null($authUser->access_token)) {
                return response()->json([
                    'ready' => false,
                    'message' => 'No cuenta con los permisos de Google para acceder a este recurso',
                ], 403);
            }

            $accessToken = json_decode(auth()->user()->access_token, true);

            $client = new Google_Client();
            $client->setAuthConfig(storage_path('app/') . 'credentials.json');
            $client->setAccessType('offline');

            $client->setAccessToken($accessToken);

            if ($client->isAccessTokenExpired()) {
                
                if ($client->getRefreshToken()) {
                    $accessToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

                    if (array_key_exists('error', $accessToken)) {
                        return response()->json([
                            'ready' => false,
                            'message' => 'Error al intentar conectar con Google',
                        ], 500);
                    }

                    $client->setAccessToken($accessToken);
                } else {
                    return response()->json([
                        'ready' => false,
                        'message' => 'No cuenta con un token de actualizacion',
                    ], 403);
                }

                $authUser->access_token = json_encode($client->getAccessToken());
                if (!$authUser->save()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Error al intentar actualizar el usuario',
                    ], 500);
                }
            }

            $this->gmail = new Google_Service_Gmail($client);

            return $next($request);
        });
    }

    private function createMessage($sender, $to, $subject, $messageText) 
    {
        $message = new Google_Service_Gmail_Message();
        $rawMessageString = "From: <{$sender}>\r\n";
        $rawMessageString .= "To: <{$to}>\r\n";
        $rawMessageString .= 'Subject: =?utf-8?B?' . base64_encode($subject) . "?=\r\n";
        $rawMessageString .= "MIME-Version: 1.0\r\n";
        $rawMessageString .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessageString .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
        $rawMessageString .= "{$messageText}\r\n";
        $rawMessage = strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_'));
        $message->setRaw($rawMessage);

        return $message;
    }

    public function sendMessage(Request $request)
    {

        $messages = [
        ];

        $validator = Validator::make($request->all(), [
            'to' => ['required','email'],
            'subject' => ['required'],
            'messageText' => ['required'],
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'ready' => false,
                'message' => 'Los datos enviados no son correctos',
                'errors' => $validator->errors(),
            ], 400);
        }

        $sender = auth()->user()->email;
        $to = $request->to;
        $subject = $request->subject;
        $messageText = $request->messageText;

        $message = $this->createMessage($sender, $to, $subject, $messageText);

        $message = $this->gmail->users_messages->send('me', $message);

        return response()->json([
            'ready' => true,
            'message' => 'El mensaje se ha enviado correctamente',
            'message' => $message,
        ]);
    }
}
