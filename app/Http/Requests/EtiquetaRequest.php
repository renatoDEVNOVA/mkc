<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EtiquetaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'slug' => 'required|unique:etiquetas,slug',
        ];
    }

    public function messages(){
        return[
           'slug.required'=>'La descripción es obligatoria',
           'slug.unique'=>'Ya se encuentra registrada una etiqueta con la misma descipcion.',
          ];
   }
}
