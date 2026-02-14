<?php

/**
 * DNS Library for handling lookups and updates.
 *
 * Copyright (c) 2020, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 *
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2020 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 *
 * @see      https://netdns2.com/
 * @since     File available since Release 0.6.0
 */

// check to see if the socket defines exist; if they don't, then define them
if (false == defined('SOCK_STREAM')) {
    define('SOCK_STREAM', 1);
}
if (false == defined('SOCK_DGRAM')) {
    define('SOCK_DGRAM', 2);
}

/**
 * Socket handling class using the PHP Streams.
 */
class Net_DNS2_Socket
{
    // type of sockets
    public const SOCK_STREAM = SOCK_STREAM;
    public const SOCK_DGRAM = SOCK_DGRAM;

    // the last error message on the object
    public $last_error;

    // date the socket connection was created, and the date it was last used
    public $date_created;
    public $date_last_used;
    private $sock;
    private $type;
    private $host;
    private $port;
    private $timeout;
    private $context;

    // the local IP and port we'll send the request from
    private $local_host;
    private $local_port;

    /**
     * constructor - set the port details.
     *
     * @param int    $type    the socket type
     * @param string $host    the IP address of the DNS server to connect to
     * @param int    $port    the port of the DNS server to connect to
     * @param int    $timeout the timeout value to use for socket functions
     */
    public function __construct($type, $host, $port, $timeout)
    {
        $this->type = $type;
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->date_created = microtime(true);
    }

    /**
     * destructor.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * sets the local address/port for the socket to bind to.
     *
     * @param string $address the local IP address to bind to
     * @param mixed  $port    the local port to bind to, or 0 to let the socket
     *                        function select a port
     *
     * @return bool
     */
    public function bindAddress($address, $port = 0)
    {
        $this->local_host = $address;
        $this->local_port = $port;

        return true;
    }

    /**
     * opens a socket connection to the DNS server.
     *
     * @return bool
     */
    public function open()
    {
        //
        // create a list of options for the context
        //
        $opts = ['socket' => []];

        //
        // bind to a local IP/port if it's set
        //
        if (strlen($this->local_host) > 0) {
            $opts['socket']['bindto'] = $this->local_host;
            if ($this->local_port > 0) {
                $opts['socket']['bindto'] .= ':'.$this->local_port;
            }
        }

        //
        // create the context
        //
        $this->context = @stream_context_create($opts);

        //
        // create socket
        //

        switch ($this->type) {
            case Net_DNS2_Socket::SOCK_STREAM:
                if (true == Net_DNS2::isIPv4($this->host)) {
                    $this->sock = @stream_socket_client(
                        'tcp://'.$this->host.':'.$this->port,
                        $errno,
                        $errstr,
                        $this->timeout,
                        STREAM_CLIENT_CONNECT,
                        $this->context
                    );
                } elseif (true == Net_DNS2::isIPv6($this->host)) {
                    $this->sock = @stream_socket_client(
                        'tcp://['.$this->host.']:'.$this->port,
                        $errno,
                        $errstr,
                        $this->timeout,
                        STREAM_CLIENT_CONNECT,
                        $this->context
                    );
                } else {
                    $this->last_error = 'invalid address type: '.$this->host;

                    return false;
                }

                break;

            case Net_DNS2_Socket::SOCK_DGRAM:
                if (true == Net_DNS2::isIPv4($this->host)) {
                    $this->sock = @stream_socket_client(
                        'udp://'.$this->host.':'.$this->port,
                        $errno,
                        $errstr,
                        $this->timeout,
                        STREAM_CLIENT_CONNECT,
                        $this->context
                    );
                } elseif (true == Net_DNS2::isIPv6($this->host)) {
                    $this->sock = @stream_socket_client(
                        'udp://['.$this->host.']:'.$this->port,
                        $errno,
                        $errstr,
                        $this->timeout,
                        STREAM_CLIENT_CONNECT,
                        $this->context
                    );
                } else {
                    $this->last_error = 'invalid address type: '.$this->host;

                    return false;
                }

                break;

            default:
                $this->last_error = 'Invalid socket type: '.$this->type;

                return false;
        }

        if (false === $this->sock) {
            $this->last_error = $errstr;

            return false;
        }

        //
        // set it to non-blocking and set the timeout
        //
        @stream_set_blocking($this->sock, 0);
        @stream_set_timeout($this->sock, $this->timeout);

        return true;
    }

