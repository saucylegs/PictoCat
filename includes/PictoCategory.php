<?php

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Category\Category;
use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleArrayFromResult;
use MediaWiki\Title\TitleFactory;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Represents a category that uses PictoCat. Has methods regarding the PageImages of member pages
 * and determines how the category page should be displayed.
 */
class PictoCategory {
	/**
	 * @var Title The title of the category page.
	 */
	private Title $categoryTitle;

	/**
	 * @var PictoCatStyle
	 */
	private PictoCatStyle $style;

	/**
	 * @var array The page properties for the page corresponding to this category, according to the ParserOutput.
	 */
	private array $pageProperties;

	/**
	 * @var int|null The number of pages (excluding files and subcategories) in the category.
	 */
	private ?int $pageCount;

	private IConnectionProvider $dbProvider;

	private TitleFactory $titleFactory;

	/**
	 * The IDs used for the double underscore magic words that override a category's display style.
	 */
	public const MAGIC_WORD_IDS = [ 'nopictocat', 'pictocat', 'usebulletstyle' ];

	/**
	 * Instantiates a PictoCategory object.
	 * The PictoCat style will be determined based on the category page's properties and the wiki's configuration.
	 * This should be called once the parser has parsed the category page.
     * @param IContextSource $context The request context.
	 */
	public function __construct( IContextSource $context ) {
        $this->categoryTitle = $context->getTitle();

		if ( $context->canUseWikiPage() ) {
			wfDebug( '[PictoCat] Using WikiPage page properties.' );
			$this->pageProperties = $context->getWikiPage()->getParserOutput()->getPageProperties();
		} else {
			wfDebug( '[PictoCat] Can\'t get page properties in this context!' );
			$this->pageProperties = [];
		}
		// TODO: Remove these debugging lines
		$pagePropsContents = var_export( $this->pageProperties, true );
		wfDebug( "[PictoCat] Page properties: $pagePropsContents" );
		$contextContents = var_export( $context->getOutput()->getMetadata(), true );
		wfDebug( "[PictoCat] context->getOutput->getMetadata: $contextContents" );

		// Set dependency injection fields
		$services = MediaWikiServices::getInstance();
		$this->dbProvider = $services->getConnectionProvider();
		$this->titleFactory = $services->getTitleFactory();
		$mainConfig = $context->getConfig();

		// Determine PictoCat style
		// First, check if the style has been explicitly set
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
				$this->style = PictoCatStyle::tryFrom(
					strtolower( $mainConfig->get( 'PictoCatDefaultStyle' ) )
				) ?? PictoCatStyle::Bullet;
				return;
			default:
				break;
		}

		// Otherwise, check if the automatic activation threshold has been met
		$activationPercentage = $mainConfig->get( 'PictoCatActivationPercentage' );
		if ( $activationPercentage > 0 ) {
			$members = $this->getPageMembers();

            // Avoid dividing by zero for empty categories
            if ( count( $members ) === 0 ) {
                $this->style = PictoCatStyle::None;
                return;
            }

			$membersWithPageImages = $services->getPageProps()->getProperties(
				$members, [ 'page_image', 'page_image_free' ]
			);

			$actualPercentage = count( $membersWithPageImages ) / (float)count( $members ) * 100.0;
			if ( ceil( $actualPercentage ) < $activationPercentage ) {
				$this->style = PictoCatStyle::None;
				return;
			}
		}

		// Finally, check the config for the default style
		// "Bullet" is the default style if the config value is unrecognized
		$this->style = PictoCatStyle::tryFrom(
			strtolower( $mainConfig->get( 'PictoCatDefaultStyle' ) )
		) ?? PictoCatStyle::Bullet;
	}

	/**
	 * @return Title The title of the associated category page.
	 */
	public function getCategoryTitle(): Title {
		return $this->categoryTitle;
	}

	/**
	 * Gets the Category object for the associated category.
	 * This object is constructed each time this method is called, so you shouldn't call it more than necessary.
	 * @return Category The Category object for the associated category.
	 */
	public function getCategory(): Category {
		return Category::newFromTitle( $this->categoryTitle );
	}

	/**
	 * @return int The total number of content pages (i.e., excluding files and subcategories)
	 * in the associated category.
	 */
	public function getPageMemberCount(): int {
		if ( !isset( $this->pageCount ) ) {
			$this->pageCount = $this->getCategory()->getPageCount( Category::COUNT_CONTENT_PAGES );
		}
		return $this->pageCount;
	}

	/**
	 * Gets the page properties for the page corresponding to this category.
	 * The pictocat_style property is not included; use getStyle() for that.
	 * @param string|string[] $propertyNames The name(s) of the page properties to get.
	 * @return array<string,string> Array of property names to property values.
	 * Properties that were requested but not found will be omitted.
	 */
	public function getPageProps( array|string $propertyNames ): array {
		if ( is_string( $propertyNames ) ) {
			if ( array_key_exists( $propertyNames, $this->pageProperties ) ) {
				return [ $propertyNames => $this->pageProperties[ $propertyNames ] ];
			}
			return [];
		}
		return array_intersect_key( $this->pageProperties, array_flip( $propertyNames ) );
	}

	/**
	 * @return PictoCatStyle The image presentation style for this category.
	 */
	public function getStyle(): PictoCatStyle {
		return $this->style;
	}

	/**
	 * Fetch a TitleArray of all the content pages in the category, i.e., not including files or subcategories.
	 * @return TitleArrayFromResult Title objects for category members.
	 */
	protected function getPageMembers(): TitleArrayFromResult {
		// This code is based on MediaWiki\Category\Category.getMembers.
		// It was changed in MW 1.44.
		$dbr = $this->dbProvider->getReplicaDatabase();
		$queryBuilder = $dbr->newSelectQueryBuilder();
		$queryBuilder->select( [ 'page_id', 'page_namespace', 'page_title', 'page_len',
			'page_is_redirect', 'page_latest' ] )
			->from( 'categorylinks' )
			->join( 'page', null, 'cl_from = page_id' )
			->join( 'linktarget', null, 'cl_target_id = lt_id' )
			->where( [ 'lt_title' => $this->categoryTitle->getDBkey(),
				'lt_namespace' => NS_CATEGORY,
				'cl_type' => 'page' ] )
			->orderBy( 'cl_sortkey' );

		$result = $this->titleFactory->newTitleArrayFromResult(
			$queryBuilder->caller( __METHOD__ )->fetchResultSet()
		);
		$this->pageCount = count( $result );
		return $result;
	}
}
