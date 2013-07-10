<?php

namespace Stormpath\Collections;

use Closure, ArrayIterator;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Common\Collections\Criteria;
use Stormpath\Persistence\ResourceManager;

class ResourceCollection implements Collection, Selectable
{
    private $_resourceManager;
    private $_className;
    private $_href;

    private $_elements;
    private $_isInitialized = false;

    public function __construct(ResourceManager $resourceManager, $className, $href)
    {
        $this->setResourceManager($resourceManager);
        $this->setClassName($className);
        $this->setHref($href);

        $this->_isInitialized = false;
    }

    private function _load()
    {
        if ($this->_isInitialized) {
            return;
        }

        $this->_isInitialized = true;

        $client = $this->getResourceManager()->getHttpClient();
        // FIXME:  add support for pagination
        $client->setUri($this->getHref());
        $client->setMethod('GET');

        $response = $client->send();

        if ($response->isSuccess()) {
            $className = $this->getClassName();
            $body = json_decode($response->getBody(), true);
            if (!isset($body['items'])) return;

            foreach ($body['items'] as $data) {
                $resource = new $className();
                $resource->setResourceManager($this->getResourceManager());
                $resource->exchangeArray($data);
                $this->add($resource);
            }
        } else {
            $this->getResourceManager()->handleInvalidResponse($response);
        }
    }

    public function getResourceManager()
    {
        return $this->resourceManager;
    }

