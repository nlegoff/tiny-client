<?php
namespace Tiny;

use Guzzle\Http\Client as  GuzzleHttpClient;
use Guzzle\Http\EntityBody;

class Client extends GuzzleHttpClient
{
    public $baseUrl = 'http://api.tinypng.org';

    public function __construct()
    {
        parent::__construct($this->baseUrl);
    }

    private function shrinkImageHttpRequest(\SplFileInfo $image)
    {
        return $this->post('/api/shrink', null, EntityBody::factory(fopen($image->getRealPath(), 'r')));
    }

    public function shrink($images)
    {
        if (!is_array($images) && !$images instanceof \Traversable) {
            throw new \InvalidArgumentException();
        }
        
        $requests = array();

        foreach ($images as $key => $image) {
            $requests[$key] = $this->shrinkImageHttpRequest($image);
        }

        return $this->send($requests);
    }
}
