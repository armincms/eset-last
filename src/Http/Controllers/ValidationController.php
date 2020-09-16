<?php

namespace Armincms\EsetLast\Http\Controllers;

use Illuminate\Routing\Controller;
use Armincms\EsetLast\Http\Requests\ValidationRequest; 

class ValidationController extends Controller
{  
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function handle(ValidationRequest $request)
    {    
        if(! $request->validateUser()) {
            // invalid API key
            return [
                'status' => '0x000001'
            ];
        }

        if(is_null($credit = $request->findCredit())) {
            // invalid API key
            return [
                'status' => '0x000002'
            ];
        }

        if(! $request->passesDeviceRestriction($credit)) {
            // Devics is fully filled
            return [
                'status' => '0x000003'
            ];
        }

        if(! $request->passesProductRestriction($credit)) {
            // invalid product  
            return [
                'status' => '0x000004',
                'type'   => data_get($credit, 'license.product.driver'),
            ];
        }

        if(is_null($device = $request->findDevice($credit)) && ! $request->isRegisterRequest()) {
            // Inactive device
            return [
                'status' => '0x000100',
                'type'   => data_get($credit, 'license.product.driver'),
            ];
        } 

        return $request->isRegisterRequest() 
                    ? $request->registerResponse($credit, $request->registerDevice($credit))
                    : $request->creditResponse($credit, $device);
        
    }   
}  
