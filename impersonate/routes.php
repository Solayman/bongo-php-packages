<?php

use Bongo\Packages\Impersonate\Controllers\ImpersonateController;
use System\Routing\Router;

Router::post('/impersonate/leave', [ImpersonateController::class, 'leave']);
Router::post('/impersonate/{id}', [ImpersonateController::class, 'take']);
