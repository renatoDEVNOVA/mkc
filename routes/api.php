<?php

use App\CustomerSaas;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

//Route
Route::post('send-email/{id}','CustomerSaasController@sendRequetJoin');
Route::post('customers', 'CustomerSaasController@store');
Route::get('customers/{id}', 'CustomerSaasController@show');
Route::post('customers/organization/{id}', 'CustomerSaasController@storeOrganization');

Route::get('/delete', function () {
    $tenant = CustomerSaas::first();
    $tenant->delete();
    return 'eliminado';
});

Route::group([
    'prefix' => 'auth'

], function () {

    //Route::post('register', 'JWTAuthController@register');
    Route::post('login', 'AuthController@login');
    Route::post('loginWithGoogle', 'AuthController@loginWithGoogle');
    Route::post('loginClipping', 'AuthController@loginClipping');
    //Route::post('logout', 'JWTAuthController@logout');
    Route::put('refresh', 'AuthController@refresh')->middleware('jwt.auth');
    Route::get('me', 'AuthController@me')->middleware('jwt.auth');

});

Route::get('downloadFile/{filename}', function ($filename) {

    $file = storage_path('app/public/') . $filename;

    if(file_exists($file)){

        $headers = [
        'Content-Type' => 'application/pdf',
        ];

        return response()->download($file, $filename, $headers);

    }else{
        //return redirect('http://agente.test/reporte/404.html');
        return response()->json([
            'ready' => false,
            'message' => 'El archivo no existe',
        ], 404);
    }
    
});

Route::get('displayFile/{filename}', function ($filename) {

    $file = storage_path('app/public/') . $filename;

    if(file_exists($file)){

        $headers = [
        'Content-Type' => 'application/pdf',
        ];

        return response()->file($file, $headers);

    }else{
        //return redirect('http://agente.test/reporte/404.html');
        return response()->json([
            'ready' => false,
            'message' => 'El archivo no existe',
        ], 404);
    }
    
});

Route::get('detallePlanResultadoPlataformas/data/getByIdEncriptado/{idEncriptado}', 'DetallePlanResultadoPlataformaController@getByIdEncriptado');
Route::get('resultadoPlataformas/data/displayImage/{id}', 'ResultadoPlataformaController@displayImage');

Route::get('tipoDeCambio', 'Controller@tipoDeCambio');

