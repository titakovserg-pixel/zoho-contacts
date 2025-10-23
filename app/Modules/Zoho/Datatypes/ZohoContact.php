<?php
namespace App\Modules\Zoho\Datatypes;

class ZohoContact extends ZohoDataType {
    public static function getTitleCaptions() {
        return [
            'First_Name'=>"Iм'я",
            'Last_Name'=>"Прізвище",
            'Email'=>"Email",
            'Phone'=>"Телефон",
            'Description'=>"Опис" ,            
        ];        
    }
    public static function getTitles() {
        $titles = self::getTitleCaptions();
        $vTitles = [];
        foreach($titles as $k=>$v) $vTitles[] = ['field' => $k, 'caption' => $v];
        return $vTitles;
    }
    public function __get($name)
    {
        if($name === 'name') {
            return $this->First_Name.' '.$this->Last_Name;
        }
        else return parent::__get($name);
    }
}