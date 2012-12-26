<?php

use Guzzle\Http\Client as  GuzzleHttpClient;
use Guzzle\Common\Exception\ExceptionCollection;
use Guzzle\Http\EntityBody;

class Client extends GuzzleHttpClient
{
    public function _construct()
    {
        parent::__construct($baseUrl = 'http://tinypng.org/api');
    }
    
    public function shrink(\SplFileInfo $image, $to)
    {
        $body = EntityBody::factory(fopen($image->getRealPath(), 'r'));
        $body->compress();
        
        $data = $this->post('/shrink', null, $body)->send()->json();
        
        if (isset($data['code'])) {
            throw new Exception("#{$data['code']}: #{$data['message']}");
        } else {
            
            file_put_contents($file, $current);
        }
    }
    
    public function shrinkMulti(array $images)
    {
        $requests = array();
        
        foreach($images as $image) {
            $body = EntityBody::factory(fopen($image->getRealPath(), 'r'));
            $body->compress();
        
            $requests[] = $this->post('/shrink', null, $body);
        }
        
        $responses = $this->send($requests);
    }
}