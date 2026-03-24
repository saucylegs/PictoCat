<?php

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Context\IContextSource;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;

/**
 * Caches a ParserOutput object so that it can be retrieved in situations
 * where it cannot be accessed by other means (like during edit previews).
 */
class ParserOutputInjector {
	/**
	 * @var ParserOutput|null The ParserOutput object being cached.
	 * Because requests are handled synchronously (I think), we shouldn't need to cache more than one.
	 */
	private ?ParserOutput $cached = null;

	/**
	 * @var Title|null The title of the page the ParserOutput came from.
	 */
	private ?Title $title = null;

	/**
	 * Sets the ParserOutput object to be held onto.
	 * @param ParserOutput $parserOutput The ParserOutput object to cache.
	 * @param Title $title The title of the page the ParserOutput came from.
	 * @return void
	 */
	public function set( ParserOutput $parserOutput, Title $title ): void {
		$this->cached = $parserOutput;
		$this->title = $title;
	}

	/**
	 * Returns the cached ParserObject only if it comes from the same page.
	 * @param IContextSource $context The request context to compare with.
	 * @return ParserOutput|null The cached ParserOutput object, or null if it is not relevant.
	 */
	public function getIfRelevant( IContextSource $context ): ?ParserOutput {
		if ( $this->cached === null || !$this->title?->equals( $context->getTitle() ) ) {
			return null;
		}
		return $this->cached;
	}

	/**
	 * Clears the ParserOutput object from this cache.
	 * @return void
	 */
	public function clear(): void {
		$this->title = null;
		$this->cached = null;
	}
}
