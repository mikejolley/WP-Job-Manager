/**
 * External dependencies
 */
import { config } from 'dotenv';
import fs from 'fs';
import process from 'node:process';
import inquirer from 'inquirer';
import chalk from 'chalk';
import { execSync } from 'node:child_process';
import prTemplate from './RELEASE_PR_TEMPLATE.md.mjs';

const PLUGINS = {
	'wp-job-manager': {
		file: 'wp-job-manager.php',
		constant: 'JOB_MANAGER_VERSION',
		repo: 'Automattic/wp-job-manager',
	},
};

const REMOTE = `origin`;

/* eslint-disable no-console */

// Processes the .env variables.
config();

// Get plugin information.
const pluginSlug         = getPluginSlug();
const plugin             = PLUGINS[ pluginSlug ];
const pluginFileName     = plugin.file;
const pluginFileContents = readFileContents( pluginFileName );
const pluginName         = pluginFileContents.match( /Plugin Name: (.*)/ )[ 1 ];
const version            = process.argv[ 3 ];

const ghPrs = `gh pr list -R ${ plugin.repo } --state merged --base trunk --search "milestone:${ version }"`;

// Confirm release through CLI.
if ( ! ( await askForConfirmation( version, pluginFileContents ) ) ) {
	process.exit( 0 );
}

// Create release branch.
const { originalBranchName, releaseBranch } = createReleaseBranch(
	pluginSlug,
	version,
);

try {
	updateVersionInFile( pluginFileName );
	updateVersionInFile( 'readme.txt' );
	replaceNextVersionPlaceholder();
	updatePackageJsonFiles();
	generatePotFiles();
	commitFiles();

	const changelog = buildReleaseNotes();

	// Create PR
	pushBranch();
	createPR( changelog );

} catch ( error ) {

	console.log( chalk.bold.red( error.message ) );
	console.log( error.message );
	console.log( error.stack );

	const { confirmation } = await inquirer.prompt( {
		type: 'confirm',
		name: 'confirmation',
		message: 'Roll back and delete release branch?',
		default: true,
	} );

	if ( confirmation ) {
		revertOnError( originalBranchName, releaseBranch );
	}
}

/**
 * Get plugin slug from command arguments.
 * Throws an error if invalid.
 *
 * @return {string} The plugin slug.
 */
function getPluginSlug() {
	const slug = process.argv[ 2 ];
	if ( ! ( slug in PLUGINS ) ) {
		throw new Error(
			'Please provide a valid plugin slug as the first parameter: ' +
			Object.keys( PLUGINS ).join( ', ' ),
		);
	}
	return slug;
}

/**
 * Return file contents given the filepath.
 * This method will throw an error if the file does not exist.
 *
 * @param {string} filepath The file path to be read.
 * @return {string} The file contents.
 */
function readFileContents( filepath ) {
	let contents = false;
	try {
		contents = fs.readFileSync( filepath, 'utf8' );
	} catch ( err ) {
		throw new Error( `File (${ filepath }) could not be read.` );
	}

	return contents;
}

/**
 * Ask for confirmation on new version and dependency versions through the CLI.
 *
 * @param {string} newVersion   The new version.
 * @param {string} fileContents The current contents of the main plugin file.
 * @return {Promise<boolean>} Whether the confirmation was accepted or not.
 */
async function askForConfirmation(
	newVersion,
	fileContents,
) {
	// WP Versions
	const currentWPRequiresAtLeast = fileContents.match(
		/Requires at least: (.*)/,
	)[ 1 ];
	const currentWPTestedUpTo      = fileContents.match( /Tested up to: (.*)/ )[ 1 ];
	// PHP
	const currentRequiresPhp       = fileContents.match( /Requires PHP: (.*)/ )[ 1 ];

	// Display all versioning information and ask for confirmation.
	console.log( `🚀 Preparing new release:`, chalk.bold( `${ pluginSlug } ${ newVersion }` ) );
	console.log( `-----------------------------` );
	console.log( chalk.bold( '📦 Plugin header:' ) );
	console.log( `   Version:`, chalk.bold.green( newVersion ) );
	console.log( `   (WP)  Requires at least:`, chalk.bold( currentWPRequiresAtLeast ) );
	console.log( `   (WP)  Tested up to:`, chalk.bold( currentWPTestedUpTo ) );
	console.log( `   (PHP) Requires PHP:`, chalk.bold( currentRequiresPhp ) );
	console.log( `-----------------------------` );
	console.log( `ℹ️️  Make sure a ` + chalk.bold( `milestone ${ newVersion }` ) + ` exists GitHub, and all PRs are assigned to the milestone.` );
	console.log( `-----------------------------` );
	console.log( `ℹ️️  Make sure you are logged in to GH CLI with \`gh auth login\`.` );
	execSync( 'gh auth status' );
	console.log( `-----------------------------` );
	console.log( `Pull requests to include (milestone ${ newVersion }):` );

	execSync( ghPrs, { stdio: 'inherit' } );

	const branch = execSync( 'git branch --show-current' ).toString().trim();

	const defaultBranch = 'trunk';
	const warning       = ( branch !== defaultBranch ) ? chalk.bgRed( ` ‼️  Not ${ defaultBranch }! ‼️ ` ) : '';

	console.log( `-----------------------------` );

	console.log( 'Branch:', chalk.bold[ branch !== defaultBranch ? 'red' : 'green' ]( branch ), warning );

	console.log( `-----------------------------` );

	const { confirmation } = await inquirer.prompt( {
		type: 'confirm',
		name: 'confirmation',
		message: 'Proceed with release preparation?',
		default: false,
	} );
	return confirmation;
}

