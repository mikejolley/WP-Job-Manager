import { requestIdleCallback } from './utils';

const EXPIRATION = 24 * 60 * 60 * 1000;
const PREFIX = 'wpjm-stat-';

export function setAsRecordedToday( stat ) {
	const key = `${ PREFIX }${ stat.unique_key }`;
	const expiresAtTimestamp = Date.now() + EXPIRATION;
	window.localStorage[ key ] = expiresAtTimestamp;
}

export function wasRecordedToday( stat ) {
	const key = `${ PREFIX }${ stat.unique_key }`;
	return ! isExpired( window.localStorage[ key ] );
}

function isExpired( record ) {
	if ( ! record ) {
		return true;
	}
	const expiration = parseInt( record, 10 );
	return Number.isNaN( expiration ) ? true : expiration < Date.now();
}

export function isUnique( stat ) {
	return !! stat.unique_key;
}

export function filterAndRecordUniques( stats ) {
	return stats.filter( stat => {
		if ( isUnique( stat ) ) {
			if ( wasRecordedToday( stat ) ) {
				return false;
			}
			setAsRecordedToday( stat );
		}
		return true;
	} );
}

export function scheduleStaleUniqueCleanup() {
	const cleanup = function () {
		for ( let i = 0; i < localStorage.length; i++ ) {
			const key = localStorage.key( i );

			if ( ! key.startsWith( PREFIX ) ) {
				continue;
			}

			if ( isExpired( localStorage.getItem( key ) ) ) {
				localStorage.removeItem( key );
			}
		}
	};

	requestIdleCallback( cleanup );
}
