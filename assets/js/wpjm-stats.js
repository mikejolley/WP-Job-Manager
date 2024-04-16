import domReady from '@wordpress/dom-ready';
import { scheduleStaleUniqueCleanup, filterAndRecordUniques } from './stats/unique';
import { initImpressionStat } from './stats/impressions';
import { requestIdleCallback } from './stats/utils';

const WPJMStats = {
	stats: [],
	actions: [],
	init( stats ) {
		WPJMStats.stats = stats;

		stats.forEach( stat => {
			WPJMStats.types[ stat.type ]?.( stat );
		} );

		WPJMStats.doAction( 'page-load' );
		scheduleStaleUniqueCleanup();
	},

	doAction( action ) {
		const stats = WPJMStats.actions[ action ];
		if ( stats ) {
			WPJMStats.log( stats );
		}
	},
	types: {
		action( stat ) {
			const { action } = stat;
			if ( ! WPJMStats.actions[ action ] ) {
				WPJMStats.actions[ action ] = [];
			}
			WPJMStats.actions[ action ].push( stat );
		},
		domEvent( stat ) {
			const { args } = stat;
			if ( args.element && args.event ) {
				const domElement = document.querySelector( args.element );
				const handler = stat.action
					? () => WPJMStats.doAction( stat.action )
					: () => WPJMStats.log( stat );
				domElement?.addEventListener( args.event, handler );
			}
		},
		impression: initImpressionStat,
	},
	async log( stats ) {
		if ( stats.name ) {
			stats = [ stats ];
		}

		const { ajaxUrl, ajaxNonce, postId } = window.job_manager_stats;

		stats = filterAndRecordUniques( stats );

		if ( ! stats.length ) {
			return false;
		}

		const payload = stats.map( stat => {
			return [ 'name', 'group', 'post_id' ].reduce( ( m, field ) => {
				if ( stat[ field ] ) {
					m[ field ] = stat[ field ];
				}
				return m;
			}, {} );
		} );

		const postData = new URLSearchParams( {
			_ajax_nonce: ajaxNonce,
			post_id: postId,
			action: 'job_manager_log_stat',
			stats: JSON.stringify( payload ),
		} );

		return fetch( ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: postData,
		} );
	},
};

window.WPJMStats = WPJMStats;

domReady( () => {
	requestIdleCallback( () => {
		const { stats } = window.job_manager_stats;
		WPJMStats.init( stats );
	} );
} );
