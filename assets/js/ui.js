// detect link color

export const ACCENT_COLOR_CSS_VAR = '--jm-ui-accent-color';

function computeAccentColor() {
	if ( getComputedStyle( document.documentElement ).getPropertyValue( ACCENT_COLOR_CSS_VAR ) ) {
		return;
	}

	const linkTag = document.createElement( 'a' );
	linkTag.setAttribute( 'href', '#?' );
	linkTag.style.display = 'none';
	const main = document.querySelector( 'main' ) ?? document.body;
	main.appendChild( linkTag );
	const color = getComputedStyle( linkTag ).color;
	linkTag.remove();

	if ( ! color ) {
		return;
	}

	document.documentElement.style.setProperty( ACCENT_COLOR_CSS_VAR, color );
}

computeAccentColor();
