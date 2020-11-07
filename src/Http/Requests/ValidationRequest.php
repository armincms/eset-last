<?php

namespace Armincms\EsetLast\Http\Requests;

use Armincms\EasyLicense\Credit;
use Armincms\Eset\Http\Requests\{EsetRequest, IntractsWithDevice, IntractsWithProduct}; 
 
class ValidationRequest extends EsetRequest
{
	use IntractsWithDevice, IntractsWithProduct {
        hasValidOperator as esetHasValidOperator;
    }  

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

    public function hasValidOperator(Credit $credit)
    { 
        return $this->esetHasValidOperator($credit) || $this->hasValidParentOperator($credit);
    }

    public function hasValidParentOperator(Credit $credit)
    { 
        return $this->getParentOperator($credit) === $this->getOperator(); 
    }

    public function getParentOperator(Credit $credit)
    {
        $driver = data_get($credit, 'license.product.driver');

        return config("licence-management.operators.eset.{$driver}.parent");
    }

    public function validateUser()
    {
    	return $this->get('ApiKey') === $this->option('eset_apikey');
    }

    public function findCredit()
    {
        return $this->creditQuery() 
                    ->whereJsonContains('data->key', $this->get($this->getLicenseProductKey()))
                    ->with([
                        'license' => function($q) {
                            $q->withTrashed()->with([
                                'product' => function($q) {
                                    $q->withTrashed();
                                }
                            ]);
                        }
                    ])
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
    	return $this->deviceQuery($credit->startIfNotStarted())->firstOrCreate([
    		'device_id' => $this->getDeviceId(),
    		'credit_id' => $credit->startIfNotStarted()->getKey()
    	], ['data' => $this->getProfileInput()->all()]);
    }

    public function registerResponse($credit, $device)
    {
    	return [
            'status'    => '0x00602', 
            'type'      => data_get($credit, 'license.product.driver'),
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
        ]);
    }
} 
