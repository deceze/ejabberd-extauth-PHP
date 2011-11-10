ejabberd-extauth-php
====================

Description
-----------

A simple worker class to get an external ejabberd authentication program up and running.
Worries about communication with the ejabberd process, delegation of tasks and logging.

Usage
-----

Simply extend the `EjabberdExternalAuth` class and implement the authentication handler
methods. Then create a new instance to start it. Example:

    require `ejabberd_external_auth.php`;

    class MyAuth extends EjabberdExternalAuth {

        protected function authenticate($user, $server, $password) {
            $this->db()->prepare(...);
            // here be dragons
            return $isValid;
        }

        protected function exists($user, $server) {
            ...
        }

    }
    
    $pdo = new PDO(...);
    new MyAuth($pdo, '/var/log/myauth.log');

Then just configure your ejabberd correctly to point to the script for external authentication.

The child class only needs to worry about implementing the actual authentication handling methods, which should return `true` or `false`. These methods are:

- `bool authenticate( string $user, string $server, string $password )`

   Handles the actual login of users, corresponds to the ejabberd `auth` event.
        
- `bool exists( string $user, string $server )`
    
   Should return whether a user already exists or not, corresponds to ejabberd `isuser` event.
        
These must be implemented as minimum.

The following methods may optionally be implemented. If the class does so, it must [formerly implement](http://www.php.net/interfaces) the `EjabberdExternalAuth_UserManagement` interface.

- `bool setPassword( string $user, string $server, string $password )`

   Set the password of the user, corresponds to ejabberd `setpass` event.

- `bool register( string $user, string $server, string $password )`

   Create a new user, corresponds to ejabberd `tryregister` event.

- `bool remove( string $user, string $server )`

   Remove a user, corresponds to ejabberd `removeuser` event.

- `bool removeSafely( string $user, string $server, string $password )`

   Remove a user if the password matches, corresponds to ejabberd `removeuser3` event.

### API

#### Constructor

The class constructor accepts two optional arguments:

    __construct( PDO $db = null, string $log = null )

- `$db`

   A PDO instance for database connections. The subclass may use this later through the `db()` method.

- `$log`

   A string denoting the path to where a log file should be written.

#### Database access

The PDO instance passed to the constructor is accessible through `$this->db()` inside subclass methods.

#### Logging

The subclass may use the `log` method for writing to the log:

    log( string $message, int $severity = LOG_ERR )

- `$message`

   The message to be logged.

- `$severity`

   One of the [`LOG_` constants](http://www.php.net/manual/en/function.syslog.php) to denote the message type.

##### Logging level

To filter certain types of log messages, set the static `$logLevel` variable to an array of `LOG_` constants:

    MyAuth::$logLevel[] = LOG_DEBUG;

(PHP's `LOG_` constants can't be used as a binary mask, so an array it is.)  
The default logging level includes pretty much everything except `LOG_DEBUG`.

Issues/Roadmap
--------------

- There's no protection against the database connection of the PDO instance timing out. Depending on your database this may or may not be an issue. Future versions of `EjabberdExternalAuth` should automatically try to keep the connection alive.
- Only PDO instances are allowed for database connections (to make it easier to implement the above point).

Also See
--------

- <http://www.process-one.net/docs/ejabberd/guide_en.html#extauth>
- <https://git.process-one.net/ejabberd/mainline/blobs/raw/2.1.x/doc/dev.html#htoc8>

Meta
----

Author: David Zentgraf (deceze@gmail.com)  
Version: 0.1

This code is provided as is, no guarantee for anything.
