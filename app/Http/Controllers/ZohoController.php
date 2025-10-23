<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Modules\Zoho\Zoho;
use Illuminate\Http\Request;
use App\Modules\Zoho\Datatypes\ZohoContact;

class ZohoController extends Controller
{
    public function token()
    {
        $zoho = new Zoho(config('app.zoho_client_id'), config('app.zoho_client_secret'));
        $redirect = route('zoho.token');
        $zoho->authorize($redirect);
    }
    public function test()
    {
        $zoho = new Zoho();
        $zoho->setApiVersion('v6');
        //$contacts = $zoho->contacts->count();
        //find(['Created_Time' => "2020-01-01T00:00:00+00:00"], 'and', 'not_equal', null, 1);        
        $contacts = $zoho->contacts->all(array_column(ZohoContact::getTitles(),'field'),1);
        echo json_encode($contacts, JSON_UNESCAPED_UNICODE);
    }    
}
