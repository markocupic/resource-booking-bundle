![Alt text](src/Resources/public/logo.png?raw=true "logo")


# Zip extension
This bundle provides a simple Zip class.

# Usage
```php
// Add dir recursive with unlimited depth, add dot files and folders too and store it to a given zip-file
$zip = (new \Markocupic\ZipBundle\Zip\Zip())
    ->ignoreDotFiles(false)
    ->stripSourcePath('path/to/source/dir')
    ->addDirRecursive('path/to/source/dir')
    ->run('path/to/destination/dir/myZip.zip');

// Add dir recursive depth: 1, collect only files and ignore empty folders
$zip = (new \Markocupic\ZipBundle\Zip\Zip())
    ->stripSourcePath('path/to/source/dir')
    ->addDirRecursive('path/to/source/dir', 1, true)
    ->run('path/to/destination/dir/myZip.zip');

// Add a file
$zip = (new \Markocupic\ZipBundle\Zip\Zip())
    ->stripSourcePath('path/to/source/dir')
    ->addFile('path/to/source/dir/myFile.txt')
    ->run('path/to/destination/dir/myZip.zip');

// Add files from a directory
$zip = (new \Markocupic\ZipBundle\Zip\Zip())
    ->stripSourcePath('path/to/source/dir')
    ->addDir('path/to/source/dir')
    ->run('path/to/destination/dir/myZip.zip');

// Add files from a directory
$zip = (new \Markocupic\ZipBundle\Zip\Zip())
   ->stripSourcePath('path')
   ->addDir('path/to/source/dir')
   ->addDir('path/toAnotherDir/source/dir')
   ->run('path/to/destination/dir/myZip.zip');
```
