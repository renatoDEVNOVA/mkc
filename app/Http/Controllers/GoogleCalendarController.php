<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Carbon\Carbon;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_ConferenceData;
use Google_Service_Calendar_CreateConferenceRequest;
use Google_Service_Calendar_EventDateTime;
use Google_Service_Calendar_EventReminders;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use Validator;
use DB;

class GoogleCalendarController extends Controller
{
    //
    private $calendar;

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

            $this->calendar = new Google_Service_Calendar($client);

            return $next($request);
        });
    }

    public function listEvents()
    {
        $calendarId = 'primary';

        $eventos = DB::table('eventos')->where('user_id', auth()->user()->id)->get();

        $events = array();

        foreach ($eventos as $evento) {
            # code...
            $event = $this->calendar->events->get($calendarId, $evento->eventId);
            $event->evento_id = $evento->id;
            array_push($events, $event);
        }

        return response()->json([
            'ready' => true,
            'events' => $events,
        ]);

    }

    public function listEventsV2()
    {
        $calendarId = 'primary';

        $optParams = array(
            "orderBy" => "startTime",
            "singleEvents" => true
        );
        $events = $this->calendar->events->listEvents($calendarId, $optParams);

        return response()->json([
            'ready' => true,
            'events' => $events->getItems(),
        ]);
    }

    public function listEventsV3(Request $request)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'timeMin' => ['required','date'],
                'timeMax' => ['required','date'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = 'primary';

            $optParams = array(
                "orderBy" => "startTime",
                "singleEvents" => true,
                "timeMin" => Carbon::parse($request->timeMin)->toIso8601String(),
                "timeMax" => Carbon::parse($request->timeMax)->toIso8601String()
            );
            $events = $this->calendar->events->listEvents($calendarId, $optParams);

            return response()->json([
                'ready' => true,
                'events' => $events->getItems(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function listEventsV4(Request $request)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'calendarIds' => ['required','array'],
                'timeMin' => ['required','date'],
                'timeMax' => ['required','date'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $optParams = array(
                "orderBy" => "startTime",
                "singleEvents" => true,
                "timeMin" => Carbon::parse($request->timeMin)->toIso8601String(),
                "timeMax" => Carbon::parse($request->timeMax)->toIso8601String()
            );

            $events = array();

            foreach ($request->calendarIds as $calendarId) {
                # code...
                $eventsCalendar = $this->calendar->events->listEvents($calendarId, $optParams);

                foreach ($eventsCalendar->getItems() as $event) {
                    # code...
                    $event->calendarId = $calendarId;
                    array_push($events, $event);
                }
                //$events = array_merge($events, $eventsCalendar->getItems());
            }

            return response()->json([
                'ready' => true,
                'events' => $events,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function getEvent($idEvento)
    {
        $calendarId = 'primary';

        $evento = DB::table('eventos')->where('id', $idEvento)->first();

        if(is_null($evento)){
            return response()->json([
                'ready' => false,
                'message' => 'Evento no encontrado',
            ], 404);
        }

        $event = $this->calendar->events->get($calendarId, $evento->eventId);
        $event->evento_id = $evento->id;

        return response()->json([
            'ready' => true,
            'event' => $event,
        ]);

    }

    public function getEventV2($idEvento)
    {
        $calendarId = 'primary';

        $event = $this->calendar->events->get($calendarId, $idEvento);

        return response()->json([
            'ready' => true,
            'event' => $event,
        ]);
    }

    public function getEventV3(Request $request)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'calendarId' => ['required'],
                'idEvento' => ['required'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = $request->calendarId;
            $idEvento = $request->idEvento;

            $event = $this->calendar->events->get($calendarId, $idEvento);
            $event->calendarId = $calendarId;

            return response()->json([
                'ready' => true,
                'event' => $event,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function insertEvent(Request $request)
    {
        try {
            DB::beginTransaction();

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'summary' => ['required'],
                'start' => ['required','date'],
                'end' => ['required','date'],
                'createConference' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = isset($request->calendarId) ? $request->calendarId : 'primary';

            $event = new Google_Service_Calendar_Event(array(
                'summary' => $request->summary,
                'start' => array(
                    'dateTime' => Carbon::parse($request->start),
                ),
                'end' => array(
                    'dateTime' => Carbon::parse($request->end),
                ),
            ));

            if(isset($request->description)){
                $event->setDescription($request->description);
            }

            if (isset($request->attendees)) {
                $attendees = json_decode(json_encode($request->attendees));

                $messagesAttendees = [
                    'attendees.*.email.required' => 'El correo es obligatorio para cada asistente.',
                    'attendees.*.email.distinct' => 'Ingrese un correo diferente para cada asistente.',
                    'attendees.*.email.email' => 'Ingrese un correo valido para cada asistente.',
                ];

                $validatorAttendees = Validator::make($request->only('attendees'), [
                    'attendees' => ['nullable','array'],
                    'attendees.*.email' => ['required','distinct','email'],
                ], $messagesAttendees);

                if ($validatorAttendees->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorAttendees->errors(),
                    ], 400);
                }

                $misAttendees = array();

                foreach ($attendees as $attendee) {

                    array_push($misAttendees, array('email' => $attendee->email));

                }

                $event->setAttendees($misAttendees);
            }

            if($request->createConference){
                $conference = new Google_Service_Calendar_ConferenceData();
                $conferenceRequest = new Google_Service_Calendar_CreateConferenceRequest();
                $conferenceRequest->setRequestId(Str::random(16));
                $conference->setCreateRequest($conferenceRequest);
                $event->setConferenceData($conference);
            }
            
            $event = $this->calendar->events->insert($calendarId, $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all'
            ]);

            // Guardamos el eventId en la BD
            $evento_id = DB::table('eventos')->insertGetId([
                'user_id' => auth()->user()->id,
                'eventId' => $event->id, 
            ]);
            $event->evento_id = $evento_id;

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha creado correctamente',
                'event' => $event,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function insertEventV2(Request $request)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'summary' => ['required'],
                'start' => ['required','date'],
                'end' => ['required','date'],
                'createConference' => ['required','boolean'],
                'reminderMinutes' => ['nullable','integer'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = isset($request->calendarId) ? $request->calendarId : 'primary';

            $event = new Google_Service_Calendar_Event(array(
                'summary' => $request->summary,
                'start' => array(
                    'dateTime' => Carbon::parse($request->start),
                ),
                'end' => array(
                    'dateTime' => Carbon::parse($request->end),
                ),
            ));

            if(isset($request->description)){
                $event->setDescription($request->description);
            }

            if (isset($request->attendees)) {
                $attendees = json_decode(json_encode($request->attendees));

                $messagesAttendees = [
                    'attendees.*.email.required' => 'El correo es obligatorio para cada asistente.',
                    'attendees.*.email.distinct' => 'Ingrese un correo diferente para cada asistente.',
                    'attendees.*.email.email' => 'Ingrese un correo valido para cada asistente.',
                ];

                $validatorAttendees = Validator::make($request->only('attendees'), [
                    'attendees' => ['nullable','array'],
                    'attendees.*.email' => ['required','distinct','email'],
                ], $messagesAttendees);

                if ($validatorAttendees->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorAttendees->errors(),
                    ], 400);
                }

                $misAttendees = array();

                foreach ($attendees as $attendee) {

                    array_push($misAttendees, array('email' => $attendee->email));

                }

                $event->setAttendees($misAttendees);
            }

            if($request->createConference){
                $conference = new Google_Service_Calendar_ConferenceData();
                $conferenceRequest = new Google_Service_Calendar_CreateConferenceRequest();
                $conferenceRequest->setRequestId(Str::random(16));
                $conference->setCreateRequest($conferenceRequest);
                $event->setConferenceData($conference);
            }

            if(isset($request->reminderMinutes)){
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $eventReminders->setUseDefault(false);
                $misReminders = array();
                array_push($misReminders, array('method' => 'email', 'minutes' => $request->reminderMinutes));
                $eventReminders->setOverrides($misReminders);
                $event->setReminders($eventReminders);
            }
            
            $event = $this->calendar->events->insert($calendarId, $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all'
            ]);

            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha creado correctamente',
                'event' => $event,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function insertEventV3(Request $request)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'calendarId' => ['required'],
                'summary' => ['required'],
                'start' => ['required','date'],
                'end' => ['required','date'],
                'createConference' => ['required','boolean'],
                'reminderMinutes' => ['nullable','integer'],
                'recurrence' => [
                    'required',
                    Rule::in([0, 1, 2]),
                ],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = $request->calendarId;

            $event = new Google_Service_Calendar_Event(array(
                'summary' => $request->summary,
                'start' => array(
                    'dateTime' => Carbon::parse($request->start),
                    'timeZone' => 'America/Lima',
                ),
                'end' => array(
                    'dateTime' => Carbon::parse($request->end),
                    'timeZone' => 'America/Lima',
                ),
            ));

            if(isset($request->description)){
                $event->setDescription($request->description);
            }

            if (isset($request->attendees)) {
                $attendees = json_decode(json_encode($request->attendees));

                $messagesAttendees = [
                    'attendees.*.email.required' => 'El correo es obligatorio para cada asistente.',
                    'attendees.*.email.distinct' => 'Ingrese un correo diferente para cada asistente.',
                    'attendees.*.email.email' => 'Ingrese un correo valido para cada asistente.',
                ];

                $validatorAttendees = Validator::make($request->only('attendees'), [
                    'attendees' => ['nullable','array'],
                    'attendees.*.email' => ['required','distinct','email'],
                ], $messagesAttendees);

                if ($validatorAttendees->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorAttendees->errors(),
                    ], 400);
                }

                $misAttendees = array();

                foreach ($attendees as $attendee) {

                    array_push($misAttendees, array('email' => $attendee->email));

                }

                $event->setAttendees($misAttendees);
            }

            if($request->createConference){
                $conference = new Google_Service_Calendar_ConferenceData();
                $conferenceRequest = new Google_Service_Calendar_CreateConferenceRequest();
                $conferenceRequest->setRequestId(Str::random(16));
                $conference->setCreateRequest($conferenceRequest);
                $event->setConferenceData($conference);
            }

            if(isset($request->reminderMinutes)){
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $eventReminders->setUseDefault(false);
                $misReminders = array();
                array_push($misReminders, array('method' => 'email', 'minutes' => $request->reminderMinutes));
                $eventReminders->setOverrides($misReminders);
                $event->setReminders($eventReminders);
            }

            switch ($request->recurrence) {
                case 1:
                    # code...
                    $event->setRecurrence(array('RRULE:FREQ=DAILY'));
                    break;

                case 2:
                    # code...
                    $event->setRecurrence(array('RRULE:FREQ=WEEKLY'));
                    break;
                
                default:
                    # code...
                    break;
            }
            
            $event = $this->calendar->events->insert($calendarId, $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all'
            ]);
            $event->calendarId = $calendarId;

            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha creado correctamente',
                'event' => $event,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function insertEventV4(Request $request)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'calendarId' => ['required'],
                'summary' => ['required'],
                'start' => ['required','date'],
                'end' => ['required','date'],
                'createConference' => ['required','boolean'],
                'reminderMinutes' => ['nullable','integer'],
                'recurrence' => [
                    'present',
                ],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = $request->calendarId;

            $event = new Google_Service_Calendar_Event(array(
                'summary' => $request->summary,
                'start' => array(
                    'dateTime' => Carbon::parse($request->start),
                    'timeZone' => 'America/Lima',
                ),
                'end' => array(
                    'dateTime' => Carbon::parse($request->end),
                    'timeZone' => 'America/Lima',
                ),
            ));

            if(isset($request->description)){
                $event->setDescription($request->description);
            }

            if (isset($request->attendees)) {
                $attendees = json_decode(json_encode($request->attendees));

                $messagesAttendees = [
                    'attendees.*.email.required' => 'El correo es obligatorio para cada asistente.',
                    'attendees.*.email.distinct' => 'Ingrese un correo diferente para cada asistente.',
                    'attendees.*.email.email' => 'Ingrese un correo valido para cada asistente.',
                ];

                $validatorAttendees = Validator::make($request->only('attendees'), [
                    'attendees' => ['nullable','array'],
                    'attendees.*.email' => ['required','distinct','email'],
                ], $messagesAttendees);

                if ($validatorAttendees->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorAttendees->errors(),
                    ], 400);
                }

                $misAttendees = array();

                foreach ($attendees as $attendee) {

                    array_push($misAttendees, array('email' => $attendee->email));

                }

                $event->setAttendees($misAttendees);
            }

            if($request->createConference){
                $conference = new Google_Service_Calendar_ConferenceData();
                $conferenceRequest = new Google_Service_Calendar_CreateConferenceRequest();
                $conferenceRequest->setRequestId(Str::random(16));
                $conference->setCreateRequest($conferenceRequest);
                $event->setConferenceData($conference);
            }

            if(isset($request->reminderMinutes)){
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $eventReminders->setUseDefault(false);
                $misReminders = array();
                array_push($misReminders, array('method' => 'email', 'minutes' => $request->reminderMinutes));
                $eventReminders->setOverrides($misReminders);
                $event->setReminders($eventReminders);
            }

            if(!empty($request->recurrence)){
                $event->setRecurrence(array($request->recurrence));
            }
            
            $event = $this->calendar->events->insert($calendarId, $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all'
            ]);
            $event->calendarId = $calendarId;

            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha creado correctamente',
                'event' => $event,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function updateEvent(Request $request, $idEvento)
    {
        try {
            DB::beginTransaction();

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'summary' => ['required'],
                'start' => ['required','date'],
                'end' => ['required','date'],
                'createConference' => ['required','boolean'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = isset($request->calendarId) ? $request->calendarId : 'primary';

            $evento = DB::table('eventos')->where('id', $idEvento)->first();

            if(is_null($evento)){
                return response()->json([
                    'ready' => false,
                    'message' => 'Evento no encontrado',
                ], 404);
            }

            $event = $this->calendar->events->get($calendarId, $evento->eventId);

            $event->setSummary($request->summary);
            $event->setStart(
                new Google_Service_Calendar_EventDateTime(['dateTime' => Carbon::parse($request->start)])
            );
            $event->setEnd(
                new Google_Service_Calendar_EventDateTime(['dateTime' => Carbon::parse($request->end)])
            );

            if(isset($request->description)){
                $event->setDescription($request->description);
            }

            if (isset($request->attendees)) {
                $attendees = json_decode(json_encode($request->attendees));

                $messagesAttendees = [
                    'attendees.*.email.required' => 'El correo es obligatorio para cada asistente.',
                    'attendees.*.email.distinct' => 'Ingrese un correo diferente para cada asistente.',
                    'attendees.*.email.email' => 'Ingrese un correo valido para cada asistente.',
                ];

                $validatorAttendees = Validator::make($request->only('attendees'), [
                    'attendees' => ['nullable','array'],
                    'attendees.*.email' => ['required','distinct','email'],
                ], $messagesAttendees);

                if ($validatorAttendees->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorAttendees->errors(),
                    ], 400);
                }

                $misAttendees = array();

                foreach ($attendees as $attendee) {

                    array_push($misAttendees, array('email' => $attendee->email));

                }

                $event->setAttendees($misAttendees);
            }

            if(!$request->createConference){
                $event->conferenceData = null;
            }else{
                if(!$event->getConferenceData()){
                    $conference = new Google_Service_Calendar_ConferenceData();
                    $conferenceRequest = new Google_Service_Calendar_CreateConferenceRequest();
                    $conferenceRequest->setRequestId(Str::random(16));
                    $conference->setCreateRequest($conferenceRequest);
                    $event->setConferenceData($conference);
                }
            }
            
            $updatedEvent = $this->calendar->events->update($calendarId, $event->getId(), $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all'
            ]);

            $updatedEvent->evento_id = $evento->id;

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha actualizado correctamente',
                'event' => $updatedEvent,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function updateEventV2(Request $request, $idEvento)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'summary' => ['required'],
                'start' => ['required','date'],
                'end' => ['required','date'],
                'createConference' => ['required','boolean'],
                'reminderMinutes' => ['nullable','integer'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = isset($request->calendarId) ? $request->calendarId : 'primary';

            $event = $this->calendar->events->get($calendarId, $idEvento);

            $event->setSummary($request->summary);
            $event->setStart(
                new Google_Service_Calendar_EventDateTime(['dateTime' => Carbon::parse($request->start)])
            );
            $event->setEnd(
                new Google_Service_Calendar_EventDateTime(['dateTime' => Carbon::parse($request->end)])
            );

            if(isset($request->description)){
                $event->setDescription($request->description);
            }

            if (isset($request->attendees)) {
                $attendees = json_decode(json_encode($request->attendees));

                $messagesAttendees = [
                    'attendees.*.email.required' => 'El correo es obligatorio para cada asistente.',
                    'attendees.*.email.distinct' => 'Ingrese un correo diferente para cada asistente.',
                    'attendees.*.email.email' => 'Ingrese un correo valido para cada asistente.',
                ];

                $validatorAttendees = Validator::make($request->only('attendees'), [
                    'attendees' => ['nullable','array'],
                    'attendees.*.email' => ['required','distinct','email'],
                ], $messagesAttendees);

                if ($validatorAttendees->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorAttendees->errors(),
                    ], 400);
                }

                $misAttendees = array();

                foreach ($attendees as $attendee) {

                    array_push($misAttendees, array('email' => $attendee->email));

                }

                $event->setAttendees($misAttendees);
            }

            if(!$request->createConference){
                $event->conferenceData = null;
            }else{
                if(!$event->getConferenceData()){
                    $conference = new Google_Service_Calendar_ConferenceData();
                    $conferenceRequest = new Google_Service_Calendar_CreateConferenceRequest();
                    $conferenceRequest->setRequestId(Str::random(16));
                    $conference->setCreateRequest($conferenceRequest);
                    $event->setConferenceData($conference);
                }
            }

            if(isset($request->reminderMinutes)){
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $eventReminders->setUseDefault(false);
                $misReminders = array();
                array_push($misReminders, array('method' => 'email', 'minutes' => $request->reminderMinutes));
                $eventReminders->setOverrides($misReminders);
                $event->setReminders($eventReminders);
            }else{
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $event->setReminders($eventReminders);
            }
            
            $updatedEvent = $this->calendar->events->update($calendarId, $event->getId(), $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all'
            ]);

            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha actualizado correctamente',
                'event' => $updatedEvent,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function updateEventV3(Request $request, $idEvento)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'calendarId' => ['required'],
                'summary' => ['required'],
                'start' => ['required','date'],
                'end' => ['required','date'],
                'createConference' => ['required','boolean'],
                'reminderMinutes' => ['nullable','integer'],
                'recurrence' => [
                    'required',
                    Rule::in([0, 1, 2]),
                ],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = $request->calendarId;

            $event = $this->calendar->events->get($calendarId, $idEvento);

            $event->setSummary($request->summary);
            $event->setStart(
                new Google_Service_Calendar_EventDateTime(['dateTime' => Carbon::parse($request->start), 'timeZone' => 'America/Lima'])
            );
            $event->setEnd(
                new Google_Service_Calendar_EventDateTime(['dateTime' => Carbon::parse($request->end), 'timeZone' => 'America/Lima'])
            );

            if(isset($request->description)){
                $event->setDescription($request->description);
            }

            if (isset($request->attendees)) {
                $attendees = json_decode(json_encode($request->attendees));

                $messagesAttendees = [
                    'attendees.*.email.required' => 'El correo es obligatorio para cada asistente.',
                    'attendees.*.email.distinct' => 'Ingrese un correo diferente para cada asistente.',
                    'attendees.*.email.email' => 'Ingrese un correo valido para cada asistente.',
                ];

                $validatorAttendees = Validator::make($request->only('attendees'), [
                    'attendees' => ['nullable','array'],
                    'attendees.*.email' => ['required','distinct','email'],
                ], $messagesAttendees);

                if ($validatorAttendees->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorAttendees->errors(),
                    ], 400);
                }

                $misAttendees = array();

                foreach ($attendees as $attendee) {

                    array_push($misAttendees, array('email' => $attendee->email));

                }

                $event->setAttendees($misAttendees);
            }

            if(!$request->createConference){
                $event->conferenceData = null;
            }else{
                if(!$event->getConferenceData()){
                    $conference = new Google_Service_Calendar_ConferenceData();
                    $conferenceRequest = new Google_Service_Calendar_CreateConferenceRequest();
                    $conferenceRequest->setRequestId(Str::random(16));
                    $conference->setCreateRequest($conferenceRequest);
                    $event->setConferenceData($conference);
                }
            }

            if(isset($request->reminderMinutes)){
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $eventReminders->setUseDefault(false);
                $misReminders = array();
                array_push($misReminders, array('method' => 'email', 'minutes' => $request->reminderMinutes));
                $eventReminders->setOverrides($misReminders);
                $event->setReminders($eventReminders);
            }else{
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $event->setReminders($eventReminders);
            }

            if(is_null($event->recurringEventId)){
                switch ($request->recurrence) {
                    case 0:
                        # code...
                        $event->recurrence = null;
                        break;

                    case 1:
                        # code...
                        $event->setRecurrence(array('RRULE:FREQ=DAILY'));
                        break;

                    case 2:
                        # code...
                        $event->setRecurrence(array('RRULE:FREQ=WEEKLY'));
                        break;
                    
                    default:
                        # code...
                        break;
                }
            }
            
            $updatedEvent = $this->calendar->events->update($calendarId, $event->getId(), $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all'
            ]);
            $updatedEvent->calendarId = $calendarId;

            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha actualizado correctamente',
                'event' => $updatedEvent,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function updateEventV4(Request $request, $idEvento)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'calendarId' => ['required'],
                'summary' => ['required'],
                'start' => ['required','date'],
                'end' => ['required','date'],
                'createConference' => ['required','boolean'],
                'reminderMinutes' => ['nullable','integer'],
                'recurrence' => [
                    'present',
                ],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = $request->calendarId;

            $event = $this->calendar->events->get($calendarId, $idEvento);

            $event->setSummary($request->summary);
            $event->setStart(
                new Google_Service_Calendar_EventDateTime(['dateTime' => Carbon::parse($request->start), 'timeZone' => 'America/Lima'])
            );
            $event->setEnd(
                new Google_Service_Calendar_EventDateTime(['dateTime' => Carbon::parse($request->end), 'timeZone' => 'America/Lima'])
            );

            if(isset($request->description)){
                $event->setDescription($request->description);
            }

            if (isset($request->attendees)) {
                $attendees = json_decode(json_encode($request->attendees));

                $messagesAttendees = [
                    'attendees.*.email.required' => 'El correo es obligatorio para cada asistente.',
                    'attendees.*.email.distinct' => 'Ingrese un correo diferente para cada asistente.',
                    'attendees.*.email.email' => 'Ingrese un correo valido para cada asistente.',
                ];

                $validatorAttendees = Validator::make($request->only('attendees'), [
                    'attendees' => ['nullable','array'],
                    'attendees.*.email' => ['required','distinct','email'],
                ], $messagesAttendees);

                if ($validatorAttendees->fails()) {
                    return response()->json([
                        'ready' => false,
                        'message' => 'Los datos enviados no son correctos',
                        'errors' => $validatorAttendees->errors(),
                    ], 400);
                }

                $misAttendees = array();

                foreach ($attendees as $attendee) {

                    array_push($misAttendees, array('email' => $attendee->email));

                }

                $event->setAttendees($misAttendees);
            }

            if(!$request->createConference){
                $event->conferenceData = null;
            }else{
                if(!$event->getConferenceData()){
                    $conference = new Google_Service_Calendar_ConferenceData();
                    $conferenceRequest = new Google_Service_Calendar_CreateConferenceRequest();
                    $conferenceRequest->setRequestId(Str::random(16));
                    $conference->setCreateRequest($conferenceRequest);
                    $event->setConferenceData($conference);
                }
            }

            if(isset($request->reminderMinutes)){
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $eventReminders->setUseDefault(false);
                $misReminders = array();
                array_push($misReminders, array('method' => 'email', 'minutes' => $request->reminderMinutes));
                $eventReminders->setOverrides($misReminders);
                $event->setReminders($eventReminders);
            }else{
                $eventReminders = new Google_Service_Calendar_EventReminders();
                $event->setReminders($eventReminders);
            }

            if(is_null($event->recurringEventId)){

                if(!empty($request->recurrence)){
                    $event->setRecurrence(array($request->recurrence));
                }else{
                    $event->recurrence = null;
                }
            }
            
            $updatedEvent = $this->calendar->events->update($calendarId, $event->getId(), $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'all'
            ]);
            $updatedEvent->calendarId = $calendarId;

            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha actualizado correctamente',
                'event' => $updatedEvent,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }

    }

    public function deleteEvent($idEvento)
    {
        $calendarId = 'primary';

        $evento = DB::table('eventos')->where('id', $idEvento)->first();

        if(is_null($evento)){
            return response()->json([
                'ready' => false,
                'message' => 'Evento no encontrado',
            ], 404);
        }

        $this->calendar->events->delete($calendarId, $evento->eventId, [
            'sendUpdates' => 'all'
        ]);

        return response()->json([
            'ready' => true,
            'message' => 'El evento se ha eliminado correctamente',
        ]);

    }

    public function deleteEventV2($idEvento)
    {
        $calendarId = 'primary';

        $this->calendar->events->delete($calendarId, $idEvento, [
            'sendUpdates' => 'all'
        ]);

        return response()->json([
            'ready' => true,
            'message' => 'El evento se ha eliminado correctamente',
        ]);

    }

    public function deleteEventV3(Request $request)
    {
        try {

            $messages = [
            ];

            $validator = Validator::make($request->all(), [
                'calendarId' => ['required'],
                'idEvento' => ['required'],
            ], $messages);

            if ($validator->fails()) {
                return response()->json([
                    'ready' => false,
                    'message' => 'Los datos enviados no son correctos',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $calendarId = $request->calendarId;
            $idEvento = $request->idEvento;

            $this->calendar->events->delete($calendarId, $idEvento, [
                'sendUpdates' => 'all'
            ]);
    
            return response()->json([
                'ready' => true,
                'message' => 'El evento se ha eliminado correctamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function listCalendars()
    {
        $calendarList = $this->calendar->calendarList->listCalendarList();

        return response()->json([
            'ready' => true,
            'calendars' => $calendarList->getItems(),
        ]);
    }

}
