<?php

class ShoppWishlist
{
	public $wishlist	= array();
	public $wish		= false;
	public $method		= '';
	public $storefront_slug;

	public function __construct( $use_session = false )
	{
		if( $use_session ) $this->method = $use_session ?
			'session' :
			shopp_setting ( 'account_system' );
		;

	}

	public function no_account()
	{
		if ( empty( $this->method ) || ( $this->method !== 'session' && ( ! shopp( 'customer', 'loggedin' ) ) ) ) return true;
		return false;
	}

	function get_customer_id( $email )
	{
		global $wpdb, $table_prefix;

		return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM { $table_prefix }shopp_customer WHERE email = '$email'" ) );
	}

	function tag ( $property, $options = array() )
	{
		global $user_ID, $Shopp;

		$property = str_replace( '-', '', $property );

		#http://greenandbenz.ohsopreview.co.uk/jewellery-box/share/MjczNzQ2ODYwNDAwJjImMzc2NzMy/

		if ( $this->method == 'shopp' )
			$user_ID = $this->get_customer_id( shopp('customer', 'email', 'mode=value&return=true' ) );

		if ( ( get_query_var( 'shoppwishlist' ) == 'share') ) {

			$user_ID = explode( '&', base64_decode(
				strrev(
					base64_decode(
						base64_decode(
							urldecode( get_query_var('customer') )
						)
					)
				)
			) );

			$user_ID = $user_ID[ 1 ];
		}

		switch ($property)
		{
			// account link
			case 'account':
			case 'accountlink':
				$before = ( !empty( $options[ 'before' ] ) ) ? $options[ 'before' ] : '';
				$inmenu = ( !empty( $options[ 'inmenu' ] ) ) ? $options[ 'inmenu' ] : false;

				if ( $inmenu == true )
				{
					return $before . '<li><h3><a href="' .
						shopp( 'wishlist', 'url', 'return=true' ) . '">' .
						__( 'View my wishlist', 'shoppwishlist' ) . '</a></h3></li>';
				}
				else
				{
					return $before . '<p class="wishlist button"><a href="' .
						shopp( 'wishlist', 'url', 'return=true' ) . '">' .
						__( 'View my wishlist', 'shoppwishlist' ) . '</a></p>';
				}
			break;
			// end account-link

			// button/link for adding/removing product from the wishlist
			case 'addbutton':
			case 'addlink':

				$before	= ( !empty( $options[ 'before' ] ) ) ? $options[ 'before' ] : '';
				$add	= ( !empty( $options[ 'add' ] ) ) ? $options[ 'add' ] : __( 'Add this product to my wishlist', 'shoppwishlist' );
				$listed	= ( !empty( $options[ 'listed' ] ) ) ? $options[ 'listed' ] : __( 'This product is already listed on my wishlist', 'shoppwishlist' );

				if ( $this->no_account ) return $output;

				$this->get_wishlist();

				if (! array_key_exists( shopp('product','id','return=true'), array_top_level( $this->wishlist ) ) )
				{
					$before .= '<p class="wishlist button"><a href="' .
						shopp('wishlist','url','type=add&return=true') . '">' .
						$add . '</a></p>';
				}
				else
				{
					if ( $options['linklisted'] == true )
					{
						$before .= '<p class="wishlist button listed"><a href="' .
						shopp('wishlist','url','type=remove&return=true') . '">' .
						$listed . '</a></p>';
					}
					else
					{
						$before .= '<p class="wishlist button listed">' . $listed . '</p>';
					}
				}

				return $before;
			break;
			// end button

			// URL for the wishlist, by default it's 'view'.
			case 'url':

				$pid = shopp( 'product', 'id', 'return=true' );

				$this->url		= trailingslashit( get_bloginfo( 'url' ) );

				switch( $options['type'] )
				{
					case 'add':
					return $this->permalinks( 'add&shopp_pid='.$pid, '/add/product/'.$pid );
					break;

					case 'share':
					$encoded_user_id = time() * rand(1,99)."&$user_ID&" . rand(100,999);
					$encoded_user_id = urlencode(
						base64_encode(base64_encode(strrev(base64_encode($encoded_user_id))))
					);
					return $this->permalinks( 'share&customer='.$encoded_user_id, '/share/customer/'.$encoded_user_id );
					break;

					case 'delete':
					case 'remove':
					return $this->permalinks( 'remove&shopp_pid='.$pid, '/remove/product/'.$pid );
					break;

					case 'wipe':
					return $this->permalinks( 'wipe', '/wipe/' );
					break;

					case 'view':
					case '':
					default:
					return $this->permalinks( 'view', '' );
					break;
				}

			break;
			// end URL

			// is-share
			case 'isshare':
				global $wp_query;
				return (get_query_var('shoppwishlist') == 'share') ? true : false;
			break;
			// end is-share

			// has-wishlist
			case 'haswishlist':

				$this->get_wishlist();
				return ($this->wishlist) ? true : false;

			break;
			// end has-wishlist

			// get-wishlist
			case 'wishlist':
			case 'getwishlist':
			case 'loop':

				if ( isset($options['num']) ) {
					return count($this->wishlist);
				}
				return $this->show_wishlist();

			break;
			// end get-wishlist

		} // end switch
	} // end tag

