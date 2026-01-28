<?php
/**
 * Address book backend accessing the NextCloud database directly.
 *
 * Read-only.
 *
 * Only returns two fields: email and name (Full name).
 *
 * @author  Christian Weiske <cweiske@cweiske.de>
 * @license AGPLv3+ http://www.gnu.org/licenses/agpl.html
 */
class nextcloud_sql_addressbook_backend extends rcube_addressbook
{
    /**
     * Nextcloud address book ID
     *
     * @var integer
     */
    protected $abId;

    /**
     * Database connection to the NextCloud database
     *
     * @var rcube_db
     */
    protected $db;

    /**
     * Database table prefix
     *
     * @var string
     */
    protected $prefix = 'oc_';

    /**
     * Result of the last operation
     *
     * @var rcube_result_set
     */
    protected $result;

    /**
     * Stored SQL filter to limit record list
     *
     * @var string
     */
    protected $filter  = null;

    /**
     * Set required parameters
     *
     * @param int      $abId   Addressbook ID (oc_addressbooks.id)
     * @param rcube_db $db     Connection to the NextCloud database
     * @param string   $prefix Database table prefix
     */
    public function __construct($abId, $db, $prefix)
    {
        $this->abId   = $abId;
        $this->db     = $db;
        $this->prefix = $prefix;
    }

    /**
     * Get the title of this address book
     *
     * Used in contact details view.
     *
     * @return string Address book name
     */
    public function get_name()
    {
        $sql = 'SELECT displayname'
             . ' FROM ' . $this->prefix . 'addressbooks'
             . ' WHERE id = ?';
        $stmt = $this->db->query($sql, $this->abId);
        $row = $this->db->fetch_assoc($stmt);

        return $row['displayname'] . ' (Nextcloud)';
    }

    /**
     * Save a search string for future listings.
     *
     * Needed to share the filter between search(), list_records() and count().
     *
     * @param string $filter Part of the SQL statement used to filter contacts
     *
     * @return void
     */
    public function set_search_set($filter)
    {
        $this->filter = $filter;
    }

    /**
     * Getter for saved search properties
     *
     * @return string Filtering part of the contact-fetching SQL statement
     */
    public function get_search_set()
    {
        return $this->filter;
    }

    /**
     * Reset saved results and search parameters
     *
     * @return void
     */
    public function reset()
    {
        $this->result = null;
        $this->filter = null;
        $this->cache  = null;
    }

    /**
     * List the current set of contact records
     *
     * @param array   $cols    List of cols to show, NULL means all
     *                         Known values:
     *                         - name
     *                         - firstname
     *                         - surname
     *                         - email
     * @param int     $subset  Only return this number of records,
     *                         use negative values for tail
     * @param boolean $nocount Do not calculate the number of all records
     *
     * @return rcube_result_set
     *
     * @internal Paging information is stored in $this->list_page
     *           and starts with 1
     */
    public function list_records($cols = null, $subset = 0, $nocount = false)
    {
        $this->result = new rcube_result_set();

        $sql = <<<SQL
SELECT
    p_email.cardid AS id,
    GROUP_CONCAT(p_email.value SEPARATOR ",") AS email,
    p_name.value AS name
FROM
    %PREFIX%cards_properties AS p_email
    JOIN %PREFIX%cards_properties AS p_name
        ON p_name.cardid = p_email.cardid
            AND p_name.name = "FN"
WHERE
    p_email.addressbookid = ?
    AND p_email.name = "EMAIL"
    %FILTER%
GROUP BY p_email.cardid
ORDER BY name, email
SQL;

        $sql = str_replace(
            '%FILTER%',
            $this->filter ? ' AND ' . $this->filter : '',
            $this->replaceTablePrefix($sql)
        );

        $firstRecord = $this->list_page * $this->page_size - $this->page_size;
        $stmt = $this->db->limitquery(
            $sql,
            $firstRecord, $this->page_size,
            $this->abId
        );
        foreach ($stmt as $row) {
            $this->result->add(
                [
                    'ID'    => $row['id'],
                    'name'  => $row['name'],
                    'email' => $row['email'],
                ]
            );
        }

        if ($nocount) {
            //do not fetch the numer of all records
            $this->result->count = count($this->result->records);
        } else {
            $this->result->count = $this->count()->count;
        }

        return $this->result;
    }

    /**
     * Search records
     *
     * @param array|string $fields   One or more field names to search in. Examples:
     *                               - '*'
     *                               - 'ID'
     * @param array|string $value    Search value
     * @param int          $mode     Search mode. Sum of self::SEARCH_*.
     * @param boolean      $select   False: only count records; do not select them
     * @param boolean      $nocount  True to not calculate the total record count
     * @param array        $required List of fields that cannot be empty
     *
     * @return rcube_result_set List of contact records and 'count' value
     */
    public function search(
        $fields, $value, $mode = 0, $select = true,
        $nocount = false, $required = []
    ) {
        $where = $this->buildSearchQuery($fields, $value, $mode);
        if (empty($where)) {
            return new rcube_result_set();
        }

        $this->set_search_set($where);
        if ($select) {
            return $this->list_records(null, 0, $nocount);
        } else {
            return $this->count();
        }
    }

