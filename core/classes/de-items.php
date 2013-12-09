<?php
class De_Items {
	public static function add( $item ) {
		$reference = uniqid();
		
		$item->reference = $reference;
		$_SESSION[ 'de' ][ 'items' ][ $reference ] = serialize( $item );
		
		return $reference;
	}
	
	public static function update( $item ) {
		$reference = $item->reference;
		
		if ( ! empty( $_SESSION[ 'de' ][ 'items' ][ $reference ] ) ) {
			$_SESSION[ 'de' ][ 'items' ][ $reference ] = serialize( $item );
		}
	}

	public static function get( $reference ) {
		return unserialize( $_SESSION[ 'de' ][ 'items' ][ $reference ] );
	}
}
