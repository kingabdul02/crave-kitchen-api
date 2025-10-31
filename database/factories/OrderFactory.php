<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 20, 1000);
        $discountType = $this->faker->optional(0.3)->randomElement(['percentage', 'fixed']);
        $discountValue = $discountType ?
            ($discountType === 'percentage' ? $this->faker->numberBetween(5, 25) : $this->faker->randomFloat(2, 5, 50)) : 0;

        $totalAmount = $discountType === 'percentage' ?
            $subtotal * (1 - $discountValue / 100) :
            max(0, $subtotal - $discountValue);

        return [
            'customer_id' => \App\Models\Customer::factory(),
            'status' => $this->faker->randomElement(['pending', 'processed', 'completed', 'cancelled']),
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'total_amount' => $totalAmount,
            'payment_status' => $this->faker->randomElement(['unpaid', 'partially_paid', 'paid']),
            'is_settled' => $this->faker->boolean(40), // 40% chance of being settled
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
