<?php
namespace App\Modules\Zoho;

use App\Modules\Zoho\Datatypes\ZohoPartner;
use DateTime;
use DateTimeZone;
use App\Models\Widget;
use App\Models\CrmDeal;
use App\Modules\Core\Core;
use App\Models\ExchangeRate;
use App\Modules\Payment\Exchange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Modules\Converter\Converter;
use App\Modules\Zoho\Datatypes\ZohoDeal;
use App\Modules\Zoho\Datatypes\ZohoLead;
use App\Modules\Zoho\Datatypes\ZohoContact;
use App\Modules\Zoho\Datatypes\ZohoInvoice;
use App\Modules\Zoho\Datatypes\ZohoPayment;
use App\Modules\Zoho\Datatypes\ZohoProduct;
use App\Modules\Zoho\Datatypes\ZohoOrderItem;
use App\Modules\Zoho\Managers\ZohoDataManager;

class ZohoWizard extends Zoho {
    const LEAD_PIPELINE = '652581000003690191';
    //const PIPELINE = '652581000000013342';
    const PIPELINE = 'Основна воронка';
    const STAGE_LEAD = 'Нова угода';
    const STAGE_PAY = 'Заявки';
    public function __construct()
    {
        $optId = DB::table('options')->where('opt_key','zoho_client_id')->first();
        $optSecret = DB::table('options')->where('opt_key','zoho_client_secret')->first();
        parent::__construct($optId->opt_value,$optSecret->opt_value);
    }
    public static function fullTrim($str) { //обрезка пробелов и невидимых символов                                                   
        return trim(preg_replace('/\s{2,}/', '', $str));                                                      
    }
    // если связующий модуль не содержит доп. данных. А если содержит, надо доработать ф-ю
    public function udpateMultiLookup(ZohoDataManager $collection, $findField, $findValue,$itemField,array $saveItemIds) {
        $dcollection = $collection->find([$findField=>$findValue]);
        Log::channel("daily")->info("udpateMultiLookup $findField => $findValue dcollection = ".print_r($dcollection, true));
        $convItems = [];
        if($dcollection && isset($dcollection['data'])) {
            Log::channel("daily")->info("udpateMultiLookup dcollection YES");
            foreach($dcollection['data'] as $item) {
                $convItems[$item[$itemField]['id']] = $item['id'];
            }
        }
        $updItems = [];
        $addItems = [];
        $updItemIds = [];
        foreach($saveItemIds as $saveId) {
            if(isset($convItems[$saveId])) {                            
                //$updItems[] = ['id'=>$saveId];
                $updItemIds[] = $saveId;                
            }
            else {
                $addItems[] = [$itemField=>['id'=>$saveId], $findField=>['id'=>$findValue]];                
            }
        }
       // if($updItems) $collection->updateEntities($updItems);  //этот метод будет иметь смысл, когда будут доп. данные в модуле
        if($addItems) {
           $addResult = $collection->addEntities($addItems);
           Log::channel("daily")->info("udpateMultiLookup addResult ".json_encode($addItems)." - ".json_encode($addResult));
        }
        //удалить левые
        $deleteIds = [];
        foreach($convItems as $itemId=>$linkRecId) {
            if(!in_array($itemId, $updItemIds)) {
                $deleteIds[] = $linkRecId;
            }
        }
        if($deleteIds) {
            Log::channel('daily')->info("Delete Teachers: ".json_encode($deleteIds));
            $delResult = $collection->remove($deleteIds);
            Log::channel('daily')->info("Delete Teachers  result: ".json_encode($delResult));
        } 
    }
    public function addDealsFromCrmDeal($startMoment, $endMoment=null) {
        $query = CrmDeal::where('created_at','>',$startMoment);
        if($endMoment) $query = $query->where('created_at','<',$endMoment);
        $crmDeals = $query->get();
        foreach($crmDeals as $deal) {
            if($deal->crm_id) continue;
            $zohoContact = new ZohoContact();  
            $zohoContact->setEmail($deal->client_email)->setPhone($deal->client_phone);
            $name = $this->fullTrim($deal->client_name);
            $nameArr = explode(' ',$name);
            if(count($nameArr) > 1 && !empty($nameArr[1])) {
                $zohoContact->setFirstName($nameArr[0])->setLastName($nameArr[1]);                
            }
            else {
                $zohoContact->setFirstName($nameArr[0])->setLastName('User');                
            }
            $resultContact = $this->contacts->add($zohoContact);
            print_r($resultContact);
            $zohoContact->setId($resultContact['data'][0]['details']['id']);
            $widget = $deal->widget;
            $zohoDeal = new ZohoDeal();        
            $zohoDeal->setContact($zohoContact)->setLead_Source("Заявка с сайта")->setStage('Нова угода')
            ->setOwner($zohoContact->Owner)->setDealName($widget->offer ?? $widget->name)
            ->setDescription($widget->name)->setPhone(''.$deal->client_phone);
            $add_tags = [];
            if($widget->tags) {
                $tags = explode(',',$widget->tags);
                
                foreach($tags as $tag) {
                    if(!empty(trim($tag))) {
                        $add_tags[] = ['name'=>trim($tag)];
                    }
                }                
            }
            $add_tags[] = ['name'=>"восстановлен из бд"];
            if($add_tags) $zohoDeal->setTag($add_tags);
            
            if($widget->warmth_of_lead_id) {
                $zohoDeal->setWarmthOfLead((string) $widget->warmth_of_lead);
            }   
            $teacher = $widget->teacher();
            if($teacher) { //автор из виджета
                $zohoDeal->setTeacher($teacher->deal_teacher_name);
            }
            if($widget->crm_product_id) {
                $zohoDeal->addProduct($widget->crm_product_id);
            }       
            
            $this->setApiVersion('v6');  
            $zohoDeal->setPipeline(self::PIPELINE); 
            /*
            if($widget->type === 'lead' ) {
                $zohoDeal->setPipeline(self::LEAD_PIPELINE);            
                //$zohoDeal->setPipeline(self::PIPELINE);
            }
            else {
                $zohoDeal->setPipeline(self::PIPELINE);
            }  */   
            //бюджет            
            if (time() > strtotime(date("Y-m-d 23:59:59", strtotime($widget->earlyDate)))){
                $price = $widget->fullPrice;
            } else {
                $price = $widget->earlyPrice;
            }
            $result['price'] = $price;
            // выставление суммы с конвертацией в гривну. 
            $dealBudget = Converter::getSumUahToDate(date("Ymd"), $price, $widget->currency);
            $zohoDeal->setAmount("".$dealBudget);
           
            if($widget->communityId) {
                $zohoDeal->setCommunityParticipation(true);
            }
            Log::channel('daily')->info('deal send data '.print_r($zohoDeal, true));                
            $resultDeal = $this->deals->add($zohoDeal,true);
            Log::channel('daily')->info('result deal'.print_r($resultDeal,true));
            $this->setApiVersion('v2');
            print_r($resultDeal);
            $zohoDeal->setId($resultDeal['data'][0]['details']['id']);

            $deal->crm_id = $resultDeal['data'][0]['details']['id'];
            $deal->save();   
           // break;         
        }
    }
    public function addContactAndDeal($postData) {
        $result = [];
        $firstName = null;
        $lastName = null;
        $price = null;

        $widget = Widget::where('id',$postData['id'])->first();   
        
        //защита от дублей сделок
        
        $dbCrmDeal = CrmDeal::where(['client_name'=>$postData['name'], 
        'client_phone'=>$postData['phone'], 'client_email'=>$postData['email'], 
        'widget_id'=>$widget->id])->orderByDesc('id')->first();
        $moment = time();
        if($dbCrmDeal) {
            $deltaMoment = abs($moment - $dbCrmDeal->moment);
            if($deltaMoment > 59) $dbCrmDeal = null; // прошла минута. Можно создавать сделку повторно
        }
        $isDealInDb = true;
        if(!$dbCrmDeal) {
            $isDealInDb = false;
            $dbCrmDeal = CrmDeal::create(['client_name'=>$postData['name'], 
                'client_phone'=>$postData['phone'], 'client_email'=>$postData['email'], 
                'widget_id'=>$widget->id,'moment'=>$moment
            ]);
        }                                                                                                                                                                                                                                                                                                      // if(time() > 1719500249) return $result;
        //-----параметры для последующей передачи в ссылке
        $result['redirect_params'] = ['email'=>$postData['email'], 'phone'=>$postData['phone'], 'name'=>$postData['name']];
        foreach($postData as $post_k => $post_v) {
            if(strpos($post_k,'utm_')!==false) $result['redirect_params'][$post_k] = $post_v;
        }        
        //--------------------------------------------
        $zohoContact = new ZohoContact();  
        $zohoContact->setEmail($postData['email'])->setPhone($postData['phone']);
        $postData['name'] = $this->fullTrim($postData['name']);
        $nameArr = explode(' ',$postData['name']);
        if(count($nameArr) > 1 && !empty($nameArr[1])) {
            $zohoContact->setFirstName($nameArr[0])->setLastName($nameArr[1]);
            $firstName = $nameArr[0];
            $lastName = $nameArr[1];
        }
        else {
            $zohoContact->setFirstName($nameArr[0])->setLastName('User');
            $firstName = $nameArr[0];
            $lastName = 'User';
        }
        $resultContact = $this->contacts->add($zohoContact);
        $zohoContact->load($this->contacts, $resultContact['data'][0]['details']['id']);
        $result['zoho_contact_id'] = $zohoContact->id;
        //$zohoContact->setId($resultContact['data'][0]['details']['id']);
        
        if($isDealInDb) {
            //такая сделка уже есть
            if($widget->type === 'pay' && $widget->fullPrice > 0 )
            {
                $zohoDeal = new ZohoDeal();
                $zohoDeal->load($this->deals,$dbCrmDeal->crm_id);
                $invoices = $zohoDeal->getInvoices($this->deals);
                if($invoices) {
                    $result['pay_redirect'] = route('payment.invoice',['invoice_id'=>$invoices[0]->id]);                    
                }
            }
            return $result; //прерываем выполнение функции
        }
        
        //------------------------------------------
        $zohoDeal = new ZohoDeal();        
        $zohoDeal->setContact($zohoContact)->setLead_Source("Заявка с сайта")->setDealName($widget->offer ?? $widget->name)
        ->setDescription($widget->name)->setPhone(''.$postData['phone'])->setEventType();
        $zohoDeal->setPipeline(self::PIPELINE);
        $this->setApiVersion('v6');
        if($widget->type === 'lead' ) { //ыефпу
            $zohoDeal->setStage(self::STAGE_LEAD);            
        }
        else {
            $zohoDeal->setStage(self::STAGE_PAY); 
        }

        //event_type
        if($zohoContact->Owner) {
            $zohoDeal->setOwner($zohoContact->Owner);
        }
        if(isset($postData['delivery'])) {
            $zohoDeal->setDelivery(''.$postData['delivery']);
        }
        if($widget->tags) {
            $tags = explode(',',$widget->tags);
            $add_tags = [];
            foreach($tags as $tag) {
                if(!empty(trim($tag))) {
                    $add_tags[] = ['name'=>trim($tag)];
                }
            }
            if($add_tags) $zohoDeal->setTag($add_tags);
        }
        if(!empty($postData['utm_content']))  $zohoDeal->setUtmContent($postData['utm_content']);
        if(!empty($postData['utm_source']))  $zohoDeal->setUtmSource($postData['utm_source']);
        if(!empty($postData['utm_medium']))  $zohoDeal->setUtmMedium($postData['utm_medium']);
        if(!empty($postData['utm_term']))  $zohoDeal->setUtmTerm($postData['utm_term']);
        if(!empty($postData['utm_group']))  $zohoDeal->setUtmGroup($postData['utm_group']);
        if(!empty($postData['utm_campaign']))  $zohoDeal->setUtmCampaign($postData['utm_campaign']);
        if(!empty($postData['referrer']))  $zohoDeal->setReferer($postData['referrer']);  
        if(!empty($postData['gcpc'])) {
            $zohoPartner = new ZohoPartner();
            if($zohoPartner->loadOfRefCode($this->partners, $postData['gcpc'])) {
                $zohoDeal->setPartner($zohoPartner);
            }
        }
        if($widget->warmth_of_lead_id) {
            $zohoDeal->setWarmthOfLead((string) $widget->warmth_of_lead);
        }   
        $teacher = $widget->teacher();
        if($teacher) { //автор из виджета
            $zohoDeal->setTeacher($teacher->deal_teacher_name);
        }
        if($widget->crm_product_id) {
            $zohoDeal->addProduct($widget->crm_product_id);
        }       
     
        //бюджет            
        if (time() > strtotime(date("Y-m-d 23:59:59", strtotime($widget->earlyDate)))){
            $price = $widget->fullPrice;
        } else {
            $price = $widget->earlyPrice;
        }
        $result['price'] = $price;
        // выставление суммы с конвертацией в гривну. 
        $dealBudget = Converter::getSumUahToDate(date("Ymd"), $price, $widget->currency);
        $zohoDeal->setAmount("".$dealBudget);
       
        if($widget->communityId) {
            $zohoDeal->setCommunityParticipation(true);
        }
        Log::channel('daily')->info('deal send data '.print_r($zohoDeal, true));                
        $resultDeal = $this->deals->add($zohoDeal,true);
        Log::channel('daily')->info('result deal'.print_r($resultDeal,true));
        $this->setApiVersion('v2');
        $zohoDeal->setId($resultDeal['data'][0]['details']['id']);
        //-------------------------------------------
        $dbCrmDeal->crm_id = $resultDeal['data'][0]['details']['id'];
        $dbCrmDeal->save();
        
        if($price > 0) {
            Log::channel('daily')->info('before create invoice ...');
            $orderItem = new ZohoOrderItem();
            $orderItem->setProduct($widget->crm_product_id)->setQuantity(1)->setProductDescription($widget->name)
            ->setUnitPrice($dealBudget);
            
            $zohoInv = new ZohoInvoice();
            $zohoInv->setProduct_Details([$orderItem->getArrayData()])->setSubject("Оплата Угоди ".$zohoDeal->Deal_Name)
            ->setSub_Total($dealBudget)->setGrand_Total($dealBudget)->setStatus('Виставлений')->setDeal($zohoDeal)
            ->setInvoice_Payment_method( (string) $widget->product->payment_method );
            Log::channel('daily')->info('prepare invoice data '.print_r($zohoInv,true));
            $resultInvoice = $this->invoices->add($zohoInv);         
            Log::channel('daily')->info("invoice 82 =".print_r($resultInvoice,true));
            if(!isset($resultInvoice['data'][0]['details']['id'])) {
               // Core::
            }
            $zohoInv->setId($resultInvoice['data'][0]['details']['id']);
            $invPayUrl = route('payment.invoice',['invoice_id'=>$resultInvoice['data'][0]['details']['id']]);
            $result['pay_redirect'] = $invPayUrl;
            $zohoInv->setInvoice_Url($invPayUrl);            
            $this->invoices->update($zohoInv);
            Log::channel('daily')->info('invoice = '.print_r($resultInvoice,true));
        }
        return $result;
    }

