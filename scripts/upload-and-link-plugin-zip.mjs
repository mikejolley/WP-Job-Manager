#!/usr/bin/env zx

import 'zx/globals'

import { execSync } from "node:child_process";
import process from "node:process";
import { parseArgs } from "node:util";

const { env } = process;

const { values: { pr, commit, merged } } = parseArgs( {
	options: {
		pr: { type: "string" },
		commit: { type: "string" },
		merged: { type: "boolean", default: false },
	},
} );

const MEDIA_LIBRARY_ENDPOINT = "https://wpjobmanager.com/wp-json/wp/v2/media"

const login = env.WPJMCOM_API_LOGIN;

if ( ! login ) {
	console.log( 'WPJobManager.com secrets not available, exiting.' );
	process.exit( 0 );
}

try {
	await deleteOldZips();
} catch ( error ) {
	console.log( 'Failed to delete old plugin zips.' );
	console.log( error.message );
	console.log( error.stack );
	process.exit( 1 );
}

if ( merged ) {
	process.exit( 0 );
}

try {
	const zip = await uploadZip();
	await addLinksToPR( zip );
} catch ( error ) {
	console.log( 'Failed to upload plugin zip.' );
	console.log( error.message );
	console.log( error.stack );
	process.exit( 1 );
}

/**
 * Remove previously uploaded plugin zip files for the PR.
 */
async function deleteOldZips() {

	const url      = `${ MEDIA_LIBRARY_ENDPOINT }?mime_type=application/zip&_fields=id,date,title,source_url`;
	const response = await $`curl -s -u ${ login } ${ url }`.quiet();

	const oldZips = JSON.parse( response.toString() );
	if ( oldZips?.code || ! Array.isArray( oldZips ) ) {
		console.log( `[${ oldZips.code }]`, oldZips.message );
		return;
	}
	await Promise.all( [ oldZips?.map( async( zip ) => {
		const
			title = zip.title.rendered;
		if ( title.startsWith( `wp-job-manager-zip-${ pr }-` ) ) {
			console.log( `Deleting old plugin build ${ title }.zip` );
			const deleteUrl = `${ MEDIA_LIBRARY_ENDPOINT }/${ zip.id }?force=true`;
			await $`curl -s -u ${ login } -X DELETE ${ deleteUrl }`.quiet();
		}
	} ) ] );
}

/**
 * Upload plugin zip to media library.
 *
 * @returns {Promise<string>} URL to plugin zip file.
 */
async function uploadZip() {

	const id = `${ pr }-${ commit.substring( 0, 8 ) }`;

	const headers  = `Content-Disposition: attachment; filename="wp-job-manager-zip-${ id }.zip"`;
	const url      = `${ MEDIA_LIBRARY_ENDPOINT }?title=wp-job-manager-zip-${ id }`;
	const response = await $`curl -s -u ${ login } --http1.1 --data-binary @build/wp-job-manager.zip -H ${ headers } ${ url }`.quiet();

	const uploadedFileUrl = JSON.parse( response.toString() )?.source_url?.replaceAll( '"', '' ).trim();
	if ( ! uploadedFileUrl ) {
		throw new Error( response );
	}
	console.log( chalk.green( '✓' ), `Plugin file uploaded to ${ uploadedFileUrl }` )

	return uploadedFileUrl;
}

/**
 * Add a link to the plugin zip and the playground to the PR description.
 *
 * @param {string} zip URL to plugin zip file.
 */
async function addLinksToPR( zip ) {

	const [ , path, id ] = zip.match( 'wp-content/uploads/(.*)/wp-job-manager-zip-(.*).zip' );
	const playgroundLink = `https://wpjobmanager.com/playground/?core=${ path }/${ id }`

	const links = `
<!-- wpjm:plugin-zip -->
----

| Plugin build for ${ commit } <a href="#"><img width=600></a> |
| ------------------------------------------------------------ |
| 📦 [Download plugin zip](${ zip })                       |
| ▶️ [Open in playground](${ playgroundLink })             |

<!-- /wpjm:plugin-zip -->
`;

	let body = await $`gh pr view ${ pr } --json body --jq .body`.quiet();

	body = body.toString().replace( /((<!-- wpjm:plugin-zip -->([\s\S]*)<!-- \/wpjm:plugin-zip -->)|$)/, links );

	await $`gh pr edit ${ pr } --body ${ body }`.quiet();
	console.log( chalk.green( '✓' ), 'Plugin build links added to PR.' );
}
