<?php

use Bongo\Packages\SocialLogin\Controllers\OAuthController;

/** @var \System\Routing\Router $router */
$router->get('/auth/{provider}', [OAuthController::class, 'redirect'], 'social.redirect');
$router->get('/auth/{provider}/callback', [OAuthController::class, 'callback'], 'social.callback');
