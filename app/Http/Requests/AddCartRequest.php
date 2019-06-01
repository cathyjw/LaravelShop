<?php

namespace App\Http\Requests;

use App\Models\ProductSku;

class AddCartRequest extends Request
{
    public function rules()
    {
        return [
            'sku_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!$sku = ProductSku::find($value)) {
                        return $fail('This product does not exist');
                    }
                    if (!$sku->product->on_sale) {
                        return $fail('The product is not in selling');
                    }
                    if ($sku->stock === 0) {
                        return $fail('This product is sold out');
                    }
                    if ($this->input('amount') > 0 && $sku->stock < $this->input('amount')) {
                        return $fail('Out of Stock');
                    }
                },
            ],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes()
    {
        return [
            'amount' => 'Quantity of Product'
        ];
    }

    public function messages()
    {
        return [
            'sku_id.required' => 'You need specify order items'
        ];
    }
}
