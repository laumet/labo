LABO installation notes
=======================

Database configuration
----------------------

The application connects to MySQL from core/connect_db.php. The connection
does not contain database credentials in the PHP source. It reads these
environment variables:

	DB_HOST    MySQL server hostname
	DB_NAME    Database name
	DB_USER    Database username
	DB_PASS    Database password

Configure these variables in the hosting control panel or in the server
configuration. For Apache, the virtual host configuration can contain:

	SetEnv DB_HOST "your-database-host"
	SetEnv DB_NAME "your-database-name"
	SetEnv DB_USER "your-database-user"
	SetEnv DB_PASS "your-database-password"

For PHP-FPM, use the pool configuration instead:

	env[DB_HOST] = your-database-host
	env[DB_NAME] = your-database-name
	env[DB_USER] = your-database-user
	env[DB_PASS] = your-database-password

Restart or reload the relevant web service after changing its configuration.
The exact location and interface depend on the hosting provider.

Localhost setup (Ubuntu 24.04, Apache, MariaDB 10.11)
-------------------------------------------------------

This is the local setup prepared for development. Apache uses mod_php on this
machine, so do not create or configure a PHP-FPM directory for this setup.

1. Generate a strong password in a terminal:

	openssl rand -hex 32

Keep the generated value available for the database and Apache steps below.
Do not paste it into this README or commit it to the project.

2. Open a MariaDB administrator session:

	sudo mariadb

Create the database and dedicated application account, replacing
PASTE_GENERATED_PASSWORD with the password generated above:

	CREATE DATABASE IF NOT EXISTS `LABO`
	  CHARACTER SET utf8mb4
	  COLLATE utf8mb4_unicode_ci;

	CREATE USER 'labo_app'@'localhost'
	  IDENTIFIED BY 'PASTE_GENERATED_PASSWORD';

	GRANT ALL PRIVILEGES ON `LABO`.* TO 'labo_app'@'localhost';

These commands are for the initial setup. If the user already exists, change
its password with ALTER USER instead of running CREATE USER again. No FLUSH
PRIVILEGES is required after CREATE USER or GRANT.

The utf8mb4_unicode_ci collation is used because MariaDB 10.11 does not
provide MySQL's utf8mb4_0900_ai_ci collation.

Folder layout
-------------

LABO has exactly three entry points, all in the project root:

	index.php       html pages: the LABO application (modules/labo_page.php)
	                for logged-in users, otherwise the login page
	                (account/account_page.php)
	labo_swap.php   ajax requests (POST, action in to_do): the account
	                actions listed in LABO_ACCOUNT_ACTIONS and set_lang are
	                public, every other action requires a logged-in user
	                (account/account_actions.php, inc/set_lang.php,
	                modules/labo_actions.php)
	file_swap.php   files, logged-in users only (modules/file_download.php)

The root .htaccess refuses every other PHP file, so all other PHP files are
included by an entry point and are never called from the web. Rules:
- a new ajax action goes into labo_swap.php: in the public list only if it
  must work without login, otherwise in modules/labo_actions.php;
- never name a file index.php, labo_swap.php or file_swap.php in a sub-folder;
- build links with labo_url() in PHP and labRootUrl, labSwapUrl and
  labFileUrl in JS, never with paths relative to the current page;
- private folders (core/, default/, inc/, logs/, cache/, tmp folders) also
  have their own .htaccess with "Require all denied" (Apache 2.4 syntax).

Account pages live in account/, the LABO application in modules/, and shared
site helpers and assets copied from RMR/Site live in inc/, css/, default/,
img/, and js/. The module database include at modules/inc/connect_db.php
loads core/connect_db.php; do not copy the old RMR database connection file,
because it contains production credentials. The LABO plugins already in
plugins/ are retained; the RMR-specific plugin was not copied.

3. Edit the active Apache virtual host:

	sudoedit /etc/apache2/sites-available/000-default.conf

This machine's active localhost virtual host is 000-default.conf. Put these
directives inside its existing <VirtualHost *:80> block, replacing the
password placeholder with the same generated password:

	SetEnv DB_HOST "localhost"
	SetEnv DB_NAME "LABO"
	SetEnv DB_USER "labo_app"
	SetEnv DB_PASS "PASTE_GENERATED_PASSWORD"

Save the file, then validate and reload Apache:

	sudo apachectl configtest
	sudo systemctl reload apache2

Proceed only if configtest reports Syntax OK. The project must be served by
this Apache virtual host for these variables to be available to its PHP code.
On this machine Apache's current DocumentRoot is /var/www/html, while the
workspace is under /home/laurent/kDrive/WWW/html/LABO. Configure an Apache
Alias/virtual host or a suitable link before expecting localhost to serve this
workspace; the PHP code being present on disk does not make it web-accessible.

