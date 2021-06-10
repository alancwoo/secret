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

$router->get('/', function () use ($router) {
    return $router->app->version();
});

// $router->get('/secret/new', function () {
//     return view('newsecret');
// });

// $router->get('/secret/{id}', function ($id) use ($router) {
//     return "Bob";
// });

$router->group(['prefix' => 'secret'], function () use ($router) {
    $router->get('{id}/{key}', ['uses' => 'SecretController@showSecret']);
    $router->post('', ['uses' => 'SecretController@create']);
});
