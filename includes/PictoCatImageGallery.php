<?php
/**
 * This file contains code copied from MediaWiki core, which is licensed under GPL-2.0-or-later.
 *
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\PictoCat;

use MediaWiki\Context\IContextSource;
use ImageGalleryBase; // 1.45 backwards-compatible name; swap out after REL_ branches are made
use TraditionalImageGallery; // 1.45 backwards-compatible name; swap out after REL_ branches are made
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Media\MediaHandler;
use MediaWiki\Media\MediaTransformError;
use MediaWiki\Media\MediaTransformOutput;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use Wikimedia\Assert\Assert;

/**
 * Version of TraditionalImageGallery with modified handling of invalid file titles.
 * This class exists solely to provide a workaround for <a href="https://phabricator.wikimedia.org/T437798">T437798</a>.
 */
class PictoCatImageGallery extends TraditionalImageGallery {
	/** @inheritDoc */
	public function __construct( $mode = 'pictocat', ?IContextSource $context = null ) {
		parent::__construct( $mode, $context );
		$this->mShowBytes = false;
		$this->mShowDimensions = false;
		$this->mShowFilename = false;
	}

	/**
	 * Return an HTML representation of the image gallery.
	 * The vast majority of this method is copied from TraditionalImageGallery::toHTML().
	 *
	 * For each image in the gallery, display
	 * - a thumbnail
	 * - the image name
	 * - the additional text provided when adding the image
	 * - the size of the image
	 *
	 * @return string
	 */
	public function toHTML(): string {
		$resolveFilesViaParser = $this->mParser instanceof Parser;
		if ( $resolveFilesViaParser ) {
			$parserOutput = $this->mParser->getOutput();
			$repoGroup = null;
			$linkRenderer = $this->mParser->getLinkRenderer();
			$badFileLookup = $this->mParser->getBadFileLookup();
		} else {
			$parserOutput = $this->getOutput();
			$services = MediaWikiServices::getInstance();
			$repoGroup = $services->getRepoGroup();
			$linkRenderer = $services->getLinkRenderer();
			$badFileLookup = $services->getBadFileLookup();
		}

		// Reset mode to traditional for display
		$this->mMode = 'traditional';

		Html::addClass( $this->mAttribs['class'], 'gallery' );
		Html::addClass( $this->mAttribs['class'], 'mw-gallery-' . $this->mMode );

		if ( $this->mPerRow > 0 ) {
			$maxwidth = $this->mPerRow * ( $this->mWidths + $this->getAllPadding() );
			$oldStyle = $this->mAttribs['style'] ?? '';
			$this->mAttribs['style'] = "max-width: {$maxwidth}px;" . $oldStyle;
		}

		$parserOutput->addModules( $this->getModules() );
		$parserOutput->addModuleStyles( [ 'mediawiki.page.gallery.styles' ] );
		$output = Html::openElement( 'ul', $this->mAttribs );
		if ( $this->mCaption ) {
			$output .= "\n\t" . Html::rawElement( 'li', [ 'class' => 'gallerycaption' ], $this->mCaption );
		}

		if ( $this->mShowFilename ) {
			// Preload LinkCache info for when generating links
			// of the filename below
			$linkBatchFactory = MediaWikiServices::getInstance()->getLinkBatchFactory();
			$lb = $linkBatchFactory->newLinkBatch()->setCaller( __METHOD__ );
			foreach ( $this->mImages as [ $title, /* see below */ ] ) {
				$lb->addObj( $title );
			}
			$lb->execute();
		}

		$lang = $this->getRenderLang();
		$hookRunner = new HookRunner( MediaWikiServices::getInstance()->getHookContainer() );

		# Output each image...
		foreach ( $this->mImages as [ $nt, $text, $alt, $link, $handlerOpts, $loading, $imageOptions ] ) {
			// "text" means "caption" here
			/** @var Title $nt */

			$descQuery = false;
			if ( $nt->inNamespace( NS_FILE ) && !$nt->isExternal() ) {
				# Get the file...
				if ( $resolveFilesViaParser ) {
					# Give extensions a chance to select the file revision for us
					$options = [];
					$hookRunner->onBeforeParserFetchFileAndTitle(
					// @phan-suppress-next-line PhanTypeMismatchArgument Type mismatch on pass-by-ref args
						$this->mParser, $nt, $options, $descQuery );
					# Fetch and register the file (file title may be different via hooks)
					[ $img, $nt ] = $this->mParser->fetchFileAndTitle( $nt, $options );
				} else {
					$img = $repoGroup->findFile( $nt );
				}
			} else {
				$img = false;
			}

			$transformOptions = $this->getThumbParams( $img ) + $handlerOpts;
			$transformOptions['requestProvenance'] = $resolveFilesViaParser ? 'parser' : 'gallery';
			$thumb = $img ? $img->transform( $transformOptions ) : false;

			$rdfaType = 'mw:File';

			$isBadFile = $img && $thumb && $this->mHideBadImages &&
				$badFileLookup->isBadFile( $nt->getDBkey(), $this->getContextTitle() );

			if ( !$img || !$thumb || $thumb->isError() || $isBadFile ) {
				$rdfaType = 'mw:Error ' . $rdfaType;

				$currentExists = $img && $img->exists();
				if ( $currentExists && !$thumb ) {
					$label = wfMessage( 'thumbnail_error', '' )->text();
				} elseif ( $thumb && $thumb->isError() ) {
					Assert::invariant(
						$thumb instanceof MediaTransformError,
						'Unknown MediaTransformOutput: ' . get_class( $thumb )
					);
					$label = $thumb->toText();
				} else {
					$label = $alt ?? '';
				}
				// This is the modification for T437798.
				if ( $nt->inNamespace( NS_FILE ) ) {
					$thumbhtml = Linker::makeBrokenImageLinkObj(
						$nt, $label, '', '', '', false, $transformOptions, $currentExists
					);
				} else {
					$thumbhtml = $linkRenderer->makeLink( $nt );
				}
				$thumbhtml = Html::rawElement( 'span', [ 'typeof' => $rdfaType ], $thumbhtml );

				$thumbhtml = "\n\t\t\t" . Html::rawElement(
						'div',
						[
							'class' => 'thumb',
							'style' => 'height: ' . ( $this->getThumbPadding() + $this->mHeights ) . 'px;'
						],
						$thumbhtml
					);

				if ( !$img && $resolveFilesViaParser ) {
					$this->mParser->addTrackingCategory( 'broken-file-category' );
				}
			} else {
				/** @var MediaTransformOutput $thumb */
				$vpad = $this->getVPad( $this->mHeights, $thumb->getHeight() );

				// Backwards compat before the $imageOptions existed
				if ( $imageOptions === null ) {
					$imageParameters = [
						'desc-link' => true,
						'desc-query' => $descQuery,
						'alt' => $alt ?? '',
						'custom-url-link' => $link
					];
				} else {
					$params = [];
					// An empty alt indicates an image is not a key part of the
					// content and that non-visual browsers may omit it from
					// rendering.  Only set the parameter if it's explicitly
					// requested.
					if ( $alt !== null ) {
						$params['alt'] = $alt;
					}
					$params['title'] = $imageOptions['title'];
					$params['img-class'] = 'mw-file-element';
					$imageParameters = Linker::getImageLinkMTOParams(
							$imageOptions, $descQuery, $this->mParser
						) + $params;
				}

				if ( $loading === ImageGalleryBase::LOADING_LAZY ) {
					$imageParameters['loading'] = 'lazy';
				}

				$this->adjustImageParameters( $thumb, $imageParameters );

				Linker::processResponsiveImages( $img, $thumb, $transformOptions );

				$thumbhtml = $thumb->toHtml( $imageParameters );
				$thumbhtml = Html::rawElement(
					'span', [ 'typeof' => $rdfaType ], $thumbhtml
				);

				# Set both fixed width and min-height.
				$width = $this->getThumbDivWidth( $thumb->getWidth() );
				$height = $this->getThumbPadding() + $this->mHeights;
				$thumbhtml = "\n\t\t\t" . Html::rawElement( 'div', [
						'class' => 'thumb',
						'style' => "width: {$width}px;" .
							( $this->mMode === 'traditional' ? " height: {$height}px;" : '' ),
					], $thumbhtml );

				// Call parser transform hook
				if ( $resolveFilesViaParser ) {
					/** @var MediaHandler $handler */
					$handler = $img->getHandler();
					if ( $handler ) {
						$handler->parserTransformHook( $this->mParser, $img );
					}
					$this->mParser->modifyImageHtml(
						$img, [ 'handler' => $imageParameters ], $thumbhtml );
				}
			}

			$meta = [];
			if ( $img ) {
				if ( $this->mShowDimensions ) {
					$meta[] = htmlspecialchars( $img->getDimensionsString( $lang ) );
				}
				if ( $this->mShowBytes ) {
					$meta[] = htmlspecialchars( $lang->formatSize( $img->getSize() ) );
				}
			} elseif ( $this->mShowDimensions || $this->mShowBytes ) {
				$meta[] = $this->msg( 'filemissing' )->escaped();
			}
			$meta = $lang->semicolonList( $meta );
			if ( $meta ) {
				$meta .= Html::rawElement( 'br', [] ) . "\n";
			}

			$textlink = $this->mShowFilename ?
				$this->getCaptionHtml( $nt, $lang, $linkRenderer ) :
				'';

			$galleryText = $this->wrapGalleryText( $textlink . $text . $meta, $thumb );

			$gbWidth = $this->getGBWidthOverwrite( $thumb ) ?: $this->getGBWidth( $thumb ) . 'px';
			# Weird double wrapping (the extra div inside the li) needed due to FF2 bug
			# Can be safely removed if FF2 falls completely out of existence
			$output .= "\n\t\t" .
				Html::rawElement(
					'li',
					[ 'class' => 'gallerybox', 'style' => 'width: ' . $gbWidth ],
					$thumbhtml
					. $galleryText
					. "\n\t\t"
				);
		}
		$output .= "\n" . Html::closeElement( 'ul' );

		return $output;
	}
}
