<?php

namespace MediaWiki\Extension\PictoCat;

/**
 * Caches PictoCategory objects.
 */
class CategoryCache {
	/**
	 * @var PictoCategory[] Keys should be a category ID, and values should be a PictoCategory object.
	 */
	private array $cache = [];

	/**
	 * Adds the given PictoCategory to the cache.
	 * @param PictoCategory $category The PictoCategory to cache.
	 * @return void
	 */
	public function add( PictoCategory $category ): void {
		$this->cache[ $category->getCategoryTitle()->getId() ] = $category;
	}

	/**
	 * Gets from the cache the PictoCategory whose category page has the given ID.
	 * @param int $categoryId The numeric ID of the category page.
	 * @return PictoCategory|null The cached PictoCategory object, or null if it's not in the cache.
	 */
	public function get( int $categoryId ): ?PictoCategory {
		return $this->cache[ $categoryId ] ?? null;
	}

	/**
	 * Gets and removes from the cache the PictoCategory whose category page has the given ID.
	 * @param int $categoryId The numeric ID of the category page.
	 * @return PictoCategory|null The cached PictoCategory object, or null if it's not in the cache.
	 */
	public function pop( int $categoryId ): ?PictoCategory {
		$result = $this->get( $categoryId );
		$this->remove( $categoryId );
		return $result;
	}

	/**
	 * Removes from the cache the PictoCategory whose category page has the given ID.
	 * Has no effect if it wasn't in the cache in the first place.
	 * @param int $categoryId The numeric ID of the category page.
	 * @return void
	 */
	public function remove( int $categoryId ): void {
		unset( $this->cache[ $categoryId ] );
	}
}
