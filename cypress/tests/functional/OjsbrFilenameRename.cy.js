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

	// The download route of a published file — article/download/… in OJS,
	// catalog/download/… in OMP — and the languages the journal or press offers.
	let download_ = null;
	let locales = [];

	// ---- OJSBR spec helpers (padrão v2): work on OJS/OMP 3.3, 3.4 and 3.5 and in PKP's CI ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');

	// Same as PKP's cy.waitJQuery(), which the support files of OJS 3.3 test sites may lack.
	// The Plugins tab can keep requests open for a while (the plugin gallery), hence the timeout.
	// jQuery may not be on the page yet when this runs, so the check retries on the window
	// itself instead of on a property that would resolve as undefined.
	const waitJQuery = () => cy.window({timeout: 60000}).should((win) => {
		expect(win.jQuery && win.jQuery.active, 'pending jQuery requests').to.eq(0);
	});

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

	// The form is only the plugin's once its PKP handler is attached: a Save clicked before
	// that submits the form natively and leaves the page for the grid's manage URL. After a
	// failed validation the modal replaces the form, so this is checked before every save.
	const waitFormHandler = (formSelector) => cy.window({timeout: 30000}).should((win) => {
		expect(win.jQuery(formSelector).data('pkp.handler'), 'form handler').to.exist;
	});

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
		waitFormHandler(formSelector);
	};

	// ---- end of helpers ----

	const openSettings = () => openPluginSettings(rowName, settingsForm);

	// A request with a session cookie always reaches the application, even on a site whose
	// edge cache serves anonymous downloads. The language goes in the URL.
	const download = (locale) => cy.request({
		url: '/index.php/' + contextPath + '/' + locale + '/' + download_.path + '?cb=' + Date.now(),
		headers: {Cookie: 'OJSSID=cypress' + Date.now() + Math.random().toString(36).slice(2)},
		encoding: 'binary',
	}).then((response) => {
		expect(response.status).to.eq(200);
		const disposition = response.headers['content-disposition'];
		const match = /filename\*=UTF-8''([^;]+)/.exec(disposition);
		expect(match, 'Content-Disposition: ' + disposition).to.not.eq(null);
		return decodeURIComponent(match[1]);
	});

	const save = (numbersOnly, filenameLocale) => {
		waitFormHandler(settingsForm);
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

	it('Enables the plugin with its defaults and finds a published file', function() {
		login(adminUser, adminPassword);
		api(pageUrl('api/v1/submissions?status=3&count=30')).then((submissions) => {
			const candidates = submissions.items.filter((item) => item.currentPublicationId);
			const look = (i) => {
				expect(i, 'a published submission with a file to download').to.be.lessThan(candidates.length);
				api(pageUrl('api/v1/submissions/' + candidates[i].id + '/publications/' + candidates[i].currentPublicationId)).then((publication) => {
					// In OJS the galley of the API already names the file; in OMP the
					// publication format does not, so the published page is read for the
					// link the reader clicks (catalog/view/{book}/{format}/{file}).
					const galley = (publication.galleys || []).find((item) => item.submissionFileId);
					if (galley) {
						download_ = {
							path: 'article/download/' + candidates[i].id + '/' + galley.id + '/' + galley.submissionFileId,
							submissionId: String(candidates[i].id),
							submissionFileId: String(galley.submissionFileId),
						};
						return;
					}
					request({url: publication.urlPublished, failOnStatusCode: false}).then((page) => {
						const match = /((?:article|catalog)\/(?:view|download)\/(\d+)\/\d+\/(\d+))/.exec(page.body || '');
						if (match) {
							download_ = {
								path: match[1].replace('/view/', '/download/'),
								submissionId: match[2],
								submissionFileId: match[3],
							};
						} else {
							look(i + 1);
						}
					});
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
					expect(name).to.match(new RegExp('^' + expected[locale](download_.submissionId, download_.submissionFileId) + '\\.[a-z0-9]+(\\.gz)?$'));
				} else {
					expect(name).to.match(new RegExp('(^|\\D)' + download_.submissionId + '(\\D).*(\\D)' + download_.submissionFileId + '\\.[a-z0-9]+(\\.gz)?$'));
				}
			});
		});
	});

	it('Delivers numbers only when asked to', function() {
		configure('1', 'user');
		download(locales[locales.length - 1]).then((name) => expect(name).to.match(new RegExp('^' + download_.submissionId + '-' + download_.submissionFileId + '\\.')));
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
