<?php
/**
 * Google search appliance class
 * @author Peter Edwards <p.l.edwards@leeds.ac.uk>
 * @version 0.0.1
 */

if ( ! class_exists('google_search_appliance') ) :

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

		/* output filters */
		public $filters;
		
		/* constructor */
		public function __construct( $appliance_options = array(), $search_options = array() )
		{
			$this->set_appliance_options( $appliance_options );
			$this->set_search_options( $search_options );
		}

		/* sets appliance options */
		public function set_appliance_options( $options = array() )
		{
			$defaults = $this->get_default_appliance_options();
			foreach ( $defaults as $key => $val ) {
				$option_value = ( isset( $options[$key] ) ) ? $options[$key]: $val;
				if ( method_exists( $this, 'set_' . $key ) ) {
					$methodname = 'set_' . $key;
					$this->$methodname( $option_value );
				} else {
					$this->appliance_options[$key] = $option_value;
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
				'paging_var'     => 'paged',
				'per_page'       => 10
			);
		}

		/* sets the URL for the search appliance */
		public function set_appliance_url( $url = '' )
		{
			/* set appliance URL if valid */
			if ( $this->is_valid_url( $url ) ) {
				$this->appliance_options['appliance_url'] = trim( $url, ' ?' );
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
				$this->appliance_options['search_url'] = "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]";
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
		private function get_XML($url)
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
		private function parse_XML($xml)
		{
			$xmlObj = new SimpleXMLElement($xml);
			return $xmlObj;
		}

		/* search interface */
		public function search($terms = '')
		{
			if ( empty( $terms ) ) {
				$terms = $this->get_search_query();
			}
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
				$xml = $this->parse_XML( $this->get_XML( $search_url ) );
				$results = $this->get_results( $xml );
				return $results;
			}
		}

		/* gets the results and formats in a results array */
		private function get_results($xml)
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
						"no" => $this->filter_text( $result->attributes()->N, 'no' ),
						"title" => $this->filter_text( $result->T, 'title' ),
						"url" => $this->filter_text( $result->U, 'url' ),
						"summary" => $this->filter_text( $result->S, 'summary' )
					);
				}
			}
			return $results;
		}

		/* gets the title for a result after filtering */
		private function filter_text( $text, $field )
		{
			if ( isset( $this->filters[$field] ) ) {
				foreach ($this->filters[$field] as $func ) {
					if ( is_callable( $func ) ) {
						$text = call_user_func( $func, $text, $field );
					}
				}
			}
			return $text;
		}

		/* adds output filters for content */
		public function add_filter( $context, $func )
		{
			if ( is_callable( $func ) ) {
				if ( ! isset( $this->filters[$context] ) ) {
					$this->filters[$context] = array();
				}
				$this->filters[$context][] = $func;
			}
		}

		/* gets a search form */
		public function get_search_form()
	    {
	        $form = sprintf('<form role="search" method="get" action="%s">', $this->appliance_options['search_url']);
	        $form .= '<label class="screen-reader-text">Search for:</label>';
	        $form .= sprintf('<input type="text" value="%s" name="%s" class="searchinput" />', $this->get_search_query(), $this->appliance_options['query_var'] );
	        $form .= '<input type="submit" class="searchsubmit" value="Go" /></form>';
	        return $this->filter_text( $form, 'form' );
	    }

		/* gets results paging navigation */
		public function get_paging_navigation( $results )
		{
			$out = "";
			if ($results["number"] > $this->appliance_options['per_page']) {
				$out = '<nav role="navigation" class="gsa-paging"><ul>';
				$currentpage = ceil($results["start"] / $this->appliance_options['per_page']);
				$base_url = $this->appliance_options['search_url'] . '?' . $this->appliance_options['query_var'] . '=' . urlencode( $results["query"] ) . '&' . $this->appliance_options['paging_var'] . '=';
				if ($results["hasprevious"]) {
					$out .= sprintf( '<li class="nav-previous"><a href="%s%d" class="next-prev prev">&larr; Previous page</a></li>', $base_url, ($currentpage - 1) );
				}
				if ($results["hasnext"]) {
					$out .= sprintf( '<li class="nav-next"><a href="%s%d" class="next-prev next">Next page &rarr;</a></li>', $base_url, ($currentpage + 1) );
				}
				$out .= '</ul></nav>';
			}
			return $this->filter_text( $out, 'nav' );
		}

		/* gets the query for the search */
		public function get_search_query()
		{
			return isset( $_REQUEST[$this->appliance_options['query_var']] ) ? trim( $_REQUEST[$this->appliance_options['query_var']] ) : '';
		}


	} /* end of class definition */

endif;