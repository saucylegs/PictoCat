<?php

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Category\Category;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleArrayFromResult;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Used to determine how a PictoCat category page should be displayed.
 */
class PictoCategory {
	/**
	 * The IDs used for the double underscore magic words that override a category's display style.
	 */
	public const MAGIC_WORD_IDS = [ 'nopictocat', 'pictocat', 'usebulletstyle' ];

	/**
	 * The options for the user preference that overrides when PictoCat should be used.
	 * The keys are the option values, and the values are the corresponding i18n message keys.
	 */
	public const USAGE_PREFERENCE_OPTIONS = [
		'default' => 'pictocat-preference-use-default',
		'always' => 'pictocat-preference-use-always',
		'never' => 'pictocat-preference-use-never'
	];

	/**
	 * @var Title The title of the category page.
	 */
	public readonly Title $categoryTitle;

	/**
	 * @var Category A Category object for the associated category.
	 */
	public readonly Category $category;

	/**
	 * @var PictoCatStyle
	 */
	private PictoCatStyle $style;

	/**
	 * @var array The page properties for the page corresponding to this category, according to the ParserOutput.
	 */
	private array $pageProperties;

	private IConnectionProvider $dbProvider;

	private TitleFactory $titleFactory;

	/**
	 * Instantiates a PictoCategory object.
	 * The PictoCat style will be determined based on the category page's properties and the wiki's configuration.
	 * This should be called once the parser has parsed the category page.
	 * @param IContextSource $context The request context.
	 */
	public function __construct( IContextSource $context ) {
		$this->categoryTitle = $context->getTitle();
		wfDebug( "[PictoCat] Entering PictoCategory constructor for {$this->categoryTitle->getFullText()}" );
		$this->category = Category::newFromTitle( $this->categoryTitle );

		// Set dependency injection fields
		$services = MediaWikiServices::getInstance();
		$this->dbProvider = $services->getConnectionProvider();
		$this->titleFactory = $services->getTitleFactory();
		$mainConfig = $context->getConfig();

		// Get category page properties
		$parserOutput = CategoryInfoInjector::getInstance()->getParserOutputIfRelevant( $context );
		if ( $parserOutput ) {
			wfDebug( '[PictoCat] Using injected ParserOutput' );
			$this->pageProperties = $parserOutput->getPageProperties();
		} elseif ( $context->canUseWikiPage() ) {
			// The injector does not have a relevant ParserOutput.
			// Instead, parse the page (or get a cached parse) via the WikiPage object.
			// This method is adequate for published edits, but not for edit previews.
			wfDebug( '[PictoCat] Looking up ParserOutput from WikiPage' );
			$this->pageProperties = $context->getWikiPage()->getParserOutput()->getPageProperties();
		} else {
			wfDebug( '[PictoCat] Can\'t get page properties in this context!' );
			$this->pageProperties = [];
		}

		// Determine PictoCat style
		// First, check for a request parameter
		$urlParam = $context->getRequest()->getRawVal( 'pictocat' );
		switch ( $urlParam ) {
			case 'false':
			case 'no':
			case 'none':
				$this->style = PictoCatStyle::None;
				return;
			case 'bullet':
				$this->style = PictoCatStyle::Bullet;
				return;
			case 'true':
			case 'yes':
				$this->style = self::getWikiDefaultStyle( $mainConfig );
				return;
			default:
				break;
		}

		// Check the user preference
		$preference = $services->getUserOptionsLookup()->getOption( $context->getUser(), 'pictocat' );
		switch ( $preference ) {
			case 'always':
				$this->style = self::getWikiDefaultStyle( $mainConfig );
				return;
			case 'never':
				$this->style = PictoCatStyle::None;
				return;
			default:
				break;
		}

		// Check if the style has been set by a magic word
		$pageProps = $this->getPageProps( self::MAGIC_WORD_IDS );
		switch ( array_key_first( $pageProps ) ) {
			case 'usebulletstyle':
				$this->style = PictoCatStyle::Bullet;
				return;
			case 'nopictocat':
				$this->style = PictoCatStyle::None;
				return;
			case 'pictocat':
				// Force default style
				$this->style = self::getWikiDefaultStyle( $mainConfig );
				return;
			default:
				break;
		}

		// Otherwise, check if the automatic activation threshold has been met
		$activationPercentage = $mainConfig->get( 'PictoCatActivationPercentage' );
		if ( $activationPercentage > 100 ) {
			// >100% isn't possible. Treat this as "none by default".
			$this->style = PictoCatStyle::None;
			return;
		}
		if ( $activationPercentage > 0 ) {
			// To avoid memory exhaustion, don't check more than $wgCategoryPagingLimit members.
			$queryLimit = $mainConfig->get( MainConfigNames::CategoryPagingLimit );
			$members = $this->getPageMembers( $queryLimit );
			$memberCount = count( $members );

			// Avoid dividing by zero for empty categories
			if ( $memberCount === 0 ) {
				$this->style = PictoCatStyle::None;
				return;
			}

			$membersWithPageImages = $services->getPageProps()->getProperties(
				// @phan-suppress-next-line PhanTypeMismatchArgument
				$members, [ 'page_image', 'page_image_free' ]
			);

			$actualPercentage = count( $membersWithPageImages ) / (float)$memberCount * 100.0;
			if ( $actualPercentage < $activationPercentage ) {
				$this->style = PictoCatStyle::None;
				return;
			}
		}

		// Finally, if no return yet, check the config for the default style
		$this->style = self::getWikiDefaultStyle( $mainConfig );
	}

