<?php
namespace obray\core\encoders;

use obray\core\interfaces\EncoderInterface;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class JSONEncoder implements EncoderInterface
{
    private bool $numericCheck = true;
    public function __construct($numericCheck = true)
    {
        $this->numericCheck = $numericCheck;
    }

    /**
     * returns the class property that if found will envoke
     * the encoder
     *
     * @return string The name of the class property.
     */
    public function getProperty(){
        return 'data';
    }

    /**
     * returns the content type for the encoder
     *
     * @return string the valid content type that will be returned in the response.
     */
    public function getContentType(){
        return 'application/json';
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
        if (isSet($data->data)) {
            $obj->data = $data->data;
        }
        $obj->runtime = (microtime(TRUE) - $start_time)*1000;
        $options = JSON_PRETTY_PRINT;
        if($this->numericCheck) $options = JSON_NUMERIC_CHECK|JSON_PRETTY_PRINT;
        $json = json_encode($obj, $options);
        if( $json === FALSE ) $json = json_encode($obj,JSON_PRETTY_PRINT);
        if( $json ){ 
            return $json;
        } else {
            throw new \Exception("Error encoding jason.");
        }
    }

    /**
     * Takes some data and decodes it
     * 
     * @param mixed $data The data to be decoded
     * 
     * @return mixed
     */

    public function decode($data)
    {
        return json_decode($data);
    }

    /**
     * Takes some data and outputs it appropariately
     * 
     * @param mixed $data The data to be displayed
     * 
     * @return null
     */

    public function out($data)
    {    
        echo $data;
    }

}