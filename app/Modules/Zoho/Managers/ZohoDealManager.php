<?php
namespace App\Modules\Zoho\Managers;

class ZohoDealManager extends ZohoDataManager {
    protected function url_add()
    {
        return "/crm/".$this->zohoApi->apiVersion()."/Deals";
    }
    protected function url_update()
    {
        return "/crm/".$this->zohoApi->apiVersion()."/Deals";
    }
    protected function url_remove()
    {
        return "/crm/".$this->zohoApi->apiVersion()."/Deals";
    }
    protected function url_find()
    {
        return "/crm/".$this->zohoApi->apiVersion()."/Deals/search";
    }
    protected function url_count() {
        return "/crm/".$this->zohoApi->apiVersion()."/Deals/actions/count";
    }
    protected function moduleName() {
        return 'Deals';
    }
}