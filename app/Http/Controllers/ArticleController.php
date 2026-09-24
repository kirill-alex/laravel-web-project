<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    // READ (список)
    public function index()
    {
        $articles = Article::paginate(5);
        return view('articles.index', ['articles' => $articles]);
    }

    // READ (одна статья)
    public function show($id)
    {
        $article = Article::findOrFail($id);
        return view('articles.show', ['article' => $article]);
    }

    // CREATE — форма создания
    public function create()
    {
        return view('articles.create');
    }

    // CREATE — сохранение
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_image' => 'nullable|string',
            'full_image' => 'nullable|string',
        ]);

        Article::create($validated);

        return redirect('/articles');
    }

    // UPDATE — форма редактирования
    public function edit($id)
    {
        $article = Article::findOrFail($id);
        return view('articles.edit', ['article' => $article]);
    }

    // UPDATE — сохранение изменений
    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'preview_image' => 'nullable|string',
            'full_image' => 'nullable|string',
        ]);

        $article->update($validated);

        return redirect('/articles');
    }

    // DELETE
    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        $article->delete();

        return redirect('/articles');
    }
}