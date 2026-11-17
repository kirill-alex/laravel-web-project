<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $imagePaths = array_map(
            fn (int $number): string => sprintf('images/cat-memes/cat-meme-%02d.jpg', $number),
            range(1, 8)
        );
        $imagePath = fake()->randomElement([...$imagePaths, 'images/cat1.jpg', 'images/cat2.jpg', 'images/cat3.jpg']);

        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'preview_image' => $imagePath,
            'full_image' => $imagePath,
        ];
    }
}
