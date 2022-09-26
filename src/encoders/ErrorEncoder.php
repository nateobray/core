<?php

namespace obray\core\encoders;

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

?>