import domReady from '@wordpress/dom-ready';

import { Color } from './ui.color-utils';

const uiVariables = {
	/**
	 * Set up accent color variable to the link color.
	 */
	'--jm-ui-accent-color': {
		init: ( { linkColor } ) => linkColor.css,
		value: null,
	},
	/**
	 * Set the background color used for overlay elements to dark/light.
	 */
	'--jm-ui-background-color': {
		init: ( { darkMode, backgroundColor } ) => {
			if ( backgroundColor ) {
				// Check if the page background color is dark enough for dark mode's white text, and use a slightly lighter version of it.
				if ( darkMode && backgroundColor.l < 0.5 ) {
					return `color-mix(in srgb, #fff, ${ backgroundColor.css } 95%)`;
				}

				if ( ! darkMode ) {
					return `color-mix(in srgb, #fff, ${ backgroundColor.css } 50%)`;
				}
			}
			return darkMode ? '#111' : '#fff';
		},
		value: null,
	},
	/**
	 * Use the text color for the UI text color if it has enough contrast with the background.
	 */
	'--jm-ui-text-color': {
		init: ( { darkMode, textColor } ) => {
			if ( textColor ) {
				// Use theme text color if it has enough contrast with the background.
				if ( ( darkMode && textColor.l < 0.25 ) || ( ! darkMode && textColor.l > 0.25 ) ) {
					return textColor.css;
				}
			}
			return darkMode ? '#fff' : '#000';
		},
		value: null,
	},
	/**
	 * Create a darker or lighter blend of the accent color, based on its brightness.
	 */
	'--jm-ui-accent-alt-color': {
		init: ( { linkColor, backgroundColor } ) => {
			const lighten = linkColor.l < 0.2;
			const darkBase = backgroundColor?.l < 0.4 ? backgroundColor.css : '#000';
			const base = lighten ? '#fff' : darkBase;
			const amount = lighten ? '65%' : '40%';

			return `color-mix(in srgb, ${ base }, var(--jm-ui-accent-color) ${ amount })`;
		},
		value: null,
	},
	/**
	 * Use the background color for the button text color if it has enough contrast.
	 */
	'--jm-ui-accent-color-contrast': {
		init: ( { linkColor, backgroundColor } ) => {
			if ( Math.abs( backgroundColor?.l - linkColor?.l ) > 0.5 ) {
				return backgroundColor.css;
			}
			return '#fff';
		},
		value: null,
	},
	/**
	 * Adjust the red danger/error color to be legible on dark backgrounds.
	 */
	'--jm-ui-danger-color': {
		init: ( { backgroundColor } ) => {
			if ( backgroundColor?.l < 0.5 ) {
				return `var(--jm-ui-danger-color-dark-mode)`;
			}
		},
		value: null,
		default: '#cc1818',
	},
	/**
	 * Try to use the font family (typically sans-serif) used by the theme for menus and other elements, rather than the serif font typically used for post content.
	 */
	'--jm-ui-font-family': {
		init: ( { bodyFontFamily } ) => bodyFontFamily,
		value: null,
	},
};

/**
 * Detect and compute style variables based on the site theme.
 * Skips any variable that's been explicitly set already.
 */
function applyThemeStyles() {
	const style = getComputedStyle( document.documentElement );
	for ( const [ key, color ] of Object.entries( uiVariables ) ) {
		let value = style.getPropertyValue( key );

		let hasCustomValue = !! value;

		if ( color.default ) {
			hasCustomValue = color.default !== value;
		}

		if ( ! hasCustomValue ) {
			value = color.init( ThemeStyles.get() );
			if ( value ) {
				document.documentElement.style.setProperty( key, value );
			}
		}

		color.value = value;
	}
}

/**
 * Detected theme styles.
 */
const ThemeStyles = {
	linkColor: null,
	textColor: null,
	backgroundColor: null,
	darkMode: false,
	bodyFontFamily: false,
	initialized: false,
	/**
	 * Query computed styles for various elements to detect theme colors and styles.
	 */
	detect() {
		const postTag = document.createElement( 'div' );
		postTag.classList.add( 'wp-block-post-content' );
		postTag.style.display = 'none';
		const linkTag = document.createElement( 'a' );
		linkTag.setAttribute( 'href', '#?' );

		const textTag = document.createElement( 'p' );

		const main = document.querySelector( 'main' ) ?? document.body;
		postTag.appendChild( linkTag );
		postTag.appendChild( textTag );
		main.appendChild( postTag );
		this.linkColor = Color.parse( getComputedStyle( linkTag ).color );
		this.textColor = Color.parse( getComputedStyle( textTag ).color );
		this.backgroundColor = Color.parse( getComputedStyle( document.body ).backgroundColor );
		postTag.remove();

		this.bodyFontFamily = getComputedStyle( document.body ).fontFamily;
		this.darkMode = this.textColor.l > 0.8;
	},
	get() {
		if ( ! this.initialized ) {
			this.detect();
			this.initialized = true;
		}
		return this;
	},
};

domReady( () => {
	try {
		applyThemeStyles();
	} catch ( e ) {
		// eslint-disable-next-line no-console
		console.error( e );
	}
} );
