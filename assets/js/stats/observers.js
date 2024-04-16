export function waitForSelector( selector ) {
	return new Promise( function ( resolve ) {
		let node = document.querySelector( selector );
		if ( node ) {
			return resolve( node );
		}

		const observer = new MutationObserver( function () {
			node = document.querySelector( selector );
			if ( node ) {
				observer.disconnect();
				resolve( node );
			}
		} );

		observer.observe( document.documentElement, {
			childList: true,
			subtree: true,
		} );
	} );
}