Apache runs as www-data on this host. Make the logs directory writable by that
account without making it world-writable:

	sudo chgrp www-data logs
	sudo chmod 770 logs

4. Test the application through http://localhost. If the connection fails,
check logs/database.log and /var/log/apache2/error.log. Do not create a
public PHP page that prints environment variables or database credentials.

Important: PHP cannot permanently set server environment variables with
putenv(). Any values set with putenv() apply only to the current request.
Do not use a publicly accessible installer to collect or store the database
password.

5. Before the first opening, choose the data management mode in
default/labo_settings.json (see "Data management mode" below). The default is
single_generator_per_page.

6. First opening: create the first administrator. While the users table does
not exist, the login page accepts only the installation account:

	user name: admin
	password:  aaBB11%%

This opens the create account form (no e-mail check, no school domain
check). Enter your first name, last name, e-mail address and a password. When
you save this form, the basis tables are created (account/inc/first_setup.php):

	base       model for new tables (CREATE TABLE new_table LIKE base)
	generator  LABO blocs; first lines: GENERATOR (edit this table),
	           Statuts (edit the roles), Utilisateurs (list of the users)
	roles      first line: super-admin (sa, level 100)
	users      created last; your account gets roles "sa", max_level 100

As soon as the users table exists, the installation account no longer works.
If you stop before saving, nothing is created and the installation account
still works. If saving fails halfway, saving again completes the missing
tables without duplicating their first lines.

The installation password is stored only as a hash in
account/inc/first_setup.php. Because it is written in this README, finish the
first opening as soon as the database is ready, especially on a public
server: until then, anyone who knows it can create the first administrator.

Two-factor authentication is off for this first administrator
(two_factors_authentication = 0), so a mail server is not needed to log in.
Recommendation: once sending mail works (or the Microsoft sign-in is in
place), switch it on for every administrator account:

	UPDATE users SET two_factors_authentication = 1 WHERE roles = 'sa';

Note: the super-admin role has two_factors_authentication = 1, and editing a
user in LABO copies the value of its roles to the user. Editing your account
in LABO therefore switches two-factor authentication on: check that mail
sending works before doing so.

Data management mode
--------------------

LABO builds its menu and its pages in one of two modes. The mode is set in
default/labo_settings.json BEFORE the first opening:

	{ "data_management_mode": "single_generator_per_page" }

	single_generator_per_page  (default) one line of the generator table is
	                           one menu entry, shown to the role of its
	                           user_type (the original LABO behaviour)
	page_with_modules          a page (table pages) groups one or more lines
	                           of the generator table (modules); the menu
	                           shows one entry per page, and each role sees
	                           only the modules it is allowed to view

The first opening saves the mode in the table labo_settings. From then on the
mode is fixed: if the file and the saved mode differ, LABO refuses to work
(message data_mode_mismatch) instead of guessing. The mode is never inferred
from the content of the database. A missing file or value means
single_generator_per_page; an unknown value is refused (data_mode_invalid).
Installations made before this setting existed have no labo_settings table:
they run as single_generator_per_page, and page_with_modules is refused until
they are migrated (data_mode_not_installed).

Tables created by the first opening (account/inc/first_setup.php):

	both modes          base, labo_settings, generator, roles, users
	page_with_modules   also pages, page_modules, module_permissions

	pages               id, slug (unique), title, tab_order, enabled
	page_modules        page_id -> pages, generator_id -> generator,
	                    position, title (optional, replaces the generator
	                    title on the page); unique (page_id, generator_id)
	module_permissions  role_id -> roles, generator_id -> generator,
	                    can_view, can_edit, can_add, can_delete;
	                    unique (role_id, generator_id)

All tables use utf8mb4 / utf8mb4_unicode_ci and the base columns (id, dates,
logical_delete, can_not_be_deleted). The first opening only uses CREATE TABLE
IF NOT EXISTS and INSERT IGNORE: running it again never drops or overwrites
data. In page_with_modules it also creates the page "Administration" with the
modules GENERATOR, Statuts, Utilisateurs, Pages, Modules des pages and
Permissions des modules, with all rights for the role sa.

Rights in page_with_modules (modules/inc/page_modules.php):
- a page is in the menu if one of the user's roles can view one of its modules;
- opening a page shows only the modules the user can view; with several
  modules, each one is a tab (same headertab tabs as form_complement
  headertabs), and the open tab is remembered for the page;
- can_edit and can_add: one of the user's roles has the right in
  module_permissions AND the generator line allows it (can_edit / can_add);
- can_delete: one of the user's roles has the right => the can_delete value of
  the generator line (1 delete, 2 logical delete);
- the user's real roles are used: sa or ad get no implicit right, every right
  is a line of module_permissions;
