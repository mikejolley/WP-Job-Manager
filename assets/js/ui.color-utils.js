/**
 * Parse a CSS color string and provide RGB and HSL values.
 *
 * Supported formats: "rgb(255, 255, 255)" or "rgba(255, 255, 255, 0.5)"
 *
 * Use as Color.parse( rgbString )
 */
export class Color {
	/**
	 * Create a new Color instance.
	 *
	 * @param {Object} rgb RGB values ( { r: 255, g: 255, b: 255, a: 1.0 } )
	 */
	constructor( rgb ) {
		Object.assign( this, rgb );
		Object.assign( this, Color.toHsl( rgb ) );
	}

	/**
	 * Parse a CSS color string and return a new Color instance.
	 *
	 * @param {string} rgbString CSS color string.
	 *
	 * @return {?Color} Color instance or null if parsing failed.
	 */
	static parse( rgbString ) {
		const match = rgbString?.match?.( /(\d+)/g );

		if ( ! match || match.length < 3 ) {
			return null;
		}

		const [ r, g, b, a ] = match;
		return new Color( { r: +r, g: +g, b: +b, a: a ? +a : 1.0 } );
	}

	/**
	 * Convert RGB to HSL.
	 *
	 * @param {{r: number, g: number, b: number, a: number}} rgb RGB values.
	 * @return {{a: number, s: number, h: number, l: number}} HSL values.
	 */
	static toHsl( rgb ) {
		let { r, g, b, a } = rgb;
		r /= 255;
		g /= 255;
		b /= 255;

		const max = Math.max( r, g, b );
		const min = Math.min( r, g, b );
		let h, s;
		const l = ( max + min ) / 2;

		if ( max === min ) {
			h = s = 0;
		} else {
			const d = max - min;
			s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );

			switch ( max ) {
				case r:
					h = ( g - b ) / d + ( g < b ? 6 : 0 );
					break;
				case g:
					h = ( b - r ) / d + 2;
					break;
				case b:
					h = ( r - g ) / d + 4;
					break;
			}

			h /= 6;
		}

		return { h, s, l, a: +a };
	}

	/**
	 * Render as CSS color string.
	 */
	get css() {
		const { r, g, b, a } = this;
		return `rgba(${ r }, ${ g }, ${ b }, ${ a })`;
	}
}
