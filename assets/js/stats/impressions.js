import { debounce, getPostId } from './utils';
import { waitForSelector } from './observers';

function createStatsQueue( statName ) {
	const sent = {};
	let queue = [];

	function sendNow() {
		const stats = queue.map( stat => {
			sent[ stat.post_id ] = true;
			return stat;
		} );

		queue = [];

		window.WPJMStats.log( stats );
	}

	const sendLater = debounce( sendNow, 1000 );

	function maybeSendLogs() {
		if ( queue.length >= 10 ) {
			sendNow();
		} else {
			sendLater();
		}
	}

	function queueLogStat( id ) {
		if ( ! sent[ id ] ) {
			queue.push( { name: statName, post_id: id } );
			maybeSendLogs();
		}
	}

	return { queueLogStat };
}

function createIntersectionObserver( container, onVisible ) {
	const viewed = {};

	const options = {
		root: null,
		rootMargin: '0px',
		threshold: 1.0,
	};

	const observer = new IntersectionObserver( entries => {
		entries.forEach( entry => {
			if ( entry.isIntersecting && entry.intersectionRatio > 0.99 ) {
				const node = entry.target;
				const nodeId = getPostId( node );
				if ( nodeId > 0 && ! viewed[ nodeId ] ) {
					viewed[ nodeId ] = true;
					onVisible( nodeId );
				}
				observer.unobserve( node );
			}
		} );
	}, options );

	return observer;
}

function observeMutations( container, { selector: itemSelector, onAdded, onRemoved } ) {
	const observer = new MutationObserver( mutations => {
		mutations.forEach( mutation => {
			mutation.removedNodes.forEach( node => {
				if ( node.matches?.( itemSelector ) ) {
					onRemoved( node );
				}
			} );
			mutation.addedNodes.forEach( node => {
				if ( node.matches?.( itemSelector ) ) {
					onAdded( node );
				}
			} );
		} );
	} );

	observer.observe( container, { childList: true } );
}

export async function initImpressionStat( stat ) {
	const { args: selectors } = stat;
	const { queueLogStat } = createStatsQueue( stat.name );
	const container = await waitForSelector( selectors.container );

	const intersections = createIntersectionObserver( container, queueLogStat );

	const initialItems = container.querySelectorAll( selectors.item );
	initialItems.forEach( node => intersections.observe( node ) );

	observeMutations( container, {
		selector: selectors.item,
		onAdded: node => intersections.observe( node ),
		onRemoved: node => intersections.unobserve( node ),
	} );
}
