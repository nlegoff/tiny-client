<?php

use Nlegoff\Tiny\FileIterator;

class FileIteratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers  Tiny\FileIterator::__construct
     * @covers  Tiny\FileIterator::count
     * @covers  Tiny\FileIterator::isPngFile
     */
    public function testRecursive()
    {
        $files = new FileIterator(__DIR__ . '/../../../resources/image_recursive');

        $this->assertEquals(2, count($files));

        return $files;
    }

    /**
     * @covers  Tiny\FileIterator::__construct
     * @covers  Tiny\FileIterator::count
     */
    public function testNoRecursive()
    {
        $files = new FileIterator(__DIR__ . '/../../../resources/image_recursive', null, false);

        $this->assertEquals(1, count($files));

        return $files;
    }

    /**
     * @covers  Tiny\FileIterator::accept
     */
    public function testWithCallback()
    {
        $files = new FileIterator(__DIR__ . '/../../../resources/image_recursive', function($file) {
            return $file->getBasename() !== 'troll.png';
        }, false);

        $this->assertEquals(0, count($files));
    }

    /**
     * @expectedException InvalidArgumentException
     * @covers  Tiny\FileIterator::__construct
     */
    public function testBadArgument()
    {
        new FileIterator('non-existant');
    }

    /**
     * @depends testNoRecursive
     * @covers  Tiny\FileIterator::findFileByName
     */
    public function testFindFileByName($iterator)
    {
        foreach ($iterator as $file) {
            $filename = $file->getPathname();
            break;
        }

        $this->assertNull($iterator->findFileByName('toto'));
        $this->assertNotNull($iterator->findFileByName($filename));
    }
}
