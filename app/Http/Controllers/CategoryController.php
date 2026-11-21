<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('viewAny', Category::class, only: ['index'])]
#[Authorize('create', Category::class, only: ['create', 'store'])]
#[Authorize('update', 'category', only: ['edit', 'update'])]
#[Authorize('delete', 'category', only: ['destroy'])]
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::with('parent')->withCount('products')->orderBy('name')->paginate($this->perPage()),
        ]);
    }

    public function create(): View
    {
        return view('categories.form', [
            'category' => new Category,
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return to_route('categories.index')->with('status', __('Category created.'));
    }

    public function edit(Category $category): View
    {
        return view('categories.form', [
            'category' => $category,
            'parents' => $this->parentOptions($category),
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return to_route('categories.index')->with('status', __('Category updated.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', __('This category still has products and cannot be deleted.'));
        }

        $category->delete();

        return to_route('categories.index')->with('status', __('Category deleted.'));
    }

    /**
     * @return Collection<int, Category>
     */
    private function parentOptions(?Category $except = null): Collection
    {
        return Category::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->orderBy('name')
            ->get();
    }
}
