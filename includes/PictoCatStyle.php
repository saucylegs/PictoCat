<?php

namespace MediaWiki\Extension\PictoCat;

/**
 * The PictoCat image presentation style for a category.
 * Can be None, Bullet, or Gallery.
 */
enum PictoCatStyle: string {
    case None = 'none';
    case Bullet = 'bullet';
    case Gallery = 'gallery';
}
