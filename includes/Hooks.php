<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;

class Hooks implements
	ArticleFromTitleHook,
	OutputPageParserOutputHook,
	GetDoubleUnderscoreIDsHook
{
	private ParserOutputInjector $parserOutputInjector;

	public function __construct() {
		$this->parserOutputInjector = new ParserOutputInjector();
	}

	/**
	 * Used to set PictoCategoryPage as the article rendering class for category pages.
	 * @param Title $title Title used to create the article object
	 * @param Article &$article Article that will be returned
	 * @param IContextSource $context
	 * @return void
	 */
	public function onArticleFromTitle( $title, &$article, $context ): void {
		if ( $title->getNamespace() === NS_CATEGORY ) {
			$article = new PictoCategoryPage( $title, $this->parserOutputInjector );
		}
	}

	/**
	 * Used to intercept a category page's ParserOutput so that it can be passed to PictoCategoryPage.
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput ParserOutput instance being added in $outputPage
	 * @return void
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( $title->getNamespace() === NS_CATEGORY ) {
			$this->parserOutputInjector->set( $parserOutput, $title );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ): void {
		// Add PictoCat magic words
		$doubleUnderscoreIDs = array_merge( $doubleUnderscoreIDs, PictoCategory::MAGIC_WORD_IDS );
	}
}
