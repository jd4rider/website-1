<?php
namespace Destiny\Common\User;

use Destiny\Common\Application;
use Destiny\Common\Config;
use Destiny\Common\DBException;
use Destiny\Common\Exception;
use Destiny\Common\Log;
use Destiny\Common\Service;
use Destiny\Common\Session\Session;
use Destiny\Common\Utils\Date;
use Destiny\Common\Utils\FilterParams;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use PDO;

/**
 * @method static UserService instance()
 */
class UserService extends Service {

    /**
     * @var array
     */
    protected $roles = null;

    /**
     * @var array
     */
    protected $features = null;

    /**
     * @throws DBException
     */
    public function getAllRoles(): array {
        try {
            if ($this->roles == null) {
                $conn = Application::getDbConn();
                $stmt = $conn->prepare('SELECT roleId, roleName, roleLabel FROM `dfl_roles` ORDER BY roleLabel ASC');
                $stmt->execute();
                $this->roles = [];
                while ($a = $stmt->fetch()) {
                    $this->roles [$a ['roleName']] = $a;
                }
            }
            return $this->roles;
        } catch (DBALException $e) {
            throw new DBException("Failed to load roles.", $e);
        }
    }

    /**
     * @return array <featureName, []>
     * @throws DBException
     */
    public function getAllFeatures(): array {
        try {
            if ($this->features == null) {
                $conn = Application::getDbConn();
                $stmt = $conn->prepare('SELECT featureId, featureName, featureLabel FROM dfl_features ORDER BY featureLabel ASC');
                $stmt->execute();
                $this->features = [];
                while ($a = $stmt->fetch()) {
                    $this->features [$a['featureName']] = $a;
                }
            }
            return $this->features;
        } catch (DBALException $e) {
            throw new DBException("Failed to load features.", $e);
        }
    }

    /**
     * @throws Exception
     */
    public function getRoleIdByName(string $roleName): int {
        $roles = $this->getAllRoles();
        FilterParams::required($roles, $roleName);
        return $roles[$roleName]['roleId'];
    }

    /**
     * Remove a role from a user
     * @throws Exception
     */
    public function removeUserRole(int $userId, string $roleName) {
        try {
            $roleId = $this->getRoleIdByName($roleName);
            $conn = Application::getDbConn();
            $conn->delete('dfl_users_roles', ['userId' => $userId, 'roleId' => $roleId]);
        } catch (DBALException $e) {
            throw new DBException("Failed to remove user role.", $e);
        }
    }

    /**
     * @throws Exception
     */
    public function addUserRole(int $userId, string $roleName) {
        try {
            $roleId = $this->getRoleIdByName($roleName);
            $conn = Application::getDbConn();
            $conn->insert('dfl_users_roles', ['userId' => $userId, 'roleId' => $roleId]);
        } catch (DBALException $e) {
            throw new DBException("Failed to add user role.", $e);
        }
    }

    /**
     * @throws Exception
     */
    public function getFeatureIdByName(string $featureName): int {
        $features = $this->getAllFeatures();
        FilterParams::required($features, $featureName);
        return $features[$featureName]['featureId'];
    }

    /**
     * Get a list of user features
     * @throws DBException
     */
    public function getFeaturesByUserId(int $userId): array {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare('
                SELECT DISTINCT b.featureName AS `id` FROM dfl_users_features AS a
                INNER JOIN dfl_features AS b ON (b.featureId = a.featureId)
                WHERE userId = :userId
                ORDER BY a.featureId ASC
            ');
            $stmt->bindValue('userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $features = [];
            while ($feature = $stmt->fetchColumn()) {
                $features [] = $feature;
            }
            return $features;
        } catch (DBALException $e) {
            throw new DBException("Failed to load user feature.", $e);
        }
    }

    /**
     * Add a feature to a user
     * @throws Exception
     */
    public function addUserFeature(int $userId, string $featureName) {
        try {
            $featureId = $this->getFeatureIdByName($featureName);
            $conn = Application::getDbConn();
            $conn->insert('dfl_users_features', ['userId' => $userId, 'featureId' => $featureId]);
        } catch (DBALException $e) {
            throw new DBException("Failed to add user feature.", $e);
        }
    }

    /**
     * Remove a feature from a user
     * @throws Exception
     */
    public function removeUserFeature(int $userId, string $featureName) {
        try {
            $featureId = $this->getFeatureIdByName($featureName);
            $conn = Application::getDbConn();
            $conn->delete('dfl_users_features', ['userId' => $userId, 'featureId' => $featureId]);
        } catch (DBALException $e) {
            throw new DBException("Failed to remove user feature.", $e);
        }
    }

    /**
     * Throws an exception if username is taken
     * @throws Exception
     */
    public function checkUsernameTaken(string $username, $excludeUserId = 0) {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare('SELECT COUNT(*) FROM `dfl_users` WHERE username = :username AND userId != :excludeUserId');
            $stmt->bindValue('username', $username, PDO::PARAM_STR);
            $stmt->bindValue('excludeUserId', $excludeUserId, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() <= 0) return;
            throw new Exception("The username you asked for is already being used");
        } catch (DBALException $e) {
            Log::error("Failed to check username. {$e->getMessage()}");
            throw new Exception("Failed to check username.", $e);
        }
    }

