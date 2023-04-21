<?php
require_once __DIR__ . '/nextcloud_sql_addressbook_backend.php';

/**
 * Make a user's Nextcloud address books available in Roundcube.
 *
 * Directly accesses the database, which is much faster than using CardDAV.
 *
 * @author  Christian Weiske <cweiske@cweiske.de>
 * @license AGPLv3+ http://www.gnu.org/licenses/agpl.html
 */
class nextcloud_sql_addressbook extends rcube_plugin
{
    /**
     * Main roundcube instance
     *
     * @var rcube
     */
    protected $rcube;

    /**
     * Database table prefix
     *
     * @var string
     */
    protected $prefix = 'oc_';

    /**
     * Database instance
     */
    protected $db;

    /**
     * Initialization method, needs to be implemented by the plugin itself
     *
     * @return void
     */
    public function init()
    {
        $this->load_config();
        $this->add_hook('addressbooks_list', [$this, 'addressbooks_list']);
        $this->add_hook('addressbook_get', [$this, 'addressbook_get']);

        $this->rcube = rcube::get_instance();

        $this->db = rcube_db::factory(
            $this->rcube->config->get('nextcloud_sql_addressbook_dsn')
        );
        $this->db->set_debug((bool) $this->rcube->config->get('sql_debug'));

        $this->prefix = $this->rcube->config->get(
            'nextcloud_sql_addressbook_dbtableprefix', 'oc_'
        );

        // use this address books for autocompletion queries
        $config = rcmail::get_instance()->config;
        $sources = (array) $config->get(
            'autocomplete_addressbooks', array('sql')
        );
        foreach ($this->listAddressbooks() as $addressBook) {
            if (!in_array($addressBook['id'], $sources)) {
                $sources[] = $addressBook['id'];
            }
        }
        $config->set('autocomplete_addressbooks', $sources);
    }

    /**
     * Load the nextcloud address book names
     *
     * The "id" may not contain any "-" because that would break "_cid",
     * the "contact IDs" which are "$contactid-$addressbookid".
     * See rcmail_get_cids()
     *
     * @param array $arguments Unknown data, with a "sources" key that we have
     *                         to modify
     *
     * @return array Arguments with our address books added to the "sources" key
     */
    public function addressbooks_list($arguments)
    {
        $arguments['sources'] = array_merge(
            $arguments['sources'], $this->listAddressbooks()
        );
        return $arguments;
    }

    /**
     * Build a list of address books for the user
     *
     * @return array Array of arrays with the following keys:
     *               id, name, groups, readonly, undelete, autocomplete
     */
    protected function listAddressbooks()
    {
        if (!isset($this->rcube->user->data)) {
            return [];
        }

        $principalUri = 'principals/users/'
            . $this->rcube->user->data['username'];

        $sql = 'SELECT id, displayname'
             . ' FROM ' . $this->prefix . 'addressbooks'
             . ' WHERE principaluri = ?'
             . ' ORDER BY displayname';
        $stmt = $this->db->query($sql, [$principalUri]);
        $addressBooks = [];
        foreach ($stmt as $row) {
            $addressBooks[] = [
                'id'           => 'nextcloud_' . $row['id'],
                'name'         => $row['displayname'] . ' (Nextcloud)',
                'groups'       => false,
                'readonly'     => true,
                'undelete'     => false,
                'autocomplete' => true,
            ];
        }
        return $addressBooks;
    }

    /**
     * Return a adress book object for the given address book ID
     *
     * @param array $arguments Some data with an "id" key that contains the
     *                         address book ID
     *
     * @return array $arguments with added "instance" key
     */
    public function addressbook_get($arguments)
    {
        $parts = explode('_', $arguments['id'], 2);
        if (count($parts) != 2 || $parts[0] != 'nextcloud') {
            return $arguments;
        }

        $id = $parts[1];
        //FIXME: security check if this ID really belongs to the user

        $arguments['instance'] = new nextcloud_sql_addressbook_backend(
            $id, $this->db, $this->prefix
        );

        return $arguments;
    }
}
?>