    /**
     * closes a socket connection to the DNS server.
     *
     * @return bool
     */
    public function close()
    {
        if (true === is_resource($this->sock)) {
            @fclose($this->sock);
        }

        return true;
    }

    /**
     * writes the given string to the DNS server socket.
     *
     * @param string $data a binary packed DNS packet
     *
     * @return bool
     */
    public function write($data)
    {
        $length = strlen($data);
        if (0 == $length) {
            $this->last_error = 'empty data on write()';

            return false;
        }

        $read = null;
        $write = [$this->sock];
        $except = null;

        //
        // increment the date last used timestamp
        //
        $this->date_last_used = microtime(true);

        //
        // select on write
        //
        $result = stream_select($read, $write, $except, $this->timeout);
        if (false === $result) {
            $this->last_error = 'failed on write select()';

            return false;
        }
        if (0 == $result) {
            $this->last_error = 'timeout on write select()';

            return false;
        }

        //
        // if it's a TCP socket, then we need to packet and send the length of the
        // data as the first 16bit of data.
        //
        if (Net_DNS2_Socket::SOCK_STREAM == $this->type) {
            $s = chr($length >> 8).chr($length);

            if (false === @fwrite($this->sock, $s)) {
                $this->last_error = 'failed to fwrite() 16bit length';

                return false;
            }
        }

        //
        // write the data to the socket
        //
        $size = @fwrite($this->sock, $data);
        if ((false === $size) || ($size != $length)) {
            $this->last_error = 'failed to fwrite() packet';

            return false;
        }

        return true;
    }

    /**
     * reads a response from a DNS server.
     *
     * @param int &$size    the size of the DNS packet read is passed back
     * @param int $max_size the max data size returned
     *
     * @return mixed returns the data on success and false on error
     */
    public function read(&$size, $max_size)
    {
        $read = [$this->sock];
        $write = null;
        $except = null;

        //
        // increment the date last used timestamp
        //
        $this->date_last_used = microtime(true);

        //
        // make sure our socket is non-blocking
        //
        @stream_set_blocking($this->sock, 0);

        //
        // select on read
        //
        $result = stream_select($read, $write, $except, $this->timeout);
        if (false === $result) {
            $this->last_error = 'error on read select()';

            return false;
        }
        if (0 == $result) {
            $this->last_error = 'timeout on read select()';

            return false;
        }

        $data = '';
        $length = $max_size;

        //
        // if it's a TCP socket, then the first two bytes is the length of the DNS
        // packet- we need to read that off first, then use that value for the
        // packet read.
        //
        if (Net_DNS2_Socket::SOCK_STREAM == $this->type) {
            if (($data = fread($this->sock, 2)) === false) {
                $this->last_error = 'failed on fread() for data length';

                return false;
            }
            if (0 == strlen($data)) {
                $this->last_error = 'failed on fread() for data length';

                return false;
            }

            $length = ord($data[0]) << 8 | ord($data[1]);
            if ($length < Net_DNS2_Lookups::DNS_HEADER_SIZE) {
                return false;
            }
        }

        //
        // at this point, we know that there is data on the socket to be read,
        // because we've already extracted the length from the first two bytes.
        //
        // so the easiest thing to do, is just turn off socket blocking, and
        // wait for the data.
        //
        @stream_set_blocking($this->sock, 1);

        //
        // read the data from the socket
        //
        $data = '';

        //
        // the streams socket is weird for TCP sockets; it doesn't seem to always
        // return all the data properly; but the looping code I added broke UDP
        // packets- my fault-
        //
        // the sockets library works much better.
        //
        if (Net_DNS2_Socket::SOCK_STREAM == $this->type) {
            $chunk = '';
            $chunk_size = $length;

            //
            // loop so we make sure we read all the data
            //
            while (1) {
                $chunk = fread($this->sock, $chunk_size);
                if (false === $chunk) {
                    $this->last_error = 'failed on fread() for data';

                    return false;
                }

                $data .= $chunk;
                $chunk_size -= strlen($chunk);

                if (strlen($data) >= $length) {
                    break;
                }
            }
        } else {
            //
            // if it's UDP, it's a single fixed-size frame, and the streams library
            // doesn't seem to have a problem reading it.
            //
            $data = fread($this->sock, $length);
            if (false === $length) {
                $this->last_error = 'failed on fread() for data';

                return false;
            }
        }

        $size = strlen($data);

        return $data;
    }
}
