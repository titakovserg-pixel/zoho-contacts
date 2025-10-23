<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Modules\Zoho\Zoho;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Modules\Zoho\Datatypes\ZohoContact;

class EntityController extends Controller
{
    public function contactList(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $zoho = new Zoho();
        $zoho->setApiVersion('v6');
        $contacts = $zoho->contacts->all(array_column(ZohoContact::getTitles(),'field'),$page);       
        $data = $contacts['data'] ?? null;
        if(!$data) abort(404);
        $pagination = $contacts['info'];
        $zCount = $contacts = $zoho->contacts->count();
        $pagination['full_count'] = $zCount['count'];
        $pagination['route_name'] = 'contacts';
        return Inertia::render('Entities/EntityList', [
            'titles' => ZohoContact::getTitles(),
            'data' => $data,
            'pagination'=>$pagination
        ]);
    }
    public function contactAdd(Request $request) {
        // Получаем список полей
        $titles = ZohoContact::getTitles();

        // Создаём пустой массив для новой сущности
        $emptyEntity = [];
        foreach ($titles as $elem) {
            $emptyEntity[$elem['field']] = '';
        }

        return Inertia::render('Entities/EntityForm', [
            'titles' => $titles,
            'entity' => $emptyEntity,
            'save_route'=>route('contact.create')
        ]);
    }
    public function contactCreate(Request $request)
    {        
        $zoho = new Zoho();
        $zoho->setApiVersion('v6');
        $zohoContact = new ZohoContact();                
        foreach($request->all() as $k=>$v) {
            $zohoContact->$k = $v;
        }        
        $zohoContact->Created_Time = date("Y-m-dTH:i:s+00:00"); 
        try {       
            $result = $zoho->contacts->add($zohoContact,true);
            Log::channel('daily')->info('update result: '.print_r($result,1));       

            if (isset($result['data'][0]['code']) && $result['data'][0]['code'] === 'SUCCESS') {
                return Inertia::location(route('contacts'));
            }
            if (isset($result['data'][0]['details']['api_name'])) {
                $apiName = $result['data'][0]['details']['api_name'];                
                $titles = ZohoContact::getTitleCaptions();
                $fieldName = $titles[$apiName] ?? $apiName;
                return back()->withErrors(['zoho' => "Ошибочное значение в поле $fieldName"])->withInput();
            }
            return back()->withErrors(['zoho' => 'Неизвестная ошибка Zoho'])->withInput();
        }
        catch(Exception $e) {
            return back()->withErrors(['zoho' => $e->getMessage()])->withInput();
        }        
    }

    public function contactEdit($id)
    {        
        $zoho = new Zoho();
        $zoho->setApiVersion('v6');
        $zohoContact = new ZohoContact();
        $zohoContact->load($zoho->contacts,$id);
        //echo json_encode($zohoContact->getArrayData());        

        return Inertia::render('Entities/EntityForm', [
            'titles' => ZohoContact::getTitles(),
            'entity' => $zohoContact->getArrayData(),
            'save_route'=>route('contact.update',$id)
        ]);
    }

    public function contactUpdate(Request $request, $id)
    {        
        Log::channel('daily')->info("contactUpdate1: ".print_r($request->all(),1));
        $zoho = new Zoho();
        $zoho->setApiVersion('v6');
        $zohoContact = new ZohoContact();
        $zohoContact->load($zoho->contacts,$id);
        foreach($request->all() as $k=>$v) {
            $zohoContact->$k = $v;
        }
        try {
            $result = $zoho->contacts->update($zohoContact); 

            Log::channel('daily')->info('update result: '.print_r($result,1));       

            if (isset($result['data'][0]['code']) && $result['data'][0]['code'] === 'SUCCESS') {
                return Inertia::location(route('contacts'));
            }
            if (isset($result['data'][0]['details']['api_name'])) {
                $apiName = $result['data'][0]['details']['api_name'];                
                $titles = ZohoContact::getTitleCaptions();
                $fieldName = $titles[$apiName] ?? $apiName;
                return back()->withErrors(['zoho' => "Ошибочное значение в поле $fieldName"])->withInput();
            }
            return back()->withErrors(['zoho' => 'Неизвестная ошибка Zoho'])->withInput();
        }
        catch(Exception $e) {
            return back()->withErrors(['zoho' => $e->getMessage()])->withInput();
        }               
        
    }
    public function contactRemove($id)
    {
        $zoho = new Zoho();
        $zoho->setApiVersion('v6');
        $zoho->contacts->remove([$id]);
        return redirect()->route('contacts');
    }

}
