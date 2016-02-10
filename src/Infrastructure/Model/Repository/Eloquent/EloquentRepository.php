<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 7/02/16
 * Time: 15:58.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Foundation\Infrastructure\Model\Repository\Eloquent;

use Illuminate\Database\Eloquent\Model;
use NilPortugues\Assert\Assert;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Page;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\PageRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\ReadRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\WriteRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Filter as DomainFilter;
use NilPortugues\Foundation\Domain\Model\Repository\Page as ResultPage;

abstract class EloquentRepository implements ReadRepository, WriteRepository, PageRepository
{
    /**
     * @var Model
     */
    protected static $instance;

    /**
     * Retrieves an entity by its id.
     *
     * @param Identity|Model $id
     * @param Fields|null    $fields
     *
     * @return array
     */
    public function find(Identity $id, Fields $fields = null)
    {
        $model = $this->getModelInstance();
        $columns = ($fields) ? $fields->get() : ['*'];

        return $model->query()->where($model->getKeyName(), '=', $id->id())->get($columns)->first();
    }

    /**
     * Returns all instances of the type.
     *
     * @param Filter|null $filter
     * @param Sort|null   $sort
     * @param Fields|null $fields
     *
     * @return array
     */
    public function findBy(Filter $filter = null, Sort $sort = null, Fields $fields = null)
    {
        $model = $this->getModelInstance();
        $query = $model->query();
        $columns = ($fields) ? $fields->get() : ['*'];

        if ($filter) {
            EloquentFilter::filter($query, $filter);
        }

        if ($sort) {
            EloquentSorter::sort($query, $sort);
        }

        return $query->get($columns)->toArray();
    }

    /**
     * Returns an Eloquent Model instance.
     *
     * @return Model
     */
    protected function getModelInstance()
    {
        if (null === self::$instance) {
            $modelInstance = $this->modelClassName();
            self::$instance = new $modelInstance();
        }

        return self::$instance;
    }

    /**
     * Must return the Eloquent Model Fully Qualified Class Name as a string.
     *
     * eg: return App\Model\User::class
     *
     * @return string
     */
    abstract protected function modelClassName();

    /**
     * Returns whether an entity with the given id exists.
     *
     * @param Identity|Model $id
     *
     * @return bool
     */
    public function exists(Identity $id)
    {
        $model = $this->getModelInstance();

        $filter = new DomainFilter();
        $filter->must()->equals($model->getKeyName(), $id->id());

        return $this->count($filter) > 0;
    }

    /**
     * Returns the total amount of elements in the repository given the restrictions provided by the Filter object.
     *
     * @param Filter|null $filter
     *
     * @return int
     */
    public function count(Filter $filter = null)
    {
        $model = $this->getModelInstance();
        $query = $model->query();

        if ($filter) {
            EloquentFilter::filter($query, $filter);
        }

        return (int) $query->getQuery()->count();
    }

    /**
     * Adds a new entity to the storage.
     *
     * @param Identity|Model $value
     *
     * @return mixed
     */
    public function add(Identity $value)
    {
        $this->guard($value);
        $value->save();

        return $value;
    }

    /**
     * @param $value
     *
     * @throws \Exception
     */
    protected function guard($value)
    {
        Assert::isInstanceOf($value, $this->getModelInstance());
    }

    /**
     * Adds a collections of entities to the storage.
     *
     * @param Model[] $values
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function addAll(array $values)
    {
        $model = $this->getModelInstance();

        try {
            $model->getConnection()->beginTransaction();
            foreach ($values as $value) {
                $this->guard($value);
                $value->save();
            }
            $model->getConnection()->commit();
        } catch (\Exception $e) {
            $model->getConnection()->rollback();
            throw $e;
        }
    }

    /**
     * Removes the entity with the given id.
     *
     * @param Identity|Model $id
     *
     * @return bool
     */
    public function remove(Identity $id)
    {
        $model = $this->getModelInstance();

        return (bool) $model->query()->find($id->id())->delete();
    }

    /**
     * Removes all elements in the repository given the restrictions provided by the Filter object.
     * If $filter is null, all the repository data will be deleted.
     *
     * @param Filter $filter
     *
     * @return bool
     */
    public function removeAll(Filter $filter = null)
    {
        $model = $this->getModelInstance();
        $query = $model->query();

        if ($filter) {
            EloquentFilter::filter($query, $filter);
        }

        return $query->delete();
    }

    /**
     * Returns a Page of entities meeting the paging restriction provided in the Pageable object.
     *
     * @param Pageable $pageable
     *
     * @return Page
     */
    public function findAll(Pageable $pageable = null)
    {
        $model = $this->getModelInstance();
        $query = $model->query();

        if ($pageable) {
            $fields = $pageable->fields();
            $columns = ($fields) ? $fields->get() : ['*'];

            $filter = $pageable->filters();
            if ($filter) {
                EloquentFilter::filter($query, $filter);
            }

            $sort = $pageable->sortings();
            if ($sort) {
                EloquentSorter::sort($query, $sort);
            }

            return new ResultPage(
                $query->paginate($pageable->pageSize(), $columns, 'page', $pageable->pageNumber())->items(),
                $query->paginate()->total(),
                $pageable->pageNumber(),
                ceil($query->paginate()->total() / $pageable->pageSize())
            );
        }

        return new ResultPage(
            $query->paginate($query->paginate()->total(), ['*'], 'page', 1)->items(),
            $query->paginate()->total(),
            1,
            1
        );
    }
}
