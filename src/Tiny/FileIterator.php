<?php

namespace Tiny;

/**
 * This class catches all the png file into an iterator
 */
class FileIterator extends \FilterIterator implements \Countable
{
    private $bag;
    private $callback;
    private $recursive;

    /**
     * Constructor
     *
     * @param string   $path      A path to a file or a directory
     * @param \Closure $callback  A callback to apply in the accept method
     * @param boolean  $recursive Tells wether or not to recurse in the provided directories
     *
     * @throws \InvalidArgumentException In the case an invalid path is provided
     */
    public function __construct($path, \Closure $callback = null, $recursive = true)
    {
        if (is_dir($path)) {
            $iterator = $recursive ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::CURRENT_AS_FILEINFO)) : new \DirectoryIterator($path);
        } elseif (is_file($path)) {
            $iterator = new \ArrayIterator(array(new \SplFileInfo($path)));
        } else {
            throw new \InvalidArgumentException();
        }

        parent::__construct($iterator);

        $this->bag = array();
        $this->recursive = $recursive;
        $this->callback = $callback;

        foreach ($this as $that) {
            // Tricks to fill $this->bag property by iterating, and call count() after object instanciation
        }
    }

    /**
     * @inheritdoc
     */
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

        if ($accept) {
            $this->bag[md5($file->getPathname())] = $file;
        }

        return $accept;
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
       return count($this->bag);
    }

    /**
     * Finds a file by it's name inside the iterator
     *
     * @param  string            $filename A pathname to a file
     * @return null|\SplFIleInfo
     */
    public function findFileByName($filename)
    {
        return isset($this->bag[md5($filename)]) ? $this->bag[md5($filename)] : null;
    }

    /**
     * Checks whether or not the provided file is a png file
     *
     * @param  \SplFileInfo $image A \SplFileInfo instance
     * @return boolean
     */
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
