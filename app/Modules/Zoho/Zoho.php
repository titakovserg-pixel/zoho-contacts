<?php
namespace App\Modules\Zoho;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Modules\Zoho\Managers\ZohoDealManager;
use App\Modules\Zoho\Managers\ZohoContactManager;

class Zoho {
    protected $api_domain; //заполняется при чтении токена
    protected $auth_domen = 'accounts.zoho.eu';
    protected $client_id;
    protected $client_secret;
    protected $versApi;
    //collections  ------------    
    public ?ZohoDealManager $deals;    
    public ?ZohoContactManager $contacts;   
    //--------------------------
    public function apiVersion() {
        return $this->versApi;
    }
    public function setApiVersion($version) {
        $this->versApi = $version;
    }
    public function __construct($client_id=null, $client_secret=null)
    {
       $this->versApi = 'v2';
       $this->client_id = $client_id;
       $this->client_secret = $client_secret;       
       $this->deals = new ZohoDealManager($this);
       $this->contacts = new ZohoContactManager($this);       
    }
    public function __destruct() {       
       $this->deals = null;
       $this->contacts = null;       
    }
    public function generateToken($code, $redirect_uri) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,'https://'.$this->auth_domen."/oauth/".$this->apiVersion().'/token?client_id='.$this->client_id.'&grant_type=authorization_code&client_secret='.$this->client_secret.'&redirect_uri='.$redirect_uri.'&code='.$code);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close ($ch);        
        return json_decode($server_output);
    }
    public function refreshToken($refresh_token) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,'https://'.$this->auth_domen.'/oauth/'.$this->apiVersion().'/token?client_id='.$this->client_id.'&grant_type=refresh_token&client_secret='.$this->client_secret.'&refresh_token='.$refresh_token);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close ($ch);        
        return json_decode($server_output);
    }
    public function get_request($action,$data=null) {	
        $token = $this->getToken();
        $curl = curl_init();
        if($data) {
            $action .='?'.http_build_query($data);            
        }        
        curl_setopt($curl, CURLOPT_URL, $this->api_domain.$action);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Content-type: application/json",
                "Accept: application/json",
                "Cache-Control: no-cache",
                "Pragma: no-cache",
                'Authorization: Zoho-oauthtoken ' . $token)
        );
    
        $response = curl_exec($curl);        
        curl_close($curl);
        if(empty(trim($response))) return null;
        return json_decode($response, true);
        
    }
    public function post_request($params, $action) {
        $token = $this->getToken();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$this->api_domain.$action);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization". ":" . "Zoho-oauthtoken " . $token));
        //CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close ($ch);        
        return json_decode($server_output, true);
    }
    public function put_request($params, $action) {
        $token = $this->getToken();
        $curl = curl_init();
        $setpotarr = array(
            CURLOPT_URL => $this->api_domain.$action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => json_encode($params), 
            CURLOPT_HTTPHEADER => array(
                'Authorization: Zoho-oauthtoken '.$token,
                'Content-Type: application/json',                
            ),
        );
        Log::channel('daily')->info("sending params = ".json_encode($params));
        curl_setopt_array($curl, $setpotarr);  
        $response = curl_exec($curl);
        curl_close($curl);       
        return json_decode($response, true);
    } 
    public function delete_request($params,$action)  {
        $token = $this->getToken();
        $url = $this->api_domain.$action;
        if($params) {
            $url .='?'.http_build_query($params);            
        }
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Zoho-oauthtoken '.$token,
                'Content-Type: application/json',                
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }
    protected function saveTokenInfo($tokenInfo) {
        $tokenInfo->rectime = time();
        $option = DB::table('options')->where('opt_key','zoho_token_info')->first();
        if($option) {
            DB::table('options')->where('opt_key','zoho_token_info')->update(['opt_value'=>json_encode($tokenInfo)]);
        }        
        else {
            DB::table('options')->where('opt_key','zoho_token_info')->insert([
                'opt_key'=>'zoho_token_info',                
                'opt_value'=>json_encode($tokenInfo),
                'serialized'=>1
            ]);
        }
    }
    public function getToken() {
        //получение токена всегда делаем по api v2. Поэтому запоминаем версию api в начале метода и вернем ее в конце
        $currentApi = $this->apiVersion();
        $this->setApiVersion('v2');
        //-----------------------------------------------------------------------------
        $option = DB::table('options')->where('opt_key','zoho_token_info')->first();
        $tokenInfo = json_decode($option->opt_value);
        $this->api_domain = $tokenInfo->api_domain;
        $activeTime = $tokenInfo->rectime + $tokenInfo->expires_in;
        $tekTime = time();
      /*  echo "<pre>";
        echo "tektime = ".$tekTime." : activeTime  = $activeTime ";
        echo "\n prev token info ... \n";
        print_r($tokenInfo);      */
        
        if($tekTime > $activeTime) {
           // echo "refresh ... \n";
            $rtoken = $tokenInfo->refresh_token;
            $refresh = $this->refreshToken($rtoken);
           // print_r($refresh);
            foreach($refresh as $k=>$v) {
                $tokenInfo->$k = $v;
            }
            $tokenInfo->rectime = $tekTime;
            $tokenInfo->refresh_token = $rtoken;
         /*   echo "new token info ... \n";
            print_r($tokenInfo); */
            $this->saveTokenInfo($tokenInfo);
        }
        $this->setApiVersion($currentApi);
        return $tokenInfo->access_token;
    }
    public function authorize( $redirect_uri) { //вызывается на странице получения токена
        if(!isset($_GET['code'])) {
            $params = [
                'scope'=>'ZohoCRM.modules.ALL',
             // 'scope'=>'ZohoCRM.settings.pipeline.READ',
                'client_id'=>$this->client_id,
                'response_type'=>'code',
                'access_type'=>'offline',
                'redirect_uri'=>$redirect_uri
            ];
            $auth_url = "https://accounts.zoho.eu/oauth/".$this->apiVersion()."/auth?".http_build_query($params);
            header("Location: $auth_url");
            return;
        }        
        $tokenInfo = $this->generateToken($_GET['code'], $redirect_uri);
        echo "tokenInfo = ";
        print_r($tokenInfo);
        $this->saveTokenInfo($tokenInfo);        
    }

}