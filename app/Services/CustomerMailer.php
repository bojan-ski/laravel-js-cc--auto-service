<?php

namespace App\Services;

use App\Enums\Locale;
use App\Models\Customer;
use App\Models\Job;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class CustomerMailer
{
    public function send(Customer $customer, Mailable $mailable): bool
    {
        if (blank($customer->email)) {
            return false;
        }

        Mail::to($customer->email)->locale(Locale::shop()->value)->queue($mailable);

        return true;
    }

    public function noticeFor(Job $job): string
    {
        $email = Customer::withTrashed()->whereKey($job->customer_id)->value('email');

        return blank($email)
            ? __('mail.notice.skipped')
            : __('mail.notice.queued', ['email' => $email]);
    }
}