- these rights are checked on the server for every read and write
  (modules/inc/check_hability.php, defineContent, defineRow, defineEmptyRow,
  save_row.php, delete_row.php, restore_row.php); hiding something in the page
  is not an authorization;
- module rights don't give access to every row or column: the rows stay limited
  by the request of the generator line (request_where) and the columns by its
  display.

"See as" (levels 90 and more) is a preview: it changes the data shown, never
the rights. Rights always come from the logged-in user, and a row saved during
a preview is logged (logs/audit.log) with the id of the logged-in user and the
id of the previewed user.

Migrating an existing installation to page_with_modules (manual, explicit,
nothing is reinterpreted automatically):

1. Back up the database (mysqldump).
2. Create the tables labo_settings, pages, page_modules and module_permissions
   with the CREATE TABLE statements of labo_basis_tables_sql() in
   account/inc/first_setup.php (they use IF NOT EXISTS).
3. Create the pages and choose which generator lines go on which page:

	INSERT INTO pages (slug, title, tab_order) VALUES ('students', 'Students', 1);
	INSERT INTO page_modules (page_id, generator_id, position) VALUES (1, 12, 1), (1, 15, 2);

4. Give the rights. To keep the rights of single_generator_per_page (a role
   sees the generator lines whose user_type is its short name):

	INSERT IGNORE INTO module_permissions (role_id, generator_id, can_view, can_edit, can_add, can_delete)
	SELECT r.id, g.id, 1, g.can_edit, g.can_add, IF(g.can_delete > 0, 1, 0)
	FROM generator AS g INNER JOIN roles AS r ON r.role_short_name = g.user_type
	WHERE g.page = 'labo' AND g.logical_delete = 0;

   In single_generator_per_page, sa and ad also saw the lines of the other
   roles: add their lines to module_permissions if they must keep this.
5. Save the mode, then change default/labo_settings.json to the same value
   (in between, LABO refuses to work with data_mode_mismatch):

	INSERT INTO labo_settings (can_not_be_deleted, setting_key, setting_value)
	VALUES (1, 'data_management_mode', 'page_with_modules');

To go back, set both values to single_generator_per_page; the page tables can
stay, they are not used in this mode.

Tests
-----

	php tests/run_tests.php

Runs without database (the installer is tested against a fake database): modes
and their resolution, tables chosen by the installer, menu grouping, permission
checks. The tests folder is refused from the web.

	LABO_TEST_DB_HOST=127.0.0.1 LABO_TEST_DB_NAME=LABO_..._TEST \
	LABO_TEST_DB_USER=... LABO_TEST_DB_PASS=... php tests/run_db_tests.php

Runs the installer of both modes against a real MariaDB TEST database (its
name must contain TEST; its LABO tables are emptied before and after): tables,
foreign keys, rerun safety, mode protection, menu and rights, and the manual
migration above. Add LABO_TEST_DB_READONLY=LABO to also read the mode of an
existing installation (read only). Never point LABO_TEST_DB_NAME to a real
database; give the credentials only as environment variables.

Logs
----

Application events are written as JSON Lines, with one JSON object per line.
The current database connection failures are written to:

	logs/database.log

PHP's native errors are written to:

	logs/php_errors.log

Application code can write a categorized event by including core/logger.php
and calling labo_log() with an allowed channel:

	require_once __DIR__ . '/core/logger.php';
	labo_log('security', 'Rejected request.', array('reason' => 'invalid token'));

Allowed channels are core, database, security, audit, auth, and plugin. Each
channel writes to logs/<channel>.log. Entries include a timestamp and request
identifier. Add context only when it is safe and useful for diagnosis.

Never log passwords, access tokens, session identifiers, full request bodies,
or sensitive personal/medical data. Prefer internal record IDs and event
metadata. Restrict filesystem access to the PHP/web-server user and trusted
administrators. The PHP/web-server user must have write permission to logs/.

The supplied logs/.htaccess denies direct access when Apache honors per-folder
access files. Nginx does not read .htaccess; configure an equivalent deny rule
in the Nginx server configuration. For production, storing logs outside the
public web root is safer. Rotate logs and define an appropriate retention
period so files cannot grow indefinitely.

Generated *.log files are excluded by logs/.gitignore; do not commit logs or
credentials to the repository.

Security
--------

Never commit database credentials, logs, or server configuration files to the
repository. Rotate any password that was previously stored in source code.
Use a separate database user with only the permissions required by LABO.

Testing
-------

Check the PHP file syntax from the LABO directory:

	php -l core/connect_db.php

Then test the application through the web server. A missing variable or
failed connection returns a generic error to the visitor; the event is
written to logs/database.log. Native PHP errors go to logs/php_errors.log.