    /**
     * Build an SQL WHERE clause to search for $value
     *
     * TODO: We do not support space-separated search words yet
     *
     * @param array|string $fields One or more field names to search in.
     *                             Examples:
     *                             - '*'
     *                             - 'ID'
     * @param array|string $value  Search value
     * @param int          $mode   Search mode. Sum of self::SEARCH_*.
     *
     * @return string Part of an SQL query, but without the prefixed " AND "
     */
    protected function buildSearchQuery($fields, $value, $mode)
    {
        if ($fields === 'ID') {
            return 'p_email.cardid = ' . intval($value);

        } else if ($fields === '*') {
            return '('
                . $this->buildSearchQueryField('name', $value, $mode)
                . ' OR '
                . $this->buildSearchQueryField('email', $value, $mode)
                . ')';
        }

        $fields = (array) $fields;
        $sqlParts = [];
        foreach ($fields as $field) {
            if ($field != 'name' && $field != 'email') {
                continue;
            }

            $sqlParts[] = $this->buildSearchQueryField($field, $value, $mode);
        }
        return '(' . implode(' OR ', $sqlParts) . ')';
    }

    /**
     * Build a search SQL for a single field
     *
     * @param string       $field Field name. Examples:
     *                            - '*'
     *                            - 'ID'
     * @param array|string $value Search value
     * @param int          $mode  Search mode. Sum of self::SEARCH_*.
     *
     * @return string Part of an SQL query
     */
    protected function buildSearchQueryField($field, $value, $mode)
    {
        $sqlField = 'p_' . $field . '.value';

        if ($mode & self::SEARCH_STRICT) {
            //exact match
            return $sqlField . ' = ' . $this->db->quote($value);

        } else if ($mode & self::SEARCH_PREFIX) {
            return $this->db->ilike($sqlField, $value . '%');
        }

        return $this->db->ilike($sqlField, '%' . $value . '%');
    }

    /**
     * Count number of available contacts in database
     *
     * @return rcube_result_set Result set with values for 'count' and 'first'
     */
    public function count()
    {
        $count = isset($this->cache['count'])
            ? $this->cache['count']
            : $this->_count();

        return new rcube_result_set(
            $count, ($this->list_page - 1) * $this->page_size
        );
    }

    /**
     * Count number of available contacts in database
     *
     * @return int Contacts count
     */
    protected function _count()
    {
        $sql = <<<SQL
SELECT COUNT(DISTINCT p_name.cardid) AS cnt
FROM
    %PREFIX%cards_properties AS p_email
    JOIN %PREFIX%cards_properties AS p_name
        ON p_name.cardid = p_email.cardid
            AND p_name.name = "FN"
WHERE
    p_email.addressbookid = ?
    AND p_email.name = "EMAIL"
    %FILTER%
SQL;

        $sql = str_replace(
            '%FILTER%',
            $this->filter ? ' AND ' . $this->filter : '',
            $this->replaceTablePrefix($sql)
        );

        $stmt = $this->db->query($sql, $this->abId);
        $row = $this->db->fetch_assoc($stmt);

        $this->cache['count'] = (int) $row['cnt'];
        return $this->cache['count'];
    }

    /**
     * Return the last result set
     *
     * @return rcube_result_set Current result set or NULL if nothing selected yet
     */
    public function get_result()
    {
        return $this->result;
    }

    /**
     * Get a specific contact record
     *
     * @param mixed   $id    Record identifier
     * @param boolean $assoc True to return record as associative array.
     *                       False: a result set is returned
     *
     * @return rcube_result_set|array|null Result object with all record fields
     *                                     NULL when it does not exist/
     *                                     is not accessible
     */
    public function get_record($id, $assoc = false)
    {
        $sql = <<<SQL
SELECT
    p_email.cardid AS id,
    GROUP_CONCAT(p_email.value SEPARATOR ",") AS email,
    p_name.value AS name
FROM
    %PREFIX%cards_properties AS p_email
    JOIN %PREFIX%cards_properties AS p_name
        ON p_name.cardid = p_email.cardid
            AND p_name.name = "FN"
WHERE
    p_email.addressbookid = ?
    AND p_email.cardid = ?
    AND p_email.name = "EMAIL"
GROUP BY p_email.cardid
ORDER BY name, email
SQL;

        $stmt = $this->db->query(
            $this->replaceTablePrefix($sql),
            $this->abId, $id
        );
        $row = $this->db->fetch_assoc($stmt);

        if ($row === false) {
            return null;
        }

        $this->result = new rcube_result_set(1);
        $this->result->add(
            [
                'ID' => $row['id'],
                'name'  => $row['name'],
                'email' => explode(',', $row['email']),
            ]
        );

        return $assoc ? $this->result->first() : $this->result;
    }

    /**
     * Replace the %PREFIX% variable in SQL queries with the configured
     * NextCloud table prefix
     *
     * @param string $sql SQL query with %PREFIX% variables
     *
     * @return string Working SQL query
     */
    protected function replaceTablePrefix($sql)
    {
        return str_replace('%PREFIX%', $this->prefix, $sql);
    }
}
?>
