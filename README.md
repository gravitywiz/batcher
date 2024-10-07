# Gravity Wiz Batcher

A class for bulk processing items in batches. Useful for migration routines, updating entries, generating content, and more.

## Usage

### Basic usage in a plugin

Install the Gravity Wiz Batcher and Jetpack Autoloader. Allow Jetpack Autoloader to run as a plugin. This
will help prevent conflicts with other plugins using this same class.

```bash
composer require gravitywiz/batcher automattic/jetpack-autoloader
```

Then, create a new plugin file and include the following code:

```php
<?php
/*
 * Plugin Name:  Gravity Forms Batcher Example
 * Plugin URI:   http://gravitywiz.com
 * Description:  A plugin to do XYZ.
 * Author:       Gravity Wiz
 * Version:      0.1
 * Author URI:   http://gravitywiz.com
 */

add_action( 'init', function() {
	if ( ! is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';

	new \GWiz_Batcher\Batcher( array(
		'title'        => 'Dummy Entry Generator',
		'id'           => 'gwiz-example-batcher',
		'size'         => 25,
		'create_admin_page' => true,
		// 'show_form_selector' => true,
		// 'require_form_selection' => true,
		// 'additional_inputs' => '<p><label>Example additional input</label><input type="number" value="123" name="example_additional_input" /></p>',
		'get_items'    => function ( $size, $offset ) {
			// $example_additional_input = rgpost( 'example_additional_input' );

			$paging  = array(
				'offset'    => $offset,
				'page_size' => $size,
			);

			$entries = GFAPI::get_entries( null, array(), null, $paging, $total );

			return array(
				'items' => $entries,
				'total' => $total,
			);
		},
		'process_item' => function ( $entry ) {
			// Process the item here.
		},
	) );
} );
```
