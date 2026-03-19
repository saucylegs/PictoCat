<?php

namespace MediaWiki\Extension\PictoCat;

/**
 * The PictoCat image presentation style for a category.
 * Can be None or Bullet.
 */
enum PictoCatStyle: string {
    case None = 'none';
    case Bullet = 'bullet';
	// 'Gallery' is from an abandoned feature; it might be re-introduced in the feature.
    // case Gallery = 'gallery';
}
