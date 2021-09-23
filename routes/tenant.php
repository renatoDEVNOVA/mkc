<?php 
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/',function(){
    return 'Hola';
});
Route::post('users/tenant',[UserController::class,'storeUserTenant']);
Route::post('users',[UserController::class,'store']);
Route::get('users',[UserController::class,'index']);


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