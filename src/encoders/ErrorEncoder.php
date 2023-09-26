<?php
namespace obray\core\encoders;

use obray\core\Helpers;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class ErrorEncoder extends JSONEncoder
{
    /**
     * returns the class property that if found will envoke
     * the encoder
     *
     * @return string The name of the class property.
     */
    public function getProperty()
    {
        return 'errors';
    }

    /**
     * Takes some data and encodes it to json.
     *
     * @param mixed $data The data to be encoded
     *
     * @return mixed
     */
    public function encode($data, $start_time)
    {
        $obj = new \stdClass();
        if (!empty($data->errors)) {
            $obj->errors = $data->errors;
        } else {
            $obj->code = $data->getCode();
            $obj->error = $data->getMessage();
            if(defined('__IS_PRODUCTION__') && !__IS_PRODUCTION__){
                $obj->line = $data->getLine();
                $obj->file = $data->getFile();
            }
        }

        $args = func_get_args();
        if( PHP_SAPI == 'cli' && !empty($args) ) {
            //Helpers::console($obj);
            return $obj;
        }
        
        $obj->runtime = (microtime(true) - $start_time) * 1000;
        $json = json_encode($obj, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);
        if ($json === false) {
            $json = json_encode($obj, JSON_PRETTY_PRINT);
        }
        if ($json) {
            return $json;
        } else {
            echo 'There was en error encoding JSON.';
            print_r($obj->errors);
        }
    }
}