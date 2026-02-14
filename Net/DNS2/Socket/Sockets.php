<?php

// vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:

/**
 * DNS Library for handling lookups and updates.
 *
 * PHP Version 5
 *
 * Copyright (c) 2010, Mike Pultz <mike@mikepultz.com>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Mike Pultz nor the names of his contributors
 *     may be used to endorse or promote products derived from this
 *     software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRIC
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Networking
 *
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2010 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 *
 * @version   SVN: $Id$
 *
 * @see      http://pear.php.net/package/Net_DNS2
 * @since     File available since Release 0.6.0
 */

/**
 * Socket handling class using the PHP sockets extension.
 *
 * The sockets extension is faster than the stream functions in PHP, but it's
 * not standard. So if the extension is loaded, then this class is used, if
 * it's not, then the Net_DNS2_Socket_Streams class is used.
 *
 * @category Networking
 *
 * @author   Mike Pultz <mike@mikepultz.com>
 * @license  http://www.opensource.org/licenses/bsd-license.php  BSD License
 *
 * @see     http://pear.php.net/package/Net_DNS2
 * @see      Net_DNS2_Socket
 */
class Net_DNS2_Socket_Sockets extends Net_DNS2_Socket
{
    /**
     * opens a socket connection to the DNS server.
     *
     * @return bool
     */
    public function open()
    {
        //
        // create the socket
        //
        if (true == Net_DNS2::isIPv4($this->host)) {
            $this->sock = @socket_create(
                AF_INET,
                $this->type,
                (Net_DNS2_Socket::SOCK_STREAM == $this->type) ? SOL_TCP : SOL_UDP
            );
        } elseif (true == Net_DNS2::isIPv6($this->host)) {
            $this->sock = @socket_create(
                AF_INET6,
                $this->type,
                (Net_DNS2_Socket::SOCK_STREAM == $this->type) ? SOL_TCP : SOL_UDP
            );
        } else {
            $this->last_error = 'invalid address type: '.$this->host;

            return false;
        }

        if (false === $this->sock) {
            $this->last_error = socket_strerror(socket_last_error());

            return false;
        }

        @socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 1);

        //
        // bind to a local IP/port if it's set
        //
        if (strlen($this->local_host) > 0) {
            $result = @socket_bind(
                $this->sock,
                $this->local_host,
                ($this->local_port > 0) ? $this->local_port : null
            );
            if (false === $result) {
                $this->last_error = socket_strerror(socket_last_error());

                return false;
            }
        }

        //
        // mark the socket as non-blocking
        //
        if (false === @socket_set_nonblock($this->sock)) {
            $this->last_error = socket_strerror(socket_last_error());

            return false;
        }

        //
        // connect to the socket; don't check for status here, we'll check it on the
        // socket_select() call so we can handle timeouts properly
        //
        @socket_connect($this->sock, $this->host, $this->port);

        $read = null;
        $write = [$this->sock];
        $except = null;

        //
        // select on write to check if the call to connect worked
        //
        $result = @socket_select($read, $write, $except, $this->timeout);
        if (false === $result) {
            $this->last_error = socket_strerror(socket_last_error());

            return false;
        }
        if (0 == $result) {
            $this->last_error = 'timeout on write select for connect()';

            return false;
        }

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
            @socket_close($this->sock);
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
        // select on write
        //
        $result = @socket_select($read, $write, $except, $this->timeout);
        if (false === $result) {
            $this->last_error = socket_strerror(socket_last_error());

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

            if (false === @socket_write($this->sock, $s)) {
                $this->last_error = socket_strerror(socket_last_error());

                return false;
            }
        }

        //
        // write the data to the socket
        //
        $size = @socket_write($this->sock, $data);
        if ((false === $size) || ($size != $length)) {
            $this->last_error = socket_strerror(socket_last_error());

            return false;
        }

        return true;
    }

    /**
     * reads a response from a DNS server.
     *
     * @param int   &$size    the size of the DNS packet read is passed back
     * @param mixed $max_size
     *
     * @return mixed returns the data on success and false on error
     */
    public function read(&$size, $max_size)
    {
        $read = [$this->sock];
        $write = null;
        $except = null;

        //
        // make sure our socket is non-blocking
        //
        if (false === @socket_set_nonblock($this->sock)) {
            $this->last_error = socket_strerror(socket_last_error());

            return false;
        }

        //
        // select on read
        //
        $result = @socket_select($read, $write, $except, $this->timeout);
        if (false === $result) {
            $this->last_error = socket_strerror(socket_last_error());

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
            if (($size = @socket_recv($this->sock, $data, 2, 0)) === false) {
                $this->last_error = socket_strerror(socket_last_error());

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
        if (false === @socket_set_block($this->sock)) {
            $this->last_error = socket_strerror(socket_last_error());

            return false;
        }

        //
        // read the data from the socket
        //
        // loop while reading since some OS's (specifically Win < 2003) don't support
        // MSG_WAITALL properly, so they may return with less data than is available.
        //
        // According to M$, XP and below don't support MSG_WAITALL at all; and there
        // also seems to be some issue in 2003 and 2008 where the MSG_WAITALL is
        // defined as 0, but if you actually pass 8 (which is the correct defined
        // value), it works as it's supposed to- so in these cases, it's just the
        // define that's incorrect- this is likely a PHP issue.
        //
        $data = '';
        $size = 0;

        while (1) {
            $chunk_size = @socket_recv($this->sock, $chunk, $length, MSG_WAITALL);
            if (false === $chunk_size) {
                $size = $chunk_size;
                $this->last_error = socket_strerror(socket_last_error());

                return false;
            }

            $data .= $chunk;
            $size += $chunk_size;

            $length -= $chunk_size;
            if (($length <= 0) || (Net_DNS2_Socket::SOCK_DGRAM == $this->type)) {
                break;
            }
        }

        return $data;
    }
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */
