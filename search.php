<?php
/**
 * A usage example of the google search appliance class
 * @author Peter Edwards <p.l.edwards@leeds.ac.uk>
 * @version 0.0.1
 */
 
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
	'appliance_name' => 'app1|app2',

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

print('<h2>Search Results</h2>');
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