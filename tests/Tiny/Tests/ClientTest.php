<?php

use Tiny\Client;
use Guzzle\Plugin\Mock\MockPlugin;
use Guzzle\Http\Message\Response;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Tiny\Client::__construct
     */
    public function testNewClient()
    {
        return new client();
    }
    
    /**
     * @depends testNewClient
     * @expectedException InvalidArgumentException
     * @covers Tiny\Client::shrink
     */
    public function testShrinkBadArgument($client)
    {
        $client->shrink('an image');
    }
    
    /**
     * @covers Tiny\Client::shrink
     */
    public function testShrink()
    {
        $returnValue = new Response('hellow');
        $plugin = new MockPlugin(array($returnValue));
        
        $client = new Client();
        $client->addSubscriber($plugin);
        
        $responses = $client->shrink(array(new \SplFileInfo(__FILE__)));
        
        $this->assertTrue(is_array($responses));
        $this->assertEquals($returnValue, end($responses));
    }
}
