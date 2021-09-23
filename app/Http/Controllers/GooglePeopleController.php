<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Google_Client;
use Google_Service_PeopleService;

use Validator;
use DB;

class GooglePeopleController extends Controller
{
    //
    private $people;

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

            $this->people = new Google_Service_PeopleService($client);

            return $next($request);
        });
    }

    public function listContacts()
    {
        try {
            $contacts = $this->people->people_connections->listPeopleConnections('people/me', array('personFields' => 'names,emailAddresses'));
        } catch (\Google\Service\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

        return response()->json([
            'ready' => true,
            'contacts' => $contacts,
        ]);
    }
    
    public function listOtherContacts()
    {
        try {
            $otherContacts = $this->people->otherContacts->listOtherContacts(array('readMask' => 'names,emailAddresses'));
        } catch (\Google\Service\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

        return response()->json([
            'ready' => true,
            'otherContacts' => $otherContacts,
        ]);
    }
}
