<?php

namespace App\Http\Controllers;

use App\PersonaEmail;
use Illuminate\Http\Request;

use Validator;
use DB;
use Illuminate\Validation\Rule;

class PersonaEmailController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $personaEmails = PersonaEmail::with('persona','tipoEmail')->get();

        return response()->json([
            'ready' => true,
            'personaEmails' => $personaEmails,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\PersonaEmail  $personaEmail
     * @return \Illuminate\Http\Response
     */
    public function show(PersonaEmail $personaEmail)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\PersonaEmail  $personaEmail
     * @return \Illuminate\Http\Response
     */
    public function edit(PersonaEmail $personaEmail)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PersonaEmail  $personaEmail
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PersonaEmail $personaEmail)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\PersonaEmail  $personaEmail
     * @return \Illuminate\Http\Response
     */
    public function destroy(PersonaEmail $personaEmail)
    {
        //
        try {
            DB::beginTransaction();

            if (!$personaEmail->delete()) {
                DB::rollBack();
                return response()->json([
                    'ready' => false,
                    'message' => 'El registro no se ha eliminado',
                ], 500);
            }

            DB::commit();
            return response()->json([
                'ready' => true,
                'message' => 'El registro se ha eliminado correctamente',
                'personaEmail' => $personaEmail,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ready' => false,
            ], 500);
        }
    }

    public function getListByPersona($idPersona)
    {
        $personaEmails = PersonaEmail::with('persona','tipoEmail')->where('persona_id', $idPersona)->get();

        return response()->json([
            'ready' => true,
            'personaEmails' => $personaEmails->values(),
        ]);
    }
}
