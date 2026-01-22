<?php

namespace App\Http\Requests;

use App\Enum\AddressTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'addr_name' => 'nullable|string|max:100',
            'address_line1' => 'required|string|max:150',
            'address_line2' => 'nullable|string|max:150',

            'village' => 'nullable|string|max:100',
            'taluka' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:40|exists:mst_states,name',
            'state_iso' => 'nullable|string|max:2|exists:mst_states,iso_code',
            'postal_code' => 'required|string|max:6',

            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',

            'addr_type' => 'nullable|string|in:' . implode(',', AddressTypeEnum::casesAsValues()),
        ];
    }
}
