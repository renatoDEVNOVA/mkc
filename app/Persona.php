<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nicolaslopezj\Searchable\SearchableTrait;
use Spatie\Activitylog\Traits\LogsActivity;
//use App\Traits\Encryptable;

class Persona extends Model
{
    use SoftDeletes;
    use LogsActivity;
    use SearchableTrait;
    //use Encryptable;

    protected $searchable = [
        'columns' => [
            'personas.apellidos' => 10,
            'personas.apellidos' => 10,
            'personas.tiposPersona' => 10,
            'categorias.descripcion' => 10,
            'persona_emails.email' => 10,
            'persona_telefonos.telefono' => 10,
            'programas.nombre' => 10,
            'medios.nombre' => 10,
        ],
        'joins' => [
            'persona_emails' => ['persona_emails.persona_id','personas.id'],
            'persona_telefonos' => ['persona_telefonos.persona_id','personas.id'],
            'categoria_persona' => ['categoria_persona.persona_id','personas.id'],
            'categorias' => ['categorias.id','categoria_persona.categoria_id'],
            'programa_contactos' => ['personas.id','programa_contactos.idContacto'],
            'programas' => ['programas.id','programa_contactos.programa_id'],
            'medios' => ['programas.medio_id','medios.id'],
        ],
        'groupBy'=>'personas.id'
    ];

    protected $fillable = [
        'apellidos', 'nombres', 'apodo', 'genero', 'fechaNacimiento', 'profesion', 'observacion', 'tiposPersona', 'famoso',
    ];

    protected static $logFillable = true;

    protected static $logOnlyDirty = true;

    protected static $logName = 'persona';

    protected static $submitEmptyLogs = false;

    // Indicamos los campos que van a guardarse encriptados
    //protected $encryptable = ['apellidos', 'nombres'];

    public function getFullNameAttribute(){
        return $this->nombres . ' ' .$this->apellidos;
    }

    public function categorias()
    {
        return $this->belongsToMany('App\Categoria')
                    ->whereNull('categoria_persona.deleted_at')
                    ->withTimestamps();
    }

    public function temas()
    {
        return $this->belongsToMany('App\Tema')  
                    ->whereNull('persona_tema.deleted_at')
                    ->withTimestamps();
    }

    public function emails()
    {
        return $this->hasMany('App\PersonaEmail');
    }

    public function telefonos()
    {
        return $this->hasMany('App\PersonaTelefono');
    }

    public function direccions()
    {
        return $this->hasMany('App\PersonaDireccion');
    }

    public function reds()
    {
        return $this->hasMany('App\PersonaRed');
    }

    public function horarios()
    {
        return $this->hasMany('App\PersonaHorario');
    }

    public function isContacto()
    {
        $tiposPersona = explode(',', (is_null($this->tiposPersona) ? "" : $this->tiposPersona));

        return in_array(1, $tiposPersona);
    }

    public function isVocero()
    {
        $tiposPersona = explode(',', (is_null($this->tiposPersona) ? "" : $this->tiposPersona));

        return in_array(2, $tiposPersona);
    }

    public function programaContactos()
    {
        return $this->hasMany('App\ProgramaContacto', 'idContacto');
    }

}
