<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/


$router->get('/', ['as' => 'secret.new', function () {
    return view('secret-new');
}]);

$router->post('secret', [
    'uses' => 'SecretController@create'
]);

$router->get('{id}/{key}', [
    'as' => 'secret',
    'uses' => 'SecretController@showSecret'
]);

$router->get('{id}', function () {
    return view('secret-no-key');
});
