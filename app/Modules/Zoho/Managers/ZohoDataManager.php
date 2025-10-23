<?php
namespace App\Modules\Zoho\Managers;

use Illuminate\Support\Facades\Log;
use App\Modules\Zoho\Datatypes\ZohoDataType;

abstract class ZohoDataManager {
    protected $zohoApi;
    protected $records;
    abstract protected function url_add();
    abstract protected function url_update();
    abstract protected function url_remove();
    abstract protected function url_find();
    abstract protected function url_count();
    abstract protected function moduleName();
    protected function url_fields() {
        return "/crm/".$this->zohoApi->apiVersion()."/settings/modules/".$this->moduleName();
    }
    public function getZoho() {
        return $this->zohoApi;
    }
    public function __construct($zohoApi)
    {
        $this->zohoApi = $zohoApi;
        $this->records = [];
    }
    public function multiAdd(array $zohoDataItems) {
        $data = [];
        foreach($zohoDataItems as $$item) {
            $data[] = $item->getArrayData();
        }
        return $this->zohoApi->post_request(['data'=>$data],$this->url_add());        
    }
    public function add(ZohoDataType $dataItem, $execTrigger = false) {
        $data = [$dataItem->getArrayData()];
        $sending = ['data'=>$data];
        if($execTrigger) $sending['trigger'] = ["workflow","blueprint","approval"];
        Log::channel('daily')->info("sending: ".json_encode($sending, JSON_UNESCAPED_UNICODE));
        return $this->zohoApi->post_request($sending,$this->url_add());        
    }
    public function addEntities(array $data, $execTrigger = false) {  // data = [ array dataItem,  array dataItem, ...   ]              
        $sending = ['data'=>$data];
        if($execTrigger) $sending['trigger'] = ["workflow","blueprint","approval"];
        Log::channel('daily')->info("sending: ".json_encode($sending, JSON_UNESCAPED_UNICODE));
        return $this->zohoApi->post_request($sending,$this->url_add());        
    }
    public function addNote($title, $content, $parentId) {
        $url = $this->url_add()."/{$parentId}/Notes";
        $note = [
            'Note_Title'=>$title,
            'Note_Content'=>$content,
            'Parent_Id'=>$parentId,
            'se_module'=>$this->moduleName()
        ];
        $sending = ['data'=>[$note]];
        return $this->zohoApi->post_request($sending,$url);
    }
    public function update(ZohoDataType $dataItem, $execTrigger = false) {
        $data = [$dataItem->getArrayData()];
        $sending = ['data'=>$data];
        Log::channel('daily')->info('zoho update request = '.json_encode($sending));
        if($execTrigger) $sending['trigger'] = ["workflow","blueprint","approval"]; //"workflow","blueprint","approval"        
        return $this->zohoApi->put_request($sending,$this->url_update());
    }
    public function updateArray(array $dataItem, $execTrigger = false) {
        $data = [$dataItem];
        $sending = ['data'=>$data];
        if($execTrigger) $sending['trigger'] = ["workflow","blueprint","approval"]; //"workflow","blueprint","approval"        
        return $this->zohoApi->put_request($sending,$this->url_update());
    }
    public function updateEntities(array $data, $execTrigger = false) {   // data = [ array dataItem,  array dataItem, ...   ]        
        $sending = ['data'=>$data];
        if($execTrigger) $sending['trigger'] = ["workflow","blueprint","approval"]; //"workflow","blueprint","approval"        
        return $this->zohoApi->put_request($sending,$this->url_update());
    }
    public function remove(array $ids) {
        return $this->zohoApi->delete_request(['ids'=>implode(',',$ids),'wf_trigger'=>'true'],$this->url_remove());
    }
    
    public function find(array $filters, $connectRuler = 'and', $matchingRule = 'equals', $fields = null, $page = null) {
        /*
        The supported operators are 
        equals, starts_with, in, not_equal, greater_equal, greater_than, less_equal, less_than and between.
        */
        $queryFilters = [];
        //criteria=((Last_Name:equals:Burns%5C%2CB)and(First_Name:starts_with:M))
        $i=0;        
        foreach($filters as $k=>$v) {
            if(is_array($matchingRule)) {
                $queryFilters[] = "($k:".$matchingRule[$i].":$v)";
            }    
            else {
                $queryFilters[] = "($k:$matchingRule:$v)";
            }
            $i++;
        }
        $strFilters = implode($connectRuler,$queryFilters);
        $data = ['criteria' => "($strFilters)"];
        if($fields) $data['fields'] = implode(',',$fields);
       // if($per_page) $data['per_page'] = $per_page;
       if($page) $data['page'] = $page;
        return $this->zohoApi->get_request($this->url_find(),$data);
    }
    public function findArrayOfPropertyArray($propName,array $propertyArray) {
        $connectRuler = 'or';
        $queryFilters = [];        
        foreach($propertyArray as $value) {
            $queryFilters[] = "($propName:equals:$value)";
        }
        $strFilters = implode($connectRuler,$queryFilters);
        if(empty($strFilters)) return [];
       // Log::channel('daily')->info('findArrayOfPropertyArray query = '.print_r($strFilters,true));
        $result = $this->zohoApi->get_request($this->url_find(),['criteria' => "($strFilters)"]);
       // Log::channel('daily')->info('findArrayOfPropertyArray = '.print_r($result,true));
        $fresult = $result['data'] ?? [];
        return $fresult;
    }
    public function filter($getParams) {
        return $this->zohoApi->get_request($this->url_find(),$getParams);
    }
    public function all(array $fields, $page=1, $countOnPage=10) {        
        $getParams['fields'] = implode(',',$fields);
        $getParams['per_page'] = $countOnPage;
        $getParams['page'] = $page;
        return $this->zohoApi->get_request($this->url_add(),$getParams);
    }
    public function count() {
        return $this->zohoApi->get_request($this->url_count());
    }
    public function relatedRecords($id, $relationName,$fields='id,name') {
        $url = $this->url_add()."/{$id}/{$relationName}?fields=$fields";
        return $this->zohoApi->get_request($url);
    }
    public function getRecord($id) {
        $url = $this->url_add()."/{$id}";
        return $this->zohoApi->get_request($url);
    }
    public function getFirstRecord($id, $clearCash = false) {
        if(isset($this->records[$id]) && !$clearCash) {
            return $this->records[$id];
        }
        $arr = $this->getRecord($id);
        if(isset($arr['data'][0])) {
            $this->records[$id] = $arr['data'][0];
            return $this->records[$id];
        }
        return null;
    }
}