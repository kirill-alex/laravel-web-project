<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, ModeratorSeeder::class]);

        $author = User::factory()->create();
        Article::factory(10)->has(Comment::factory(3)->approved()->for($author))->create();
    }
}
