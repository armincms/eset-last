<?php

namespace Armincms\EsetLast\Http\Requests;

use Armincms\Eset\Http\Requests\{EsetRequest, IntractsWithDevice, IntractsWithProduct};
use Armincms\Eset\Decoder; 
 
class ValidationRequest extends EsetRequest
{
	use IntractsWithDevice, IntractsWithProduct;  

    const OPERATOR_KEY = 'ProductType';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {  
        return  true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    { 
        return [
            $this->getDeviceIdKey() 	  => 'required',
            $this->getLicenseProductKey() => 'required',
        ];
    }

    public function validateUser()
    {
    	return $this->get('ApiKey') === $this->option('eset_apikey');
    }

    public function findCredit()
    {
        return $this->creditQuery() 
                    ->whereJsonContains('data->key', $this->get($this->getLicenseProductKey()))
                    ->first();
    }

    public function getDeviceIdKey()
    {
    	return 'Device_id';
    }

    public function getLicenseProductKey()
    {
    	return 'LicenseProduct';
    }
    
    public function getDeviceId()
    {
        return $this->get($this->getDeviceIdKey());
    } 

    public function findDevice($credit)
    {
    	return $this->deviceQuery($credit)->where('device_id', $this->getDeviceId())->first();
    }

    public function isRegisterRequest()
    {
    	return collect($this->getProfileInput())->filter()->isNotEmpty();
    }

    public function getProfileInput()
    { 
    	return  collect([
		    		'Country', 'City', 'F_Name', 'L_Name', 'Company', 'Phone', 'Mail', 'Note'
		    	])->mapWithKeys(function($key) {
		    		return [$key => $this->get($key)];
		    	})->filter();
    }

    public function registerDevice($credit)
    {
    	$device = $this->deviceQuery($credit->markAsInUse())->firstOrCreate([
    		'device_id' => $this->getDeviceId(),
    		'credit_id' => $credit->getKey()
    	]);

        return tap($device, function($device) {
        	$device->update([
	            'data' => array_merge((array) $device->data, $this->getProfileInput()->all())
	        ]);
        }); 
    }

    public function registerResponse($credit, $device)
    {
    	return [
            'status'    => '0x00602', 
            'username'  => data_get($credit, 'data.username'),
            'password'  => data_get($credit, 'data.password'), 
            'expiresOn' => $credit->expires_on->toDateTimeString(),
            'startedAt' => $credit->startedAt()->toDateTimeString(), 
            'daysLeft'  => $credit->daysLeft(),
            'users'     => $credit->license->users,
            'inUse'     => $this->deviceQuery($credit)->count(),
            'fileServer'=> $this->servers($this->getOperator(), 'file_server'),
            'FailSafeServer'=> $this->servers($this->getOperator(), 'fails_server'),
        ];
    }

    public function creditResponse($credit, $device)
    {
    	return array_merge($this->registerResponse($credit, $device), [
            'status'    => '0x00502',
            'country'   => data_get($device, 'data.Country'),
            'city'      => data_get($device, 'data.City'),
            'f_name'    => data_get($device, 'data.F_Name'),
            'l_name'    => data_get($device, 'data.L_Name'),
            'company'   => data_get($device, 'data.Company'),
            'phone'     => data_get($device, 'data.Phone'),
            'mail'      => data_get($device, 'data.Mail'),
            'note'      => data_get($device, 'data.Note'), 
            'username'  => Decoder::hexDecode(data_get($credit, 'data.username')),
            'password'  => Decoder::decode(data_get($credit, 'data.password')),  
        ]);
    }
} 
