<?php

/**
 * This file is part of a markocupic Contao Bundle
 *
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    zip-bundle
 * @license    MIT
 * @see        https://github.com/markocupic/zip-bundle
 *
 */

declare(strict_types=1);

namespace Markocupic\ZipBundle\Zip;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Zip
 *
 * @package Markocupic\ZipBundle\Zip
 */
class Zip
{
    /** @var \ZipArchive */
    private $zip;

    /** @var array */
    private $arrStorage = [];

    /** @var string */
    private $strStripSourcePath;

    /**
     * @var bool
     */
    private $ignoreDotFiles = true;

    /**
     * Zip constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {

        if (!extension_loaded('zip'))
        {
            throw new \Exception('PHP Extension "ext-zip" not loaded.');
        }

        return $this;
    }

    /**
     * Strip the source path in the zip archive
     *
     * @param string $path
     * @return $this
     */
    public function stripSourcePath(string $path): self
    {

        $this->strStripSourcePath = $path;
        return $this;
    }

    /**
     * Ignore dot files/folders like .ecs, .gitattribute, etc.
     *
     * @param bool $blnIgnore
     */
    public function ignoreDotFiles(bool $blnIgnore): self
    {
        $this->ignoreDotFiles = $blnIgnore;
        return $this;
    }

    /**
     * Zip directory recursively and store it to a predefined destination
     *
     * @param string $source
     * @return $this
     * @throws \Exception
     */
    public function addFile(string $source): self
    {

        if (!is_file($source))
        {
            throw new \Exception(sprintf('File "%s" not found.', $source));
        }

        $this->addToStorage($source);

        return $this;
    }

    /**
     * Add files from the directory
     *
     * @param string $source
     * @return $this
     * @throws \Exception
     */
    public function addDir(string $source): self
    {

        if (!is_dir($source))
        {
            throw new \Exception(sprintf('Source directory "%s" not found.', $source));
        }

        $this->addToStorage($source, 0, true);

        return $this;
    }

    /**
     *
     * @param string $source
     * @param int $intDepth
     * @param bool $blnFilesOnly
     * @return $this
     * @throws \Exception
     */
    public function addDirRecursive(string $source, int $intDepth = -1, bool $blnFilesOnly = false): self
    {

        if (!is_dir($source))
        {
            throw new \Exception(sprintf('Source directory "%s" not found.', $source));
        }

        $this->addToStorage($source, $intDepth, $blnFilesOnly);

        return $this;
    }

    /**
     * @param string $destinationPath
     * @return bool
     * @throws \Exception
     */
    public function run(string $destinationPath): bool
    {

        if ($this->zip($destinationPath))
        {
            $this->reset();

            return true;
        }

        return false;
    }

    /**
     * @param string $filename
     */
    public function downloadArchive(string $filename)
    {

        if (!is_file($filename))
        {
            throw new FileNotFoundException(sprintf('File "%s" not found.', $filename));
        }
        $response = new Response(file_get_contents($filename));
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . basename($filename) . '"');
        $response->headers->set('Content-length', filesize($filename));

        $response->send();
    }

    /**
     * @return array
     */
    public function getStorage(): array
    {

        return $this->arrStorage;
    }

    /**
     * @return $this
     */
    public function purgeStorage(): self
    {

        $this->arrStorage = [];
        return $this;
    }

    /**
     * Add files/directories (recursive or not) to the storage
     *
     * @param string $source
     * @param int $intDepth
     * @param bool $blnFilesOnly
     * @return $this
     */
    private function addToStorage(string $source, int $intDepth = -1, bool $blnFilesOnly = false): self
    {

        if (!file_exists($source))
        {
            throw new FileNotFoundException(sprintf('File or folder "%s" not found', $source));
        }

        if (is_dir($source))
        {
            $finder = new Finder();

            $finder->ignoreDotFiles($this->ignoreDotFiles);

            if ($intDepth > -1)
            {
                if ($blnFilesOnly)
                {
                    $finder->files();
                }
                $finder->depth('== ' . $intDepth);
            }
            else
            {
                if ($blnFilesOnly)
                {
                    $finder->files();
                }
            }

            foreach ($finder->in($source) as $file)
            {
                $this->arrStorage[] = $file->getRealPath();
            }
        }
        else
        {
            $this->arrStorage[] = $source;
        }

        $this->arrStorage = array_unique($this->arrStorage);

        return $this;
    }

    /**
     * @param string $destination
     * @return bool
     * @throws \Exception
     */
    private function zip(string $destination): bool
    {

        if (!preg_match('/\.zip$/', $destination))
        {
            throw new \Exception(
                sprintf(
                    'Illegal destination path defined "%s". Destination must be a valid path (f.ex. "file/path/to/archive.zip".',
                    $destination
                )
            );
        }

        if (!is_dir(dirname($destination)))
        {
            throw new \Exception(sprintf('Destination directory "%s" not found.', $destination));
        }

        $this->zip = new \ZipArchive();
        $this->zip->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Check if $this->strStripSourcePath stands at the beginning of each file path
        $blnStripSourcePath = false;
        if (strlen((string) $this->strStripSourcePath))
        {
            $blnStripSourcePath = true;
            foreach ($this->arrStorage as $res)
            {
                if (strpos($this->strStripSourcePath, $res) != 0)
                {
                    $blnStripSourcePath = false;
                    break;
                }
            }
        }

        foreach ($this->arrStorage as $res)
        {
            if (is_dir($res))
            {
                // Add empty dir (and remove the source path)
                if ($blnStripSourcePath === true)
                {
                    $this->zip->addEmptyDir(str_replace($this->strStripSourcePath . DIRECTORY_SEPARATOR, '', $res));
                }
                else
                {
                    $this->zip->addEmptyDir(ltrim($res, DIRECTORY_SEPARATOR));
                }
            }
            else
            {
                if (is_file($res))
                {
                    // Add file (and remove the source path)
                    if ($blnStripSourcePath === true)
                    {
                        $this->zip->addFromString(str_replace($this->strStripSourcePath . DIRECTORY_SEPARATOR, '', $res), file_get_contents($res));
                    }
                    else
                    {
                        $this->zip->addFromString(ltrim($res, DIRECTORY_SEPARATOR), file_get_contents($res));
                    }
                }
            }
        }
        $this->zip->close();

        return true;
    }

    /**
     * Reset to defaults
     *
     * @return $this
     */
    private function reset(): self
    {

        $this->zip = null;
        $this->purgeStorage();
        $this->strStripSourcePath = null;
        return $this;
    }

}
