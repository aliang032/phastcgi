<?
# record types
define('FCGI_BEGIN_REQUEST', 1);
define('FCGI_ABORT_REQUEST', 2);
define('FCGI_END_REQUEST', 3);
define('FCGI_PARAMS', 4);
define('FCGI_STDIN', 5);
define('FCGI_STDOUT', 6);
define('FCGI_STDERR', 7);
define('FCGI_DATA', 8);
define('FCGI_GET_VALUES', 9);
define('FCGI_GET_VALUES_RESULT' , 10);
define('FCGI_UNKNOWN_TYPE', 11);
define('FCGI_MAXTYPE', FCGI_UNKNOWN_TYPE);
define('FCGI_NULL_REQUEST_ID', 0);

define('FCGI_VERSION_1', 1);
define('FCGI_KEEP_CONN', 1);

define('FCGI_RESPONDER', 1);
define('FCGI_AUTHORIZER', 2);
define('FCGI_FILTER', 3);

define('FCGI_REQUEST_COMPLETE', 0);
define('FCGI_CANT_MPX_CONN', 1);
define('FCGI_OVERLOADED', 2);
define('FCGI_UNKNOWN_ROLE', 3);       

class FastCGIRecord
{
	/**
	* Socket stream	
	*/
	private $sock;
    public $params;
    public $data = "";
	
    public function __construct($sock)
    {
		$this->sock = $sock;
        $this->version = FCGI_VERSION_1;
        $this->contentLengthB0 = 0;
        $this->contentLengthB1 = 0;
        $this->type = FCGI_UNKNOWN_TYPE;
        $this->paddingLength = 0;

        $this->contentLength = 0;
    }
    public function read()
    {
        $data = socket_read($this->sock, 8);
        $this->type = ord($data[1]);
        $this->requestId = (ord($data[2]) << 8) + ord($data[3]);
        $this->contentLength = (ord($data[4]) << 8) + ord($data[5]);
        $this->paddingLength = ord($data[6]);

        if($this->contentLength > 0)
            $data = socket_read($this->sock, $this->contentLength);

        if($this->type == FCGI_PARAMS)
        {
            $offset = 0;
            while($offset < $this->contentLength)
            {
                $namelen = ord($data[$offset++]);
                if($namelen > 127)
                {
                    $namelen = (($namelen & 0x7f) << 24) +
                                (ord($data[$offset++]) << 16) + 
                                (ord($data[$offset++]) << 8) + 
                                ord($data[$offset++]);
                }

                $valuelen = ord($data[$offset++]);
                if($valuelen > 127)
                {
                    $valuelen = (($valuelen & 0x7f) << 24) +
                                (ord($data[$offset++]) << 16) + 
                                (ord($data[$offset++]) << 8) + 
                                ord($data[$offset++]);
                }

                $name = substr($data, $offset, $namelen);
                $offset += $namelen;
                $value = substr($data, $offset, $valuelen);
                $offset += $valuelen;
                $this->params[$name] = $value;
            }
        }
    }

    public function write()
    {

        $this->contentLength = strlen($this->data);
        $this->contentLengthB0 = $this->contentLength & 0xff;
        $this->contentLengthB1 = $this->contentLength >> 8 & 0xff;

        socket_write($this->sock,
            chr($this->version).
            chr($this->type)."\x00\x00".
            chr($this->contentLengthB1).
            chr($this->contentLengthB0).
            chr($this->paddingLength).
            0x00);
        if($this->contentLength > 0)
            socket_write($this->sock, $this->data);
    }
}

class FastCGIRequest
{
    public function __construct($s)
    {
        $this->sock = $s;
        $this->headers = array();
        do {
            $rec = new FastCGIRecord($this->sock);
            $rec->read();
            if(is_array($rec->params))
                $this->headers = array_merge($this->headers, $rec->params);
        } while ($rec->type != FCGI_STDIN);
    }
}

class FastCGIReply
{
    public function __construct($s)
    {
        $this->sock = $s;
    }

    public function add_header($key, $value)
    {
        $this->headers[$key] = $value;
    }

    public function send_headers()
    {
        $record = new FastCGIRecord($this->sock);
        $record->type = FCGI_STDOUT;
        foreach($this->headers as $key => $value)
        {
            $record->data .= $key.": ".$value."\r\n";
            print $key.": ".$value."\r\n";
        }
        $record->data .= "\r\n";
        $record->write();
        $this->headers_sent = true;
    }

    public function send_data($data)
    {
        if(!isset($this->headers_sent))
            $this->send_headers();

        $record = new FastCGIRecord($this->sock);
        $record->type = FCGI_STDOUT;
        $record->data = $data;
        $record->write();
    }

    public function end_request()
    {
//        $this->send_data("");

        $record = new FastCGIRecord($this->sock);
        $record->type = FCGI_STDOUT;
        $record->write();


        $record = new FastCGIRecord($this->sock);
        $record->type = FCGI_END_REQUEST;
        $record->write();
    }
}

