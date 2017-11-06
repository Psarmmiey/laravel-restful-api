<?php namespace App\Http\Controllers;

use App\Http\Requests\CreateArticleRequest;
use App\Repositories\ArticleRepository;
use App\Repositories\TagRepository;
use App\Services\CacheService;
use App\Transformers\ArticleTransformer;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
	public function index () {
		//
	}

	public function store (CreateArticleRequest $request, ArticleRepository $articleRepository, TagRepository $tagRepository, ArticleTransformer $transformer, CacheService $cacheService) {
		$articleData = [ 'title' => $request->get('title'), 'content' => $request->get('content') ];
		// lower the contents
		// then generate slug
		$tagData = collect($request->get('tags'))->map(function ($item) {
			return Str::lower($item);
		})->map(function ($item) {
			return str_slug($item);
		});

		try {
			$article = app('db')->transaction(function () use ($articleData, $articleRepository, $tagRepository, $tagData) {
				$userId = auth()->user()->id;
				$article = $articleRepository->storeArticle($articleData, $userId);
				$tags = $tagRepository->storeUnavailableTags($tagData, $userId);
				$articleRepository->insertArticleTagToPivot($article, $tags);

				return $article;
			});
		} catch (\Exception $exception) {
			throw new \Exception("Cannot create article.");
		}

		$cacheService->insertArticleToCache($article, 10);

		return $this->respondSuccess($transformer->transform($article), 201);
	}

	public function show (ArticleTransformer $articleTransformer, ArticleRepository $articleRepository, CacheService $cacheService, $slug) {
		// check if article is in the cache or not
		if ($article = $cacheService->checkIfArticleExists($slug)) {
			// relations are not loaded
			$article->load([ 'user', 'tags' ]);
		} else {
			// load from database
			$article = $articleRepository->fetchAnArticleBySlug($slug, [ 'user', 'tags' ]);
			// no article found
			if (!$article) {
				return $this->respondError([ 'article' => 'Not found!' ], 404);
			}
			// just only to save the the model, not relations
			$cacheService->insertArticleToCache($article->fresh());
		}

		// return the article
		return $this->respondSuccess($articleTransformer->transform($article));
	}

	public function edit ($id) {
		//
	}

	public function update (Request $request, $id) {
		//
	}

	public function destroy ($id) {
		//
	}
}