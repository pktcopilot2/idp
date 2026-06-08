<?php

namespace App\Helpers;

class LdapHelper
{
    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function ldapConfig(): array
    {
        return config('app.ldap');
    }

    /**
     * Open and authenticate an LDAP connection.
     * Returns the connection resource/object, or false on failure.
     */
    private static function ldapConnect(): mixed
    {
        if (!self::ldapConfig()['enabled']) {
            return false;
        }

        $cfg  = self::ldapConfig();
        $conn = ldap_connect($cfg['host'], $cfg['port']);
        if (!$conn) {
            return false;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        if (!@ldap_bind($conn, $cfg['dn'], $cfg['pass'])) {
            ldap_close($conn);
            return false;
        }

        return $conn;
    }

    /**
     * Format a raw LDAP entry into a normalized user array.
     */
    private static function formatEntry(array $entry): array
    {
        $emails = [];
        for ($i = 0; $i < ($entry['mail']['count'] ?? 0); $i++) {
            $emails[] = $entry['mail'][$i];
        }

        $npk_aliases = array_map(fn($mail) => explode('@', $mail)[0], $emails);

        $address = [
            'street'      => $entry['street'][0] ?? null,
            'city'        => $entry['l'][0] ?? null,
            'state'       => $entry['st'][0] ?? null,
            'postal_code' => $entry['postalcode'][0] ?? null,
            'country'     => $entry['co'][0] ?? null,
        ];

        $address_parts = array_filter(array_values($address));

        return [
            'npk'                 => $entry['uid'][0] ?? null,
            'name'                => $entry['cn'][0] ?? null,
            'display_name'        => $entry['displayname'][0] ?? null,
            'first_name'          => $entry['givenname'][0] ?? null,
            'last_name'           => $entry['sn'][0] ?? null,
            'email'               => $entry['mail'][0] ?? null,
            'emails'              => $emails,
            'npk_aliases'         => $npk_aliases,
            'phone'               => $entry['telephonenumber'][0] ?? null,
            'mobile'              => $entry['mobile'][0] ?? null,
            'position'            => $entry['title'][0] ?? null,
            'department'          => $entry['department'][0] ?? null,
            'employee_number'     => $entry['employeenumber'][0] ?? null,
            'organizational_unit' => $entry['ou'][0] ?? null,
            'account_status'      => $entry['zimbraaccountstatus'][0] ?? null,
            'address'             => $address,
            'full_address'        => $address_parts ? implode(', ', $address_parts) : null,
            'description'         => $entry['description'][0] ?? null,
        ];
    }

    /**
     * Shared alias resolver for getUserAliases / getUserAliasesOrNull.
     */
    private static function resolveAliases(string|array $npk, bool $nullIfNotFound = false): mixed
    {
        $npks       = is_array($npk) ? $npk : [$npk];
        $result_map = [];

        foreach ($npks as $n) {
            $result_map[$n] = $nullIfNotFound ? null : [$n];
        }

        $conn = self::ldapConnect();
        if (!$conn) {
            return is_array($npk) ? $result_map : $result_map[$npk];
        }

        $mail_filters = [];
        $username_map = [];

        foreach ($npks as $n) {
            $email                        = self::concat_with_pkt_email($n);
            $mail_filters[]               = '(mail=' . ldap_escape($email, '', LDAP_ESCAPE_FILTER) . ')';
            $username_map[strtolower($email)] = $n;
        }

        $filter  = '(|' . implode('', $mail_filters) . ')';
        $result  = @ldap_search($conn, self::ldapConfig()['tree'], $filter, ['mail']);
        $entries = $result ? @ldap_get_entries($conn, $result) : null;

        for ($i = 0; $i < ($entries['count'] ?? 0); $i++) {
            if (!isset($entries[$i]['mail'])) {
                continue;
            }

            $all_emails = [];
            for ($j = 0; $j < $entries[$i]['mail']['count']; $j++) {
                $all_emails[] = strtolower($entries[$i]['mail'][$j]);
            }

            $prefixes = array_map(fn($m) => explode('@', $m)[0], $all_emails);

            foreach ($all_emails as $mail) {
                if (isset($username_map[$mail])) {
                    $result_map[$username_map[$mail]] = $prefixes;
                    break;
                }
            }
        }

        ldap_close($conn);

        return is_array($npk) ? $result_map : $result_map[$npk];
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Get user aliases from LDAP based on NPK (username).
     * Falls back to returning the NPK itself when not found in LDAP.
     *
     * @param string|array $npk Single NPK or array of NPKs
     * @return array
     */
    public static function getUserAliases($npk)
    {
        return self::resolveAliases($npk, false);
    }

    /**
     * Get user aliases from LDAP based on NPK (username).
     * Returns null for NPKs not found in LDAP.
     *
     * @param string|array $npk Single NPK or array of NPKs
     * @return array|null
     */
    public static function getUserAliasesOrNull($npk)
    {
        return self::resolveAliases($npk, true);
    }

    /**
     * Add a new alias to an existing LDAP user.
     *
     * @param string $username The primary NPK/username
     * @param string $new_alias The new alias to add (with or without @domain)
     * @return array Returns array with 'success' boolean and 'message' string
     */
    public static function addAliasToLdap($username, $new_alias)
    {
        if (empty($username) || empty($new_alias)) {
            return ['success' => false, 'message' => 'Username and new alias are required'];
        }

        $cfg             = self::ldapConfig();
        $new_alias       = str_replace('@pupukkaltim.com', '', trim($new_alias));
        $new_alias_email = self::concat_with_pkt_email($new_alias);

        $conn = ldap_connect($cfg['host'], $cfg['port']);
        if (!$conn) {
            return ['success' => false, 'message' => 'Failed to connect to LDAP server'];
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        if (!@ldap_bind($conn, $cfg['dn'], $cfg['pass'])) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to authenticate with LDAP server'];
        }

        $email  = self::concat_with_pkt_email($username);
        $result = @ldap_search($conn, $cfg['tree'],
            '(mail=' . ldap_escape($email, '', LDAP_ESCAPE_FILTER) . ')',
            ['mail', 'dn', 'zimbraMailAlias']
        );

        if (!$result) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to search for user in LDAP'];
        }

        $entries = @ldap_get_entries($conn, $result);
        if (!$entries || $entries['count'] === 0) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'User not found in LDAP'];
        }

