<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class CustomerMailer
{
    public function send(Customer $customer, Mailable $mailable): void
    {
        if (blank($customer->email)) {
            return;
        }

        Mail::to($customer->email)->queue($mailable);
    }
}