Route::group(['middleware' => ['jwt.auth']], function() {
    /*AÑADE AQUI LAS RUTAS QUE QUIERAS PROTEGER CON JWT*/

    Route::get('googleCalendar/data/listCalendars', 'GoogleCalendarController@listCalendars');
    Route::post('googleCalendar/data/listEvents', 'GoogleCalendarController@listEventsV4');
    Route::post('googleCalendar/data/getEvent', 'GoogleCalendarController@getEventV3');
    Route::post('googleCalendar/data/insertEvent', 'GoogleCalendarController@insertEventV4');
    Route::put('googleCalendar/data/updateEvent/{idEvento}', 'GoogleCalendarController@updateEventV4');
    Route::post('googleCalendar/data/deleteEvent', 'GoogleCalendarController@deleteEventV3');

    //Route::get('googlePeople/data/listContacts', 'GooglePeopleController@listContacts');
    //Route::get('googlePeople/data/listOtherContacts', 'GooglePeopleController@listOtherContacts');

    //Route::post('googleGmail/data/sendMessage', 'GoogleGmailController@sendMessage');

    /** INICIO */

    Route::get('baseDeDatos/data/getCount', 'Controller@getCount');
    Route::get('detallePlanResultadoPlataformas/data/getCountByPlataformas', 'DetallePlanResultadoPlataformaController@getCountByPlataformas');
    Route::get('detallePlanResultadoPlataformas/data/getValorizadoByYears', 'DetallePlanResultadoPlataformaController@getValorizadoByYears');
    Route::get('detallePlanMedios/data/getCountByEstado', 'DetallePlanMedioController@getCountByEstado');

    Route::post('detallePlanResultadoPlataformas/data/impactosPorCampanasAndPlataformas', 'DetallePlanResultadoPlataformaController@impactosPorCampanasAndPlataformas');
    Route::post('detallePlanResultadoPlataformas/data/impactosPorCampanasAndPlataformasByLogged', 'DetallePlanResultadoPlataformaController@impactosPorCampanasAndPlataformasByLogged');
    Route::post('detallePlanResultadoPlataformas/data/impactosPorVocerosAndPlataformas', 'DetallePlanResultadoPlataformaController@impactosPorVocerosAndPlataformas');
    Route::post('detallePlanResultadoPlataformas/data/impactosPorVocerosAndPlataformasByLogged', 'DetallePlanResultadoPlataformaController@impactosPorVocerosAndPlataformasByLogged');
    Route::post('detallePlanResultadoPlataformas/data/impactosPorTipoTier', 'DetallePlanResultadoPlataformaController@impactosPorTipoTier');
    Route::post('detallePlanResultadoPlataformas/data/impactosPorTipoTierByLogged', 'DetallePlanResultadoPlataformaController@impactosPorTipoTierByLogged');
    Route::post('detallePlanResultadoPlataformas/data/impactosPorTipoRegion', 'DetallePlanResultadoPlataformaController@impactosPorTipoRegion');
    Route::post('detallePlanResultadoPlataformas/data/impactosPorTipoRegionByLogged', 'DetallePlanResultadoPlataformaController@impactosPorTipoRegionByLogged');

    
    /** ADMINISTRADOR */

    //Route::apiResource('users', 'UserController');
    Route::get('users', 'UserController@index');
    Route::post('users', 'UserController@store')->middleware('permission:user.create');
    Route::get('users/{user}', 'UserController@show');
    Route::put('users/{user}', 'UserController@update')->middleware('permission:user.edit');
    Route::delete('users/{user}', 'UserController@destroy')->middleware('permission:user.delete');
    Route::get('users/data/displayImage/{id}', 'UserController@displayImage');
    Route::get('users/data/getList', 'UserController@index');
    Route::get('users/data/getCount', 'UserController@getCount');
    Route::get('users/data/getListByRole/{idRole}', 'UserController@getListByRole');


    //Route::apiResource('roles', 'RoleController');
    Route::get('roles', 'RoleController@index');
    Route::post('roles', 'RoleController@store')->middleware('permission:role.create');
    Route::get('roles/{role}', 'RoleController@show');
    Route::put('roles/{role}', 'RoleController@update')->middleware('permission:role.edit');
    Route::delete('roles/{role}', 'RoleController@destroy')->middleware('permission:role.delete');
    Route::get('roles/data/getList', 'RoleController@index');


    Route::apiResource('permissions', 'PermissionController');


    //Route::apiResource('tipoCambios', 'TipoCambioController');
    Route::get('tipoCambios', 'TipoCambioController@index');
    Route::post('tipoCambios', 'TipoCambioController@store')->middleware('permission:tipo-cambio.create');
    Route::get('tipoCambios/{tipoCambio}', 'TipoCambioController@show');
    Route::put('tipoCambios/{tipoCambio}', 'TipoCambioController@update')->middleware('permission:tipo-cambio.edit');
    Route::delete('tipoCambios/{tipoCambio}', 'TipoCambioController@destroy')->middleware('permission:tipo-cambio.delete');
    Route::get('tipoCambios/data/getCurrent', 'TipoCambioController@getCurrent');


    Route::get('activities', 'ActivityController@index');
    Route::get('activities/data/getListByUser/{idUser}', 'ActivityController@getListByUser');
    Route::get('activities/data/getListByLog/{log}', 'ActivityController@getListByLog');


    /** BASE DE DATOS */

    // PERSONAS
    //Route::apiResource('personas', 'PersonaController');
    
    Route::get('personas', 'PersonaController@index');
    Route::get('persona/list', 'PersonaController@indexV2');
    Route::get('personas/contacto/select', 'PersonaController@contactoSelect');
    Route::post('personas', 'PersonaController@store')->middleware('permission:persona.create');
    Route::get('personas/{persona}', 'PersonaController@show');
    Route::put('personas/{persona}', 'PersonaController@update')->middleware('permission:persona.edit');
    Route::delete('personas/{persona}', 'PersonaController@destroy')->middleware('permission:persona.delete');
    Route::get('personas/data/getContactos', 'PersonaController@getContactos');
    Route::get('personas/data/getVoceros', 'PersonaController@getVoceros');
    Route::get('personas/data/getList', 'PersonaController@getList');



    Route::apiResource('personaEmails', 'PersonaEmailController');
    Route::get('personaEmails/data/getListByPersona/{idPersona}', 'PersonaEmailController@getListByPersona');

    // COMPAÑIAS
    //Route::apiResource('companies', 'CompanyController');
    Route::get('companies', 'CompanyController@index');
    Route::post('companies', 'CompanyController@store')->middleware('permission:company.create');
    Route::get('companies/{company}', 'CompanyController@show');
    Route::put('companies/{company}', 'CompanyController@update')->middleware('permission:company.edit');
    Route::delete('companies/{company}', 'CompanyController@destroy')->middleware('permission:company.delete');
    Route::get('companies/data/getList', 'CompanyController@index');


    // MEDIOS
    //Route::apiResource('medios', 'MedioController');
    Route::get('medios', 'MedioController@index');
    Route::get('medios/select', 'MedioController@mediosSelect');
    Route::post('medios', 'MedioController@store')->middleware('permission:medio.create');
    Route::get('medios/{medio}', 'MedioController@show');
    Route::put('medios/{medio}', 'MedioController@update')->middleware('permission:medio.edit');
    Route::delete('medios/{medio}', 'MedioController@destroy')->middleware('permission:medio.delete');
    Route::get('medios/data/getList', 'MedioController@index');

    //Route::apiResource('medioPlataformas', 'MedioPlataformaController');
    Route::get('medioPlataformas', 'MedioPlataformaController@index');
    Route::post('medioPlataformas', 'MedioPlataformaController@store')->middleware('permission:medio.plataforma.add');
    Route::get('medioPlataformas/{medioPlataforma}', 'MedioPlataformaController@show');
    Route::put('medioPlataformas/{medioPlataforma}', 'MedioPlataformaController@update')->middleware('permission:medio.plataforma.edit');
    Route::delete('medioPlataformas/{medioPlataforma}', 'MedioPlataformaController@destroy')->middleware('permission:medio.plataforma.delete');
    Route::get('medioPlataformas/data/getListByMedio/{idMedio}', 'MedioPlataformaController@getListByMedio');

    //Route::apiResource('programas', 'ProgramaController');
    Route::get('programas', 'ProgramaController@index');
    Route::post('programas', 'ProgramaController@store')->middleware('permission:medio.programa.add');
    Route::get('programas/{programa}', 'ProgramaController@show');
    Route::put('programas/{programa}', 'ProgramaController@update')->middleware('permission:medio.programa.edit');
    Route::delete('programas/{programa}', 'ProgramaController@destroy')->middleware('permission:medio.programa.delete');
    Route::get('programas/data/getListByMedio/{idMedio}', 'ProgramaController@getListByMedio');

    //Route::apiResource('programaPlataformas', 'ProgramaPlataformaController');
    Route::get('programaPlataformas', 'ProgramaPlataformaController@index');
    Route::post('programaPlataformas', 'ProgramaPlataformaController@store')->middleware('permission:medio.programa-plataforma.add');
    Route::get('programaPlataformas/{programaPlataforma}', 'ProgramaPlataformaController@show');
    Route::put('programaPlataformas/{programaPlataforma}', 'ProgramaPlataformaController@update')->middleware('permission:medio.programa-plataforma.edit');
    Route::delete('programaPlataformas/{programaPlataforma}', 'ProgramaPlataformaController@destroy')->middleware('permission:medio.programa-plataforma.delete');
    Route::get('programaPlataformas/data/getListByPrograma/{idPrograma}', 'ProgramaPlataformaController@getListByPrograma');

    //Route::apiResource('programaContactos', 'ProgramaContactoController');
    Route::get('programaContactos', 'ProgramaContactoController@index');
    Route::post('programaContactos', 'ProgramaContactoController@store')->middleware('permission:persona.medio.add,medio.contacto.add');
    Route::get('programaContactos/{programaContacto}', 'ProgramaContactoController@show');
    Route::put('programaContactos/{programaContacto}', 'ProgramaContactoController@update')->middleware('permission:persona.medio.edit,medio.contacto.edit');
    Route::delete('programaContactos/{programaContacto}', 'ProgramaContactoController@destroy')->middleware('permission:persona.medio.delete,medio.contacto.delete');
    Route::get('programaContactos/data/getListByMedio/{idMedio}', 'ProgramaContactoController@getListByMedio');
    Route::get('programaContactos/data/getListByContacto/{idContacto}', 'ProgramaContactoController@getListByContacto');
    Route::get('programaContactos/data/activate/{id}', 'ProgramaContactoController@activate')->middleware('permission:persona.medio.activo,medio.contacto.activo');
    Route::get('programaContactos/data/deactivate/{id}', 'ProgramaContactoController@deactivate')->middleware('permission:persona.medio.activo,medio.contacto.activo');

    
    // CLIENTES
    //Route::apiResource('clientes', 'ClienteController');
    Route::get('clientes', 'ClienteController@index');
    Route::get('clientes/select', 'ClienteController@clienteSelect');
    Route::post('clientes', 'ClienteController@store')->middleware('permission:cliente.create');
    Route::get('clientes/{cliente}', 'ClienteController@show');
    Route::put('clientes/{cliente}', 'ClienteController@update')->middleware('permission:cliente.edit');
    Route::delete('clientes/{cliente}', 'ClienteController@destroy')->middleware('permission:cliente.delete');
    Route::get('clientes/data/displayImage/{id}', 'ClienteController@displayImage');
    Route::get('clientes/data/getList', 'ClienteController@index');
    Route::post('clientes/data/addMember', 'ClienteController@addMember')->middleware('permission:clipping.miembro.add');
    Route::post('clientes/data/editMember', 'ClienteController@editMember')->middleware('permission:clipping.miembro.edit');
    Route::get('clientes/data/deleteMember/{idUser}', 'ClienteController@deleteMember')->middleware('permission:clipping.miembro.delete');
    Route::get('clientes/data/enviarCredenciales/{idUser}', 'ClienteController@enviarCredenciales')->middleware('permission:clipping.miembro.send-cd');

    //Route::apiResource('clienteEncargados', 'ClienteEncargadoController');
    Route::get('clienteEncargados', 'ClienteEncargadoController@index');
    Route::post('clienteEncargados', 'ClienteEncargadoController@store')->middleware('permission:cliente.encargado.add');
    Route::get('clienteEncargados/{clienteEncargado}', 'ClienteEncargadoController@show');
    Route::put('clienteEncargados/{clienteEncargado}', 'ClienteEncargadoController@update')->middleware('permission:cliente.encargado.edit');
    Route::delete('clienteEncargados/{clienteEncargado}', 'ClienteEncargadoController@destroy')->middleware('permission:cliente.encargado.delete');
    Route::get('clienteEncargados/data/getListByCliente/{idCliente}', 'ClienteEncargadoController@getListByCliente');

    //Route::apiResource('clienteVoceros', 'ClienteVoceroController');
    Route::get('clienteVoceros', 'ClienteVoceroController@index');
    Route::post('clienteVoceros', 'ClienteVoceroController@store')->middleware('permission:persona.cliente.add');
    Route::get('clienteVoceros/{clienteVocero}', 'ClienteVoceroController@show');
    Route::put('clienteVoceros/{clienteVocero}', 'ClienteVoceroController@update')->middleware('permission:persona.cliente.edit');
    Route::delete('clienteVoceros/{clienteVocero}', 'ClienteVoceroController@destroy')->middleware('permission:persona.cliente.delete');
    Route::get('clienteVoceros/data/getListByVocero/{idVocero}', 'ClienteVoceroController@getListByVocero');
    Route::get('clienteVoceros/data/getListByCliente/{idCliente}', 'ClienteVoceroController@getListByCliente');

    //Route::apiResource('clienteEnvios', 'ClienteEnvioController');
    Route::get('clienteEnvios', 'ClienteEnvioController@index');
    Route::post('clienteEnvios', 'ClienteEnvioController@store')->middleware('permission:clipping.send-auto.add');
    Route::get('clienteEnvios/{clienteEnvio}', 'ClienteEnvioController@show');
    Route::put('clienteEnvios/{clienteEnvio}', 'ClienteEnvioController@update')->middleware('permission:clipping.send-auto.edit');
    Route::delete('clienteEnvios/{clienteEnvio}', 'ClienteEnvioController@destroy')->middleware('permission:clipping.send-auto.delete');
    Route::post('clienteEnvios/data/nextSendAndDateRange', 'ClienteEnvioController@nextSendAndDateRange');


    // CAMPAÑAS
    //Route::apiResource('campaigns', 'CampaignController');
    Route::get('campaigns', 'CampaignController@index');
    Route::get('campaigns/select', 'CampaignController@campaignSelect');
    Route::get('campaign/list', 'CampaignController@indexV2');
    Route::post('campaigns', 'CampaignController@store')->middleware('permission:campaign.create');
    Route::get('campaigns/{campaign}', 'CampaignController@show');
    Route::put('campaigns/{campaign}', 'CampaignController@update')->middleware('permission:campaign.edit');
    Route::delete('campaigns/{campaign}', 'CampaignController@destroy')->middleware('permission:campaign.delete');
    Route::get('campaigns/data/getListByCliente/{idCliente}', 'CampaignController@getListByCliente');
    Route::get('campaigns/data/getListByLogged', 'CampaignController@getListByLogged');
    Route::post('campaigns/data/getListForReporte', 'CampaignController@getListForReporte');
    Route::post('campaigns/data/valorar', 'CampaignController@valorar');
    Route::post('campaigns/data/getListByDates', 'CampaignController@getListByDates');

    //Route::apiResource('campaignVoceros', 'CampaignVoceroController');
    Route::get('campaignVoceros', 'CampaignVoceroController@index');
    Route::post('campaignVoceros', 'CampaignVoceroController@store')->middleware('permission:campaign.vocero.add');
    Route::get('campaignVoceros/{campaignVocero}', 'CampaignVoceroController@show');
    Route::put('campaignVoceros/{campaignVocero}', 'CampaignVoceroController@update')->middleware('permission:campaign.vocero.edit');
    Route::delete('campaignVoceros/{campaignVocero}', 'CampaignVoceroController@destroy')->middleware('permission:campaign.vocero.delete');
    Route::get('campaignVoceros/data/getListByCampaign/{idCampaign}', 'CampaignVoceroController@getListByCampaign');

    //Route::apiResource('campaignResponsables', 'CampaignResponsableController');
    Route::get('campaignResponsables', 'CampaignResponsableController@index');
    Route::post('campaignResponsables', 'CampaignResponsableController@store')->middleware('permission:campaign.agente.add');
    Route::get('campaignResponsables/{campaignResponsable}', 'CampaignResponsableController@show');
    Route::delete('campaignResponsables/{campaignResponsable}', 'CampaignResponsableController@destroy')->middleware('permission:campaign.agente.delete');
    Route::get('campaignResponsables/data/getListByCampaign/{idCampaign}', 'CampaignResponsableController@getListByCampaign');


    // NOTAS DE PRENSA
    //Route::apiResource('notaPrensas', 'NotaPrensaController');
    Route::get('notaPrensas', 'NotaPrensaController@index');
    Route::post('notaPrensas', 'NotaPrensaController@store')->middleware('permission:nota-prensa.create');
    Route::get('notaPrensas/{notaPrensa}', 'NotaPrensaController@show');
    Route::put('notaPrensas/{notaPrensa}', 'NotaPrensaController@update')->middleware('permission:nota-prensa.edit');
    Route::delete('notaPrensas/{notaPrensa}', 'NotaPrensaController@destroy')->middleware('permission:nota-prensa.delete');
    Route::get('notaPrensas/data/downloadFile/{id}', 'NotaPrensaController@downloadFile');
    Route::get('notaPrensas/data/displayFile/{id}', 'NotaPrensaController@displayFile');
    Route::get('notaPrensas/data/{id}/associatePlanMedio/{idPlanMedio}', 'NotaPrensaController@associatePlanMedio')->middleware('permission:nota-prensa.plan-medio.add');
    Route::get('notaPrensas/data/{id}/dissociatePlanMedio/{idPlanMedio}', 'NotaPrensaController@dissociatePlanMedio')->middleware('permission:nota-prensa.plan-medio.delete');
    Route::post('notaPrensas/data/sendMassNotaPrensa', 'NotaPrensaController@sendMassNotaPrensaV2')->middleware('permission:plan-medio.dpm.send-np');


    // PLANES DE MEDIOS
    //Route::apiResource('planMedios', 'PlanMedioController');
    Route::get('planMedios', 'PlanMedioController@index');
    Route::get('planMedios/select', 'PlanMedioController@planMediosSelect');
    Route::post('planMedios', 'PlanMedioController@storeV2')->middleware('permission:plan-medio.create,plan-medio.duplicate');
    Route::get('planMedios/{planMedio}', 'PlanMedioController@show');
    Route::put('planMedios/{planMedio}', 'PlanMedioController@update')->middleware('permission:plan-medio.edit');
    Route::delete('planMedios/{planMedio}', 'PlanMedioController@destroy')->middleware('permission:plan-medio.delete');
    Route::get('planMedios/data/getListByLogged', 'PlanMedioController@getListByLogged');
    Route::get('planMedios/data/getListByEstado/{estado}', 'PlanMedioController@getListByEstado');
    Route::post('planMedios/data/changeStatus', 'PlanMedioController@changeStatus');
    Route::get('planMedios/data/getListByCliente/{idCliente}', 'PlanMedioController@getListByCliente');
    Route::get('planMedios/data/getListByCampaign/{idCampaign}', 'PlanMedioController@getListByCampaign');
    Route::get('planMedios/data/getList', 'PlanMedioController@getList');
    Route::post('planMedios/data/getListByLoggedAndDates', 'PlanMedioController@getListByLoggedAndDates');
    
    Route::get('planMedios/data/list','PlanMedioController@dataList');
    Route::get('planMedios/data/listEstado/{estado}','PlanMedioController@dataListEstado');
    Route::get('planMedios/data/listDetails','PlanMedioController@dataListDetalles');
    Route::get('planMedios/detalle/{planMedio_id}','PlanMedioController@planMedioDetails');


    Route::apiResource('registros', 'RegistroController');

    //Route::apiResource('detallePlanMedios', 'DetallePlanMedioController');
    Route::get('detallePlanMedios', 'DetallePlanMedioController@index');
    Route::post('detallePlanMedios', 'DetallePlanMedioController@storeV2')->middleware('permission:persona.dpm.add,plan-medio.dpm.add');
    Route::get('detallePlanMedios/{detallePlanMedio}', 'DetallePlanMedioController@show');
    Route::put('detallePlanMedios/{detallePlanMedio}', 'DetallePlanMedioController@update')->middleware('permission:persona.dpm.edit,plan-medio.dpm.edit');
    Route::delete('detallePlanMedios/{detallePlanMedio}', 'DetallePlanMedioController@destroy')->middleware('permission:persona.dpm.delete,plan-medio.dpm.delete');
    Route::post('detallePlanMedios/data/associateDPM', 'DetallePlanMedioController@associateDPM')->middleware('permission:plan-medio.dpm.associate');
    Route::get('detallePlanMedios/data/dissociateDPM/{id}', 'DetallePlanMedioController@dissociateDPM')->middleware('permission:plan-medio.dpm.dissociate');
    Route::get('detallePlanMedios/data/getListAssociatedDPM/{id}', 'DetallePlanMedioController@getListAssociatedDPM');
    Route::post('detallePlanMedios/data/updateMassVoceros', 'DetallePlanMedioController@updateMassVoceros')->middleware('permission:plan-medio.dpm.add-vocero');
    Route::post('detallePlanMedios/data/updateMassMuestras', 'DetallePlanMedioController@updateMassMuestras')->middleware('permission:plan-medio.dpm.manage-muestra');
    Route::get('detallePlanMedios/data/getListByContacto/{idContacto}', 'DetallePlanMedioController@getListByContacto');
    Route::get('detallePlanMedios/data/getListByContactoAndLogged/{idContacto}', 'DetallePlanMedioController@getListByContactoAndLogged');
    Route::get('detallePlanMedios/data/getListByEstado/{estado}', 'DetallePlanMedioController@getListByEstado');
    Route::post('detallePlanMedios/data/getListByEstados', 'DetallePlanMedioController@getListByEstados');
    Route::post('detallePlanMedios/data/getListByLogged', 'DetallePlanMedioController@getListByLogged');
    Route::post('detallePlanMedios/data/getListByLoggedV2', 'DetallePlanMedioController@getListByLoggedV2');
    Route::get('detallePlanMedios/data/getCountByEstadoAndByLogged', 'DetallePlanMedioController@getCountByEstadoAndByLogged');
    Route::post('detallePlanMedios/data/getListByEstadoAndByLogged', 'DetallePlanMedioController@getListByEstadoAndByLogged');

    Route::post('detallePlanMedios/data/updateMassTipoTier', 'DetallePlanMedioController@updateMassTipoTier');
    Route::post('detallePlanMedios/data/transferir', 'DetallePlanMedioController@transferir');
    Route::post('detallePlanMedios/data/transferirMass', 'DetallePlanMedioController@transferirMass');

    Route::post('detallePlanMedios/data/generatePlanMedio', 'DetallePlanMedioController@generatePlanMedioV2');
    Route::post('detallePlanMedios/data/analisisEstrategico', 'DetallePlanMedioController@analisisEstrategico');
    Route::get('detallePlanMedios/data/getListSistemaExpertoEnviados', 'DetallePlanMedioController@getListSistemaExpertoEnviados');
    Route::get('detallePlanMedios/data/getListSistemaExpertoNoEnviados', 'DetallePlanMedioController@getListSistemaExpertoNoEnviados');
    Route::get('detallePlanMedios/data/addSistemaExperto/{id}', 'DetallePlanMedioController@addSistemaExpertoV2')->middleware('permission:plan-medio.dpm.sistema-experto,sistema-experto.casos.send');
    Route::post('detallePlanMedios/data/addMassSistemaExperto', 'DetallePlanMedioController@addMassSistemaExpertoV2')->middleware('permission:sistema-experto.casos.send');
    Route::get('detallePlanMedios/data/deleteSistemaExperto/{id}', 'DetallePlanMedioController@deleteSistemaExperto')->middleware('permission:sistema-experto.casos.delete');
    Route::post('detallePlanMedios/data/deleteMassSistemaExperto', 'DetallePlanMedioController@deleteMassSistemaExperto')->middleware('permission:sistema-experto.casos.delete');

    Route::apiResource('resultadoPlataformas', 'ResultadoPlataformaController');
    Route::post('resultadoPlataformas/data/saveResultados', 'ResultadoPlataformaController@saveResultados');
    Route::get('resultadoPlataformas/data/getListByDPM/{idDPM}', 'ResultadoPlataformaController@getListByDPM');
    Route::post('resultadoPlataformas/data/getResultadosForReporte', 'ResultadoPlataformaController@getResultadosForReporteV2');
    Route::post('resultadoPlataformas/data/generateReporte', 'ResultadoPlataformaController@generateReporte');
    Route::post('resultadoPlataformas/data/sendReporte', 'ResultadoPlataformaController@sendReporte');
    Route::post('resultadoPlataformas/data/getImpactosByPlataformas', 'ResultadoPlataformaController@getImpactosByPlataformas');
    Route::post('resultadoPlataformas/data/generateImpactosByPlataformas', 'ResultadoPlataformaController@generateImpactosByPlataformas');
    Route::post('resultadoPlataformas/data/sendImpactosByPlataformas', 'ResultadoPlataformaController@sendImpactosByPlataformas');

    Route::apiResource('bitacoras', 'BitacoraController');
    Route::apiResource('comentarios', 'ComentarioController');


    /** MANTENIMIENTO */

    Route::apiResource('atributos', 'AtributoController');
    Route::apiResource('tipoAtributos', 'TipoAtributoController');
    Route::get('tipoAtributos/data/getListBySlug/{slug}', 'TipoAtributoController@getListBySlug');

    Route::apiResource('plataformas', 'PlataformaController');
    Route::apiResource('plataformaClasificacions', 'PlataformaClasificacionController');

    Route::apiResource('cargos', 'CargoController');

    Route::apiResource('categorias', 'CategoriaController');

    Route::apiResource('etiquetas', 'EtiquetaController');
    
    Route::apiResource('etiquetasMantenimiento', 'EtiquetaMaintenanceController');
    
    Route::apiResource('temas', 'TemaController');

    Route::apiResource('campaignGroups', 'CampaignGroupController');

});