        $entry    = $entries[0];
        $user_dn  = $entry['dn'];

        $current_emails = [];
        for ($i = 0; $i < ($entry['mail']['count'] ?? 0); $i++) {
            $current_emails[] = strtolower($entry['mail'][$i]);
        }

        $current_zimbra_aliases = [];
        for ($i = 0; $i < ($entry['zimbramailalias']['count'] ?? 0); $i++) {
            $current_zimbra_aliases[] = strtolower($entry['zimbramailalias'][$i]);
        }

        if (in_array(strtolower($new_alias_email), $current_emails)) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Alias already exists for this user', 'current_aliases' => $current_emails];
        }

        // Check if alias is already used by another user
        $check_filter = '(|(mail=' . ldap_escape($new_alias_email, '', LDAP_ESCAPE_FILTER) . ')'
            . '(zimbraMailAlias=' . ldap_escape($new_alias_email, '', LDAP_ESCAPE_FILTER) . '))';
        $check_result = @ldap_search($conn, $cfg['tree'], $check_filter, ['dn']);
        if ($check_result) {
            $check_entries = @ldap_get_entries($conn, $check_result);
            if ($check_entries && $check_entries['count'] > 0 && $check_entries[0]['dn'] !== $user_dn) {
                ldap_close($conn);
                return ['success' => false, 'message' => 'Alias is already used by another user'];
            }
        }

        if (!@ldap_mod_add($conn, $user_dn, ['mail' => $new_alias_email])) {
            $error = ldap_error($conn);
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to add mail alias: ' . $error];
        }

        if (!@ldap_mod_add($conn, $user_dn, ['zimbraMailAlias' => $new_alias_email])) {
            @ldap_mod_del($conn, $user_dn, ['mail' => $new_alias_email]); // rollback
            $error = ldap_error($conn);
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to add Zimbra mail alias: ' . $error];
        }

        ldap_close($conn);

        return [
            'success'        => true,
            'message'        => 'Alias added successfully and can be used for login',
            'new_alias'      => $new_alias_email,
            'all_aliases'    => array_merge($current_emails, [strtolower($new_alias_email)]),
            'zimbra_aliases' => array_merge($current_zimbra_aliases, [strtolower($new_alias_email)]),
        ];
    }

    /**
     * Remove an alias from an LDAP user.
     *
     * @param string $username The primary NPK/username
     * @param string $alias_to_remove The alias to remove (with or without @domain)
     * @return array Returns array with 'success' boolean and 'message' string
     */
    public static function removeAliasFromLdap($username, $alias_to_remove)
    {
        if (empty($username) || empty($alias_to_remove)) {
            return ['success' => false, 'message' => 'Username and alias to remove are required'];
        }

        $cfg             = self::ldapConfig();
        $alias_to_remove = str_replace('@pupukkaltim.com', '', trim($alias_to_remove));
        $alias_email     = self::concat_with_pkt_email($alias_to_remove);

        $conn = ldap_connect($cfg['host'], $cfg['port']);
        if (!$conn) {
            return ['success' => false, 'message' => 'Failed to connect to LDAP server'];
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        if (!@ldap_bind($conn, $cfg['dn'], $cfg['pass'])) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to authenticate with LDAP server'];
        }

        $email  = self::concat_with_pkt_email($username);
        $result = @ldap_search($conn, $cfg['tree'],
            '(mail=' . ldap_escape($email, '', LDAP_ESCAPE_FILTER) . ')',
            ['mail', 'dn', 'uid', 'zimbraMailAlias']
        );

        if (!$result) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to search for user in LDAP'];
        }

        $entries = @ldap_get_entries($conn, $result);
        if (!$entries || $entries['count'] === 0) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'User not found in LDAP'];
        }

        $entry       = $entries[0];
        $user_dn     = $entry['dn'];
        $primary_uid = $entry['uid'][0] ?? null;

        $current_emails = [];
        for ($i = 0; $i < ($entry['mail']['count'] ?? 0); $i++) {
            $current_emails[] = strtolower($entry['mail'][$i]);
        }

        if (strtolower($alias_email) === strtolower(self::concat_with_pkt_email($primary_uid))) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Cannot remove primary email alias'];
        }

        if (!in_array(strtolower($alias_email), $current_emails)) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Alias not found for this user', 'current_aliases' => $current_emails];
        }

        if (count($current_emails) <= 1) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Cannot remove the last email alias'];
        }

        if (!@ldap_mod_del($conn, $user_dn, ['mail' => $alias_email])) {
            $error = ldap_error($conn);
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to remove mail alias: ' . $error];
        }

        @ldap_mod_del($conn, $user_dn, ['zimbraMailAlias' => $alias_email]);
        ldap_close($conn);

        return [
            'success'           => true,
            'message'           => 'Alias removed successfully',
            'removed_alias'     => $alias_email,
            'remaining_aliases' => array_values(array_diff($current_emails, [strtolower($alias_email)])),
        ];
    }

    /**
     * Get all LDAP users with pagination and filtering.
     *
     * @param array $options Keys: page, per_page, search, attributes, filter, sort_by, sort_order
     * @return array Paginated user data with metadata
     */
    public static function getAllUsers($options = [])
    {
        $page          = max(1, (int) ($options['page'] ?? 1));
        $per_page      = max(1, min(1000, (int) ($options['per_page'] ?? 50)));
        $search        = trim($options['search'] ?? '');
        $sort_by       = $options['sort_by'] ?? 'cn';
        $sort_order    = strtolower($options['sort_order'] ?? '') === 'desc' ? 'desc' : 'asc';
        $custom_filter = $options['filter'] ?? '';

        $default_attributes = [
            'uid', 'cn', 'mail', 'displayName', 'givenName', 'sn', 'title', 'department',
            'telephoneNumber', 'mobile', 'employeeNumber', 'ou', 'street', 'l', 'st',
            'postalCode', 'co', 'description', 'zimbraAccountStatus',
        ];
        $attributes = (isset($options['attributes']) && is_array($options['attributes']))
            ? $options['attributes']
            : $default_attributes;

        $empty_pagination = [
            'current_page' => $page,
            'per_page'     => $per_page,
            'total'        => 0,
            'total_pages'  => 0,
            'from'         => 0,
            'to'           => 0,
        ];

        $conn = self::ldapConnect();
        if (!$conn) {
            return ['success' => false, 'message' => 'Failed to connect to LDAP server', 'data' => [], 'pagination' => $empty_pagination];
        }

        $base_filter = '(objectClass=inetOrgPerson)';

        if (!empty($search)) {
            $s           = ldap_escape($search, '', LDAP_ESCAPE_FILTER);
            $base_filter = "(&{$base_filter}(|(uid=*{$s}*)(cn=*{$s}*)(displayName=*{$s}*)(givenName=*{$s}*)(sn=*{$s}*)(mail=*{$s}*)(department=*{$s}*)(title=*{$s}*)))";
        }

        if (!empty($custom_filter)) {
            $base_filter = "(&{$base_filter}{$custom_filter})";
        }

        $result = @ldap_search($conn, self::ldapConfig()['tree'], $base_filter, $attributes);
        if (!$result) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to search LDAP', 'data' => [], 'pagination' => $empty_pagination];
        }

        $entries = @ldap_get_entries($conn, $result);
        ldap_close($conn);

        if (!$entries || $entries['count'] === 0) {
            return ['success' => true, 'message' => 'No users found', 'data' => [], 'pagination' => $empty_pagination];
        }

        $total_count = $entries['count'];
        $all_users   = [];
        for ($i = 0; $i < $total_count; $i++) {
            $all_users[] = self::formatEntry($entries[$i]);
        }

        usort($all_users, function ($a, $b) use ($sort_by, $sort_order) {
            $val_a = (string) ($a[$sort_by] ?? '');
            $val_b = (string) ($b[$sort_by] ?? '');
            $cmp   = strcasecmp($val_a, $val_b);
            return $sort_order === 'desc' ? -$cmp : $cmp;
        });

        $total_pages = (int) ceil($total_count / $per_page);
        $offset      = ($page - 1) * $per_page;

        return [
            'success'    => true,
            'message'    => "Found {$total_count} user(s)",
            'data'       => array_slice($all_users, $offset, $per_page),
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $per_page,
                'total'        => $total_count,
                'total_pages'  => $total_pages,
                'from'         => $total_count > 0 ? $offset + 1 : 0,
                'to'           => min($offset + $per_page, $total_count),
                'has_more'     => $page < $total_pages,
                'has_prev'     => $page > 1,
            ],
        ];
    }

    /**
     * Get total count of users in LDAP.
     *
     * @param string $filter Optional LDAP filter
     * @return int
     */
    public static function getUserCount($filter = '(objectClass=inetOrgPerson)')
    {
        $conn = self::ldapConnect();
        if (!$conn) {
            return 0;
        }

        $result  = @ldap_search($conn, self::ldapConfig()['tree'], $filter, ['uid']);
        $entries = $result ? @ldap_get_entries($conn, $result) : null;
        ldap_close($conn);

        return $entries ? $entries['count'] : 0;
    }

    /**
     * Get user data from LDAP based on NPK (username).
     *
     * @param string $username
     * @return array|null Returns user data array if found, null if not found
     */
    public static function getUserData($username)
    {
        $conn = self::ldapConnect();
        if (!$conn) {
            return null;
        }

        $attributes = [
            'uid', 'cn', 'mail', 'displayName', 'givenName', 'sn',
            'telephoneNumber', 'mobile', 'title', 'ou', 'department',
            'employeeNumber', 'street', 'l', 'st', 'postalCode', 'co', 'description',
        ];

        $email  = self::concat_with_pkt_email($username);
        $filter = '(mail=' . ldap_escape($email, '', LDAP_ESCAPE_FILTER) . ')';
        $result = @ldap_search($conn, self::ldapConfig()['tree'], $filter, $attributes);

        if (!$result) {
            ldap_close($conn);
            return null;
        }

        $entries = @ldap_get_entries($conn, $result);
        ldap_close($conn);

        if (!$entries || $entries['count'] === 0) {
            return null;
        }

        return self::formatEntry($entries[0]);
    }

    /**
     * Get all available attributes for a user from LDAP.
     *
     * @param string $npk NPK/username
     * @return array|null Returns all LDAP attributes if found, null if not found
     */
    public static function getAllUserAttributes($npk)
    {
        if (empty($npk)) {
            return null;
        }

        $conn = self::ldapConnect();
        if (!$conn) {
            return null;
        }

        $escaped_email = ldap_escape(self::concat_with_pkt_email($npk), '', LDAP_ESCAPE_FILTER);
        $escaped_npk   = ldap_escape($npk, '', LDAP_ESCAPE_FILTER);
        $filter        = "(|(mail={$escaped_email})(uid={$escaped_npk}))";

        $result  = @ldap_search($conn, self::ldapConfig()['tree'], $filter);
        $entries = $result ? @ldap_get_entries($conn, $result) : null;
        ldap_close($conn);

        if (!$entries || $entries['count'] === 0) {
            return null;
        }

        $attributes = [];
        foreach ($entries[0] as $key => $value) {
            if (is_numeric($key) || $key === 'count') {
                continue;
            }

            if (!is_array($value) || !isset($value['count'])) {
                $attributes[$key] = $value;
                continue;
            }

            $count = $value['count'];
            if ($count === 0) {
                $attributes[$key] = null;
            } elseif ($count === 1) {
                $attributes[$key] = $value[0];
            } else {
                $values = [];
                for ($i = 0; $i < $count; $i++) {
                    $values[] = $value[$i];
                }
                $attributes[$key] = $values;
            }
        }

        return $attributes;
    }

    /**
     * Search users in LDAP by username or name.
     *
     * @param string $query Search query (minimum 2 characters)
     * @param int $limit Maximum number of results (default: 50)
     * @return array
     */
    public static function searchUsers($query, $limit = 50)
    {
        if (empty($query) || strlen($query) < 2) {
            return ['success' => false, 'message' => 'Search query must be at least 2 characters', 'data' => []];
        }

        $conn = self::ldapConnect();
        if (!$conn) {
            return ['success' => false, 'message' => 'Failed to connect to LDAP server', 'data' => []];
        }

        $s          = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
        $filter     = "(|(uid=*{$s}*)(cn=*{$s}*)(displayName=*{$s}*)(givenName=*{$s}*)(sn=*{$s}*)(mail=*{$s}*))";
        $attributes = ['uid', 'cn', 'mail', 'displayName', 'givenName', 'sn', 'title', 'department', 'telephoneNumber', 'mobile'];
        $result     = @ldap_search($conn, self::ldapConfig()['tree'], $filter, $attributes);

        if (!$result) {
            ldap_close($conn);
            return ['success' => false, 'message' => 'Failed to search LDAP', 'data' => []];
        }

        $entries = @ldap_get_entries($conn, $result);
        ldap_close($conn);

        if (!$entries || $entries['count'] === 0) {
            return ['success' => true, 'message' => 'No users found', 'data' => [], 'count' => 0];
        }

        $total = $entries['count'];
        $users = [];
        for ($i = 0; $i < min($total, $limit); $i++) {
            $users[] = self::formatEntry($entries[$i]);
        }

        return [
            'success' => true,
            'message' => 'Found ' . count($users) . ' user(s)',
            'data'    => $users,
            'count'   => count($users),
            'total'   => $total,
            'limited' => $total > $limit,
        ];
    }

    /**
     * Find a user in LDAP by exact NPK/username.
     *
     * @param string $npk Exact NPK/username to search
     * @return array|null Returns user data if found, null if not found
     */
    public static function findUserByNpk($npk)
    {
        if (empty($npk)) {
            return null;
        }

        $conn = self::ldapConnect();
        if (!$conn) {
            return null;
        }

        $attributes = ['uid', 'cn', 'mail', 'displayName', 'givenName', 'sn', 'title', 'department', 'telephoneNumber', 'mobile', 'employeeNumber'];
        $filter     = '(uid=' . ldap_escape($npk, '', LDAP_ESCAPE_FILTER) . ')';
        $result     = @ldap_search($conn, self::ldapConfig()['tree'], $filter, $attributes);

        if (!$result) {
            ldap_close($conn);
            return null;
        }

        $entries = @ldap_get_entries($conn, $result);
        ldap_close($conn);

        if (!$entries || $entries['count'] === 0) {
            return null;
        }

        return self::formatEntry($entries[0]);
    }

    /**
     * Check if NPK/username exists in LDAP.
     *
     * @param string $npk NPK/username to check
     * @return bool
     */
    public static function userExists($npk)
    {
        return self::findUserByNpk($npk) !== null;
    }

    /**
     * Authenticate a user against LDAP using username and password.
     *
     * @param string $username NPK/username (with or without @domain)
     * @param string $password Plaintext password to verify
     * @return bool Returns true if authentication is successful, false otherwise
     */
    public static function authAttempt(string $username, string $password): bool
    {
        $cnf = self::ldapConfig();
        $conn = self::ldapConnect();

        if (! $conn) {
            return false;
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

        if (! str_contains($username, '@pupukkaltim.com')) {
            $username .= '@pupukkaltim.com';
        }

        $result = @ldap_search(
            $conn,
            $cnf['tree'],
            "(mail={$username})",
            ['displayname', 'mail', 'uid', 'ou', 'sn', 'givenname']
        );

        $entry = @ldap_first_entry($conn, $result);

        if (! $entry) {
            return false;
        }

        $userDn = @ldap_get_dn($conn, $entry);

        if (! $userDn || ! @ldap_bind($conn, $userDn, $password)) {
            return false;
        }

        return true;
    }

    private static function concat_with_pkt_email($username)
    {
        $username   = trim($username);
        $pkt_domain = '@pupukkaltim.com';
        return str_contains($username, $pkt_domain) ? $username : $username . $pkt_domain;
    }
}
