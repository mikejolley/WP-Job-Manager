export function debounce( func, delay ) {
	let timeoutId;

	return function ( ...args ) {
		clearTimeout( timeoutId );

		timeoutId = setTimeout( () => {
			func( ...args );
		}, delay );
	};
}

export function getPostId( node ) {
	return +node.className.match( /\bpost-(\d+)\b/ )?.[ 1 ] || 0;
}

export function indexBy( list, key ) {
	return list.reduce( function ( accum, item ) {
		if ( ! item[ key ] ) {
			return accum;
		}
		accum[ item[ key ] ] = item;
		return accum;
	}, {} );
}

export const requestIdleCallback = window.requestIdleCallback
	? fn => window.requestIdleCallback( fn, { timeout: 500 } )
	: fn => setTimeout( fn, 100 );
