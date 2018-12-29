***********************************************
NextCloud SQL address book plugin for Roundcube
***********************************************

Roundcube__ plugin that allows access to NextCloud__ address books.
Uses direct database access (SQL), which is much faster than accessing the
address book entries via CardDAV__.

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
- Read-only SQL database access from Roundcube to the NextCloud database
- Roundcube user login e-mail addresses must equal the username in NextCloud
  (Users log in with `alice@example.org` in both Roundcube and NextCloud)


Debugging
=========
If you do not see any address books:
The address books are only found if the ``principaluri`` in the ``oc_addressbooks``
table equals ``principals/users/`` + ``$useremailaddress``.
