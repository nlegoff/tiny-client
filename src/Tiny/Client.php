<?php
namespace Tiny;

use Guzzle\Http\Client as  GuzzleHttpClient;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Request;

/**
 * This class extends Guzzle\Http\Client and acts as a HTTP client.
 */
class Client extends GuzzleHttpClient
{
    public $baseUrl = 'http://api.tinypng.org';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct($this->baseUrl);
    }

    /**
     * To shrink a PNG image, post the data to the tinypng API shrink endpoint
     * 
     * @param   array|\Traversable  $images An array or a \Traversable object of \SplFileInfo objects
     * @return  array   An array of Guzzle\Http\Message\Response objects
     * 
     * @throws  \InvalidArgumentException
     */
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
    
    /**
     * Forge a POST Request object for the provided file
     * 
     * @param   \SplFileInfo $image An \SplFileInfo object
     * @return  Request
     */
    private function shrinkImageHttpRequest(\SplFileInfo $image)
    {
        return $this->post('/api/shrink', null, EntityBody::factory(fopen($image->getRealPath(), 'r')));
    }
}