    public function paymentCheck(ZohoPayment $zohoPayment, $amount, $currency) { //проверим данные ЗОХО из платежа на соответствие сумме и валюте
        return ($zohoPayment->Currency === $currency) && 
            ((float) $zohoPayment->Sum_of_payment <= (float) $amount) &&
            ($zohoPayment->Payment_status === 'Створений');
    }
    public function paymentComplete(ZohoPayment $zohoPayment) {  //переводит оплату в статус Успешно, а вместе с ним и Счет. 
        //А сделка должна перейти автоматически в нужную воронку
        //$zohoPayment
        $zohoInvoice = new ZohoInvoice();
        $zohoInvoice->load($this->invoices, $zohoPayment->getInvoiceId());
        $zohoPayment->setPayment_status('Оплачений');
        $zohoPayment->setPayment_date_custom(date('Y-m-d\TH:i:s'));
        $res = $this->payments->update($zohoPayment, true);        
    }

    public function updateExchangeRate() { //запуск по крону процедуры обновления
       
        ExchangeRate::updateRateFromApi();
        $widgets = Widget::where('is_active',1)->where('fullPrice','>',0)->get();
        $zoho = new ZohoWizard();
        $creates = [];
        $updates = [];
        foreach($widgets as $widget) {
            if($widget->crm_product_id) $updates[] = $widget;
            else $creates[] = $widget;
        }
        $cur_update = [];
        foreach($updates as $widg) {
            $zohoProduct = new ZohoProduct();
            $price = 0;
            if($widg->fullPrice && $widg->currency) $price = $widg->fullPrice * ExchangeRate::getActualRateValue($widg->currency);
            $zohoProduct->setProduct_Name($widg->name)->setDescription($widg->offer) //Converter::getSumUahToDate(date("Ymd"), $widg->fullPrice, $widg->currency)
                ->setUnit_Price($price)->setProduct_Code('' . $widg->id);
            if($widg->lms_id) {
                $zohoProduct->setLmsId(''.$widg->lms_id);
            }
            if($widg->communityId) {
                $zohoProduct->setCommunityParticipation(true);
            }
            if($widg->crm_offer_name) {
                $zohoProduct->setNameForMail($widg->crm_offer_name);
            }
            $zohoProduct->setId($widg->crm_product_id);

           // $zoho->products->update($zohoProduct);
            
            $cur_update[] = $zohoProduct->getArrayData();
            if(count($cur_update)>19) {
                $result = $zoho->products->updateEntities($cur_update);
                print_r($result);
                $cur_update = [];
                echo "\n ===================================================================\n" ;
            }
            
        }
        
        if(count($cur_update)>0) {
            $result = $zoho->products->updateEntities($cur_update);
            print_r($result);
            $cur_update = [];
        }
        

        $a = file_get_contents("https://www.zohoapis.eu/crm/v2/functions/upd_exchange_rate/actions/execute?auth_type=apikey&zapikey=1003.4a3040ee728455ef8adc2fe0f18a4532.461c4e94c5a4e62a50ba52322dc141b4");
        echo "\na = ".$a;
    }

    
}



