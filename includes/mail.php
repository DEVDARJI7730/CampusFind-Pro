<?php
/**
 * CampusFind Pro - Lightweight Native SMTP Mailer Client
 * Transmits HTML emails over TCP/IP sockets supporting SSL/TLS encryption.
 */

class SimpleSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $secure;

    public function __construct(string $host, int $port, string $user, string $pass, string $secure) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->secure = strtolower($secure);
    }

    /**
     * Sends an HTML email message via native SMTP sockets
     */
    public function send(string $to, string $subject, string $htmlBody): bool {
        $server = $this->host;
        if ($this->secure === 'ssl') {
            $server = 'ssl://' . $server;
        }

        // Open TCP Connection Socket (Timeout: 15s)
        $socket = @fsockopen($server, $this->port, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("SMTP Socket Connection Failed: $errstr ($errno)");
        }

        try {
            // Read Greeting response code (220)
            $this->readResponse($socket, 220);

            // Handshake (EHLO)
            $localhost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $this->sendCommand($socket, "EHLO $localhost", 250);

            // If STARTTLS is enabled, handshake again under TLS
            if ($this->secure === 'tls') {
                $this->sendCommand($socket, "STARTTLS", 220);
                
                // Enable cryptographic wrapper on stream
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("STARTTLS cryptographic handshake failed.");
                }

                // Resend EHLO now that connection is encrypted
                $this->sendCommand($socket, "EHLO $localhost", 250);
            }

            // Authentication (AUTH LOGIN)
            if (!empty($this->user) && !empty($this->pass)) {
                $this->sendCommand($socket, "AUTH LOGIN", 334);
                $this->sendCommand($socket, base64_encode($this->user), 334);
                $this->sendCommand($socket, base64_encode($this->pass), 235);
            }

            // Define Envelope Sender
            $this->sendCommand($socket, "MAIL FROM: <{$this->user}>", 250);

            // Define Recipient
            $this->sendCommand($socket, "RCPT TO: <{$to}>", 250);

            // Start Data transmission
            $this->sendCommand($socket, "DATA", 354);

            // Construct SMTP payload headers
            $headers = [
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "From: " . APP_NAME . " <{$this->user}>",
                "To: <{$to}>",
                "Subject: {$subject}",
                "Date: " . date('r'),
                "Message-ID: <" . time() . "-" . md5($to . $subject) . "@" . ($localhost ?: 'campusfindpro') . ">"
            ];

            // Payload structure (headers + double CRLF + body + termination dot)
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.\r\n";
            
            // Send payload & verify success response (250)
            fwrite($socket, $payload);
            $this->readResponse($socket, 250);

            // Quit session gracefully
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return true;

        } catch (Exception $e) {
            // Ensure socket is closed on errors
            @fclose($socket);
            throw $e;
        }
    }

    /**
     * Sends command and checks for expected SMTP status code
     */
    private function sendCommand($socket, string $command, int $expectedCode): void {
        fwrite($socket, $command . "\r\n");
        $this->readResponse($socket, $expectedCode);
    }

    /**
     * Reads multi-line response lines from the SMTP server stream
     */
    private function readResponse($socket, int $expectedCode): string {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // Standard SMTP multi-line indicators: if the 4th character is a space (e.g. "250 "), response is complete.
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP Protocol Error: Expected status $expectedCode, received response: " . trim($response));
        }
        return $response;
    }
}
