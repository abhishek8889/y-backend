<?php

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'venue_type_id' => ['nullable', 'integer', 'exists:venue_types,id'],
            'description' => ['nullable', 'string'],
            'maximum_capacity' => ['nullable', 'integer', 'min:0'],
            'standing_capacity' => ['nullable', 'integer', 'min:0'],
            'seated_capacity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,inactive,draft'],
            'is_private_hire_available' => ['nullable', 'boolean'],
            'private_hire_description' => ['nullable', 'string'],

            'address' => ['nullable', 'array'],
            'address.address_line_1' => ['required_with:address', 'string', 'max:255'],
            'address.address_line_2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['required_with:address', 'string', 'max:255'],
            'address.state' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['required_with:address', 'string', 'max:50'],
            'address.country' => ['required_with:address', 'string', 'max:255'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address.google_maps_url' => ['nullable', 'string', 'max:500'],

            'contact' => ['nullable', 'array'],
            'contact.type' => ['nullable', 'string', 'max:50'],
            'contact.name' => ['required_with:contact', 'string', 'max:255'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.alternative_phone' => ['nullable', 'string', 'max:50'],
            'contact.website' => ['nullable', 'string', 'max:255'],
            'contact.facebook_url' => ['nullable', 'string', 'max:255'],
            'contact.instagram_url' => ['nullable', 'string', 'max:255'],
            'contact.twitter_url' => ['nullable', 'string', 'max:255'],
            'contact.youtube_url' => ['nullable', 'string', 'max:255'],

            'accessibility' => ['nullable', 'array'],
            'accessibility.accessible_entrance' => ['nullable', 'boolean'],
            'accessibility.accessible_toilet' => ['nullable', 'boolean'],
            'accessibility.wheelchair_access' => ['nullable', 'boolean'],
            'accessibility.notes' => ['nullable', 'string'],

            'logistics' => ['nullable', 'array'],
            'logistics.parking_information' => ['nullable', 'string'],
            'logistics.public_transport_information' => ['nullable', 'string'],
            'logistics.travel_information' => ['nullable', 'string'],

            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'distinct', 'exists:facilities,id'],

            'suitable_for_option_ids' => ['nullable', 'array'],
            'suitable_for_option_ids.*' => ['integer', 'distinct', 'exists:venue_suitable_for_options,id'],

            'images' => ['nullable', 'array'],
            'images.*.id' => ['nullable', 'integer', 'exists:venue_images,id'],
            'images.*.type' => ['required', 'string', Rule::in(['banner', 'gallery'])],
            'images.*.path' => ['required', 'string', 'max:500'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'venue name',
            'venue_type_id' => 'venue type',
            'is_private_hire_available' => 'private hire available',
            'private_hire_description' => 'private hire description',
            'address.address_line_1' => 'address line 1',
            'address.address_line_2' => 'address line 2',
            'address.postal_code' => 'postal code',
            'address.google_maps_url' => 'google maps url',
            'contact.name' => 'contact name',
            'facility_ids' => 'facilities',
            'suitable_for_option_ids' => 'suitable for options',
            'images.*.id' => 'image id',
            'images.*.type' => 'image type',
            'images.*.path' => 'image path',
        ];
    }
}
