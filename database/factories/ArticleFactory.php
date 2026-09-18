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
        'preview_image' => 'https://picsum.photos/200/200?random=' . fake()->unique()->numberBetween(1, 1000),
        'full_image' => 'https://picsum.photos/800/600?random=' . fake()->unique()->numberBetween(1, 1000),
    ];
}
}