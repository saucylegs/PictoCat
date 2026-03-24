<?php
/**
 * PictoCat modification of the CategoryPage class.
 * This file contains code copied from MediaWiki core, which is licensed under GPL-2.0-or-later.
 *
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Page\CategoryPage;
use MediaWiki\Title\Title;

/**
 * PictoCat override of category page handling.
 * This displays category members: subcategories, pages, and files categorized here.
 */
class PictoCategoryPage extends CategoryPage {
	/**
	 * @var class-string<PictoCategoryViewer> Override the viewer class
	 */
	protected $mCategoryViewerClass = PictoCategoryViewer::class;

	/**
	 * @var ParserOutputInjector To inject a ParserOutput object into PictoCategoryViewer
	 */
	private ParserOutputInjector $parserOutputInjector;

	/**
	 * @param Title $title The title of the page to construct.
	 * @param ParserOutputInjector $injector
	 */
	public function __construct( Title $title, ParserOutputInjector $injector ) {
		parent::__construct( $title );
		$this->parserOutputInjector = $injector;
	}

	/**
	 * Inserts the category members into the page output.
	 * Identical to the overridden method except for a modified constructor call.
	 * @return void
	 */
	public function closeShowCategory(): void {
		$request = $this->getContext()->getRequest();
		$oldFrom = $request->getVal( 'from' );
		$oldUntil = $request->getVal( 'until' );

		$reqArray = $request->getQueryValues();

		$from = $until = [];
		foreach ( [ 'page', 'subcat', 'file' ] as $type ) {
			$from[$type] = $request->getVal( "{$type}from", $oldFrom );
			$until[$type] = $request->getVal( "{$type}until", $oldUntil );

			// Do not want old-style from/until propagating in nav links.
			if ( !isset( $reqArray["{$type}from"] ) && isset( $reqArray["from"] ) ) {
				$reqArray["{$type}from"] = $reqArray["from"];
			}
			if ( !isset( $reqArray["{$type}to"] ) && isset( $reqArray["to"] ) ) {
				$reqArray["{$type}to"] = $reqArray["to"];
			}
		}

		unset( $reqArray["from"] );
		unset( $reqArray["to"] );

		$viewer = new $this->mCategoryViewerClass(
			$this->getPage(),
			$this->getContext(),
			$this->parserOutputInjector,
			$from,
			$until,
			$reqArray
		);
		$out = $this->getContext()->getOutput();
		$out->addHTML( $viewer->getHTML() );
		$this->addHelpLink( 'Help:Categories' );
	}
}
