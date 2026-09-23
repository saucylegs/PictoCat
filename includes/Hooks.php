<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\GalleryGetModesHook; // 1.45 backwards-compatible name; swap out after REL_ branches are made
use MediaWiki\Hook\CategoryViewer__doCategoryQueryHook;
use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Rdbms\IResultWrapper;

class Hooks implements
	ArticleFromTitleHook,
	OutputPageParserOutputHook,
	CategoryViewer__doCategoryQueryHook,
	GetDoubleUnderscoreIDsHook,
	GetPreferencesHook,
	GalleryGetModesHook
{
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
	 * Used to intercept a category page's ParserOutput so that it can be passed to the CategoryInfoInjector.
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput ParserOutput instance being added in $outputPage
	 * @return void
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$title = $outputPage->getTitle();
		if ( $title->getNamespace() === NS_CATEGORY ) {
			CategoryInfoInjector::getInstance()->addParserOutput( $parserOutput, $title );
		}
	}

	/**
	 * After querying for pages to be displayed in a Category page, load the injector with the results.
	 * @param string $type Category type, either 'page', 'file', or 'subcat'
	 * @param IResultWrapper $res Query result from Wikimedia\Rdbms\IDatabase::select()
	 * @return void
	 * @since 1.35
	 */
	public function onCategoryViewer__doCategoryQuery( $type, $res ): void {
		if ( $type === 'page' ) {
			CategoryInfoInjector::getInstance()->addPageImagesFromQuery( $res );
		}
	}

	/**
	 * Used to add PictoCat's double underscore magic words.
	 * @inheritDoc
	 */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ): void {
		$doubleUnderscoreIDs = array_merge( $doubleUnderscoreIDs, PictoCategory::MAGIC_WORD_IDS );
	}

	/**
	 * Used to add new user preferences.
	 * @param User $user User whose preferences are being modified
	 * @param array &$preferences Preferences description array, to be fed to an HTMLForm object
	 * @return void
	 */
	public function onGetPreferences( $user, &$preferences ): void {
		$preferences['pictocat'] = [
			'type' => 'select',
			'section' => 'rendering/pictocat',
			'label-message' => 'pictocat-preference-use-label',
			'options-messages' => array_flip( PictoCategory::USAGE_PREFERENCE_OPTIONS ),
		];
	}

	/**
	 * Used to add PictoCatImageGallery to the list of available gallery modes.
	 * @inheritDoc
	 */
	public function onGalleryGetModes( &$modeArray ): void {
		$modeArray['pictocat'] = PictoCatImageGallery::class;
	}
}
