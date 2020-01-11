<?php
/**
 * Created by PhpStorm.
 * User: allan
 * Date: 1/9/20
 * Time: 11:06 AM
 */
namespace MTN;

require_once( MOMOPAY_PLUGIN_DIR_PATH . 'mtn-momopay-php-sdk/vendor/autoload.php' );

use GuzzleHttp\Client;

class Momopay
{
    protected $primary_key;
    protected $secondary_key;
    protected $api_user;
    protected $api_key;
    protected $env;
    protected $base_url;

    protected $access_token = null;

    protected $reference_id;
    protected $amount;
    protected $currency;
    protected $external_id;
    protected $phone;
    protected $payer_message;
    protected $payee_note;
    protected $body;
    protected $headers;
    protected $requery_count = 0;
    protected $error;

    /**
     * Momopay constructor.
     * @param $primary_key
     * @param $api_user
     * @param $api_key
     * @param $base_url
     * @param $env
     */
    public function __construct($primary_key, $api_user, $api_key, $base_url, $env)
    {
        $this->primary_key = $primary_key;
        $this->api_user = $api_user;
        $this->api_key = $api_key;
        $this->base_url = $base_url;
        $this->env = $env;

        $this->setAccessToken();

        return $this;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    public function setPhone($phone)
    {
        $this->phone = $phone;
        return $this;
    }
    public function setExternalID($external_id)
    {
        $this->external_id = $external_id;
        return $this;
    }

    public function setPayerMessage($message)
    {
        $this->payer_message = $message;
        return $this;
    }
    public function setPayeeNote($note)
    {
        $this->payee_note = $note;
        return $this;
    }

    protected function setReferenceId()
    {
        $this->reference_id = self::generateUuid();
    }

    public function setBody()
    {
        $data =  array(
            'amount' => $this->amount,
            'currency' => $this->currency,
            'externalId' => $this->external_id,
            'payer' => array(
                'partyIdType' => 'MSISDN',
                'partyId' => $this->phone
            ),
            'payerMessage' => $this->payer_message,
            'payeeNote' => $this->payee_note
        );
        $this->body = json_encode($data);
    }

    protected function setAccessToken()
    {
        $url = $this->base_url . 'token/';
        $this->setHeaders(array(
            'Authorization' => 'Basic ' . base64_encode( $this->api_user . ':' . $this->api_key ),
            'Content-Type' => 'application/json',
            'Ocp-Apim-Subscription-Key' => $this->primary_key
        ));

        try {
            //$response = Request::post($url, $this->headers);
            $client = new  Client();
            $response = $client->post($url, array(
                'headers' =>$this->headers,
            ));
        } catch (\Exception $e) {
            //
        }

        if (isset($response)){
            $body = json_decode($response->getBody()->getContents());
            $this->access_token =  'Bearer ' . $body->access_token;
        }
    }

    public function getAccessToken()
    {
        return $this->access_token;
    }

    public function getError()
    {
        return $this->error;
    }

    public function setError($reason)
    {
        switch ($reason) {
            case 'EXPIRED':
                $this->error = "The Payment Request Expired! Please Try Again! ($reason)";
                break;
            case 'INTERNAL_PROCESSING_ERROR':
                $this->error = "The Payment Request was Rejected! Please Try Again! ($reason)";
                break;
            case 'APPROVAL_REJECTED':
                $this->error = "The Payment Request Was Rejected! Please Try Again ($reason)";
                break;
            default:
                $this->error = "Payment Error: Please try again later! ($reason)";
        }
    }

    protected function setHeaders($headers)
    {
        $this->headers = $headers;
    }
    public function getHeaders()
    {
        return $this->headers;
    }

    protected function getFormattedHeaders($headers)
    {
        $formatted = array();
        foreach ($headers as $key => $value){
            $formatted[] = $key.': '.$value;
        }
        return $formatted;
    }

    public function requestPayment()
    {
        $this->setReferenceId();
        $this->setBody();

        $url = $this->base_url . 'v1_0/requesttopay';
        $this->setHeaders(array(
            'Authorization' => $this->access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Ocp-Apim-Subscription-Key' => $this->primary_key,
            'X-Reference-Id' => $this->reference_id,
            'X-Target-Environment' => $this->env
        ));

        try {
            $client = new  Client();
            $response = $client->post($url, array(
                'headers' => $this->headers,
                'body' => $this->body
            ));
        } catch (\Exception $e) {
            //
        }

        if (isset($response) && in_array($response->getStatusCode(), array(200, 201, 202))){ //202
            return true;
        }

        return false;
    }

    public function getRequestStatus()
    {
        $url = $this->base_url . 'v1_0/requesttopay/'.$this->reference_id;

        try {
            $client = new  Client();
            $response = $client->get($url, array(
                'headers' => array_diff_key($this->headers, array('X-Reference-Id' => $this->reference_id)),
            ));
        } catch (\Exception $e) {
            //
        }
        $status = 'error';
        if (isset($response)){
            $body = json_decode($response->getBody()->getContents());

            switch ($body->status) {
                case 'SUCCESSFUL':
                    $status = 'successful';
                    break;
                case 'FAILED':
                    $status = 'failed';
                    $this->setError($body->reason);
                    break;
                case 'PENDING':
                    if ($this->requery_count < 6){
                        $status = $this->requeryRequestStatue();
                    }else{
                        $status = 'failed';
                    }
                    break;
                default:
                    $status = 'failed';
            }
        }
        return $status;
    }

    protected function requeryRequestStatue()
    {
        $this->requery_count ++;
        sleep(10);
        return $this->getRequestStatus();
    }

    /**
     * uuid4 generator
     * @return string uuid4
     * @throws \Exception
     */
    public static function generateUuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}