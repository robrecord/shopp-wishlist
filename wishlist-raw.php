<?php

if( shopp('wishlist','has-wishlist') )
{
	while( shopp( 'wishlist', 'get-wishlist' ) )
	{
		$sku = shopp( 'product', 'sku', 'return=1' );
		$name = ucwords( strtolower( shopp( 'product', 'name', 'return=1' ) ) );
		echo "\t- $name ($sku)\n";
	}
}
else  // no items on the wishlist
{
	_e( 'There are no items in the wishlist.', 'shoppwishlist' );
}
