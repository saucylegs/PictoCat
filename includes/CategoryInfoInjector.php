<?php

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Context\IContextSource;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Caches information about a category page that must be supplied by hooks but used elsewhere.
 * Because requests are handled synchronously (I think), this class is a singleton.
 * Currently, it caches the following objects:
 * - ParserOutput
 * - PageImageCache
 */
class CategoryInfoInjector {
	/**
	 * @var CategoryInfoInjector|null The singleton instance of this class.
	 */
	private static ?CategoryInfoInjector $instance = null;

	/**
	 * @var ParserOutput|null The ParserOutput object from when a category page's wikitext is parsed.
	 */
	private ?ParserOutput $parserOutput = null;

	/**
	 * @var PageImageCache|null Caches the page images of category members.
	 * Should be populated by the doCategoryQuery hook.
	 */
	private ?PageImageCache $pageImageCache = null;

	/**
	 * @var Title|null The title of the page the ParserOutput came from.
	 */
	private ?Title $title = null;

	private function __construct() {
		// Singleton class; no public constructor
	}

	/**
	 * @return CategoryInfoInjector The singleton instance of this class.
	 */
	public static function getInstance(): CategoryInfoInjector {
		self::$instance ??= new CategoryInfoInjector();
		return self::$instance;
    }

	/**
	 * Sets the ParserOutput object to be held onto.
	 * @param ParserOutput $parserOutput The ParserOutput object to cache.
	 * @param Title $title The title of the page the ParserOutput came from.
	 * @return void
	 */
	public function addParserOutput( ParserOutput $parserOutput, Title $title ): void {
		$this->parserOutput = $parserOutput;
		$this->title = $title;
	}

	/**
	 * Uses the database query result to populate the PageImageCache.
	 * This will clear any existing PageImageCache.
	 * @param IResultWrapper $queryResult A database result returned by CategoryViewer::doCategoryQuery.
	 * @return void
	 */
	public function addPageImagesFromQuery( IResultWrapper $queryResult ): void {
		$this->pageImageCache = new PageImageCache();
		$this->pageImageCache->addFromDbQuery( $queryResult );
	}

	/**
	 * @return ParserOutput|null The cached ParserOutput object, or null if none is cached.
	 */
	public function getParserOutput(): ?ParserOutput {
		return $this->parserOutput;
	}

	/**
	 * Returns the cached ParserOutput only if it comes from the same page.
	 * @param IContextSource $context The request context to compare with.
	 * @return ParserOutput|null The cached ParserOutput object, or null if it is not relevant.
	 */
	public function getParserOutputIfRelevant( IContextSource $context ): ?ParserOutput {
		if ( $this->parserOutput === null || !$this->title?->equals( $context->getTitle() ) ) {
			return null;
		}
		return $this->parserOutput;
	}

	/**
	 * @return PageImageCache The cached PageImageCache. May be an empty instance if no PageImageCache was available.
	 */
	public function getPageImageCache(): PageImageCache {
		if ( $this->pageImageCache === null ) {
			wfWarn( '[PictoCat] Trying to access a null PageImageCache from the injector.' );
			$this->pageImageCache = new PageImageCache();
		}
		return $this->pageImageCache;
	}

	/**
	 * Clears all objects from this cache.
	 * @return void
	 */
	public function clear(): void {
		$this->title = null;
		$this->parserOutput = null;
		$this->pageImageCache = null;
	}
}
