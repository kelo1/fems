<?php

namespace Database\Factories;

use App\Models\ServiceProviderDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceProviderDevice>
 */
class ServiceProviderDeviceFactory extends Factory
{
    protected $model = ServiceProviderDevice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'device_serial_number' => 'DEV-' . strtoupper(uniqid()), // Generate unique serial number
            'service_provider_id' => null, // Or you can assign a random service provider ID
        ];
    }
}