	/**
	 * Returns a PictoCatStyle based on the value of the wiki's $wgPictoCatDefaultStyle.
	 * Defaults to Bullet if the value is unrecognized.
	 * @param Config $siteConfig The site configuration, from a ContextSource or MediaWikiServices.
	 * @return PictoCatStyle The default PictoCat style for the wiki.
	 */
	public static function getWikiDefaultStyle( Config $siteConfig ): PictoCatStyle {
		return PictoCatStyle::tryFrom(
			strtolower( $siteConfig->get( 'PictoCatDefaultStyle' ) )
		) ?? PictoCatStyle::Bullet;
	}

	/**
	 * Gets (from the database) the total number of content pages (i.e., excluding files and subcategories)
	 * in the associated category.
	 * @return int The total number of content pages in the associated category.
	 */
	public function fetchPageMemberCount(): int {
		return $this->category->getPageCount( Category::COUNT_CONTENT_PAGES );
	}

	/**
	 * Gets the page properties for the page corresponding to this category.
	 * @param string|string[]|null $propertyNames The name(s) of the page properties to get, or null to get them all.
	 * @return array<string,string> Array of property names to property values.
	 * Properties that were requested but not found will be omitted.
	 */
	public function getPageProps( array|string|null $propertyNames = null ): array {
		if ( $propertyNames === null ) {
			return $this->pageProperties;
		}
		if ( is_string( $propertyNames ) ) {
			if ( array_key_exists( $propertyNames, $this->pageProperties ) ) {
				return [ $propertyNames => $this->pageProperties[ $propertyNames ] ];
			}
			return [];
		}
		return array_intersect_key( $this->pageProperties, array_flip( $propertyNames ) );
	}

	/**
	 * This is a simple getter; the style is determined during instantiation.
	 * @return PictoCatStyle The image presentation style for this category.
	 */
	public function getStyle(): PictoCatStyle {
		return $this->style;
	}

	/**
	 * Fetch a TitleArray of all the content pages in the category, i.e., not including files or subcategories.
	 * @param int $limit The maximum number of pages to fetch.
	 * @return TitleArrayFromResult Title objects for category members.
	 */
	private function getPageMembers( int $limit ): TitleArrayFromResult {
		// This code is based on MediaWiki\Category\Category.getMembers, which changed in MW 1.44.
		// It is modified to only retrieve pages, not subcategories or files.
		$queryBuilder = $this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder();
		$queryBuilder->select( [ 'page_id', 'page_namespace', 'page_title', 'page_len',
			'page_is_redirect', 'page_latest' ] )
			->from( 'categorylinks' )
			->join( 'page', null, 'cl_from = page_id' )
			->join( 'linktarget', null, 'cl_target_id = lt_id' )
			->where( [ 'lt_title' => $this->categoryTitle->getDBkey(),
				'lt_namespace' => NS_CATEGORY,
				'cl_type' => 'page' ] )
			->orderBy( 'cl_sortkey' )
			->limit( $limit );

		return $this->titleFactory->newTitleArrayFromResult(
			$queryBuilder->caller( __METHOD__ )->fetchResultSet()
		);
	}
}
