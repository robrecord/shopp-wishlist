<?php
/*

	Plugin Name: Shopp Wishlist
	Plugin URI: http://www.jbollinger.co.uk
	Description: Add a wishlist to your Shopp webshop.
	Version: 1.2.1b1
	Original Author: illutic WebDesign
	Author URI: http://www.jbollinger.co.uk
	Modified By: Jon Bollinger

	Copyright 2012, Jon Bollinger

	This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

include_once( 'shopp-wishlist-class.php' );
include_once( 'shopp-wishlist-widget.php' );


define( 'SHOPP_WISHLIST_DIR', WP_PLUGIN_DIR . '/shopp-wishlist' );

/****************************************************/
/*        HOOKS                                     */
/****************************************************/
/**/
add_filter( 'rewrite_rules_array', 'shoppwishlist_rewrite' );
add_filter( 'query_vars', 'shoppwishlist_queryvars' );
// add_filter( 'init', 'shoppwishlist_flushrules' );

add_filter( 'shopp_product_description', 'shoppwishlist_button' );
add_filter( 'shopp_account_template', 'shoppwishlist_account' );
add_filter( 'parse_query', 'shoppwishlist_parseQuery' );
add_filter( 'shoppwishlist_page', 'shoppwishlistdiv' );

add_action( 'widgets_init', create_function( '', 'return register_widget( "ShoppWishlist_Widget" );' ));

add_shortcode( 'wishlist', 'shoppwishlist_output_wishlist' );

//register_activation_hook( __FILE__, 'shoppwishlist_activate_plugin' );


/****************************************************/
/*        FUNCTIONS                                 */
/****************************************************/


/**
 * Desc: add the wishlist button to the product
 **/
function shoppwishlist_button( $output='' )
{
	// if (! shopp( 'customer', 'loggedin' ) ) return $output;

	return shopp( 'wishlist', 'add-button', 'return=true&before=' . $output);
} // end shoppwishlist_button


/**
 * Desc: add link to the wishlist page on the account
 **/
function shoppwishlist_account ( $output='' )
{
	$output = preg_replace( '#<li><h3><a href="(.*?)/?acct=account">(.*?)</a></h3></li>#',
		'<li><h3><a href="$1/?acct=account">$2</a></h3></li>' . "\n" .
		shopp( 'wishlist', 'account-link', 'return=true&inmenu=true' ), $output);
	return $output;
} // end shoppwishlist_account


/**
 * Desc: redirect to the shoppwishlist template function if 'wishlist=view' is in the URI
 **/
function shoppwishlist_parseQuery()
{
	global $wp_query, $Shopp, $user_ID;

	if ( in_array( get_query_var( 'shoppwishlist' ), array( 'view', 'share' ) ) )
	{
		$wp_query->is_single	= false;
		$wp_query->is_page		= false;
		$wp_query->is_archive	= false;
		$wp_query->is_search	= false;
		$wp_query->is_home		= false;

		add_action( 'template_redirect', 'shoppwishlist_template' ); // use the shopp-wishlist template
	}
	elseif ( get_query_var( 'shoppwishlist' ) )
	{
		global $ShoppWishlist;
		if ( ! isset( $ShoppWishlist ) ) $ShoppWishlist = new ShoppWishlist( true );

		if( $ShoppWishlist->no_account() )
		{
			shopp_redirect( shoppurl( false, 'catalog' ));
			exit();
		}

		$method = $ShoppWishlist->method;

		$shopp_pid = get_query_var( 'shopp_pid' );

		$wishlist_items = ($method == 'session') ?
			$_SESSION[ 'shopp_wishlist' ] :
			array_top_level( get_usermeta( $user_ID, 'shoppwishlist', false ) );

		switch ( get_query_var( 'shoppwishlist' ) )
		{
			# add product to wishlist
			case 'add':

				if ( ! shoppwishlist_product_exists_in_wishlist( $wishlist_items ) )
				{
					$wishlist_items[ $shopp_pid ] = $shopp_pid;
				}
			break;
			# end product to wishlist

			# remove product from wishlist
			case 'remove':
			case 'delete':
				if ( shoppwishlist_product_exists_in_wishlist( $wishlist_items ) ) {
					unset( $wishlist_items[ $shopp_pid ] );
				}
			break;
			# end remove product from wishlist

			# wipe wishlist clean
			case 'wipe':
				$wishlist_items = array();
			break;
			# end wipe wishlist clean
		} // end switch

		shoppwishlist_update_wishlist_items( $wishlist_items, $method );

		if ( get_query_var( 'shoppwishlist' ) )
		{
			shopp_redirect( shopp( 'wishlist', 'url', 'type=view&return=true' ), true );
			exit();
		}
	}
} // end shoppwishlist_parseQuery