/* старый код распределения сделки и лида
if($widget->type === 'lead' ) 
        {
            $zohoLead = new ZohoLead();
            $zohoLead->setFirst_Name($firstName)->setLast_Name($lastName)->setPhone($postData['phone'])
            ->setLead_Status('Нова заявка')->setProduct_Name($widget->name)->setDescription($widget->name)
            ->setEmail($postData['email'])->setLead_Source('Заявка с сайта');
            if($widget->warmth_of_lead_id) {
                $zohoLead->setWarmth_of_lead((string) $widget->warmth_of_lead);
            }
            if($widget->speakerId) {
                $zohoLead->setSpeaker((string) $widget->speaker);
            }

            if(!empty($postData['utm_content']))  $zohoLead->setUtmContent($postData['utm_content']);
            if(!empty($postData['utm_source']))  $zohoLead->setUtmSource($postData['utm_source']);
            if(!empty($postData['utm_medium']))  $zohoLead->setUtmMedium($postData['utm_medium']);
            if(!empty($postData['utm_term']))  $zohoLead->setUtmTerm($postData['utm_term']);
            if(!empty($postData['utm_group']))  $zohoLead->setUtm_campaign($postData['utm_group']);
            if(!empty($postData['utm_campaign']))  $zohoLead->setUtm_campaign($postData['utm_campaign']);
            if(!empty($postData['referrer']))  $zohoLead->setReferer($postData['referrer']); 

            $resultDeal = $this->leads->add($zohoLead);            
        }
        else {
            $zohoDeal = new ZohoDeal();        
            $zohoDeal->setContact($zohoContact)->setPipeline(self::PIPELINE)->setLead_Source("Заявка с сайта")
            ->setOwner($zohoContact->Owner)->setDealName($widget->offer ?? $widget->name)->setDescription($widget->name);
            if(!empty($postData['utm_content']))  $zohoDeal->setUtmContent($postData['utm_content']);
            if(!empty($postData['utm_source']))  $zohoDeal->setUtmSource($postData['utm_source']);
            if(!empty($postData['utm_medium']))  $zohoDeal->setUtmMedium($postData['utm_medium']);
            if(!empty($postData['utm_term']))  $zohoDeal->setUtmTerm($postData['utm_term']);
            if(!empty($postData['utm_group']))  $zohoDeal->setUtmGroup($postData['utm_group']);
            if(!empty($postData['utm_campaign']))  $zohoDeal->setUtmCampaign($postData['utm_campaign']);
            if(!empty($postData['referrer']))  $zohoDeal->setReferer($postData['referrer']);        
            if($widget->warmth_of_lead_id) {
                $zohoDeal->setWarmthOfLead((string) $widget->warmth_of_lead);
            }
            //бюджет            
            if (time() > strtotime(date("Y-m-d 23:59:59", strtotime($widget->earlyDate)))){
                $price = $widget->fullPrice;
            } else {
                $price = $widget->earlyPrice;
            }
            $result['price'] = $price;
            // выставление суммы с конвертацией в гривну. Отключено
            $dealBudget = Converter::getSumUahToDate(date("Ymd"), $price, $widget->currency);
            $zohoDeal->setAmount("".$dealBudget);
            
            //заменено на выставление в валюте товара
           // $zohoDeal->setAmount("".$price)->setCurrency()
            if($widget->communityId) {
                $zohoDeal->setCommunityParticipation(true);
            }
            $resultDeal = $this->deals->add($zohoDeal);
            $zohoDeal->setId($resultDeal['data'][0]['details']['id']);
        }  
*/

