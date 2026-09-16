<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'preview_image' => fake()->imageUrl(200, 200, 'news'),
            'full_image' => fake()->imageUrl(800, 600, 'news'),
        ];
    }
}