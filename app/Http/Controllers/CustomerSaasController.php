<?php

namespace App\Http\Controllers;

use App\CustomerSaas;
use App\User;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Str;
use App\Models\Tenant\User as UserTenant;
use Illuminate\Support\Facades\Http;
use Mail;
use App\Mail\SendRequestJoin;
use App\Models\CustomersUsers;

class CustomerSaasController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return CustomerSaas::get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $validator = $this->validateData($request);

        if ($validator->fails()) {
            return response()->json([
                'ready' => false,
                'message' => 'Los datos enviados no son correctos',
                'errors' => $validator->errors(),
            ], 400);
        }
        //crear cliente saas
        $customer = new CustomersUsers();
        $customer->name = $request->name;
        $customer->email = $request->email;
        $customer->password = bcrypt($request->password);
        $customer->save();

        return response()->json([
            'ready' => True,
            'message' => 'Usuario Saas Creado Correctamente',
            'customer' => $customer
        ]);
    }

    public function validateData($request){
        $messages = [
            'name.required' => 'Nombre es obligatorio',
            'email.required' => 'Email es obligatorio',
            'email.unique'=>'Email ya utilizado',
            'password.required' => 'Contraseña Obligatoria',
        ];
    
        $validator = Validator::make($request->all(), [
            'name'=> ['required'],
            'email'=> ['required','email','unique:customers_users'],
            'password'=> ['required'],
        ], $messages);

        return $validator;
    }

    public function storeOrganization($id,Request $request)
    {
        $customerUser = CustomersUsers::find($id);
        $customer = new CustomerSaas();
        $customer->nroDocumento = $request->nroDocumento;
        $customer->razonSocial = $request->razonSocial;
        $customer->slug = Str::slug($request->razonSocial);
        $customer->teamSize = $request->teamSize;
        $customer->industry = $request->industry;
        $customer->customers_users_id = $customerUser->id;
        $customer->save();

        $ruta =  'http://saas.test/'.$customer->slug.'/users';
        //return $ruta;
        $response = Http::post($ruta, [
            'name' => $customerUser->name,
            'email' => $customerUser->email,
            'password' => $customerUser->password
        ]);
        
        return response()->json([
            'ready' => True,
            'message' => 'Organización Creado Correctamente',
            'customer' => $customer
        ]);
    }

    public function sendRequetJoin($id,Request $request){
        $customer = CustomersUsers::find($id); 
        $customerManager = CustomersUsers::where('email',$request->email)->first();
        Mail::to($customerManager->email,$customer->name)
                        ->send(new SendRequestJoin($customer->email,$customer->name,$customerManager->id));

        return response()->json([
            "ready" => true,
            "message" => "Mensaje enviado exitosamente"
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $customer = CustomerSaas::find($id);
        return response()->json([
            'ready' => True,
            'message' => 'Organización Creado Correctamente',
            'customer' => $customer
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Customer $customer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Customer  $customer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Customer $customer)
    {
        //
    }
}