/* старый метод теста создания контакта и сделки
public function addContactAndDeal2() {  //тестовая
        $return_arr = [];
        $zohoContact = new ZohoContact();        
        $zohoContact->setEmail('sergtest@gmail.com')->setPhone('+380441234567');
        $zohoContact->setFirstName('Sergey')->setLastName('Test');        
        $resultContact = $this->contacts->add($zohoContact);
        $return_arr['create_contact'] = $resultContact;
        $zohoContact->setId($resultContact['data'][0]['details']['id']);
        $widgId = 7;
        $widget = Widget::where('id',$widgId)->first();        
        $zohoDeal = new ZohoDeal();        
        $zohoDeal->setContact($zohoContact)->setPipeline(self::PIPELINE)   ///->setProductDealCustom(['id'=>$widget->crm_product_id])
        ->setDealName(mb_strimwidth('Sergey Test. '.$widget->name,0,40,'...'));

        //бюджет
        $price = null;
        if (time() > strtotime(date("Y-m-d 23:59:59", strtotime($widget->earlyDate)))){
            $price = $widget->fullPrice;
        } else {
            $price = $widget->earlyPrice;
        }
        $dealBudget = Converter::getSumUahToDate(date("Ymd"), $price, $widget->currency);
        $zohoDeal->setAmount($dealBudget);
        $resultDeal = $this->deals->add($zohoDeal);
        $zohoDeal->setId($resultDeal['data'][0]['details']['id']);
        $return_arr['create_deal'] = $resultDeal;
        Log::channel('daily')->info("add zoho deal: ".print_r($resultDeal,true));  
        //счет
        if($price) {
            $orderItem = new ZohoOrderItem();
            $orderItem->setProduct($widget->crm_product_id)->setQuantity(1)->setProductDescription($widget->name)
            ->setUnitPrice($dealBudget);
            
            $zohoInv = new ZohoInvoice();
            $zohoInv->setProduct_Details([$orderItem->getArrayData()])->setSubject("Рахунок Угоди ".$zohoDeal->Deal_Name)
            ->setSub_Total($dealBudget)->setStatus('Виставлений')->setDeal($zohoDeal);
            $invRes = $this->invoices->add($zohoInv);
            $return_arr['create_invoice'] = $invRes;
            
            //а еще создаем Оплату (Платеж) связанный со счетом
            $zohoPaym = new ZohoPayment();
            $zohoPaym->setEmail('sergtest@gmail.com')->setName("Оплата рахунку ".$zohoInv->Subject)           
            ->setSum_of_payment($dealBudget)->setPayments_invoice_related(['id'=>$invRes['data'][0]['details']['id']]);
            $return_arr['create_payment'] = $this->payments->add($zohoPaym);
        }
        return $return_arr;
    }
*/