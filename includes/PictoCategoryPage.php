<?php
/**
 * PictoCat modification of the CategoryPage class.
 *
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Page\CategoryPage;

/**
 * PictoCat override of category page handling to use a custom viewer class.
 */
class PictoCategoryPage extends CategoryPage {
	/**
	 * @var class-string<PictoCategoryViewer> Override the viewer class
	 */
	protected $mCategoryViewerClass = PictoCategoryViewer::class;
}