	function permalinks( $action, $uri )
	{
		if ( SHOPP_PRETTYURLS )
			return trailingslashit( $this->url . 'wishlist' . $uri );
		return $this->url. 'index.php?shoppwishlist=' . $action;
	}

	function get_wishlist()
	{
		if ( $this->method == 'session' )
			$this->wishlist = $_SESSION['shopp_wishlist'];

		else switch ( shopp_setting ( 'account_system') )
		{
			# wordpress
			case 'wordpress':
				$this->wishlist = get_user_meta($user_ID, 'shoppwishlist', true);
			break;
			# end wordpress

			#shopp
			case 'shopp':
				#$customer = shopp('customer','loginname','mode=value&return=true');
				$this->wishlist = get_option('shoppwishlist_customer_'.$user_ID, array());
			break;
			# end shopp
		}
		return $this->wishlist;
	}

	function show_wishlist()
	{
		if (!isset($this->_wishlist_loop))
		{
			reset($this->wishlist);
			$this->wish = current($this->wishlist);
			$this->_windex = 0;
			$this->_wishlist_loop = true;
		}
		else
		{
			$this->wish = next($this->wishlist);
			$this->_windex++;
		}
		// load current product
		// if( $this->method == 'wordpress' )
		// {
			// shopp('storefront','product','id='.$this->wish.'&load=true');
		// }
		// else
		// {
			shopp('catalog','product','id='.$this->wish.'&load=true');
		// }

		if ($this->wish !== false) return true;
		else
		{
			unset($this->_wishlist_loop);
			$this->_windex = 0;
			$this->wish = false;
			return false;
		}
	}

} // end class ShoppWishlist

$ShoppWishlist = new ShoppWishlist( true );

 // register filter callback
 // use later priority (11 or higher) to override Shopp
 add_filter('shopp_themeapi_object', 'my_themeapi_wishlist_fltr', 11, 2);

  // Set my custom object for shopp('wishlist') calls
 function my_themeapi_wishlist_fltr ( $Object, $context ) {
     if ( $context == 'wishlist' ) {
     	global $ShoppWishlist;
       	$Object = &$ShoppWishlist;

     }
     return $Object;
 }

 add_filter('shopp_themeapi_wishlist','wishlist_tag', 10, 4);

 function wishlist_tag ( $result, $options, $tag, $Object ) {

     if ( is_a( $Object, 'ShoppWishlist' ) ) {
         $result = $Object->tag($tag, $options);
     }
     return $result;
 }






?>
