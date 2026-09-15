/**
 * @file cypress/tests/functional/OjsbrFilenameRename.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: the name a downloaded submission file is delivered under,
 * in each interface language, and the settings that change it.
 *
 * What these guard is what the person downloading gets: a neutral name in their
 * language (or the journal's), never the uploaded name and never "##key##".
 *
 * Parameters (cypress.env.json or --env):
 *   contextPath        journal path                         (publicknowledge)
 *   galleyDownloadPath article/download/{id}/{galleyId}/{submissionFileId}
 *                      of a published galley; the download tests are skipped without it
 *   adminUser, adminPassword
 *                      a journal manager; the settings tests are skipped without them
 *
 * The plugin must be enabled with its default settings for the download tests.
 * Navigation is by URL and assertions are on names and ids rather than labels,
 * so the spec runs unchanged against a journal in any language.
 */

describe('Rename Files on Download plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const galleyDownloadPath = Cypress.env('galleyDownloadPath');
	const adminUser = Cypress.env('adminUser');
	const adminPassword = Cypress.env('adminPassword');

	// The expected stem of each language, as shipped in locale/*/locale.po.
	const expected = {
		pt_BR: (s, f) => 'submissao-' + s + '-arquivo-' + f,
		en: (s, f) => 'submission-' + s + '-file-' + f,
		es: (s, f) => 'envio-' + s + '-fichero-' + f,
	};

	const ids = () => {
		const [, submissionId, , submissionFileId] = galleyDownloadPath.match(/download\/(\d+)\/(\d+)\/(\d+)/);
		return {submissionId, submissionFileId};
	};

	// OJS 3.4 has no language in the URL: the interface language lives in the
	// session. A fresh session picks the language, and its cookie also makes the
	// download reach the application on a site whose edge cache serves
	// anonymous downloads.
	const download = (locale) => {
		cy.clearCookies();
		cy.request('/index.php/' + contextPath + '/user/setLocale/' + locale + '?source=' + encodeURIComponent('/index.php/' + contextPath + '/'));
		return cy.request({
			url: '/index.php/' + contextPath + '/' + galleyDownloadPath + '?cb=' + Date.now(),
			encoding: 'binary',
		}).then(response => {
			expect(response.status).to.eq(200);
			const disposition = response.headers['content-disposition'];
			const match = /filename\*=UTF-8''([^;]+)/.exec(disposition);
			expect(match, 'Content-Disposition: ' + disposition).to.not.eq(null);
			return decodeURIComponent(match[1]);
		});
	};

	// Settings are saved over AJAX from a modal; these helpers follow the
	// conventions of the PKP plugin grid (see the notes in the README).
	const settingsForm = 'form[id="ojsbrFilenameRenameSettings"]';

	const login = () => {
		cy.visit('/index.php/' + contextPath + '/login');
		cy.get('input[id=username]').clear().type(adminUser, {delay: 0});
		cy.get('input[id=password]').clear().type(adminPassword, {delay: 0, log: false});
		cy.get('form[id=login] button').click();
		cy.get('form[id=login]', {timeout: 30000}).should('not.exist');
	};

	const openSettings = () => {
		cy.visit('/index.php/' + contextPath + '/management/settings/website');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.waitJQuery();
		cy.get('tr[id*="ojsbrfilenamerenameplugin"] a.show_extras', {timeout: 30000}).click();
		cy.get('a[id*="ojsbrfilenamerenameplugin-settings"]', {timeout: 30000}).should('be.visible').click();
		cy.waitJQuery();
		cy.get(settingsForm, {timeout: 30000}).should('exist');
	};

	const save = (numbersOnly, filenameLocale) => {
		openSettings();
		cy.get(settingsForm + ' input[name="numbersOnly"][value="' + numbersOnly + '"]').check({force: true});
		cy.get(settingsForm + ' input[name="filenameLocale"][value="' + filenameLocale + '"]').check({force: true});
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').click({force: true});
		cy.waitJQuery();
		cy.get(settingsForm).should('not.exist');
	};

	Cypress.on('uncaught:exception', (err) => !err.message.includes('is not valid JSON'));

	describe('Downloads', function() {
		before(function() {
			if (!galleyDownloadPath) {
				this.skip();
			}
		});

		Object.keys(expected).forEach(locale => {
			it('Names the file in the ' + locale + ' interface language', function() {
				const {submissionId, submissionFileId} = ids();
				download(locale).then(name => {
					expect(name).to.match(new RegExp('^' + expected[locale](submissionId, submissionFileId) + '\\.[a-z0-9]+(\\.gz)?$'));
					expect(name).to.not.contain('##');
				});
			});
		});
	});

	describe('Settings', function() {
		before(function() {
			if (!galleyDownloadPath || !adminUser || !adminPassword) {
				this.skip();
			}
		});

		it('Offers the two formats and the two languages, defaulting to the descriptive name in the reader language', function() {
			login();
			openSettings();
			cy.get(settingsForm + ' input[name="numbersOnly"]').should('have.length', 2);
			cy.get(settingsForm + ' input[name="filenameLocale"]').should('have.length', 2);
			cy.get(settingsForm + ' input[name="numbersOnly"][value="0"]').should('be.checked');
			cy.get(settingsForm + ' input[name="filenameLocale"][value="user"]').should('be.checked');
		});

		it('Delivers numbers only when asked to', function() {
			const {submissionId, submissionFileId} = ids();
			login();
			save('1', 'user');
			download('es').then(name => expect(name).to.match(new RegExp('^' + submissionId + '-' + submissionFileId + '\\.')));
		});

		it('Uses the primary language of the journal when asked to', function() {
			login();
			save('0', 'context');
			download('en').then(english => {
				download('es').then(spanish => expect(spanish).to.eq(english));
			});
		});

		after(function() {
			if (galleyDownloadPath && adminUser && adminPassword) {
				login();
				save('0', 'user');
			}
		});
	});
});
