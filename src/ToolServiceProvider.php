<?php

namespace Armincms\EsetLast;
 
use Illuminate\Support\ServiceProvider; 
use Laravel\Nova\Nova; 

class ToolServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {    
        $this->map(); 
    }  

    public function map()
    {
        $this
            ->app['router']   
            ->namespace(__NAMESPACE__.'\Http\Controllers')  
            ->prefix('api')
            ->group(function($router) {
                $router->match('get', 'eset-api', 'ValidationController@handle'); 
            });
    } 
}