    public function setResourceManager(ResourceManager $resourceManager)
    {
        $this->resourceManager = $resourceManager;
        return $this;
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function setClassName($value)
    {
        $this->className = $value;
        return $this;
    }

    public function getHref()
    {
        return $this->href;
    }

    public function setHref($href)
    {
        $this->href = $href;
    }

    /**
     * Gets the PHP array representation of this collection.
     *
     * @return array The PHP array representation of this collection.
     */
    public function toArray()
    {
        $this->_load();
        return $this->_elements;
    }

    /**
     * Sets the internal iterator to the first element in the collection and
     * returns this element.
     *
     * @return mixed
     */
    public function first()
    {
        $this->_load();
        return reset($this->_elements);
    }

    /**
     * Sets the internal iterator to the last element in the collection and
     * returns this element.
     *
     * @return mixed
     */
    public function last()
    {
        $this->_load();
        return end($this->_elements);
    }

    /**
     * Gets the current key/index at the current internal iterator position.
     *
     * @return mixed
     */
    public function key()
    {
        $this->_load();
        return key($this->_elements);
    }

    /**
     * Moves the internal iterator position to the next element.
     *
     * @return mixed
     */
    public function next()
    {
        $this->_load();
        return next($this->_elements);
    }

    /**
     * Gets the element of the collection at the current internal iterator position.
     *
     * @return mixed
     */
    public function current()
    {
        $this->_load();
        return current($this->_elements);
    }

    /**
     * Removes an element with a specific key/index from the collection.
     *
     * @param mixed $key
     * @return mixed The removed element or NULL, if no element exists for the given key.
     */
    public function remove($key)
    {
        $this->_load();
        if (isset($this->_elements[$key])) {
            $removed = $this->_elements[$key];
            unset($this->_elements[$key]);

            return $removed;
        }

        return null;
    }

    /**
     * Removes the specified element from the collection, if it is found.
     *
     * @param mixed $element The element to remove.
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeElement($element)
    {
        $this->_load();
        $key = array_search($element, $this->_elements, true);

        if ($key !== false) {
            unset($this->_elements[$key]);

            return true;
        }

        return false;
    }

    /**
     * ArrayAccess implementation of offsetExists()
     *
     * @see containsKey()
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        $this->_load();
        return $this->containsKey($offset);
    }

    /**
     * ArrayAccess implementation of offsetGet()
     *
     * @see get()
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        $this->_load();
        return $this->get($offset);
    }

    /**
     * ArrayAccess implementation of offsetSet()
     *
     * @see add()
     * @see set()
     *
     * @param mixed $offset
     * @param mixed $value
     * @return bool
     */
    public function offsetSet($offset, $value)
    {
        $this->_load();
        if ( ! isset($offset)) {
            return $this->add($value);
        }
        return $this->set($offset, $value);
    }

    /**
     * ArrayAccess implementation of offsetUnset()
     *
     * @see remove()
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetUnset($offset)
    {
        $this->_load();
        return $this->remove($offset);
    }

    /**
     * Checks whether the collection contains a specific key/index.
     *
     * @param mixed $key The key to check for.
     * @return boolean TRUE if the given key/index exists, FALSE otherwise.
     */
    public function containsKey($key)
    {
        $this->_load();
        return isset($this->_elements[$key]);
    }

    /**
     * Checks whether the given element is contained in the collection.
     * Only element values are compared, not keys. The comparison of two elements
     * is strict, that means not only the value but also the type must match.
     * For objects this means reference equality.
     *
     * @param mixed $element
     * @return boolean TRUE if the given element is contained in the collection,
     *          FALSE otherwise.
     */
    public function contains($element)
    {
        $this->_load();
        foreach ($this->_elements as $collectionElement) {
            if ($element === $collectionElement) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tests for the existence of an element that satisfies the given predicate.
     *
     * @param Closure $p The predicate.
     * @return boolean TRUE if the predicate is TRUE for at least one element, FALSE otherwise.
     */
    public function exists(Closure $p)
    {
        $this->_load();
        foreach ($this->_elements as $key => $element) {
            if ($p($key, $element)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Searches for a given element and, if found, returns the corresponding key/index
     * of that element. The comparison of two elements is strict, that means not
     * only the value but also the type must match.
     * For objects this means reference equality.
     *
     * @param mixed $element The element to search for.
     * @return mixed The key/index of the element or FALSE if the element was not found.
     */
    public function indexOf($element)
    {
        $this->_load();
        return array_search($element, $this->_elements, true);
    }

    /**
     * Gets the element with the given key/index.
     *
     * @param mixed $key The key.
     * @return mixed The element or NULL, if no element exists for the given key.
     */
    public function get($key)
    {
        $this->_load();
        if (isset($this->_elements[$key])) {
            return $this->_elements[$key];
        }
        return null;
    }

    /**
     * Gets all keys/indexes of the collection elements.
     *
     * @return array
     */
    public function getKeys()
    {
        $this->_load();
        return array_keys($this->_elements);
    }

    /**
     * Gets all elements.
     *
     * @return array
     */
    public function getValues()
    {
        $this->_load();
        return array_values($this->_elements);
    }

    /**
     * Returns the number of elements in the collection.
     *
     * Implementation of the Countable interface.
     *
     * @return integer The number of elements in the collection.
     */
    public function count()
    {
        $this->_load();
        return count($this->_elements);
    }

    /**
     * Adds/sets an element in the collection at the index / with the specified key.
     *
     * When the collection is a Map this is like put(key,value)/add(key,value).
     * When the collection is a List this is like add(position,value).
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $this->_load();
        $this->_elements[$key] = $value;
    }

    /**
     * Adds an element to the collection.
     *
     * @param mixed $value
     * @return boolean Always TRUE.
     */
    public function add($value)
    {
        $this->_load();
        $this->_elements[] = $value;
        return true;
    }

    /**
     * Checks whether the collection is empty.
     *
     * Note: This is preferable over count() == 0.
     *
     * @return boolean TRUE if the collection is empty, FALSE otherwise.
     */
    public function isEmpty()
    {
        $this->_load();
        return ! $this->_elements;
    }

    /**
     * Gets an iterator for iterating over the elements in the collection.
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        $this->_load();
        return new ArrayIterator($this->_elements);
    }

    /**
     * Applies the given function to each element in the collection and returns
     * a new collection with the elements returned by the function.
     *
     * @param Closure $func
     * @return Collection
     */
    public function map(Closure $func)
    {
        $this->_load();
        return new static(array_map($func, $this->_elements));
    }

    /**
     * Returns all the elements of this collection that satisfy the predicate p.
     * The order of the elements is preserved.
     *
     * @param Closure $p The predicate used for filtering.
     * @return Collection A collection with the results of the filter operation.
     */
    public function filter(Closure $p)
    {
        $this->_load();
        return new static(array_filter($this->_elements, $p));
    }

    /**
     * Applies the given predicate p to all elements of this collection,
     * returning true, if the predicate yields true for all elements.
     *
     * @param Closure $p The predicate.
     * @return boolean TRUE, if the predicate yields TRUE for all elements, FALSE otherwise.
     */
    public function forAll(Closure $p)
    {
        $this->_load();
        foreach ($this->_elements as $key => $element) {
            if ( ! $p($key, $element)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Partitions this collection in two collections according to a predicate.
     * Keys are preserved in the resulting collections.
     *
     * @param Closure $p The predicate on which to partition.
     * @return array An array with two elements. The first element contains the collection
     *               of elements where the predicate returned TRUE, the second element
     *               contains the collection of elements where the predicate returned FALSE.
     */
    public function partition(Closure $p)
    {
        $this->_load();
        $coll1 = $coll2 = array();
        foreach ($this->_elements as $key => $element) {
            if ($p($key, $element)) {
                $coll1[$key] = $element;
            } else {
                $coll2[$key] = $element;
            }
        }
        return array(new static($coll1), new static($coll2));
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        $this->_load();
        return __CLASS__ . '@' . spl_object_hash($this);
    }

    /**
     * Clears the collection.
     */
    public function clear()
    {
        $this->_load();
        $this->_elements = array();
    }

    /**
     * Extract a slice of $length elements starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param int $offset
     * @param int $length
     * @return array
     */
    public function slice($offset, $length = null)
    {
        $this->_load();
        return array_slice($this->_elements, $offset, $length, true);
    }

    /**
     * Select all elements from a selectable that match the criteria and
     * return a new collection containing these elements.
     *
     * @param  Criteria $criteria
     * @return Collection
     */
    public function matching(Criteria $criteria)
    {
        $this->_load();
        $expr     = $criteria->getWhereExpression();
        $filtered = $this->_elements;

        if ($expr) {
            $visitor  = new ClosureExpressionVisitor();
            $filter   = $visitor->dispatch($expr);
            $filtered = array_filter($filtered, $filter);
        }

        if ($orderings = $criteria->getOrderings()) {
            $next = null;
            foreach (array_reverse($orderings) as $field => $ordering) {
                $next = ClosureExpressionVisitor::sortByField($field, $ordering == 'DESC' ? -1 : 1, $next);
            }

            usort($filtered, $next);
        }

        $offset = $criteria->getFirstResult();
        $length = $criteria->getMaxResults();

        if ($offset || $length) {
            $filtered = array_slice($filtered, (int)$offset, $length);
        }

        return new static($filtered);
    }
}