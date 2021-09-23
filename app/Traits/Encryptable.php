<?php

namespace App\Traits;

use Log;
use Crypt;

trait Encryptable
{
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        if (in_array($key, $this->encryptable)) {
            Log::info('Encryptable getAttribute');
            try {
                if(!is_null($value)){
                    $value = Crypt::decrypt($value);
                }
            } catch (\Exception $e) {

            }
        }
        
        return $value;
    }
 
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encryptable)) {
            Log::info('Encryptable setAttribute');
            if(!is_null($value)){
                $value = Crypt::encrypt($value);
            }
        }
        return parent::setAttribute($key, $value);
    }

    public function toArray()
    {
        $array = parent::toArray();

        foreach ($array as $key => $attribute) {
            if (in_array($key, $this->encryptable)) {
                Log::info('Encryptable toArray');
                try {
                    if(!is_null($attribute)){
                        $array[$key] = Crypt::decrypt($attribute);
                    }
                } catch (\Exception $e) {

                }
            }
        }
        return $array;
    }
}
