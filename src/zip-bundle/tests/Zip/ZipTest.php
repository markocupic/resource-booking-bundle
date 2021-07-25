<?php

/**
 * This file is part of a markocupic Contao Bundle
 *
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    zip bundle
 * @license    MIT
 * @see        https://github.com/markocupic/zip-bundle
 *
 */

declare(strict_types=1);

namespace Markocupic\ZipBundle\Tests\Zip;

use Contao\TestCase\ContaoTestCase;
use Markocupic\ZipBundle\Zip\Zip;

/**
 * Class ZipTest
 *
 * @package Markocupic\ZipBundle\Tests\Zip
 */
class ZipTest extends ContaoTestCase
{
    /** @var Zip */
    private $zip;

    /** @var array  */
    private $arrRes;

    /** @var string */
    private $zipDestPath;


    public function setUp(): void
    {

        parent::setUp();
        $this->zip = new Zip();
        $this->arrRes = [
            [
                'folder' => 'dir1'
            ],
            [
                'file'    => 'dir1/file_1_1.txt',
                'content' => 'FooBar'
            ],
            [
                'folder' => 'dir1/subdir1_1'
            ],
            [
                'folder' => 'dir1/subdir1_2'
            ],
            [
                'file'    => 'dir1/subdir1_1/file_1_1_1.txt',
                'content' => 'FooBar'
            ],
            [
                'file'    => 'dir1/subdir1_1/file_1_1_2.txt',
                'content' => 'FooBar'
            ],
            [
                'file'    => 'dir1/subdir1_2/file_1_2_1.txt',
                'content' => 'FooBar'
            ],
        ];

        $this->zipDestPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myzip.zip';

        // Delete files
        $this->delTempFiles(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->arrRes[0]['folder']);

        foreach ($this->arrRes as $res)
        {
            if (isset($res['folder']))
            {
                mkdir(sys_get_temp_dir() . '/' . $res['folder'], 0777, true);
            }
            else
            {
                $fh = fopen(sys_get_temp_dir() . '/' . $res['file'], 'w');
                fwrite($fh, $res['content']);
                fclose($fh);
            }
        }
    }

    public function tearDown(): void
    {

        // Delete files
        $this->delTempFiles(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->arrRes[0]['folder']);
        $this->delTempFiles($this->zipDestPath);
    }

    public function testInstantiation(): void
    {

        $this->assertInstanceOf(Zip::class, new Zip());
    }

    /**
     * @throws \Exception
     */
    public function testAddFile()
    {

        $this->zip->addFile(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->arrRes[1]['file']);
        $this->assertTrue(1 === count($this->zip->getStorage()));
    }

    /**
     * @throws \Exception
     */
    public function testAddDir()
    {

        $this->zip->addDir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->arrRes[2]['folder']);
        $this->assertTrue(2 === count($this->zip->getStorage()));
    }

    /**
     * @throws \Exception
     */
    public function testAddDirRecursive()
    {

        // Depth: -1 (unlimited), files and directories
        $this->zip->addDirRecursive(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->arrRes[0]['folder']);
        $this->assertTrue(count($this->arrRes) - 1 === count($this->zip->getStorage()));

        $this->zip->purgeStorage();

        // Depth: 0, files and directories
        $this->zip->addDirRecursive(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->arrRes[0]['folder'], 0);
        $this->assertTrue(3 === count($this->zip->getStorage()));

        $this->zip->purgeStorage();

        // Depth: 0, files only
        $this->zip->addDirRecursive(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->arrRes[0]['folder'], 0, true);
        $this->assertTrue(1 === count($this->zip->getStorage()));
    }

    /**
     * @throws \Exception
     */
    public function testRun()
    {

        // Delete old files
        $this->delTempFiles($this->zipDestPath);

        // Make zip archive
        $source = sys_get_temp_dir() . '/' . $this->arrRes[0]['folder'];

        $this->zip
            ->addDirRecursive($source)
            ->stripSourcePath($source)
            ->run($this->zipDestPath);
        $this->assertTrue(true === is_file($this->zipDestPath));
        $this->assertTrue(true === filesize($this->zipDestPath) > 0);
    }

    /**
     * @param $res
     * @return bool|void
     */
    private function delTempFiles($res)
    {

        if (!file_exists($res))
        {
            return;
        }
        // File
        if (is_file($res))
        {
            return unlink($res);
        }

        // Folder
        $files = array_diff(scandir($res), ['.', '..']);
        foreach ($files as $file)
        {
            (is_dir($res . DIRECTORY_SEPARATOR . $file)) ? $this->delTempFiles($res . DIRECTORY_SEPARATOR . $file) : unlink($res . DIRECTORY_SEPARATOR . $file);
        }
        return rmdir($res);
    }

}
