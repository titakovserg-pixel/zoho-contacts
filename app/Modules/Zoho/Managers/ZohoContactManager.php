<?php
namespace App\Modules\Zoho\Managers;

class ZohoContactManager extends ZohoDataManager {
    protected function url_add()
    {
        return "/crm/".$this->zohoApi->apiVersion()."/Contacts";
    }
    protected function url_update()
    {
        return "/crm/".$this->zohoApi->apiVersion()."/Contacts";
    }
    protected function url_remove()
    {
        return "/crm/".$this->zohoApi->apiVersion()."/Contacts";
    }
    protected function url_find()
    {
        return "/crm/".$this->zohoApi->apiVersion()."/Contacts/search";
    }
    protected function url_count() {
        return "/crm/".$this->zohoApi->apiVersion()."/Contacts/actions/count";
    }
    protected function moduleName() {
        return 'Contacts';
    }
}