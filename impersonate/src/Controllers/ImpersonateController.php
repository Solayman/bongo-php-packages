<?php

namespace Bongo\Packages\Impersonate\Controllers;

use Bongo\Packages\Impersonate\Impersonate;
use System\Http\Request;
use System\Http\Response;

class ImpersonateController
{
    public function take(Request $request, $id)
    {
        $guard = $request->input('guard', 'web');
        $success = Impersonate::take((int) $id, $guard);

        if ($success) {
            $redirectUrl = config('impersonate.redirect_to', '/');
            return Response::redirect($redirectUrl)->with('success', 'You are now impersonating this user.');
        }

        return Response::redirect('/')->with('error', 'Unable to impersonate user.');
    }

    public function leave(Request $request)
    {
        $success = Impersonate::leave();

        $redirectUrl = config('impersonate.leave_redirect_to', '/admin');
        if ($success) {
            return Response::redirect($redirectUrl)->with('success', 'Returned to your original account.');
        }

        return Response::redirect('/');
    }
}
