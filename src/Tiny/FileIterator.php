<?php

namespace Tiny;

class FileIterator extends \FilterIterator implements \Countable 
{
    private $bag;
    private $callback;
    private $recursive;
    
    public function __construct($path, \Closure $callback = null, $recursive = true)
    {
        if (is_dir($path)) {
            $iterator = $recursive ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::CURRENT_AS_FILEINFO)) : new \DirectoryIterator($path);
        } elseif (is_file($path)) {
            $iterator = new \ArrayIterator(array(new \SplFileInfo($path)));
        } else {
            throw new \LogicException();
        }
        
        parent::__construct($iterator);
        
        $this->bag = array();
        $this->recursive = $recursive;
        $this->callback = $callback;
        
        foreach($this as $that) {
            //trigger count on constructor
        }
    }

    public function accept()
    {
        $accept = false;
        $file = $this->getInnerIterator()->current();
        
        if ($file->isFile() && $this->isPngFile($file)) {
            $accept = true;
            
            if (is_callable($this->callback)) {
                $accept = call_user_func($this->callback, $file);
            }
        }
        
        $filename = md5($file->getPathname());
        
        if ($accept) {
            $this->bag[$filename] = $file;
        }
        
        return $accept;
    }
    
    public function count()
    {
       return count($this->bag);
    }

    public function findFileByName($filename)
    {
        return isset($this->bag[md5($filename)]) ? $this->bag[md5($filename)] : null;
    }
    
    private function isPngFile(\SplFileInfo $image)
    {
        // define the array of first 8 png bytes
        $png_header = array(137, 80, 78, 71, 13, 10, 26, 10);
        // or: array(0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A);

        $f = fopen($image->getPathname(), 'r');
        $header = fread($f, 8);
        fclose($f);

        return count(array_diff($png_header, array_map('ord', preg_split(
            '//',
            $header,
            -1,
            PREG_SPLIT_NO_EMPTY
        )))) === 0;
    }
}