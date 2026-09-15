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
 * Parameters (--env): contextPath, adminUser, adminPassword (captcha on login
 * must be off for the run). The defaults match the data set of PKP's continuous
 * integration; the first test enables the plugin when it is off and finds a
 * published galley through the REST API. Every setting touched is put back.
 * Assertions are on names and ids rather than labels.
 */

describe('Rename Files on Download plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';

	const rowName = 'ojsbrfilenamerenameplugin';
	const settingsForm = 'form[id="ojsbrFilenameRenameSettings"]';

	// The expected stem of each language, as shipped in locale/*/locale.po.
	const expected = {
		pt_BR: (s, f) => 'submissao-' + s + '-arquivo-' + f,
		en: (s, f) => 'submission-' + s + '-file-' + f,
		es: (s, f) => 'envio-' + s + '-fichero-' + f,
	};

	// article/download/{submissionId}/{galleyId}/{submissionFileId} of a published galley,
	// and the languages the journal offers.
	let galley = null;
	let locales = [];

	// ---- OJSBR spec helpers (padrão v2): work on OJS/OMP 3.3, 3.4 and 3.5 and in PKP's CI ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');

	// Same as PKP's cy.waitJQuery(), which the support files of OJS 3.3 test sites may lack.
	// The Plugins tab can keep requests open for a while (the plugin gallery), hence the timeout.
	const waitJQuery = () => cy.window().its('jQuery.active', {timeout: 60000}).should('eq', 0);

	// Requests carry the browser's User-Agent: OJS 3.3 drops a session whose agent changes.
	const request = (options) => cy.window({log: false}).then((win) => cy.request(Object.assign(
		typeof options === 'string' ? {url: options} : options,
		{headers: Object.assign({'User-Agent': win.navigator.userAgent}, (typeof options === 'string' ? {} : options.headers) || {})}
	)));

	// Signs in through requests (the login page can re-render while it is typed into), then
	// falls back to the form when the session did not stick (OJS 3.3 cookie handling).
	const login = (username, password) => {
		cy.clearCookies();
		request(pageUrl('login')).then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// The form posts to the URL with the language: a redirect would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: username, password: password}, log: false});
		});
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		cy.get('body').then(($body) => {
			if ($body.find('form#login').length) {
				cy.get('form#login input[name="username"]').type(username, {delay: 0});
				cy.get('form#login input[name="password"]').type(password, {delay: 0, log: false});
				cy.get('form#login').submit();
				cy.get('form#login', {timeout: 30000}).should('not.exist');
			}
		});
	};

	// REST API calls made from the page itself, so they carry the browser's own session.
	const api = (path, options = {}) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(path, Object.assign({credentials: 'same-origin'}, options)).then((response) => {
			if (!response.ok) {
				return response.text().then((text) => {
					throw new Error(path + ' answered ' + response.status + ': ' + text.slice(0, 300));
				});
			}
			return response.json();
		}),
		{log: false, timeout: 30000}
	));

	// The website settings page on its Plugins tab (a new query string forces a load). Load it
	// once per test: loading it again while its plugin gallery request is pending stalls the
	// web server of PKP's CI; API calls and settings modals work on the page already open.
	const openPluginsTab = () => {
		cy.visit(pageUrl('management/settings/website') + '?reload=' + Date.now() + '#plugins');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.get('button[id="plugins-button"]').should('have.attr', 'aria-selected', 'true');
		waitJQuery();
	};

	// Enables the plugin in the grid when it is off (never turns it off).
	const enablePlugin = (rowName) => {
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).then(($checkbox) => {
			if (!$checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				waitJQuery();
			}
		});
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]').should('be.checked');
	};

	// Opens the settings modal from the grid, without reloading the page: a reload right
	// after saving can stall the web server of PKP's CI. The form is fetched each time.
	const openPluginSettings = (rowName, formSelector) => {
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]', {timeout: 30000}).then(($link) => {
			if (!$link.is(':visible')) {
				cy.get('tr[id$="-row-' + rowName + '"] a.show_extras').first().click();
			}
		});
		// The grid may still be animating the extras row: the link is clicked once it exists.
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]').first().click({force: true});
		waitJQuery();
		cy.window().should((win) => {
			expect(win.jQuery(formSelector).data('pkp.handler')).to.exist;
		});
	};

	// ---- end of helpers ----

	const openSettings = () => openPluginSettings(rowName, settingsForm);

	// OJS 3.4 has no language in the URL: the interface language lives in the session. A fresh
	// session picks the language, and its cookie also makes the download reach the application
	// on a site whose edge cache serves anonymous downloads.
	const download = (locale) => {
		cy.clearCookies();
		request('/index.php/' + contextPath + '/user/setLocale/' + locale + '?source=' + encodeURIComponent('/index.php/' + contextPath + '/'));
		return download34();
	};
	const download34 = () => cy.request({
		url: '/index.php/' + contextPath + '/article/download/' + galley.submissionId + '/' + galley.galleyId + '/' + galley.submissionFileId + '?cb=' + Date.now(),
		encoding: 'binary',
	}).then((response) => {
		expect(response.status).to.eq(200);
		const disposition = response.headers['content-disposition'];
		const match = /filename\*=UTF-8''([^;]+)/.exec(disposition);
		expect(match, 'Content-Disposition: ' + disposition).to.not.eq(null);
		return decodeURIComponent(match[1]);
	});

	const save = (numbersOnly, filenameLocale) => {
		cy.get(settingsForm + ' input[name="numbersOnly"][value="' + numbersOnly + '"]').check({force: true});
		cy.get(settingsForm + ' input[name="filenameLocale"][value="' + filenameLocale + '"]').check({force: true});
		cy.get(settingsForm + ' button[id^="submitFormButton-"]').click({force: true});
		waitJQuery();
		cy.get(settingsForm).should('not.exist');
	};

	const configure = (numbersOnly, filenameLocale) => {
		login(adminUser, adminPassword);
		openPluginsTab();
		openSettings();
		save(numbersOnly, filenameLocale);
	};

	it('Enables the plugin with its defaults and finds a published galley', function() {
		login(adminUser, adminPassword);
		api(pageUrl('api/v1/submissions?status=3&count=30')).then((submissions) => {
			const candidates = submissions.items.filter((item) => item.currentPublicationId);
			const look = (i) => {
				expect(i, 'a published article with a galley file').to.be.lessThan(candidates.length);
				api(pageUrl('api/v1/submissions/' + candidates[i].id + '/publications/' + candidates[i].currentPublicationId)).then((publication) => {
					const withFile = (publication.galleys || []).find((item) => item.submissionFileId);
					if (withFile) {
						galley = {submissionId: candidates[i].id, galleyId: withFile.id, submissionFileId: withFile.submissionFileId};
					} else {
						look(i + 1);
					}
				});
			};
			look(0);
		});

		api('/index.php/index/api/v1/contexts?count=100').then((contexts) => {
			const journal = contexts.items.find((item) => item.urlPath === contextPath);
			api(pageUrl('api/v1/contexts/' + journal.id)).then((context) => {
				locales = context.supportedLocales;
			});
		});

		openPluginsTab();
		enablePlugin(rowName);
		openSettings();
		cy.get(settingsForm + ' input[name="numbersOnly"]').should('have.length', 2);
		cy.get(settingsForm + ' input[name="filenameLocale"]').should('have.length', 2);
		save('0', 'user');
	});

	it('Names the file in each interface language of the journal', function() {
		expect(locales, 'languages of the journal').to.have.length.at.least(1);
		locales.forEach((locale) => {
			download(locale).then((name) => {
				expect(name).to.not.contain('##');
				if (expected[locale]) {
					expect(name).to.match(new RegExp('^' + expected[locale](galley.submissionId, galley.submissionFileId) + '\\.[a-z0-9]+(\\.gz)?$'));
				} else {
					expect(name).to.match(new RegExp('(^|\\D)' + galley.submissionId + '(\\D).*(\\D)' + galley.submissionFileId + '\\.[a-z0-9]+(\\.gz)?$'));
				}
			});
		});
	});

	it('Delivers numbers only when asked to', function() {
		configure('1', 'user');
		download(locales[locales.length - 1]).then((name) => expect(name).to.match(new RegExp('^' + galley.submissionId + '-' + galley.submissionFileId + '\\.')));
	});

	it('Uses the primary language of the journal when asked to', function() {
		configure('0', 'context');
		download(locales[0]).then((first) => {
			download(locales[locales.length - 1]).then((last) => expect(last).to.eq(first));
		});
	});

	it('Puts the defaults back', function() {
		configure('0', 'user');
	});
});
