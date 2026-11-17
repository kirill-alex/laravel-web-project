<?php

namespace App\Http\Middleware;

use App\Models\Article;
use App\Models\ArticleView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackArticleView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            $article = $request->route('article');
            ArticleView::create([
                'article_id' => $article instanceof Article ? $article->id : (int) $article,
                'url' => $request->url(),
            ]);
        }

        return $response;
    }
}
