<?php

namespace MediaWiki\Extension\PictoCat;

/**
 * Contains functions for generating HTML for select Codex components
 * much more quickly and efficiently than the Codex PHP library.
 */
class CodexLite {
	/**
	 * @param string|null $imageUrl A fully-qualified and valid URL to the background image to use for the thumbnail.
	 * If null or an empty string, a placeholder icon will be used instead.
	 * @return string The HTML for a "Thumbnail" Codex component.
	 */
	public static function makeThumbnail( ?string $imageUrl ): string {
		$output = "<span class=\"cdx-thumbnail\">\n\t";

		if ( $imageUrl ) {
			// To avoid inadvertent escaping, do not use MediaWiki\Html\Html methods.
			$url = self::sanitizeUrl( $imageUrl );
			$output .= "<span class=\"cdx-thumbnail__image\" style=\"background-image: url('$url');\"></span>";
		} else {
			$output .= '<span class="cdx-thumbnail__placeholder"><span class="cdx-thumbnail__placeholder__icon"></span></span>';
		}

		$output .= "\n</span>";
		return $output;
	}

	/**
	 * Replaces quote marks and spaces with their percent-encodings.
	 * @param string $url The URL to sanitize.
	 * @return string The sanitized URL.
	 */
	private static function sanitizeUrl( string $url ): string {
		return strtr( $url, [
			' ' => '%20',
			'"' => '%22',
			'\'' => '%27',
		] );
	}
}
