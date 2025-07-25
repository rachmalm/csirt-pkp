<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class ArticleController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('permission:articles index', only: ['index']),
            new Middleware('permission:articles create', only: ['create', 'store']),
            new Middleware('permission:articles edit', only: ['edit', 'update']),
            new Middleware('permission:articles delete', only: ['destroy']),
        ];
    }

    /**
     * Admin: List all articles.
     */
    public function index(Request $request)
    {
        $articles = Article::with('author')
            ->when($request->search, fn($q) =>
                $q->where('title', 'like', '%' . $request->search . '%')
            )
            ->latest()
            ->paginate(10);

        return inertia('Articles/Index', [
            'articles' => $articles,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Admin: Form to create article.
     */
    public function create()
    {
        return inertia('Articles/Create');
    }

    /**
     * Admin: Store new article.
     */
    public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|min:5|max:255',
        'content' => 'required',
        'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $slug = $this->generateUniqueSlug($validated['title']);

    // Default image null
    $imagePath = null;

    // Simpan file gambar jika ada
    if ($request->hasFile('image')) {
        // Simpan ke storage/app/public/articles
        $imagePath = $request->file('image')->store('articles', 'public');
        // Hasilnya: "articles/namafile.jpg"
    }

    // Buat artikel
    Article::create([
        'title' => $validated['title'],
        'slug' => $slug,
        'content' => $validated['content'],
        'author_id' => auth()->id(),
        'image' => $imagePath, // hanya simpan "articles/namafile.jpg"
    ]);

    return to_route('articles.index')->with('success', 'Artikel berhasil ditambahkan.');
}


    /**
     * Admin: Edit form.
     */
    public function edit(Article $article)
    {
        return inertia('Articles/Edit', [
            'article' => $article,
        ]);
    }

    /**
     * Admin: Update article.
     */
    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|min:5|max:255',
            'content' => 'required',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $slug = $article->slug;
        if ($validated['title'] !== $article->title) {
            $slug = $this->generateUniqueSlug($validated['title'], $article->id);
        }

        $imagePath = $article->image;
        
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('articles', 'public');
        }

        $article->update([
            'title' => $validated['title'],
            'slug' => $slug,
            'content' => $validated['content'],
            'image' => $imagePath,
        ]);

        return to_route('articles.index')->with('success', 'Artikel berhasil diperbarui.');
    }

    /**
     * Admin: Delete article.
     */
    public function destroy(Article $article)
    {
        $article->delete();
        return back()->with('success', 'Artikel berhasil dihapus.');
    }

    /**
     * Public: List articles with pagination.
     */
    public function publicIndex()
	{
		$articles = Article::select('id', 'title', 'slug', 'content', 'created_at', 'image')
			->latest()
			->paginate(6)
			->through(fn($article) => [
				'id' => $article->id,
				'title' => $article->title,
				'slug' => $article->slug,
				'excerpt' => Str::limit(strip_tags($article->content), 120),
				'created_at' => $article->created_at,
                'image' => $article->image,
			]);

		return inertia('Public/Articles/Index', [
			'articles' => $articles,
		]);
	}


    /**
     * Public: Show article by slug.
     */
    public function show($slug)
    {
    $article = Article::where('slug', $slug)->with('author')->firstOrFail();

    // Ambil 2 artikel lain sebagai sugesti (selain yang sedang dibuka)
    $suggestions = Article::where('slug', '!=', $slug)
        ->latest()
        ->take(2)
        ->get(['id', 'title', 'slug', 'content', 'image']);

    return inertia('Public/Articles/Show', [
        'article' => $article,
        'suggestions' => $suggestions,
    ]);
}

    /**
     * Generate unique slug.
     */
    protected function generateUniqueSlug($title, $excludeId = null)
    {
        $slug = Str::slug($title);
        $original = $slug;
        $counter = 1;

        while (
            Article::where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
