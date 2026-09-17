<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement([Transaction::TYPE_EXPENSE, Transaction::TYPE_INCOME]);

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory()->state(['type' => $type]),
            'type' => $type,
            'amount' => fake()->numberBetween(10000, 500000),
            'transaction_date' => fake()->dateTimeBetween('-40 days', 'now')->format('Y-m-d'),
            'note' => fake()->sentence(3),
            'source' => fake()->randomElement([Transaction::SOURCE_AI, Transaction::SOURCE_MANUAL]),
        ];
    }
}
