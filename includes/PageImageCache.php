<?php

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageProps;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Caches page IDs and their corresponding PageImage.
 */
class PageImageCache {
	/**
	 * @var Title[] Keys should be a page ID, and values should be a file title or null.
	 */
	private array $cache = [];

	/**
	 * @var PageProps Allows the properties of any page to be retrieved.
	 */
	private PageProps $pagePropsService;

	public function __construct() {
		$this->pagePropsService = MediaWikiServices::getInstance()->getPageProps();
	}

	/**
	 * Add to the cache all the pages from a database query.
	 * Intended to be used with the CategoryViewer::doCategoryQuery hook.
	 * @param IResultWrapper $rows The result from a database query.
	 * @return void
	 */
	public function addFromDbQuery( IResultWrapper $rows ): void {
		// Create a Title object for each page
		$titles = [];
		$additions = [];
		foreach ( $rows as $row ) {
			$title = Title::newFromRow( $row );
			// Skip files and categories
			if ( $title->getNamespace() === NS_FILE || $title->getNamespace() === NS_CATEGORY ) {
				continue;
			}
			$titles[] = $title;
			// null will be overwritten later if and only if the page has a PageImage
			// TODO: Test if the null remains
			$additions[ $title->getId() ] = null;
		}

		// Fetch the PageImage properties for the pages
		$pagePropsResult = $this->pagePropsService->getProperties( $titles, [ 'page_image', 'page_image_free' ] );
		foreach ( $pagePropsResult as $pageId => $props ) {
			$pageImageName = $props[ 'page_image_free' ] ?? $props[ 'page_image' ];
			$additions[ (int)$pageId ] = Title::makeTitle( NS_FILE, $pageImageName );
			wfDebug( "[PictoCat][PageImageCache] Page $pageId has page image $pageImageName" );
		}

		$this->cache = $this->cache + $additions;
	}

	/**
	 * Get a page's PageImage without removing it from the cache.
	 * @param int $pageId The numeric page ID of the page to look up.
	 * @return Title|null The title of the page's PageImage file, or null if it doesn't have one.
	 */
	public function peek( int $pageId ): ?Title {
		if ( array_key_exists( $pageId, $this->cache ) ) {
			return $this->cache[ $pageId ];
		}

		// Not already cached
		return $this->fetch( $pageId, true );
	}

	/**
	 * Get a page's PageImage and remove it from the cache.
	 * @param int $pageId The numeric page ID of the page to look up.
	 * @return Title|null The title of the page's PageImage file, or null if it doesn't have one.
	 */
	public function pop( int $pageId ): ?Title {
		if ( array_key_exists( $pageId, $this->cache ) ) {
			$result = $this->cache[ $pageId ];
			unset( $this->cache[ $pageId ] );
			return $result;
		}

		// Not already cached
		return $this->fetch( $pageId );
	}

	/**
	 * Fetches a page's PageImage from the database.
	 * This method should only be used if the page is not already in the cache.
	 * @param int $pageId The numeric page ID of the page to look up.
	 * @param bool $addToCache Whether to add the page to the cache.
	 * @return Title|null The title of the page's PageImage file, or null if it doesn't have one.
	 */
	private function fetch( int $pageId, bool $addToCache = false ): ?Title {
		wfDebug( "[PictoCat][PageImageCache] Image for page $pageId is not already cached" );
		$title = Title::newFromId( $pageId );
		$pageProps = $this->pagePropsService->getProperties( $title, [ 'page_image', 'page_image_free' ] );
		$pageImageTitle = $pageProps ?
			Title::makeTitle( NS_FILE,
				$pageProps[ $pageId ][ 'page_image_free' ] ?? $pageProps[ $pageId ][ 'page_image' ] )
			: null;
		if ( $addToCache ) {
			$this->cache[ $pageId ] = $pageImageTitle;
		}
		return $pageImageTitle;
	}
}