function shoppwishlist_product_exists_in_wishlist( &$items )
{
	return array_key_exists( get_query_var( 'shopp_pid' ), $items );
}

function shoppwishlist_update_wishlist_items( $items, $method = 'session' )
{
	global $user_ID;

	switch( $method )
	{
		case 'session':
		$_SESSION[ 'shopp_wishlist' ] = $items;
		break;

		case 'wordpress':
		if( count( $items > 0 ) )
			// update_user_meta( $user_ID, 'shoppwishlist', $items );
			update_user_meta( $user_ID, 'shoppwishlist', array_top_level( $items ) );
		else
			delete_usermeta( $user_ID, 'shoppwishlist' );
		break;

		case 'shopp':
		if( count( $items > 0 ) )
			update_option( 'shoppwishlist_customer_' . $user_ID, $items );
		else
			delete_option( $user_ID, 'shoppwishlist_customer_' . $user_ID );
		break;
	}
}

/**
 * Desc: Use the shoppwishlist template to display the wishlist
 **/
function shoppwishlist_template()
{
	if ( in_array( get_query_var( 'shoppwishlist' ), array( 'view', 'share' ) ) )
	{
		// is there a wishlist.php inside the shopp folder of the current theme?
		if ( file_exists( get_query_template( 'shopp/wishlist' ) ) == true )
		{
			$template = get_query_template( 'shopp/wishlist' );
		}
		// else: is there a wishlist.php inside the current theme?
		elseif ( file_exists( get_query_template( 'wishlist' ) ) == true )
		{
			$template = get_query_template( 'wishlist' );
		}
		// use the default wishlist.php
		else
		{
			$template = SHOPP_WISHLIST_DIR . '/wishlist.php';
		}

		global $Shopp, $user_ID;
		include( $template );
		exit();
	}
} // end shoppwishlist_template


/**
 * Desc: output for when the shortcode [wishlist] has been used, the slug should NOT be 'wishlist' when it's a toplevel page
 **/
function shoppwishlist_output_wishlist()
{
	shoppwishlist_template();
} // end shoppwishlist_output_wishlist


/**
 * Desc: Output the URL to the wishlist page, for backwards compatibility
 **/
function shoppwishlist_url( $type='view' )
{
	global $Shopp;

	shopp( 'wishlist', 'url', 'type=' . $type . '&return=true' );
} // end shoppwishlist_url


/**
 * Desc: Remember to flush_rules() when adding rules
 **/
function shoppwishlist_flushrules()
{
	global $wp_rewrite;
   	$wp_rewrite->flush_rules();
} // end shoppwishlist_flushrules



/**
 * Desc: Add the queryvars for permalink support
 **/
function shoppwishlist_queryvars( $vars )
{
    return array_merge( $vars, array(
		'shopp_pid',
		'shoppwishlist',
		'customer',
   	) );
} // end shoppwishlist_queryvars


/**
 * Desc: Add rewrite rules
 **/
function shoppwishlist_rewrite( $wp_rewrite_rules )
{
	$rules = array();
	$rules[ 'wishlist/(.+?)/page/?([0-9]{1,})/?$' ] =
		'index.php?shoppwishlist=$matches[1]&paged=$matches[2]';
	$rules[ 'wishlist/(.+?)/product/?([0-9]{1,})/?$' ] =
		'index.php?shoppwishlist=$matches[1]&shopp_pid=$matches[2]';
	$rules[ 'wishlist/share/customer/?([0-9]{1,})/?$' ] =
		'index.php?shoppwishlist=share&customer=$matches[1]';
	$rules[ 'wishlist/(.+?)/?$' ]	=
		'index.php?shoppwishlist=$matches[1]';
	$rules[ 'wishlist/?$' ] =
		'index.php?shoppwishlist=view';

	return $rules + $wp_rewrite_rules;
} // end shoppwishlist_rewrite

function shoppwishlist_activate_plugin()
{
	do_action( 'permalink_structure_changed' );
}
/**
 * Desc: Make sure the array always is 1 level deep.
 **/
if ( !function_exists( 'array_top_level' ) )
{
	function array_top_level( $array )
	{
		$newarray = array();
		foreach ( $array as $value )
		{
			if ( is_array( $value ) )
			{
				foreach ( $value as $val )
				{
					if ( is_array( $val ) )
					{
						foreach ( $val as $v )
						{
							$newarray[ $v ] = $v;
						}
					}
					else
					{
						$newarray[ $val ] = $val;
					}
				}
			}
			else
			{
				$newarray[ $value ] = $value;
			}
		}
		return $newarray;
	} // end array_top_level
} // end if


?>
