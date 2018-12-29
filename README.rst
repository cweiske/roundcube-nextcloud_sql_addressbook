***********************************************
NextCloud SQL address book plugin for Roundcube
***********************************************

Roundcube__ plugin that allows access to NextCloud__ address books.
Uses direct database access (SQL), which is much faster than accessing the
address book entries via the `CardDAV plugin`__.

__ https://roundcube.net/
__ https://nextcloud.com/
__ https://plugins.roundcube.net/packages/roundcube/carddav


Features
========
- List all user's NextCloud address books
- Search
- Autocomplete

Missing features
----------------
- Access to address books shared by other people in NextCloud
- Access to more fields than "email" and "name" (full name)
- Updating/adding address book entries (not planned)


Prerequisites
=============
- Read-only SQL database access from Roundcube to the NextCloud database.
  Read access to the following tables needed:

  - ``oc_addressbooks``
  - ``oc_cards_properties``
- Roundcube user login e-mail addresses must equal the username in NextCloud
  (Users log in with `alice@example.org` in both Roundcube and NextCloud)
- Known to work with:
  - PHP 7.3.0
  - NextCloud 14
  - Roundcube 1.4.0


Installation
============
#. Clone the git repository into the roundcube ``plugins/`` directory as
   ``nextcloud_sql_addressbook``.
#. Copy ``config.inc.php.dist`` to ``config.inc.php`` and adjust it:

   #. Database connection
   #. Table prefix (defaults to ``oc_``)
#. Enable the plugin in roundcube's ``config/config.inc.php`` file by
   adding it to the ``$config['plugins']`` array.


Debugging
=========
If you do not see any address books:
The address books are only found if the ``principaluri`` in the ``oc_addressbooks``
table equals ``principals/users/`` + ``$useremailaddress``.

If you do not see all contacts: Only contacts with an e-mail address are shown.


Links
=====
- Git repository: https://git.cweiske.de/roundcube-nextcloud_sql_addressbook.git
- Git mirror: https://github.com/cweiske/roundcube-nextcloud_sql_addressbook
- Roundcube plugin page: https://plugins.roundcube.net/packages/cweiske/nextcloud_sql_addressbook
