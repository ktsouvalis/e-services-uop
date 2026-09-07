<?php

namespace Database\Factories;

use App\Models\AImodel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chatbot>
 */
class ChatbotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ai_model_id' => AImodel::factory(),
            'title' => fake()->sentence(3),
            'api_key' => Crypt::encryptString('test-api-key'),
            'history' => null,
        ];
    }
}
