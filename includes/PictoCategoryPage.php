<?php

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Page\CategoryPage;

/**
 * PictoCat override of category page handling.
 * This displays category members: subcategories, pages, and files categorized here.
 */
class PictoCategoryPage extends CategoryPage {
	/**
	 * @var class-string<PictoCategoryViewer> Override the viewer class
	 */
	protected $mCategoryViewerClass = PictoCategoryViewer::class;
}
