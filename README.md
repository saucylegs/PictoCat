# PictoCat
This is a MediaWiki extension that displays a small image preview next to each page in a category.

## Function
Next to each listing in a category, PictoCat displays a thumbnail containing an image from the corresponding page.
The thumbnail takes the place of the bullet point. The exact image used is determined by the PageImages extension.

PictoCat does not affect every category. By default, PictoCat will only activate on a category if at least 50%
of its members (excluding files and subcategories) have a page image. The wiki's sysadmin can change this threshold
by setting the relevant [configuration variable](#configuration). You can override the default behavior for a 
particular category by using a [magic word](#magic-words).

PictoCat does not affect the display of subcategories or files in a category.
For files, this is because MediaWiki already displays a preview of each file.
For subcategories, this is because most categories do not have page images, and regardless, this extension would
conflict with the popular CategoryTree extension.

### Magic words
A set of [magic words](https://www.mediawiki.org/wiki/Special:MyLanguage/Help:Magic_words) (behavior switches)
are included to allow you to explicitly set PictoCat's behavior in a particular category.
You can use them by including one in the wikitext of a category page. No more than one of these should be used
in a single category, otherwise there may be unexpected behavior.
- `__PICTOCAT__` — Use PictoCat in this category.
- `__NOPICTOCAT__` — Do not use PictoCat in this category.
- `__USEBULLETSTYLE__` — Currently, this has the same effect as `__PICTOCAT__`. If additional display styles are added 
  in the future, then categories with this magic word will continue using the current style.

## Stability
I consider this extension to be in a beta state. This is because of the limited amount of testing I have done
and the fact that there are features that I may or may not add in the future. However, the extension does not modify
the database, so there shouldn't be any risk of permanent damage. I would love for someone to try it out and send me
any feedback!

Also note that this extension makes use of some MediaWiki interfaces that are not very stable.
When upgrading to a new major version of MediaWiki, be sure to upgrade this extension to a compatible version as well.

## Installation
### Requirements
This extension has the following dependencies:
- The [PageImages extension](https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:PageImages).
  This extension is included with most modern releases of MediaWiki, so you just need to make sure it's enabled and configured.
- MediaWiki 1.45.
  - It does not work with MediaWiki 1.44 or earlier. If you're using 1.44 or 1.43 and would like that version 
    to be supported, create a GitHub issue to let me know. I have no interest in supporting any versions earlier 
    than 1.43 since they are now considered obsolete.
  - I haven't looked into pre-release versions yet, but would like to do so soon.

### Download
#### Using Git
From the `extensions` directory in your MediaWiki installation, run the following command:
```bash
git clone https://github.com/saucylegs/PictoCat.git
```
This should download the extension into a new PictoCat directory.

### Activation
Once the extension has been downloaded, add the following line to the wiki's LocalSettings.php file to enable it:
```php
wfLoadExtension( 'PictoCat' );
```
PictoCat should now be active! Check the wiki's Special:Version page to verify.
You can configure the extension's behavior as necessary; see the Configuration section below.

## Configuration
This extension includes the following configuration variables that can be set in your LocalSettings.php file:
- `$wgPictoCatActivationPercentage`
  - If less than this percentage of members in a category (excluding files and subcategories) has a page image,
    then PictoCat will not activate on that category.
  - This behavior can be overridden on a category-by-category basis by using the [magic words](#magic-words).
  - Can be an int or a float, but any non-number value may cause an exception.
  - If set to 0 (or any negative number), then all categories will use PictoCat by default.
  - If set to 101 (or any number greater than 100), then no categories will use PictoCat by default.
    Effectively, each category would have to opt in using the magic words.
  - If a category is large, then it won't check *every* page member; it will only check the first 200 or so.
    The maximum number of members checked is controlled by 
    [$wgCategoryPagingLimit](https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:$wgCategoryPagingLimit).
  - **Default value:** 50
  - **Example usage:** `$wgPictoCatActivationPercentage = 25;`

## Name
The name "PictoCat" is an abbreviation of "pictographic categories". 
Any similarity to the name of any game-console-exclusive messaging apps is purely coincidental.

## AI usage during development
This extension was *not* vibe-coded! This repository contains no code generated by a chatbot or AI agent.
Any mistakes or suboptimal implementations are solely the result of my own incompetence
(this is my first time working with the MediaWiki codebase).

## License
Copyright © 2026 saucylegs

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, see https://www.gnu.org/licenses/.
