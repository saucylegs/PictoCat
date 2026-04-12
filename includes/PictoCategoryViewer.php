<?php
/**
 * PictoCat modification of the listing and paging of category members.
 * This file contains code copied from MediaWiki core, which is licensed under GPL-2.0-or-later.
 *
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\PictoCat;

use InvalidArgumentException;
use MediaWiki\Category\CategoryViewer;
use MediaWiki\Context\IContextSource;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Html\Html;
use MediaWiki\Language\ILanguageConverter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Title\TitleValue;
use MediaWiki\Utils\UrlUtils;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\HtmlArmor\HtmlArmor;

/**
 * Responsible for generating the HTML of the listing of pages in a category.
 */
class PictoCategoryViewer extends CategoryViewer {
	/**
	 * @var int The width, in pixels, of the requested thumbnail for bullet-style images.
	 * This is not necessarily the displayed size of a bullet image.
	 */
	public const BULLET_RENDER_SIZE = 60;

	/**
	 * @var PictoCategory The PictoCategory object for this category. Useful for getting the style.
	 */
	public PictoCategory $pictocat;

	/** @var ILanguageConverter */
	protected ILanguageConverter $languageConverter;

	/**
	 * @var RepoGroup The file repositories used in this wiki.
	 */
	protected RepoGroup $repoGroup;

	/** @var array The original query array, to be used in generating paging links. */
	protected array $query;

	/** @var LinkRenderer */
	protected LinkRenderer $linkRenderer;

	/**
	 * @var Codex The Wikimedia Codex instance, used to generate bullet thumbnails.
	 */
	private Codex $codex;

	/**
	 * @var CategoryInfoInjector
	 */
	private CategoryInfoInjector $injector;

	/**
	 * @var UrlUtils
	 */
	private UrlUtils $urlUtils;

	/**
	 * @param PageIdentity $page
	 * @param IContextSource $context
	 * @param array $from An array with keys page, subcat,
	 *        and file for offset of results of each section (since 1.17)
	 * @param array $until An array with 3 keys for until of each section (since 1.17)
	 * @param array $query
	 *@since 1.19 $context is a second, required parameter
	 */
	public function __construct( PageIdentity $page, IContextSource $context,
								 array $from = [], array $until = [], array $query = []
	) {
		parent::__construct( $page, $context, $from, $until, $query );
		$this->query = $query;
		$services = MediaWikiServices::getInstance();
		$this->repoGroup = $services->getRepoGroup();
		$this->languageConverter = $services->getLanguageConverterFactory()->getLanguageConverter();
		$this->linkRenderer = $services->getLinkRenderer();
		$this->urlUtils = $services->getUrlUtils();
		$this->codex = new Codex();
		$this->injector = CategoryInfoInjector::getInstance();
		$this->pictocat = new PictoCategory( $context );
		if ( $this->pictocat->getStyle() === PictoCatStyle::Bullet ) {
			$this->getOutput()->addModuleStyles( 'ext.pictoCat.bullet' );
		}
	}

	public function __destruct() {
		wfDebug( '[PictoCat] PictoCategoryViewer destructor called.' );
		$this->injector->clear();
	}

	/**
	 * @inheritDoc
	 */
	public function addPage(
		PageReference $page,
		string $sortkey,
		int $pageLength,
		bool $isRedirect = false
	): void {
		if ( $this->pictocat->getStyle() !== PictoCatStyle::Bullet ) {
			parent::addPage( $page, $sortkey, $pageLength, $isRedirect );
			return;
		}

		$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromPageReference( $page );
		$image = $this->repoGroup->findFile( $this->injector->getPageImageCache()->pop( $title->getId() ) );

		// Render image bullet
		$thumbnail = $this->codex->thumbnail();
		if ( $image ) {
			$thumbUrl = $image->createThumb( self::BULLET_RENDER_SIZE );
			// createThumb can output a relative URL, which Codex doesn't like.
			$thumbUrl = $this->urlUtils->expand( $thumbUrl );
			$thumbnail->setBackgroundImage( $thumbUrl );
		}
		// Otherwise, Codex should automatically use a placeholder icon

		$html = $thumbnail->build()->getHtml();

		// Render page name
		$html .= Html::element(
			'span',
			[ 'class' => $isRedirect ? 'member-name redirect-in-category' : 'member-name' ],
			$title->getFullText()
		);

		// Make link
		$html = new HtmlArmor( $html );
		$link = $this->linkRenderer->makeLink( $page, $html, [
			'class' => 'pictocat-bullet'
		] );

		$this->articles[] = $link;
		$this->articles_start_char[] =
			$this->languageConverter->convert( $this->collation->getFirstLetter( $sortkey ) );
	}

