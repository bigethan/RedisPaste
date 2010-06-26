<?php
/**
 * Redisent, a Redis interface for the modest
 * @author Justin Poliey <jdp34@njit.edu>
 * @copyright 2009 Justin Poliey <jdp34@njit.edu>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package Redisent
 */
 
define('CRLF', sprintf('%s%s', chr(13), chr(10)));

/**
 * Wraps native Redis errors in friendlier PHP exceptions
 */
class RedisException extends Exception {
}

/**
 * Redisent, a Redis interface for the modest among us
 */
class Redisent {

    /**
     * Socket connection to the Redis server
     * @var resource
     * @access private
     */
    private $__sock;
    
    /**
     * Redis bulk commands, they are sent in a slightly different format to the server
     * @var array
     * @access private
     */
    private $bulk_cmds = array(
        'SET',   'GETSET', 'SETNX', 'ECHO',
        'RPUSH', 'LPUSH',  'LSET',  'LREM',
        'SADD',  'SREM',   'SMOVE', 'SISMEMBER'
    );
    
    /**
     * Creates a Redisent connection to the Redis server on host {@link $host} and port {@link $port}.
     *
     * @access public
     * @param mixed $host The hostname of the Redis server
     * @param mixed $port. (default: 6379) The port number of the Redis server
     * @return void
     */
    public function __construct($host, $port = 6379) {        
        $this->__sock = fsockopen($host, $port, $errno, $errstr);
        if (!$this->__sock) {
            throw new Exception("{$errno} - {$errstr}");
        }
    }
    
    /**
     * When the class is done with, make sure to close up
     * the leftover connection
     * 
     * @access private
     * @return void
     */
    public function __destruct() {
        fclose($this->__sock);
    }

    /**
     * Translates method calls made on the Redis object into redis commands
     * the arguments for the command are expected in a space delimited format.
     * 
     * @access private
     * @param mixed $name the name of the redis command (eg: mget)
     * @param mixed $args the argumetns passed to the command(eg: "key1 key2 key3")
     * @return void
     */
    private function __call($name, $args) {
        /* Build the Redis protocol command */
        $name = strtoupper($name);
        if (in_array($name, $this->bulk_cmds)) {
            $value = array_pop($args);
            $command = sprintf("%s %s %d%s%s%s", $name, trim(implode(' ', $args)), strlen($value), CRLF, $value, CRLF);
        }
        else {
            $command = sprintf("%s %s%s", $name, trim(implode(' ', $args)), CRLF);
        }
        /* Open a Redis connection and execute the command */
        fwrite($this->__sock, $command);
        /* Parse the response based on the reply identifier */
        $reply = trim(fgets($this->__sock));
        return $this->parseReply($reply);   
    }
    
    /**
     * given a redis reply, suss out the type and read the value
     * 
     * @access private
     * @param mixed $reply
     * @return void
     */
    private function parseReply($reply) {
    
        switch (substr($reply, 0, 1)) {
            /* Error reply */
            case '-':
                throw new RedisException(substr(trim($reply), 4));
                break;
            /* Integer reply */
            case ':':
            /* Inline reply */
            case '+':
                $response = substr(trim($reply), 1);
                break;
            /* Bulk reply */
            case '$':
                if ($reply == '$-1') {
                    $response = null;
                    break;
                }
                $size = substr($reply, 1) + 2;
                $responseStream = '';
                $bytesRead = 0;
                while ($bytesRead < $size) {
                    /* 800 bytes was the largest size that would work for 
                     * values in the ~40k byte range without php losing
                     * the count and returning a truncated / garbled
                     * response. Theoretically the value should be PHP's
                     * max fread() size of 8192
                     */
                    $readSize = min(800, $size - $bytesRead);
                    $responseStream .= fread($this->__sock, $readSize);
                    $bytesRead += $readSize;
                }
                $response = trim($responseStream);
            break;
            /* Multi-key reply */
            case '*':
                $count = substr($reply, 1);
                if ($count == '-1') {
                    return null;
                }
                $response = array();
                for ($i = 0; $i < $count; $i++) {
                    $subReply = trim(fgets($this->__sock, 512));                                       
                    $response[] = $this->parseReply($subReply);
                }
                break;
            default:
                throw new RedisException("invalid server response: {$reply}");
                break;
        }
        /* Party on */
        return $response;
    }
}