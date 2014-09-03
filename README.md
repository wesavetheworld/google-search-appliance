Google Search Appliance class
=============================

This PHP class provides an interface to a Google Search Appliance.

### Usage

See the example below for an example of a simple search interface.

```php
/* require the class definition */
require_once('google-search-appliance.php');

/* instantiate object */
$gsa = new google_search_appliance();

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
	'appliance_url'  => 'https://gsa.example.com/search',
	// The appliance name(s) or site/sites configured on the appliance
	'appliance_name' => 'site_name1|site_name2',

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
print( $gsa->get_search_form );

/* get results */
$results = $gsa->search();

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