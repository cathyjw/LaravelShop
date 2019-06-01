<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;

class HandleRefundRequest extends Request
{
    public function rules()
    {
        return [
            'agree'  => ['required', 'boolean'],
            'reason' => ['required_if:agree,false'], // To refuse a refund, you need to enter the reason for refusal
        ];
    }
}
