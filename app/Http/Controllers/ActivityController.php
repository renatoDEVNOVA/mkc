<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $activities = Activity::orderBy('id', 'desc')->get(); 

        return response()->json([
            'ready' => true,
            'activities' => $activities,
        ]);
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function getListByUser($idUser)
    {
        //
        $activities = Activity::where('causer_id' , $idUser)->orderBy('id', 'desc')->get(); 

        return response()->json([
            'ready' => true,
            'activities' => $activities,
        ]);
    }

    public function getListByLog($log)
    {
        //
        $activities = Activity::inLog($log)->orderBy('id', 'desc')->get(); 

        return response()->json([
            'ready' => true,
            'activities' => $activities,
        ]);
    }
}
