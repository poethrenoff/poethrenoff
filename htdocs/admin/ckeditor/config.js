/**
 * @license Copyright (c) 2003-2013, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.html or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
	
	config.language = 'ru';
	config.removePlugins = 'save,liststyle,tabletools,contextmenu';
	config.filebrowserUploadUrl = '/admin/fm/upload_file';
	config.allowedContent = true; // disable ACF
	config.disableNativeSpellChecker = false;
	
	config.toolbarGroups = [
		{ name: 'document', groups: [ 'mode', 'document', 'doctools' ] },
		{ name: 'clipboard', groups: [ 'clipboard', 'undo' ] },
		{ name: 'editing', groups: [ 'find', 'selection' ] },
		{ name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
		{ name: 'paragraph', groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
		{ name: 'links' }, { name: 'insert' }, { name: 'styles' }, { name: 'colors' },
		{ name: 'tools' }, { name: 'others' }, { name: 'about' }
	];
};
