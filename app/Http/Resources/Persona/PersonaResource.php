<?php

namespace App\Http\Resources\Persona;

use Illuminate\Http\Resources\Json\JsonResource;

class PersonaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "apellidos" => $this->apellidos,
            "nombres" => $this->nombres,
            "apodo" => $this->apodo,
            "genero" => $this->genero,
            "fechaNacimiento" => $this->fechaNacimiento,
            "profesion" => $this->profesion,
            "observacion" => $this->observacion,
            "tiposPersona" => $this->tiposPersona,
            "famoso" => $this->famoso,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "deleted_at" => $this->deleted_at,
            "emails"=>$this->emails,
            "telefonos"=>$this->telefonos,
            "categorias"=>$this->categorias,
            "programa_contactos"=>$this->programaContactos,
        ];
    }
}
