<?php

/**
 * @todo Comment
 */
class RestClient
{
    protected $request;
    protected $headers;
    protected $url;
    protected $options;
    protected $acceptType;
    protected $acceptTypes;
    protected $method;
    protected $data;
    protected $infileReq;
    protected $infile;
    protected $infileSize;
    protected $info;
    protected $error;
    protected $response;
    
    public function __construct()
    {
        $this->headers   = array();
        $this->options   = array();
        $this->request   = curl_init();
        $this->infileReq = false;
        
        $this->addHeader(
            array(
                'Cache-Control' => 'max-age=0',
                'Connection' => 'keep-alive',
                'Keep-Alive' => '300')
        );
        
        $this->addAcceptType(
            array(
                'xml' => 'text/xml',
                'html' => 'text/html',
                'json' => 'application/json',
                'text' => 'text/plain',
                'form' => 'application/x-www-form-urlencoded')
        );
        
        $this->setOption(CURLOPT_HEADER, false);
        $this->setOption(CURLOPT_AUTOREFERER, true);
        $this->setOption(CURLOPT_FRESH_CONNECT, true);
        $this->setOption(CURLOPT_RETURNTRANSFER, true);
    }
    
    public function setUrl($url)
    {
        $this->url = $url;
        $this->setOption(CURLOPT_URL, $this->url);
        
        return $this;
    }
    
    public function addHeader($field, $value = false)
    {
        if (is_array($field)) {
            foreach ($field as $fld => $value) {
                $this->headers[$fld] = $fld . ': ' . $value;
            }
        } else {
            $this->headers[$field] = $field . ': ' . $value;
        }
        
        $this->headers = array_unique($this->headers);
        
        return $this;
    }
    
    public function clearHeaders()
    {
        $this->headers = array();
        
        return $this;
    }
    
    public function getHeaders()
    {
        return $this->headers;
    }
    
    public function setOption($option, $value = false)
    {
        if (is_array($option)) {
            foreach ($option as $opt => $value) {
                $this->options[$opt] = $value;
            }
        } else {
            $this->options[$option] = $value;
        }
        
        return $this;
    }
    
    public function addAcceptType($key, $type = false)
    {
        if (is_array($key)) {
            foreach ($key as $k => $type) {
                $this->acceptTypes[$k] = $type;
            }
        } else {
            $this->acceptTypes[$key] = $type;
        }
        
        return $this;
    }
    
    public function addBasicAuthentication($username, $password)
    {
        $pass = $username . ':' . $password;
        $this->setOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $this->setOption(CURLOPT_USERPWD, $pass);
        
        return $this;
    }
    
    public function ignoreSSL()
    {
        $this->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $this->setOption(CURLOPT_SSL_VERIFYPEER, false);
        
        return $this;
    }
    
    public function setAcceptType($type)
    {
        if (is_array($type)) {
            foreach ($type as $t) {
                if (!$this->validAcceptType($t)) {
                    throw new Exception('Invalid Accept Type');
                }
            }
            
            $type = implode(', ', $type);
        } else {
            if (!$this->validAcceptType($type)) {
                throw new Exception('Invalid Accept Type');
            }
        }
        
        $this->acceptType = $type;
        $this->addHeader('Content-Type', $this->acceptTypes[$this->acceptType]);
        
        return $this;
    }
    
    public function setMethod($method)
    {
        if (!$this->validMethod($method)) {
            throw new Exception('Invalid Method');
        }
        
        $this->method = $method;
        
        return $this;
    }
    
    public function setData($data)
    {
        $this->data = $data;
        
        return $this;
    }
    
    public function request()
    {
        if (empty($this->method)) {
            throw new Exception('Method Not Set');
        }
        
        if ($this->infileReq) {
            $this->buildInfile();
        }
        
        if (empty($this->url)) {
            throw new Exception('URL Not Set');
        }
        
        call_user_func(array($this, $this->method));
        
        $this->setOption(CURLOPT_HTTPHEADER, array_values($this->headers));
        $this->setOption(CURLOPT_RETURNTRANSFER, 1);
        
        curl_setopt_array($this->request, $this->options);
        $this->response = curl_exec($this->request);
        $this->info     = curl_getinfo($this->request);
        $this->error    = curl_error($this->request);

        return array(
            'response' => $this->response,
            'info' =>  $this->info,
            'error' => $this->error);
    }
    
    public function get()
    {
        $this->infileReq = false;
        $this->setOption(CURLOPT_HTTPGET, true);
        
        return $this;
    }
    
    public function post($infileReq = false)
    {
        $this->infileReq = $infileReq;
        $this->setOption(CURLOPT_POST, true);
        $this->setOption(CURLOPT_POSTFIELDS, $this->data);
        
        return $this;
    }
    
    public function put()
    {
        $this->infileReq = true;
        $this->setOption(CURLOPT_PUT, true);
        
        return $this;
    }
    
    public function delete()
    {
        $this->infileReq = true;
        $this->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');
        
        return $this;
    }
    
    protected function validAcceptType($type)
    {
        $validTypes = array_keys($this->acceptTypes);
        
        return in_array($type, $validTypes);
    }
    
    protected function validMethod($method)
    {
        return is_callable(array($this, $method));
    }
    
    protected function buildInfile()
    {
        if (!empty($this->data)) {
            $this->infile = fopen('php://temp', 'rw');
            $this->infileSize = fwrite($this->infile, $this->data);
            rewind($this->infile);
            
            if ($this->infileSize !== false) {
                $this->setOption(CURLOPT_INFILE, $this->infile);
                $this->setOption(CURLOPT_INFILESIZE, $this->infileSize);
            } else {
                throw new Exception('Could not write data to php://temp');
            }
        }
        
        return $this;
    }
    
    public function getResponse()
    {
        return $this->response;
    }
    
    public function getInfo()
    {
        return $this->info;
    }
}