	/**
	 * @return string The HTML for the pages section
	 */
	protected function getPagesSection(): string {
		$name = $this->getOutput()->getUnprefixedDisplayTitle();
		$style = $this->pictocat->getStyle();
		$html = '';

		$databaseCount = $this->pictocat->fetchPageMemberCount();
		$localCount = count( $this->articles );
		// This function should be called even if the result isn't used, it has side effects
		$countMessage = $this->getCountMessage( $localCount, $databaseCount, 'article' );

		if ( $localCount > 0 ) {
			$html .= Html::openElement( 'div', [
					'id' => 'mw-pages',
					'class' => "pictocat-$style->value-style"
				] ) . "\n";
			$html .= Html::rawElement(
					'h2',
					[],
					$this->msg( 'category_header' )->rawParams( $name )->parse()
				) . "\n";
			$html .= $countMessage;
			$html .= $this->getSectionPagingLinks( 'page' );
			$html .= $this->formatList( $this->articles, $this->articles_start_char );
			$html .= $this->getSectionPagingLinks( 'page' );
			$html .= "\n" . Html::closeElement( 'div' );
		}
		return $html;
	}

	/**
	 * Takes a title and adds the fragment identifier that
	 * corresponds to the correct segment of the category.
	 * Identical to the parent class's method, but it is private, so we need to re-implement it.
	 *
	 * @param PageReference $page The title (usually $this->title)
	 * @param string $section Which section
	 * @return LinkTarget
	 */
	protected function addFragmentToTitle( PageReference $page, string $section ): LinkTarget {
		$fragment = match ( $section ) {
			'page' => 'mw-pages',
			'subcat' => 'mw-subcategories',
			'file' => 'mw-category-media',
			default => throw new InvalidArgumentException( __METHOD__ .
				" Invalid section $section." ),
		};

		return new TitleValue( $page->getNamespace(),
			$page->getDBkey(), $fragment );
	}

	/**
	 * Get the paging links for a section (subcats/pages/files), to go at the top and bottom
	 * of the output.
	 * Identical to the parent class's method, but it is private for some reason, so we need to re-implement it.
	 *
	 * @param string $type 'page', 'subcat', or 'file'
	 * @return string HTML output, possibly empty if there are no other pages
	 */
	protected function getSectionPagingLinks( string $type ): string {
		if ( isset( $this->until[$type] ) ) {
			// The new value for the until parameter should be pointing to the first
			// result displayed on the page which is the second last result retrieved
			// from the database.The next link should have a from parameter pointing
			// to the until parameter of the current page.
			if ( $this->nextPage[$type] !== null ) {
				return $this->pagingLinks(
					$this->prevPage[$type] ?? '',
					$this->until[$type],
					$type
				);
			}

			// If the nextPage variable is null, it means that we have reached the first page
			// and therefore the previous link should be disabled.
			return $this->pagingLinks(
				'',
				$this->until[$type],
				$type
			);
		} elseif ( $this->nextPage[$type] !== null || isset( $this->from[$type] ) ) {
			return $this->pagingLinks(
				$this->from[$type] ?? '',
				$this->nextPage[$type],
				$type
			);
		}

		return '';
	}

