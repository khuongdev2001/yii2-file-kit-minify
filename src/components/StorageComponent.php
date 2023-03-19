<?php

namespace fileKitMinify\components;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use app\components\fileSystemBuilders\FilesystemBuilderInterface;
use yii\web\UploadedFile;

class StorageComponent extends Component
{
    public $baseUrl;
    /**
     * @var
     */
    protected $filesystem;

    public $preserveFileName = false;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if ($this->baseUrl !== null) {
            $this->baseUrl = Yii::getAlias($this->baseUrl);
        }
        $this->filesystem = Yii::createObject($this->filesystem);
        if ($this->filesystem instanceof FilesystemBuilderInterface) {
            $this->filesystem = $this->filesystem->build();
        }
    }

    /**
     * @return FilesystemInterface
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * @param $filesystem
     */
    public function setFilesystem($filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param $file string|UploadedFile
     * @param string $pathPrefix string path to save current file
     * @return bool|string
     * @throws Exception
     * @throws FileExistsException
     * @throws InvalidConfigException
     */
    public function saveUploadFile(UploadedFile|string $file, string $pathPrefix = ''): bool|string
    {
        return $this->buildStreamFile([
            "filename" => $file->fullPath,
            "temp_name" => $file->tempName,
            "extension" => $file->getExtension()
        ], $pathPrefix);
    }

    /**
     * @throws Exception
     * @throws FileExistsException
     * @throws InvalidConfigException
     */
    public function savePathFile($path, string $pathPrefix = '')
    {
        return $this->buildStreamFile([
            "filename" => pathinfo($path, PATHINFO_FILENAME),
            "temp_name" => $path,
            "extension" => pathinfo($path, PATHINFO_EXTENSION)
        ], $pathPrefix);
    }

    /**
     * @param array $fileConfig [
     *   "filename" => "dev.jpg",
     *   "temp_name" => "anc.temp",
     *   "extension" => "png"
     * ]
     * @param $pathPrefix
     * @return bool|string
     * @throws Exception
     * @throws FileExistsException
     * @throws InvalidConfigException
     */
    public function buildStreamFile(array $fileConfig, $pathPrefix): bool|string
    {
        $pathPrefix = FileHelper::normalizePath($pathPrefix);
        $path = implode(DIRECTORY_SEPARATOR, array_filter([$pathPrefix, $fileConfig["filename"]]));
        if (!$this->preserveFileName) {
            $filename = implode('.', [
                time() . Yii::$app->security->generateRandomString(),
                $fileConfig["extension"]
            ]);
            $path = implode(DIRECTORY_SEPARATOR, array_filter([$pathPrefix, $filename]));
        }
        $config = array_merge(['ContentType' => FileHelper::getMimeType($fileConfig["temp_name"])]);
        $stream = fopen($fileConfig["temp_name"], 'r+');
        if ($this->getFilesystem()->writeStream($path, $stream, $config)) {
            return $this->getPathWithPrefixAdapter($path);
        }
        if (is_resource($stream)) {
            fclose($stream);
        }
        return false;
    }

    /**
     * @param $path
     * @return bool
     * @throws FileNotFoundException
     */
    public function delete($path)
    {
        if (!$this->getFilesystem()->has($path)) {
            return false;
        }
        if ($this->getFilesystem()->delete($path)) {
            return true;
        }
    }

    public function getPathWithPrefixAdapter($path)
    {
        $pathPrefixAdapter = $this->filesystem->getAdapter()->getPathPrefix();
        return FileHelper::normalizePath($pathPrefixAdapter . DIRECTORY_SEPARATOR . $path);
    }
}
