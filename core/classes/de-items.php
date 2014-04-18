<?php
class De_Items {
	public static function add( $item ) {
		$reference = uniqid();
		
		$item->reference = $reference;
		$_SESSION[ 'de' ][ 'items' ][ $reference ] = serialize( $item );
		
		return $reference;
	}
	
	public static function update( $item ) {
		if ( ! empty( $item->reference ) && ! empty( $_SESSION[ 'de' ][ 'items' ][ $item->reference ] ) ) {
			$_SESSION[ 'de' ][ 'items' ][ $item->reference ] = serialize( $item );
		}
	}

	public static function get( $reference ) {
		return unserialize( $_SESSION[ 'de' ][ 'items' ][ $reference ] );
	}
}