    /**
     * @return array|null
     * @throws DBException
     */
    public function getUserByUsername(string $username) {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare('SELECT * FROM `dfl_users` WHERE username = :username LIMIT 1');
            $stmt->bindValue('username', $username, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch();
        } catch (DBALException $e) {
            throw new DBException("Error loading user.", $e);
        }
    }

    /**
     * @return array|null
     * @throws DBException
     */
    public function getUserById(int $userId) {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare('SELECT * FROM `dfl_users` WHERE userId = :userId LIMIT 1');
            $stmt->bindValue('userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch();
        } catch (DBALException $e) {
            throw new DBException("Error loading user.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function addUser(array $user): int {
        try {
            $conn = Application::getDbConn();
            $user ['createdDate'] = Date::getSqlDateTime();
            $user ['modifiedDate'] = Date::getSqlDateTime();
            $conn->insert('dfl_users', $user);
            return intval($conn->lastInsertId());
        } catch (DBALException $e) {
            throw new DBException("Error adding user.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function updateUser(int $userId, array $user) {
        try {
            $conn = Application::getDbConn();
            $user ['modifiedDate'] = Date::getSqlDateTime();
            $conn->update('dfl_users', $user, ['userId' => $userId]);
        } catch (DBALException $e) {
            throw new DBException("Failed to update user.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function getRolesByUserId(int $userId): array {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare('
              SELECT b.roleName FROM dfl_users_roles AS a
              INNER JOIN dfl_roles b ON (b.roleId = a.roleId)
              WHERE a.userId = :userId
            ');
            $stmt->bindValue('userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $roles = [];
            while ($role = $stmt->fetchColumn()) {
                $roles [] = $role;
            }
            return $roles;
        } catch (DBALException $e) {
            throw new DBException("Failed to load user roles.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function searchAll(array $params): array {
        try {
            $joins = [];
            $clauses = [];
            $orders = [];
        if (!empty($params['search'])) {
            $clauses[] = '(u.username LIKE :wildcard1 OR u.email LIKE :wildcard1 OR u.userId IN (SELECT a.userId FROM dfl_users_auth a WHERE a.authDetail LIKE :wildcard1 OR a.authEmail LIKE :wildcard1))';
            $orders[] = '
              CASE
                WHEN u.username LIKE :wildcard2 THEN 0
                WHEN u.username LIKE :wildcard3 THEN 1
                WHEN u.username LIKE :wildcard4 THEN 2
                WHEN u.username LIKE :wildcard4 THEN 4
                WHEN u.username LIKE :wildcard4 THEN 5
              ELSE 3
              END
            ';
            }
            if (!empty($params['feature'])) {
                $joins[] = ' INNER JOIN dfl_users_features f ON f.userId = u.userId AND f.featureId = :featureId ';
            }
            if (!empty($params['role'])) {
                $joins[] = ' INNER JOIN dfl_users_roles r ON r.userId = u.userId AND r.roleId = :roleId  ';
            }
            if (!empty($params['status'])) {
                $clauses[] = ' u.userStatus = :userStatus ';
            }
            if (!empty($params['sort'])) {
                $sort = $params['sort'];
                $order = $params['order'] ?? 'DESC';
                switch (strtolower($sort)) {
                    case 'id' :
                        $orders[] = " u.userId $order ";
                        break;
                    case 'username' :
                        $orders[] = " u.username $order ";
                        break;
                    case 'status' :
                        $orders[] = " u.userStatus $order ";
                        break;
                }
            }
            $q = 'SELECT SQL_CALC_FOUND_ROWS u.userId, u.username, u.email, u.userStatus, u.createdDate FROM dfl_users AS u ';
            $q .= ' ' . join(PHP_EOL, $joins);
            if (count($clauses) > 0) {
                $q .= ' WHERE ' . join(' AND ', $clauses);
            }
            if (count($joins) > 0) {
                $q .= ' GROUP BY u.userId ';
            }
            if (count($orders) > 0) {
                $q .= ' ORDER BY ' . join(', ', $orders);
            }
            $q .= ' LIMIT :start, :limit ';
            $conn = Application::getDbConn();
            $stmt = $conn->prepare($q);
            if (!empty($params['search'])) {
                $stmt->bindValue('wildcard1', '%' . $params['search'] . '%', PDO::PARAM_STR);
                $stmt->bindValue('wildcard2', $params['search'] . ' %', PDO::PARAM_STR);
                $stmt->bindValue('wildcard3', $params['search'] . '%', PDO::PARAM_STR);
                $stmt->bindValue('wildcard4', '% %' . $params['search'] . '% %', PDO::PARAM_STR);
            }
            if (!empty($params['feature'])) {
                $stmt->bindValue('featureId', $params['feature'], PDO::PARAM_INT);
            }
            if (!empty($params['role'])) {
                $stmt->bindValue('roleId', $params['role'], PDO::PARAM_INT);
            }
            if (!empty($params['status'])) {
                $stmt->bindValue('userStatus', $params['status'], PDO::PARAM_STR);
            }

            $stmt->bindValue('start', ($params['page'] - 1) * $params['size'], PDO::PARAM_INT);
            $stmt->bindValue('limit', (int) $params['size'], PDO::PARAM_INT);
            $stmt->execute();

            $pagination = [];
            $pagination ['list'] = $stmt->fetchAll();
            $pagination ['total'] = $conn->fetchColumn('SELECT FOUND_ROWS()');
            $pagination ['totalpages'] = ceil($pagination ['total'] / $params['size']);
            $pagination ['pages'] = 5;
            $pagination ['page'] = $params['page'];
            $pagination ['limit'] = $params['size'];
            return $pagination;
        } catch (DBALException $e) {
            throw new DBException("Failed to search users.", $e);
        }
    }

    /**
     * @throws Exception
     */
    public function findByNewBDay(): array {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare("
                SELECT DISTINCT u.userId, u.createdDate, TIMESTAMPDIFF(YEAR, u.createdDate, CURDATE())+1 `years` FROM dfl_users u 
                LEFT JOIN dfl_users_features uf ON (uf.userId = u.userId AND uf.featureId = :featureId)
                WHERE DATE_FORMAT(u.createdDate,'%m-%d') = DATE_FORMAT(CURDATE(),'%m-%d')
                AND u.userStatus = :userStatus
                AND TIMESTAMPDIFF(YEAR, u.createdDate, CURDATE()) > 0
                AND uf.featureId IS NULL
             ");
            $stmt->bindValue('featureId', $this->getFeatureIdByName(UserFeature::DGGBDAY), PDO::PARAM_INT);
            $stmt->bindValue('userStatus', UserStatus::ACTIVE, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (DBALException $e) {
            throw new DBException("Failed to find new bday users.", $e);
        }
    }

    /**
     * @throws Exception
     */
    public function findByExpiredBDay(): array {
        try {
            $conn = Application::getDbConn();
            $featureId = $this->getFeatureIdByName(UserFeature::DGGBDAY);
            $stmt = $conn->prepare("
                SELECT DISTINCT u.userId FROM dfl_users u 
                INNER JOIN dfl_users_features uf ON (uf.userId = u.userId AND uf.featureId = :featureId)
                WHERE DATE_FORMAT(u.createdDate,'%m-%d') <> DATE_FORMAT(CURDATE(),'%m-%d')
                AND u.userStatus = :userStatus
             ");
            $stmt->bindValue('featureId', $featureId, PDO::PARAM_INT);
            $stmt->bindValue('userStatus', UserStatus::ACTIVE, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (DBALException $e) {
            throw new DBException("Failed to find expired bday users.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function getUserIdByField(string $field, $value) {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare("SELECT userId FROM dfl_users WHERE " . $field . " = :value LIMIT 1");
            $stmt->bindValue('value', $value, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (DBALException $e) {
            throw new DBException("Failed to load user by field $field.", $e);
        }
    }

    /**
     * Get the users from the given redis keys, strip off the beginning of the keys
     * and parse the remaining string into an int, CHAT:userips-123 will be
     * transformed into (int)123 and than later users with the given ids
     * queried from the database ordered by username in ascending order
     *
     * @throws DBException
     */
    public function getUsersByUserIds(array $userids): array {
        if (!empty($userids)) {
            try {
                $conn = Application::getDbConn();
                $stmt = $conn->executeQuery("SELECT userId, username, email, createdDate FROM dfl_users WHERE userId IN (?) ORDER BY username", [$userids], [Connection::PARAM_STR_ARRAY]);
                return $stmt->fetchAll();
            } catch (DBALException $e) {
                throw new DBException("Failed to load users.", $e);
            }
        }
        return [];
    }

    /**
     * @throws DBException
     */
    public function getUserIdsByUsernames(array $usernames): array {
        if (!empty($usernames)) {
            try {
                $conn = Application::getDbConn();
                $stmt = $conn->executeQuery("SELECT u.userId `userId` FROM `dfl_users` u WHERE u.username IN (?)", [$usernames], [Connection::PARAM_STR_ARRAY]);
                return $stmt->fetchAll();
            } catch (DBALException $e) {
                throw new DBException("Failed to load users.", $e);
            }
        }
        return [];
    }

    /**
     * @throws DBException
     */
    public function isUserOldEnough(int $userId): bool {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare("SELECT COUNT(*) FROM dfl_users AS u WHERE u.userId = :userId AND DATE_ADD(u.createdDate, INTERVAL 7 DAY) < NOW() LIMIT 1");
            $stmt->bindValue('userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return !!$stmt->fetchColumn();
        } catch (DBALException $e) {
            throw new DBException("Failed to get users age.", $e);
        }
    }

    /**
     * Returns an array of active subscribers (for announcing) with the
     * key being the authid and the value being an array of user info(userid, username)
     *
     * Expects the following $data structure:
     *  [{"123":1},{"456":0}]
     *
     *  Where the key is the twitch user id (auth.authDetail) and the value is whether
     *  the user is a subscriber or not
     *
     *  Returns a user list containing only users that have their twitch sub changed.
     *
     * @throws Exception
     */
    public function updateTwitchSubscriptions(array $data): array {
        if (empty($data))
            return [];

        $conn = Application::getDbConn();
        $batchsize = 100;

        $ids = [];
        foreach ($data as $authid => $subscriber) {
            if (!ctype_alnum($authid)) {
                throw new Exception("Non alpha-numeric authid found: $authid");
            }
            $ids[] = $authid;
        }

        // we get the users connected to the twitch authids so that later we can
        // update the users in batches efficiently and return the subs with
        // the required information to the caller
        $idToUser = [];
        $infosql = "
          SELECT
            u.username,
            u.userId,
            u.istwitchsubscriber,
            ua.authId
          FROM
            dfl_users_auth AS ua,
            dfl_users AS u
          WHERE
            u.userId        = ua.userId AND
            ua.authProvider = 'twitch' AND
            ua.authId       IN('%s')
        ";

        try {
            // do it in moderate batches
            foreach (array_chunk($ids, $batchsize) as $chunk) {
                $stmt = $conn->prepare(sprintf($infosql, implode("', '", $chunk)));
                $stmt->execute();
                while ($row = $stmt->fetch()) {
                    $idToUser[$row['authId']] = $row;
                }
            }
            unset($ids);

            if (empty($idToUser)) {
                return [];
            }

            $changed = $subs = $nonsubs = [];
            foreach ($idToUser as $authid => $user) {
                if ($data[$authid] <> $user['istwitchsubscriber']) {
                    if ($data[$authid] == 1) {
                        $subs[] = $user['userId'];
                    } else if ($data[$authid] == 0) {
                        $nonsubs[] = $user['userId'];
                    }
                    $user['istwitchsubscriber'] = $data[$authid];
                    $changed[$user['authId']] = $user;
                }
            }

            $subsql = "UPDATE dfl_users AS u SET u.istwitchsubscriber = '%s' WHERE u.userId IN('%s')";

            // update the subs first
            foreach (array_chunk($subs, $batchsize) as $chunk) {
                $conn->exec(sprintf($subsql, '1', implode("', '", $chunk)));
            }
            // update nonsubs
            foreach (array_chunk($nonsubs, $batchsize) as $chunk) {
                $conn->exec(sprintf($subsql, '0', implode("', '", $chunk)));
            }
        } catch (DBALException $e) {
            throw new DBException("Error running subscription update query.", $e);
        }

        return $changed;
    }

    /**
     * @throws DBException
     */
    public function getActiveTwitchSubscriptions(): array {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare("
              SELECT ua.authId
              FROM
                dfl_users AS u,
                dfl_users_auth AS ua
              WHERE
                u.userId             = ua.userId AND
                ua.authProvider      = 'twitch' AND
                ua.authId            IS NOT NULL AND
                u.istwitchsubscriber = 1
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (DBALException $e) {
            throw new DBException("Error getting active twitch subs.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function saveChatSettings(int $userId, string $settings): bool {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare("UPDATE dfl_users SET `chatsettings` = :chatsettings WHERE userId = :userid LIMIT 1");
            $stmt->bindValue('userid', $userId, PDO::PARAM_INT);
            $stmt->bindValue('chatsettings', $settings, PDO::PARAM_STR);
            $stmt->execute();
            return (bool)$stmt->rowCount();
        } catch (DBALException $e) {
            throw new DBException("Failed to save chat settings.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function fetchChatSettings(int $userId) {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare("SELECT `chatsettings` FROM dfl_users WHERE `userId` = :userid LIMIT 1");
            $stmt->bindValue('userid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchColumn();
            return !empty($data) ? json_decode($data) : [];
        } catch (DBALException $e) {
            throw new DBException("Failed to load chat settings.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function deleteChatSettings(int $userId): bool {
        try {
            $conn = Application::getDbConn();
            $stmt = $conn->prepare("UPDATE dfl_users SET `chatsettings` = NULL WHERE userId = :userid LIMIT 1");
            $stmt->bindValue('userid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        } catch (DBALException $e) {
            throw new DBException("Failed to remove chat settings.", $e);
        }
    }

    /**
     * @throws DBException
     */
    public function getUserDeletedByUserId(int $userId) {
        try {
            $conn = Application::getDbConn();
            return $conn->executeQuery('
                SELECT d.*, u1.username as `deletedByUsername` 
                FROM users_deleted d 
                INNER JOIN dfl_users u1 ON u1.userId = d.deletedby 
                WHERE d.userid = :userId', ['userId' => $userId], [PDO::PARAM_INT]
            )->fetch();
        } catch (DBALException $e) {
            throw new DBException("Failed to load user.", $e);
        }
    }

    /**
     * Does a variety of deletions, but doesnt actually remove the user record.
     *
     * @throws DBException
     */
    public function allButDeleteUser(array $user) {
        try {
            $deletedBy = Session::getCredentials()->getUserId();
            $userId = intval($user['userId']);
            $newUsername = "deleted$userId";
            $newEmail = "deleted$userId";

            $conn = Application::getDbConn();
            $conn->beginTransaction();

            $conn->delete('dfl_users_auth', ['userId' => $userId], [PDO::PARAM_INT]);
            $conn->delete('dfl_users_auth_token', ['userId' => $userId], [PDO::PARAM_INT]);
            $conn->delete('oauth_access_tokens', ['userId' => $userId], [PDO::PARAM_INT]);
            $conn->delete('oauth_client_details', ['ownerId' => $userId], [PDO::PARAM_INT]);
            $conn->update('donations', ['username' => $newUsername], ['userid' => $userId], [PDO::PARAM_STR, PDO::PARAM_INT]);

            $conn->insert('users_deleted', [
                'userid' => $userId,
                'deletedby' => $deletedBy,
                'timestamp' => Date::getSqlDateTime(),
                'usernamehash' => $this->hashUserProperty($user['username']),
                'emailhash' => !empty($user['email']) ? $this->hashUserProperty($user['email']) : '',
            ]);

            $conn->update('dfl_users', [
                'userStatus' => UserStatus::REDACTED,
                'username' => $newUsername,
                'email' => $newEmail
            ], ['userId' => $userId]);

            $conn->commit();
        } catch (DBALException $e) {
            throw new DBException("Failed to delete user.", $e);
        }
    }

    private function hashUserProperty(string $value): string {
        return base64_encode(hash('sha256', $value . Config::$a['deleted_user_hash']));
    }

    /**
     * @throws DBException
     */
    public function getAuditLog(int $start = 0, int $limit = 250): array {
        try {
            $conn = Application::getDbConn();
            return $conn->executeQuery(
                'SELECT a.* FROM users_audit a ORDER BY a.id DESC LIMIT ?,?',
                [$start, $limit],
                [PDO::PARAM_INT, PDO::PARAM_INT]
            )->fetchAll();
        } catch (DBALException $e) {
            throw new DBException("Failed to load audit log.", $e);
        }
    }

}
