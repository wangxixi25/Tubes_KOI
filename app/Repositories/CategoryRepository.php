<?php

namespace App\Repositories;

use App\Enums\Category\CategoryFieldsEnum;
use App\Enums\Category\CategoryFiltersEnum;
use App\Exceptions\DBCommitException;
use App\Models\Category;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HigherOrderWhenProxy;

class CategoryRepository
{
    const MAX_RETRY = 5;

    /**
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @param array $fields
     * @param array $expand
     * @param string $sortBy
     * @param string $sortOrder
     * @return LengthAwarePaginator
     */
    public function getAll(
        int    $page,
        int    $perPage,
        array  $filters = [],
        array  $fields = [],
        array  $expand = [],
        string $sortBy = "id",
        string $sortOrder = "ASC"
    ): LengthAwarePaginator
    {
        $query = $this->getQuery($filters)
            ->orderBy($sortBy, $sortOrder)
            ->with($expand);

        if (count($fields) > 0) {
            $query = $query->select($fields);
        }

        return $query->paginate(
            perPage: $perPage,
            page: $page
        )->withQueryString();
    }

    /**
     * @param array $filters
     * @return bool
     */
    public function exists(array $filters = []): bool
    {
        return $this->getQuery($filters)->exists();
    }

    /**
     * @param array $filters
     * @param array $expand
     * @return Category|null
     */
    public function find(array $filters = [], array $expand = []): ?Category
    {
        return $this->getQuery($filters)
            ->with($expand)
            ->first();
    }

    /**
     * @param array $payload
     * @return mixed
     * @throws DBCommitException
     */
    public function create(array $payload): mixed
    {
        try {
            DB::beginTransaction();
            $category = Category::create($payload);
            DB::commit();
            return $category;
        } catch (Exception $exception) {
            DB::rollBack();
            throw new DBCommitException($exception);
        }
    }

    /**
     * @param Category $category
     * @param array $changes
     * @return Category
     * @throws Exception
     */
    public function update(Category $category, array $changes): Category
    {
        $attempt = 1;
        do {
            $updated = $category->update($changes);
            $attempt++;
        } while (!$updated && $attempt <= self::MAX_RETRY);

        if (!$updated && $attempt > self::MAX_RETRY) {
            throw new Exception("Max retry exceeded during category update");
        }

        return $category->refresh();
    }

    /**
     * @param Category $category
     * @return bool|null
     */
    public function delete(Category $category): ?bool
    {
        return $category->delete();
    }

    /**
     * @param array $filters
     * @return Builder|HigherOrderWhenProxy
     */
    private function getQuery(array $filters): Builder|HigherOrderWhenProxy
    {
        return Category::query()
            ->when(isset($filters[CategoryFiltersEnum::ID->value]), function ($query) use ($filters) {
                $query->where(CategoryFieldsEnum::ID->value, $filters[CategoryFiltersEnum::ID->value]);
            })
            ->when(isset($filters[CategoryFiltersEnum::NAME->value]), function ($query) use ($filters) {
                $query->where(CategoryFieldsEnum::NAME->value, "like", "%" . $filters[CategoryFiltersEnum::NAME->value] . "%");
            })
            ->when(isset($filters[CategoryFiltersEnum::CREATED_AT->value]), function ($query) use ($filters) {
                $query->whereBetween(CategoryFieldsEnum::CREATED_AT->value, $filters[CategoryFiltersEnum::CREATED_AT->value]);
            });
    }
}
