<?php
/*
    SceneGames
    Copyright (C) 2018  GoodOldDownloads

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/
namespace GoodOldDownloads;

require __DIR__ . '/db.php';

class Users
{
    public function __construct()
    {
        require __DIR__ . '/config.php';
        $this->CONFIG = $CONFIG;
        $this->APIKEYS = $APIKEYS;
    }

    public function register($username, $password, $regcode)
    {
        global $dbh;
        $username = trim($username);
        $regcode = trim($regcode);
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $apikey =  'sg_'.implode('-', str_split(substr(md5(base64_encode(random_bytes(32))), 0, 25), 5));

        $dbh->beginTransaction();

        try {
            // Check regcode
            $checkReg = $dbh->prepare("SELECT COUNT(*) FROM `regcodes` WHERE `code` = :code");
            $checkReg->bindParam(':code', $regcode, \PDO::PARAM_STR);
            $checkReg->execute();
            if ($checkReg->fetchColumn() != 1) {
                throw new \Exception("Invalid Registration Code.");
            }

            // Regcode is good so delete it
            $deleteReg = $dbh->prepare("DELETE FROM `regcodes` WHERE `code` = :code");
            $deleteReg->bindParam(':code', $regcode, \PDO::PARAM_STR);
            $deleteReg->execute();

            // Check if user with that name exists
            $checkUser = $dbh->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = :user");
            $checkUser->bindParam(':user', $username, \PDO::PARAM_STR);
            $checkUser->execute();
            if ($checkUser->fetchColumn() > 0) {
                throw new \Exception("User already exists.");
            }

            // Now make a user
            $stmt = $dbh->prepare("INSERT INTO `users` (`username`, `password`, `class`)
                                                VALUES (:user, :pass, 'DISABLED')");
            $stmt->bindParam(':user', $username, \PDO::PARAM_STR);
            $stmt->bindParam(':pass', $hashed, \PDO::PARAM_STR);
            if ($stmt->execute() == false) {
                throw new \Exception("Failed to create user.");
            }
            $dbh->commit();
            
        } catch(\Exception $e){
            $dbh->rollBack();
            return $e->getMessage();
        }
        return true;
    }

    public function login($username, $password)
    {
        global $Memcached;
        global $dbh;
        $username = trim($username);
        $dbh->beginTransaction();
        try {
            // Check if user exists
            $checkExists = $dbh->prepare("SELECT * FROM `users` WHERE `username` = :username");
            $checkExists->bindParam(':username', $username, \PDO::PARAM_STR);
            $checkExists->execute();
            $userData = $checkExists->fetch();
            if ($userData === false) {
                throw new \Exception("Incorrect Username/Password");
            }

            if ($userData['class'] === 'DISABLED') {
                throw new \Exception("Account is Disabled (or has not been enabled yet)");
            }

            // check password
            if (!password_verify($password, $userData['password'])) {
                throw new \Exception("Incorrect Username/Password");
            } else {
                $_SESSION['user'] = $userData;
            }
            setcookie("was_user", "1", 2147483647, '/');
            $dbh->commit();
        } catch(\Exception $e){
            $dbh->rollBack();
            return $e->getMessage();
        }
        return true;
    }

    public function logout()
    {
        unset($_SESSION['user']);
    }

    public function get($userid = null)
    {
        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        } else {
            return false;
        }
    }
}