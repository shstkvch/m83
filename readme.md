# M83 - Routing for WordPress
Contributors: shstkvch
Tags: development
Requires at least: 4.0
Tested up to: 4.4.2
Stable tag: 4.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

M83 is a very simple routing plugin for WordPress. It lets you define routes (in a routes.php file in your theme root) and point them to controller/helper classes, freeing you from manually writing helper initialisation code in template.php files.

M83 doesn't conflict with WordPress's normal routing; you can introduce it in addition to traditional template files.

## Usage

1. Install the plugin
2. Create a routes.php file in your theme route. It should look a bit like this:

```
<?php

//
// Router
//

use M83\Router as Router;

Router::get( 'index', 'indexHelper' );
```

3. Create your routes by assigning a template slug (like index, archive, singular, archive-category) to a helper class. You can specify the method to call on the class by using an @ sign, like this:

```
Router::get( 'singular', 'singularHelper@view' );
Router::get( 'singular-slug', 'singularHelper@special' );
```

4. M83 will direct requests to the slugs you define to a *new instance* of the helper classes you specify. If you don't specify a method, M83 will attempt to call a method on the class called 'main'.

## Notes

- It doesn't matter where your helper classes are stored, but theme-directory/helpers/ makes sense to me.
- The plugin will throw an exception if you route to a helper that doesn't exist
