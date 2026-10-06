<?php

namespace Bongo\Packages\Impersonate\Controllers;

use Bongo\Packages\Impersonate\Impersonate;
use System\Http\Request;

class ImpersonateController
{
    public function take(Request $request, $id)
    {
        // The guard comes from config, never from the request
        if (Impersonate::take((int) $id, (string) config('impersonate.guard', 'web'))) {
            flash('success', 'You are now impersonating this user.');
            redirect((string) config('impersonate.redirect_to', '/'));
        }

        flash('error', 'Unable to impersonate user.');
        redirect('/');
    }

    public function leave(Request $request)
    {
        if (Impersonate::leave()) {
            flash('success', 'Returned to your original account.');
            redirect((string) config('impersonate.leave_redirect_to', '/'));
        }

        redirect('/');
    }
}
