<?php
/**
 * Google search appliance class
 *
 * @author Peter Edwards <p.l.edwards@leeds.ac.uk>
 * @version 0.0.1
 */

if ( ! class_exists('google_search_appliance')) :

	/**
	 * class to provide search services for sites using a google search appliance.
	 */
	class google_search_appliance
	{
		/* search options */
		private $search_options;

		/* appliance options */
		private $appliance_options;

		/* search URL */
		private $search_url;

		/* appliance name */
		private $appliance_name;
		
		/* constructor */
		private function __construct( $appliance_options = array(), $search_options = array() )
		{
			$this->set_appliance_options( $appliance_options );
			$this->set_search_options( $search_options );
		}

		/* sets appliance options */
		public function set_appliance_options( $options = array() )
		{
			$defaults = $this->get_default_appliance_options();
			foreach ( $defaults as $key => $val ) {
				if ( method_exists( $this, 'set_' . $key ) ) {
					$this->set_$key();
				} else {
					$this->appliance_options[$key] = ( isset( $options[$key] ) ) ? $options[$key]: $val;
				}
			}
		}

		/* gets a default set of search options */
		private function get_default_appliance_options()
		{
			return array(
				'appliance_url'  => '',
				'appliance_name' => '',
				'search_url'     => '',
				'query_var'      => 's',
				'proxy'          => '',
				'paging_var'     => 'paged'
				'per_page'       => 10
			);
		}

		/* sets the URL for the search appliance */
		public function set_appliance_url( $url = '' )
		{
			/* set appliance URL if valid */
			if ( $this->is_valid_url( $url ) ) {
				$this->appliance_options['appliance_url'] = $url;
			}
		}

		/* sets the name of the search appliance */
		public function set_appliance_name( $name )
		{
			if ( '' !== trim( $name ) ) {
				$this->appliance_options['appliance_name'] = $name;
			}
		}

		/* sets the URL for the search page (defaults to current URL) */
		public function set_search_url( $url = '' )
		{
			if ( $this->is_valid_url( $url ) ) {
				$this->appliance_options['search_url'] = $url;
			} else {
				$this->appliance_options['search_url'] = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
			}
		}

		/* checks for a valid URL */
		private function is_valid_url( $url )
		{
			/* try to use filter_var (PHP > 5.2) */
			if ( function_exists( 'filter_var' ) && filter_var( $url, FILTER_VALIDATE_URL ) !== false ) {
				return true;
			/* if not, just check to make sure it starts with http:// or https:// */
			} elseif ( preg_match( '/^https?:\/\//', $url ) ) {
				return true;
			}
			return false;
		}

		/* sets search options */
		public function set_search_options( $options = array() )
		{
			$defaults = $this->get_default_search_options();
			foreach ( $defaults as $key => $val ) {
				$this->search_options[$key] = ( isset( $options[$key] ) ) ? $options[$key]: $val;
			}
		}

		/* gets a default set of search options */
		private function get_default_search_options()
		{
			return array(
				'sort'          => 'date:D:L:d1',
				'output'        => 'xml_no_dtd',
				'filter'        => '1',
				'ie'            => 'UTF-8',
				'oe'            => 'UTF-8'
			);
		}

		/* gets XML from google appliance */
		private static function getXML($url)
		{
			$out = "";
			$ch = curl_init( $url );
			if ( $ch ) {
				curl_setopt($ch, CURLOPT_HEADER, 0);
				/* set proxy */
				if ( ! empty( $this->appliance_options['proxy'] ) ) {
					curl_setopt( $ch, CURLOPT_PROXY, $this->appliance_options['proxy'] );
				}
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				$out .= curl_exec( $ch );
				curl_close( $ch );
			}
			return $out;
		}

		/* parses XML from google appliance */
		private static function parseXML($xml)
		{
			$xmlObj = new SimpleXMLElement($xml);
			return $xmlObj;
		}

		/* search interface */
		public static function search()
		{
			$terms = $this->get_search_query();
			if ( ! empty( $this->appliance_options['appliance_name'] ) && ! empty( $this->appliance_options['appliance_url'] ) && ! empty( $terms ) ) {
				/* set paging */
				$start = 0;
				if ( isset( $_REQUEST[$this->appliance_options['paging_var']] ) ) {
					$start = ( ( intval( $_REQUEST[$this->appliance_options['paging_var']]) - 1 ) * $this->appliance_options['per_page'] );
				}
				/* get search URL */
				$options_str = '';
				foreach ( $this->search_options as $name => $val ) {
					if ( '' !== trim( $val ) ) {
						$options_str .= sprintf( '&%s=%s', $name, $val );
					}
				}
				$search_url = $this->appliance_options['appliance_url'] . "?site=" . $this->appliance_options['appliance_name'] . "&q=" . urlencode($terms) . "&start=" . $start . "&num=" . $this->appliance_options['per_page'] . $options_str;
				$xml = $this->parseXML( $this->getXML( $search_url ) );
				$results = $this->getResults( $xml );
				return $results;
			}
		}

		/* gets the results and formats in a results array */
		private function getResults($xml)
		{
			$results = array(
				"number" => (integer) $xml->RES->M,
				"query" => (string) $xml->Q
			);
			if ($results["number"]) {
				$results["start"] = (integer) $xml->RES->attributes()->SN;
				$results["end"] = (integer) $xml->RES->attributes()->EN;
				$results["hasprevious"] = (isset($xml->RES->NB) && isset($xml->RES->NB->PU));
				$results["hasnext"] = (isset($xml->RES->NB) && isset($xml->RES->NB->NU));
				$results["docs"] = array();
				foreach ($xml->RES->R as $result) {
					$results["docs"][] = array(
						"no" => (integer) $result->attributes()->N,
						"title" => $this->clean_content($result->T),
						"url" => (string) $result->U,
						"summary" => $this->clean_content($result->S)
					);
				}
			}
			return $results;
		}

		/* replaces <br>, <b> and <i> tags in output */
		private static function clean_content($txt)
		{
			return preg_replace(array("/b>/", "/i>/", "/<br ?\/?>/"), array("strong>", "em>", " "), $txt);
		}

		/* gets a search form */
		public function get_search_form()
	    {
	        $form = sprintf('<form role="search" method="get" action="%s">', $this->appliance_options['search_url']);
	        $form .= '<label class="screen-reader-text">Search for:</label>';
	        $form .= sprintf('<input type="text" value="%s" name="%s" class="searchinput" />', $this->get_search_query(), $this->appliance_options['query_var'] );
	        $form .= '<input type="submit" class="searchsubmit" value="Go" /></form>';
	        return $form;
	    }

		/* gets results paging navigation */
		public static function get_paging_navigation( $results )
		{
			$out = "";
			if ($results["number"] > $this->appliance_options['per_page']) {
				$out = '<nav role="navigation" class="gsa-paging"><ul>';
				$currentpage = ceil($results["start"] / $this->appliance_options['per_page']);
				$base_url = $this->appliance_options['search_url'] . '?' . $this->appliance_options['query_var'] . '=' . urlencode( $results["query"] ) . '&' . $this->appliance_options['paging_var'] . '=';
				if ($results["hasprevious"]) {
					$out .= sprintf( '<li class="nav-previous"><a href="%s/%s/%s/page/%d" class="next-prev prev">&larr; Previous page</a></li>', $base_url, ($currentpage - 1) );
				}
				if ($results["hasnext"]) {
					$out .= sprintf( '<li class="nav-next"><a href="%s/%s/%s/page/%d" class="next-prev next">Next page &rarr;</a></li>', $base_url(), ($currentpage + 1) );
				}
				$out .= '</ul></nav>';
			}
			return $out;
		}

		/* gets the query for the search */
		private function get_search_query()
		{
			return isset( $_REQUEST[$this->appliance_options['query_var']] ) ? trim( $_REQUEST[$this->appliance_options['query_var']] ) : '';
		}

	} /* end of class definition */

endif;