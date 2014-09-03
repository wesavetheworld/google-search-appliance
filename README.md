Google Search Appliance class
=============================

This PHP class provides an interface to a Google Search Appliance.

## Usage

See the example below for an example of a simple search interface.

```php
/* require the class definition */
require_once(dirname(__FILE__) . '/google-search-appliance.php');

/**
 * instantiate object 
 * Setting all options can be performed when the object is created, or set 
 * later using the appropriate methods
 */
$gsa = new google_search_appliance( /* $appliance_options, $search_options */ );

/**
 * appliance options
 * The appliance URL and name are both required, but other options 
 * are only used if you output a search form using the get_search_form()
 * method, or if you implement paging navigation using the get_paging_links()
 * method
 */
$appliance_options = array(
	
	/* REQUIRED parameters */
	// URL for search appliance, including the 'search' keyword
	'appliance_url'  => 'http://gsa.example.com/search',
	// The appliance name(s) or site/sites configured on the appliance
	'appliance_name' => 'gsa1|gsa2',

	/* OPTIONAL parameters - defaults are shown here */
	// the URL of the page where the search is carried out - defaults to current URL
	'search_url'     => '',
	// The name of the query variable used by search form
	'query_var'      => 's',
	// The name of the paging variable used by search form
	'paging_var'     => 'paged',
	// if the page is behind a proxy, enter the proxy URL/port here
	'proxy'          => '',
	// The number of results to return per page
	'per_page'       => 10

);

/* set the appliance options */
$gsa->set_appliance_options( $appliance_options );

/**
 * search options
 * this is a greatly reduced subset of the options presented in the
 * Documentation for the GSA Request format. Defaults are shown here.
 */
$search_options = array(
	'sort'   => 'date:D:L:d1',
	'output' => 'xml_no_dtd',
	'filter' => '1',
	'ie'     => 'UTF-8',
	'oe'     => 'UTF-8'
);

/* set the search options */
$gsa->set_search_options( $search_options );

/* output a search form */
print( $gsa->get_search_form() );

/* add a callback filter for summaries */
function gsa_summary_filter($text, $context)
{
	return '<span class="' . $context . '">' . preg_replace( array("/b>/", "/i>/", "/<br ?\/?>/"), array("strong>", "em>", " "), $text ) . '</span>';
}
$gsa->add_filter( 'summary', 'gsa_summary_filter');

/* add a callback filter for titles */
class gsa_title_filter
{
	/* this is an example of using a static method of a class as a callback */
	public static function run_filter($text, $context)
	{
		// trims the title to the first » symbol
		if ( false !== strpos( $text, "»" ) ) {
			return substr( $text, 0, strpos( $text, "»" ) );
		}
		// don't forget to return the text!
		return $text;
	}
}
$gsa->add_filter( 'title', array( 'gsa_title_filter', 'run_filter' ) );

/* get results */
$results = $gsa->search();

/* go through displaying results */
if ( $results['number'] > 0 ) {
	/* display a list of results with navigation underneath */
	printf( '<p>Showing results %d to %s for your query <em>%s</em></p>', $results["start"], $results["end"], $results["query"] );
	printf( '<ol start="%s">', $results["start"] );
	foreach ( $results["docs"] as $r ) {
		printf( '<li><a href="%s">%s</a><br />%s</li>', $r["url"], $r["title"], $r["summary"] );
	}
	print( '</ol>' );
	print( $gsa->get_paging_navigation( $results ) );
} else {
	/* display message for no results */
	printf('<p>Sorry, there were no results for your query <em>%s</em></p>', $gsa->get_search_query() );
}
```

## Setting options

Two sets of options can be set for the plugin, either as part of object instantiation, or by calling the methods `set_appliance_options` and `set_search_options` directly. Both consist of associative arrays with the option name as key.

### Appliance options

Both `appliance_url` and `appliance_name` are required for the search to operate. Other options here are optional, but are needed if you choose to output the search form using the class, or use the class to provide results paging:

 * `appliance_url` - URL for search appliance, including the 'search' keyword
 * `appliance_name` - The appliance name(s) or site/sites configured on the appliance
 * `search_url` - the URL of the page where the search is carried out - defaults to current URL
 * `query_var` - The name of the query variable used by search form
 * `paging_var` - The name of the paging variable used by search form
 * `per_page` - The number of results to return per page
 * `proxy` - if the page is behind a proxy, enter the proxy URL/port here

### Search Options

These are passed directly to the search appliance - see Google's [Request Format documentation](http://www.google.com/support/enterprise/static/gsa/docs/admin/72/gsa_doc_set/xml_reference/request_format.html) for details. Currently supported search options are limited to the following:

 * `sort` - defaults to `date:D:L:d1`
 * `output` - defaults to `xml_no_dtd`
 * `filter` - defaults to `1`
 * `ie` - defaults to `UTF-8`
 * `oe` - defaults to `UTF-8`

## Using output filters

The example above uses two different output filters for search results content. Any number of filters can be applied to any of the fields which are used in the results list (`url`, `title` and `summary`). Filters are applied in the order in which they are added, and need to be callable functions or methods of objects or classes - [see the PHP manual for examples of callable functions and their syntax](http://php.net/manual/en/language.types.callable.php). There are also output filters for the search form (`form`) and paging navigation (`nav`).

All functions/methods used as output filters are added using the `add_filter` method on the `google-search-appliance` object, and receive two arguments, the first one containing the text to be filteres, and the second containing the context, or field, which is being filtered:

```php
/* add a callback filter for titles */
class gsa_title_filter
{
	/* this is an example of using a static method of a class as a callback */
	public static function run_filter($text, $context)
	{
		// trims the title to the first » symbol
		if ( false !== strpos( $text, "»" ) ) {
			return ucfirst($context) . ': ' . substr( $text, 0, strpos( $text, "»" ) );
		}
		// don't forget to return the text!
		return ucfirst($context) . ': ' . $text;
	}
}
$gsa->add_filter( 'title', array( 'gsa_title_filter', 'run_filter' ) );
```
In this example, a class is used with a static method to return the first part of a page title before the &raquo; character (if this is present), prefixed with "Title: ".



