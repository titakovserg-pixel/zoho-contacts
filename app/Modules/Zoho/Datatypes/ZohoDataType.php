<?php
namespace App\Modules\Zoho\Datatypes;

use App\Modules\Zoho\Managers\ZohoDataManager;

abstract class ZohoDataType {
    protected $data = [];
    abstract public static function getTitles() ;
    public function __construct($data = [])
    {
        $this->data = $data;        
    }
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }
    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }
    public function set_d_method($name, $value) { //для реализации стрелочных сетеров
        $this->data[$name] = $value;
        return $this;
    }
    public function getArrayData() {
        return $this->data;
    }
    public function setId($value) {
        return $this->set_d_method('id',$value);
    }
    public function load(ZohoDataManager $collection, $id) {
        $list = $collection->find(['id'=>$id]);        
        if(!isset($list['data'][0])) {
            $this->data = [];            
        }
        else {
            $this->data = $list['data'][0];
        }
    }
    public function loadOfField(ZohoDataManager $collection, $field, $value) {  //v6
        $list = $collection->find([$field=>$value]);        
        if(!isset($list['data'][0])) {
            $this->data = [];
            return false;
        }
        else {
            $this->data = $list['data'][0];
            return true;
        }
    }
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
    public function isEmpty() {
        return ($this->data ? 0 : 1);
    }
}