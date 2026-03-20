<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Page\Article;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\Title\Title;

class Hooks implements
	ArticleFromTitleHook,
	GetDoubleUnderscoreIDsHook
{
	// TODO: Figure out something for edit previews

	/**
	 * Used to set PictoCategoryPage as the article rendering class for category pages.
	 * @param Title $title Title used to create the article object
	 * @param Article &$article Article that will be returned
	 * @param IContextSource $context
	 * @return void
	 */
	public function onArticleFromTitle( $title, &$article, $context ): void {
		if ( $title->getNamespace() === NS_CATEGORY ) {
			$article = new PictoCategoryPage( $title );
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