	/**
	 * Create paging links, as a helper method to getSectionPagingLinks().
	 * Identical to the parent class's method, but it is private, so we need to re-implement it.
	 *
	 * @param string $first The 'until' parameter for the generated URL
	 * @param string $last The 'from' parameter for the generated URL
	 * @param string $type A prefix for parameters, 'page' or 'subcat' or
	 *     'file'
	 * @return string HTML
	 */
	private function pagingLinks( string $first, string $last, string $type = '' ): string {
		$prevLink = $this->msg( 'prev-page' )->escaped();

		if ( $first != '' ) {
			$prevQuery = $this->query;
			$prevQuery["{$type}until"] = $first;
			unset( $prevQuery["{$type}from"] );
			$prevLink = $this->linkRenderer->makeKnownLink(
				$this->addFragmentToTitle( $this->page, $type ),
				new HtmlArmor( $prevLink ),
				[],
				$prevQuery
			);
		}

		$nextLink = $this->msg( 'next-page' )->escaped();

		if ( $last != '' ) {
			$lastQuery = $this->query;
			$lastQuery["{$type}from"] = $last;
			unset( $lastQuery["{$type}until"] );
			$nextLink = $this->linkRenderer->makeKnownLink(
				$this->addFragmentToTitle( $this->page, $type ),
				new HtmlArmor( $nextLink ),
				[],
				$lastQuery
			);
		}

		return $this->msg( 'categoryviewer-pagedlinks' )->rawParams( $prevLink, $nextLink )->escaped();
	}

	/**
	 * Format a list of articles chunked by letter, either as a
	 * bullet list or a columnar format, depending on the length.
	 * Identical to the parent class's method, but it is private for some reason, so we need to re-implement it.
	 *
	 * @param array $articles
	 * @param array $articles_start_char
	 * @param int $cutoff
	 * @return string
	 */
	protected function formatList( array $articles, array $articles_start_char, int $cutoff = 6 ): string {
		$list = '';
		if ( count( $articles ) > $cutoff ) {
			$list = self::columnList( $articles, $articles_start_char );
		} elseif ( count( $articles ) > 0 ) {
			// for short lists of articles in categories.
			$list = self::shortList( $articles, $articles_start_char );
		}

		$pageLang = MediaWikiServices::getInstance()->getTitleFactory()
			->newFromPageIdentity( $this->page )
			->getPageLanguage();
		$attribs = [ 'lang' => $pageLang->getHtmlCode(), 'dir' => $pageLang->getDir(),
			'class' => 'mw-content-' . $pageLang->getDir() ];

		return Html::rawElement( 'div', $attribs, $list );
	}

	/**
	 * What to do if the category table conflicts with the number of results
	 * returned?  This function says what. Each type is considered independently
	 * of the other types.
	 * Identical to the parent class's method, but it is private for some reason, so we need to re-implement it.
	 *
	 * @param int $localCount The number of items returned by our database query.
	 * @param int $databaseCount The number of items according to the category table.
	 * @param string $type 'subcat', 'article', or 'file'
	 * @return string A message giving the number of items, to output to HTML.
	 */
	protected function getCountMessage( int $localCount, int $databaseCount, string $type ): string {
		// There are three cases:
		//   1) The category table figure seems good.  It might be wrong, but
		//      we can't do anything about it if we don't recalculate it on every
		//      category view.
		//   2) The category table figure isn't good, like it's smaller than the
		//      number of actual results, *but* the number of results is less
		//      than $this->limit and there's no offset.  In this case we still
		//      know the right figure.
		//   3) We have no idea.

		// Check if there's a "from" or "until" for anything

		// This is a little ugly, but we seem to use different names
		// for the paging types then for the messages.
		if ( $type === 'article' ) {
			$pagingType = 'page';
		} else {
			$pagingType = $type;
		}

		$fromOrUntil = false;
		if ( isset( $this->from[$pagingType] ) || isset( $this->until[$pagingType] ) ) {
			$fromOrUntil = true;
		}

		if ( $databaseCount == $localCount ||
			( ( $localCount == $this->limit || $fromOrUntil ) && $databaseCount > $localCount )
		) {
			// Case 1: seems good.
			$totalCount = $databaseCount;
		} elseif ( $localCount < $this->limit && !$fromOrUntil ) {
			// Case 2: not good, but salvageable.  Use the number of results.
			$totalCount = $localCount;
		} else {
			// Case 3: hopeless.  Don't give a total count at all.
			// Messages: category-subcat-count-limited, category-article-count-limited,
			// category-file-count-limited
			return $this->msg( "category-$type-count-limited" )->numParams( $localCount )->parseAsBlock();
		}
		// Messages: category-subcat-count, category-article-count, category-file-count
		return $this->msg( "category-$type-count" )->numParams( $localCount, $totalCount )->parseAsBlock();
	}
}
