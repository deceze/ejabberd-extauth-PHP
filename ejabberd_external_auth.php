<?php

/**
 * A class extending EjabberdExternalAuth may optionally implement this interface
 * to support additional, non-critical methods for adding and removing users.
 */
interface EjabberdExternalAuth_UserManagement {

    /**
     * Corresponds to `setpass` operation.
     */
    public function setPassword($user, $server, $password);
    
    /**
     * Corresponds to `tryregister` operation.
     */
    public function register($user, $server, $password);

    /**
     * Corresponds to `removeuser` operation.
     */
    public function remove($user, $server);

    /**
     * Corresponds to `removeuser3` operation.
     */
    public function removeSafely($user, $server, $password);

}


abstract class EjabberdExternalAuth {

    private $db     = null;
    private $log    = null;
    private $stdin  = null;
    private $stdout = null;
    
    public static $logLevel = array(LOG_EMERG, LOG_ALERT, LOG_CRIT, LOG_ERR, LOG_WARNING, LOG_INFO, LOG_KERN);

    /**
     * Corresponds to `auth` operation.
     */
    abstract protected function authenticate($user, $server, $password);

    /**
     * Corresponds to `isuser` operation.
     */
    abstract protected function exists($user, $server);


    final public function __construct(PDO $db = null, $log = null) {
        set_error_handler(array($this, 'errorHandler'));
        
        $this->db     = $db;
        $this->stdin  = fopen('php://stdin', 'rb');
        $this->stdout = fopen('php://stdout', 'wb');

        if ($log) {
            $this->log = fopen($log, 'a');
        }

        $this->log('Starting auth service...', LOG_INFO);

        try {
            $this->work();
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }
        
        $this->log('Exiting...', LOG_INFO);
    }
    
    final protected function db() {
        return $this->db;
    }

    final private function work() {
        $this->log('Entering event loop...', LOG_INFO);
    
        while (true) {
            try {
                $message = $this->read();
                $message = $this->parseMessage($message);
            } catch (UnexpectedValueException $e) {
                $this->log($e->getMessage());
                continue;
            }

            $this->log('Received message: ' . json_encode($message), LOG_DEBUG);

            $response = false;

            switch ($message['command']) {
                case 'auth' :
                    $response = $this->authenticate($message['user'], $message['server'], $message['password']);
                    break;
                case 'isuser' :
                    $response = $this->exists($message['user'], $message['server']);
                    break;
            }
            
            if ($this instanceof EjabberdExternalAuth_UserManagement) {
                switch ($message['command']) {
                    case 'setpass' :
                        $response = $this->setPassword($message['user'], $message['server'], $message['password']);
                        break;
                    case 'tryregister' :
                        $response = $this->register($message['user'], $message['server'], $message['password']);
                        break;
                    case 'removeuser' :
                        $response = $this->remove($message['user'], $message['server']);
                        break;
                    case 'removeuser3' :
                        $response = $this->removeSafely($message['user'], $message['server'], $message['password']);
                        break;
                }
            }

            $this->respond($response);
        }
    }

    final private function read() {
        $length = fgets($this->stdin, 3);

        if (feof($this->stdin)) {
            throw new RuntimeException('Pipe broken');
        }

        $length = current(unpack('n', $length));
        if (!$length) {
            throw new UnexpectedValueException("Invalid length value, won't continue reading");
        }

        $message = fgets($this->stdin, $length + 1);
        return $message;
    }

    final private function parseMessage($message) {
        $message = explode(':', $message);
        if (count($message) < 3) {
            throw new UnexpectedValueException('Message is too short: ' . join(':', $message));
        }
        
        list($command, $user, $server) = $message;
        $password = isset($message[3]) ? $message[3] : null;
        return compact('command', 'user', 'server', 'password');
    }

    final private function respond($status) {
        $message = pack('nn', 2, (int)$status);
        $this->log('Sending response: ' . bin2hex($message), LOG_DEBUG);
        fwrite($this->stdout, $message);
    }

    final protected function log($message, $severity = LOG_ERR) {
        if ($this->log && in_array($severity, self::$logLevel, true)) {
            static $types = array(
                LOG_EMERG   => 'EMERGENCY',
                LOG_ALERT   => 'ALERT',
                LOG_CRIT    => 'CRITICAL',
                LOG_ERR     => 'ERROR',
                LOG_WARNING => 'WARNING',
                LOG_NOTICE  => 'NOTICE',
                LOG_INFO    => 'INFO',
                LOG_DEBUG   => 'DEBUG',
                LOG_KERN    => 'KERNEL'
            );
            
            $message = sprintf('%s <%s> %9s: %s', date('Y-m-d H:i:s'), getmypid(), isset($types[$severity]) ? $types[$severity] : $types[LOG_ERR], $message);
            fwrite($this->log, $message . PHP_EOL);
        }
    }
    
    final protected function errorHandler($errno, $errstr, $errfile, $errline, array $errcontext) {
        $this->log("$errstr in $errfile on line $errline", $errno);
        return false;
    }

}