/**
 * Create release branch given the slug and version.
 *
 * @param {string} slug    Plugin slug name.
 * @param {string} version New version.
 * @return {Object} The name of the original and the new release branches.
 */
function createReleaseBranch( slug, version ) {
	const currentBranchName = execSync( 'git branch --show-current' )
		.toString()
		.trim();
	const branchName        = `release/${ slug }-${ version }`;
	console.log( `Creating branch '${ branchName }' ...` );
	try {
		execSync( `git checkout -b ${ branchName }` );
	} catch {
		throw new Error(
			'Error creating branch. Check branch does not exist.',
		);
	}
	return {
		originalBranchName: currentBranchName,
		releaseBranch: branchName,
	};
}

/**
 * Set new version in the main plugin file.
 * This method also creates a commit in the current branch.
 *
 * @param {string} filename     The path to the main plugin file.
 */
function updateVersionInFile( filename ) {
	console.log( 'Updating plugin file versions ...' );
	let newPluginFileContents = readFileContents( filename ).replace(
		/(Version|Stable tag): (.*)/,
		`$1: ${ version }`,
	);

	// Update version constant.
	const { constant }    = PLUGINS[ pluginSlug ];
	newPluginFileContents = newPluginFileContents.replace(
		new RegExp( `define\\( '${constant}', '.*' \\);` ),
		`define( '${ constant }', '${ version }' );`,
	);

	fs.writeFileSync( filename, newPluginFileContents, 'utf-8' );
	execSync(
		`git add ${ filename }`,
	);
}

/**
 * Replaces the next-version placeholder with the new version to be released.
 * This method also creates a commit in the current branch.
 */
function replaceNextVersionPlaceholder() {
	console.log( `Replacing next version placeholder with ${ version } ...` );
	execSync( `bash scripts/replace-next-version-tag.sh ${ version }` );
	execSync(
		`git add .`,
	);
}

/**
 * Update package.json and package-lock.json files with the new version to be released.
 * This method also creates a commit in the current branch.
 */
function updatePackageJsonFiles() {
	console.log( 'Updating package.json version...' );
	try {
		execSync( `npm version ${ version } --no-git-tag-version` );
		execSync(
			`git add package.json package-lock.json`,
		);
	} catch {
		console.log( 'Version could not be updated in package.json file.' );
	}

}

/**
 * Generate POT files (translations).
 * This method also creates a commit in the current branch.
 */
function generatePotFiles() {
	console.log( 'Updating POT files...' );
	try {
		execSync( `npm run i18n:build 2> /dev/null` );
	} catch {
		throw new Error( 'POT file generation failed.' );
	}
	execSync( `git add languages/` );
}

function commitFiles() {
	execSync( `git commit -m "Update plugin to ${ version }."` );
}

/**
 * Generates the changelog based on the PRs.
 * This method also creates a commit in the current branch.
 */
function buildReleaseNotes() {
	let prs = execSync( `${ ghPrs }  --json number,title,body,labels` );
	prs     = JSON.parse( prs );

	let changelog = prs.map( ( pr ) => {
		const body              = pr.body;
		const changelogSections = body.match( /### Release Notes([\S\s]*?)(?:###|<!--|$)/ );

		if ( ! changelogSections ) {
			return `* ${ pr.title } (#${ pr.number })`;
		}
		const prChangelog = changelogSections[ 1 ].trim();

		if ( ! prChangelog.match( /\w/ ) ) {
			return '';
		}

		return prChangelog;
	} ).join( "\n" );

	if ( changelog.trim().length === 0 ) {
		changelog = '* Updated plugin headers';
	}

	console.log( 'Proposed changelog: ' );
	console.log( changelog );

	return changelog;
}

/**
 * Create release PR.
 *
 * @param changelog
 */
function createPR( changelog ) {

	const title = `Release ${ pluginName } ${ version }`;

	let body = prTemplate( { changelog, version } );
	body     = body
		.replace( '"', '\"' )
		.replace( '`', '\`' )

	const prLink = execSync( `gh pr create -R ${ plugin.repo } -B trunk -H ${ releaseBranch } --assignee @me --base trunk --title "${ title }" --body "${ body }"` );
	execSync( `open ${ prLink }` );
	console.log( `PR: ${ prLink }` );
}

/**
 * Pushes release branch.
 *
 * @param {string} branch The release branch name.
 */
function pushBranch() {
	console.log( 'Pushing branch ...' );
	try {
		execSync( `git push -u ${ REMOTE } ${ releaseBranch }` );
	} catch {
		throw Error( `New branch '${ releaseBranch }' could not be pushed.` );
	}
}

/**
 * Revert workspace to original status.
 *
 * @param {string} originalBranch The original branch name.
 * @param {string} releaseBranch  The new release branch name.
 */
function revertOnError( originalBranch, releaseBranch ) {
	console.log( 'Trying to move back to previous branch...' );
	execSync( `git checkout . && git checkout ${ originalBranch }` );
	console.log( `Deleting '${ releaseBranch }'....` );
	execSync( `git branch -D ${ releaseBranch }` );
}
