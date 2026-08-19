/* VisualEditor integration for PictoCat.
 * Allows one to add a PictoCat magic word from the VisualEditor page settings interface.
 *
 * Credit to the VisualEditor and Disambiguator extensions; my implementation is based on theirs.
 */

/**
 * DataModel: Use PictoCat meta item (for `__PICTOCAT__`).
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MWUsePictoCatMetaItem = function VeDmMWUsePictoCatMetaItem() {
	// Parent constructor
	ve.dm.MWUsePictoCatMetaItem.super.apply( this, arguments );
};

/**
 * DataModel: No PictoCat meta item (for `__NOPICTOCAT__`).
 *
 * @class
 * @extends ve.dm.MetaItem
 * @constructor
 * @param {Object} element Reference to element in meta-linmod
 */
ve.dm.MWNoPictoCatMetaItem = function VeDmMWNoPictoCatMetaItem() {
	// Parent constructor
	ve.dm.MWNoPictoCatMetaItem.super.apply( this, arguments );
};

// Inheritance
OO.inheritClass( ve.dm.MWUsePictoCatMetaItem, ve.dm.MetaItem );
OO.inheritClass( ve.dm.MWNoPictoCatMetaItem, ve.dm.MetaItem );

// Static Properties
ve.dm.MWUsePictoCatMetaItem.static.name = 'mwUsePictoCat';
ve.dm.MWNoPictoCatMetaItem.static.name = 'mwNoPictoCat';
ve.dm.MWUsePictoCatMetaItem.static.group = 'mwUsePictoCat';
ve.dm.MWNoPictoCatMetaItem.static.group = 'mwNoPictoCat';
ve.dm.MWUsePictoCatMetaItem.static.matchTagNames = [ 'meta' ];
ve.dm.MWNoPictoCatMetaItem.static.matchTagNames = [ 'meta' ];
ve.dm.MWUsePictoCatMetaItem.static.matchRdfaTypes = [ 'mw:PageProp/pictocat' ];
ve.dm.MWNoPictoCatMetaItem.static.matchRdfaTypes = [ 'mw:PageProp/nopictocat' ];

ve.dm.MWUsePictoCatMetaItem.static.toDataElement = function () {
	return { type: this.name };
};
ve.dm.MWNoPictoCatMetaItem.static.toDataElement = function () {
	return { type: this.name };
};

ve.dm.MWUsePictoCatMetaItem.static.toDomElements = function ( dataElement, doc ) {
	const meta = doc.createElement( 'meta' );
	meta.setAttribute( 'property', 'mw:PageProp/pictocat' );
	return [ meta ];
};
ve.dm.MWNoPictoCatMetaItem.static.toDomElements = function ( dataElement, doc ) {
	const meta = doc.createElement( 'meta' );
	meta.setAttribute( 'property', 'mw:PageProp/nopictocat' );
	return [ meta ];
};

// Registration
ve.dm.modelRegistry.register( ve.dm.MWUsePictoCatMetaItem );
ve.dm.modelRegistry.register( ve.dm.MWNoPictoCatMetaItem );

// Add settings page checkboxes if this is a Category page
if ( mw.config.get( 'wgNamespaceNumber' ) == 14 ) {
	// Checkboxes are not ideal but are the only option; see Phabricator T434873
	ve.ui.MWSettingsPage.static.addMetaCheckbox(
		'mwUsePictoCat',
		mw.msg( 'visualeditor-dialog-meta-settings-use-pictocat-label' )
	);
	ve.ui.MWSettingsPage.static.addMetaCheckbox(
		'mwNoPictoCat',
		mw.msg( 'visualeditor-dialog-meta-settings-no-pictocat-label' )
	);
}
