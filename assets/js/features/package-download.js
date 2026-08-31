/**
 * Core Blueprint - Package Downloads theme integration.
 *
 * Adds a native-looking Download action to installed theme cards on the
 * Appearance -> Themes overview. WordPress does not expose a PHP action-link
 * hook for these card controls, so the small UI adapter is implemented in
 * vanilla JavaScript. The archive/download endpoint remains server-side.
 *
 * No jQuery dependency.
 *
 * @since   1.0.0
 */

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/package-download' );
let data = {};

if ( dataEl ) {
	try {
		data = JSON.parse( dataEl.textContent || '{}' );
	} catch ( error ) {
		// Invalid module data should fail closed: no download link is injected.
		data = {};
	}
}

const themeUrls = data.themeUrls || {};
const label = data.i18n?.download || 'Download';

/**
 * Resolve the stylesheet slug represented by a native WordPress theme card.
 *
 * WordPress core assigns the stylesheet id to `data-slug` when its Backbone
 * Theme view renders a card. Existing `more-details` / `theme-name` ids are
 * retained as defensive fallbacks for the initial server-rendered markup.
 *
 * @param {Element} card Installed-theme card.
 * @return {string} Theme stylesheet slug, or an empty string when unavailable.
 */
const resolveThemeSlug = ( card ) => {
	if ( !( card instanceof Element ) ) {
		return '';
	}

	const explicitSlug = card.getAttribute( 'data-slug' ) || '';
	if ( explicitSlug && themeUrls[ explicitSlug ] ) {
		return explicitSlug;
	}

	const details = card.querySelector( '.more-details[id$="-action"]' );
	if ( details instanceof HTMLElement ) {
		const id = details.id || '';
		const slug = id.endsWith( '-action' ) ? id.slice( 0, -7 ) : '';
		if ( slug && themeUrls[ slug ] ) {
			return slug;
		}
	}

	const name = card.querySelector( '.theme-name[id$="-name"]' );
	if ( name instanceof HTMLElement ) {
		const id = name.id || '';
		const slug = id.endsWith( '-name' ) ? id.slice( 0, -5 ) : '';
		if ( slug && themeUrls[ slug ] ) {
			return slug;
		}
	}

	return '';
};

/**
 * Create a Download link for an installed theme card.
 *
 * @param {string} slug Theme stylesheet slug.
 * @return {HTMLAnchorElement|null} Download link or null when no URL exists.
 */
const createDownloadLink = ( slug ) => {
	const url = themeUrls[ slug ];
	if ( ! url ) {
		return null;
	}

	const link = document.createElement( 'a' );
	link.className = 'button button-small cb-package-download-theme';
	link.href = url;
	link.textContent = label;
	link.dataset.themeSlug = slug;
	link.setAttribute( 'aria-label', `${ label }: ${ slug }` );

	// Keep the action local to the button if WordPress changes card-level click
	// handling; the link's normal navigation/download behavior remains intact.
	link.addEventListener( 'click', ( event ) => {
		event.stopPropagation();
	} );

	return link;
};

/**
 * Add a Download action to one installed-theme card.
 *
 * @param {Element} card Installed-theme card.
 */
const enhanceThemeCard = ( card ) => {
	if ( !( card instanceof HTMLElement ) ) {
		return;
	}

	const actions = card.querySelector( '.theme-actions' );
	const slug = resolveThemeSlug( card );
	if ( !( actions instanceof HTMLElement ) || ! slug ) {
		return;
	}

	const existing = actions.querySelector( '.cb-package-download-theme' );
	if ( existing instanceof HTMLAnchorElement && existing.dataset.themeSlug === slug ) {
		return;
	}

	if ( existing instanceof HTMLElement ) {
		existing.remove();
	}

	const link = createDownloadLink( slug );
	if ( link instanceof HTMLAnchorElement ) {
		actions.prepend( link );
	}
};

/**
 * Enhance all currently rendered installed-theme cards.
 */
const enhanceThemeCards = () => {
	document.querySelectorAll( '.themes .theme' ).forEach( enhanceThemeCard );
};

enhanceThemeCards();

// WordPress' theme.js replaces the entire `.themes` collection when its
// Backbone Appearance view initializes on DOM ready. Observing `.themes`
// itself therefore attaches to a node that core subsequently removes. The
// surrounding `.theme-browser` is persistent, so observe that lifecycle root
// instead. This also covers later filtering/re-renders without touching the
// separate Theme Details overlay.
const themeBrowser = document.querySelector( '.theme-browser' );
if ( themeBrowser instanceof HTMLElement ) {
	new MutationObserver( enhanceThemeCards ).observe( themeBrowser, { childList: true, subtree: true } );
}
