Less Theme Generator
====================

Manage Less/CSS of WordPress theme with compilation/cache on fly.

This class allows you to compile files in LESS CSS. Once compiled, the CSS is stored in a cached CSS standard.

This is the file that will be delivered to users. Whenever a file is LESS modified, the cache is invalidated and regenerated.

Usage
====================

This class must be called by the functions.php file of the theme.

### Usual structure of files :

	themes/my-theme/
	themes/my-theme/css/
	themes/my-theme/inc/
	themes/my-theme/inc/style.php
	themes/my-theme/inc/lib/lessc.inc.php

### Usage :

	// Declare less for style
	new Less_Theme_Generator( array(
			'/css/ressources/reset.less',
			'/css/ressources/text.less',
			'/css/ressources/forms.less',
			'/css/ressources/img.less',
			'/css/ressources/superfish.less',
			'/css/ressources/elements.less',
			'/css/ressources/grid.less',
			'/css/master.less'
		), TEMPLATEPATH, get_bloginfo('template_directory'), false, false );

	
### Parameters :

1. Array with ressourcess LESS to compile
2. Path to theme
3. URL to theme
4. Boolean for enable compression
5. Boolean for enable debug (inline style, non cache)