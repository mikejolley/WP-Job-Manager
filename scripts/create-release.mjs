#!/usr/bin/env zx

import 'zx/globals'

/**
 * External dependencies
 */
import fs from 'node:fs';
import process from 'node:process';
import { execSync } from 'node:child_process';

const PLUGINS = {
	'wp-job-manager': {
		file: 'wp-job-manager.php',
		constant: 'JOB_MANAGER_VERSION',
		repo: 'Automattic/wp-job-manager',
	},
};

const REMOTE = `origin`;

/* eslint-disable no-console */

// Get plugin information.
const pluginSlug         = process.argv[ 2 ];
const plugin             = PLUGINS[ pluginSlug ];
const pluginFileName     = plugin.file;
const pluginFileContents = fs.readFileSync( pluginFileName, 'utf8' );
const pluginVersion      = pluginFileContents.match( /Version: (.*)/ )[ 1 ];
const pluginName         = pluginFileContents.match( /Plugin Name: (.*)/ )[ 1 ];

const prNumber = process.argv[ 3 ];

const releaseNotes = getReleaseNotes();
updateChangelog();
commitChangelog();
tagRelease();
buildPluginZip();
await createGithubRelease();
setWorkflowStepOutput();
await success();

function getReleaseNotes() {

	const prDescription = JSON.parse( execSync( `gh pr view ${ prNumber } -R ${ plugin.repo } --json body` ).toString() ).body;
	const releaseNotes  = prDescription
		.match( /### Release Notes\s*\n---([\S\s]*?)---/ )[ 1 ]
		.replace( /^- /gm, '* ' )
		.trim();

	return releaseNotes;
}

function updateChangelog() {

	let changelog = fs.readFileSync( 'changelog.txt', 'utf8' );

	const release = `## ${ pluginVersion } - ${ new Date().toISOString().slice( 0, 10 ) }
${ releaseNotes }`;

	changelog = changelog.replace( /^(# .*\n)/, '$1\n' + release + "\n" );

	const releases = [ ...changelog.matchAll( /(^##[\S\s]*?(?=##))/mg ) ].map( ( match ) => match[ 1 ] );

	console.log( chalk.bold( 'Adding new release to changelog: ' ) );
	console.log( releases[ 0 ] )

	const readmeReleases = releases.slice( 0, 5 ).map( release => release.replace( /^## /, '### ' ));

	let readme = fs.readFileSync( 'readme.txt', 'utf8' );
	readme     = readme.replace( /(== Changelog ==\n)([\s\S]+)/gm, `$1\n${ readmeReleases.join( '' ) }` );

	fs.writeFileSync( 'changelog.txt', changelog );
	console.log( chalk.green( '✓' ), 'changelog.txt' );

	fs.writeFileSync( 'readme.txt', readme );
	console.log( chalk.green( '✓' ), 'readme.txt' );

}

function commitChangelog() {
	execSync( 'git add changelog.txt readme.txt' );
	execSync( `git commit -m "Update changelog for ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } HEAD` );
}

function tagRelease() {
	execSync( `git tag -a ${ pluginVersion } -m "Release ${ pluginVersion }"` );
	execSync( `git push ${ REMOTE } ${ pluginVersion }` );
}

function buildPluginZip() {
	execSync( `npm run build 1> /dev/null` );
}

function setWorkflowStepOutput() {
	execSync( `echo "version=${ pluginVersion }" >> "$GITHUB_OUTPUT"` );
}

async function createGithubRelease() {
	const pluginZip = `build/${ pluginSlug }.zip`
	await $`gh release create ${ pluginVersion } -R ${ plugin.repo } --title ${ `Version ${ pluginVersion }` } --notes ${ releaseNotes } ${ pluginZip }`
}

async function success() {
	console.log( chalk.bold.green( `✓ ${ pluginName } ${ pluginVersion } release created!` ) );
	const comment = `✅ **[${ pluginName } ${ pluginVersion } release](https://github.com/${ plugin.repo }/releases/tag/${ pluginVersion })** created!`;
	await $`gh pr comment ${ prNumber } -R ${ plugin.repo } --edit-last --body ${ comment }`

}
