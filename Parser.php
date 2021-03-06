<?php

namespace matveu\parser;

class DomParser
{
    private $page;
    private $host;
    private $scheme;
    private $url;
    private $visitedPages = [];
    private $pathToFolder;

    const ALLOWED_IMAGE_TYPES = [
        IMAGETYPE_GIF,
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG
    ];

    public function __construct($url)
    {
        try {
            if (empty($url) || !is_string($url)) {
                throw new InvalidUrlException();
            }

            $this->url = $url;
            $this->init();
        } catch (InvalidUrlException $e) {
            echo $e->getMessage();die();
        } catch (CreateFolderException $e) {
            echo $e->getMessage();die();
        }
    }

    private function init()
    {
        set_error_handler([new WarningException(), 'warning']);

        $this->parseUrl();
        $this->createFolder();
    }

    private function getPageData()
    {
        try {
            $page = file_get_html($this->url);
        } catch (WarningException $e) {
            echo "Can't get data from page: $this->url";die();
        }

        $this->page = $page;
    }

    private function parseImages()
    {
        $qtySavedImg = 0;

        foreach ($this->page->find('img') as $img) {
            if (empty($img->src)) {
                echo "Image src is empty.", PHP_EOL;
                continue;
            }

            $img->src = $this->getAbsoluteSrc($img->src);

            (!$this->saveImage($img->src)) ?: $qtySavedImg += 1;
        }

        echo "Number of saved images: $qtySavedImg", PHP_EOL;
    }

    private function saveImage($filePath)
    {
        $status = false;

        try {
            $type = exif_imagetype($filePath);
        } catch (WarningException $e) {
            echo "Can't check image type: $filePath", PHP_EOL;
        }

        if (!in_array($type, self::ALLOWED_IMAGE_TYPES)) {
            return false;
        }

        $imageName = bin2hex(openssl_random_pseudo_bytes(10));
        $imagePath = $this->pathToFolder . DIRECTORY_SEPARATOR . $imageName;

        switch ($type) {
            case IMAGETYPE_GIF :
                $img    = imageCreateFromGif($filePath);
                $status = imagegif($img, $imagePath . '.gif');
                break;
            case IMAGETYPE_JPEG :
                $img    = imageCreateFromJpeg($filePath);
                $status = imagejpeg($img, $imagePath . '.jpeg');
                break;
            case IMAGETYPE_PNG :
                $img    = imageCreateFromPng($filePath);
                $status = imagepng($img, $imagePath . '.png');
                break;
        }

        imagedestroy($img);
        return $status;
    }

    private function curlUrlExists($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $status = [];
        preg_match('/HTTP\/.* ([0-9]+) .*/', curl_exec($ch), $status);

        return ($status[1] == 200);
    }

    private function parseUrl()
    {
        $this->scheme   = parse_url($this->url, PHP_URL_SCHEME);
        $this->host     = parse_url($this->url, PHP_URL_HOST);
    }

    private function getAbsoluteSrc($src)
    {
        if (substr($src, 0, 2) === '//') {
            return $this->scheme . ':' . $src;
        } elseif (substr($src, 0, 1) === '/') {
            return $this->scheme . '://' . $this->host . $src;
        }

        return $src;
    }

    private function createFolder()
    {
        $folderPath = __DIR__ . DIRECTORY_SEPARATOR . 'parsed_img';
        if (!file_exists($folderPath)) {
            $mask   = umask(0);
            if (!mkdir($folderPath, 0777, true)) {
                throw new CreateFolderException(__DIR__);
            };
            umask($mask);
        }
        $this->pathToFolder = $folderPath;
    }

    private function goToAnotherPage()
    {
        foreach ($this->page->find('a') as $link) {
            (!$this->checkLink($link->href)) ?: $this->parsePage();
        }
    }

    private function checkLink($link)
    {
        if (substr($link, 0, 2) === '//' && strpos($link, $this->host) !== false) {
            $fullLink = $this->scheme . ":" . $link;
        } elseif (substr($link, 0, 1) === '/') {
            $fullLink = $this->scheme . "://" . $this->host . $link;
        } elseif (strpos($link, $this->host) !== false) {
            $fullLink = $link;
        } else {
            return false;
        }

        return (!in_array($fullLink, $this->visitedPages)) ? (($this->curlUrlExists($fullLink)) ? $this->url = $fullLink : false) : false;
    }

    private function parsePage()
    {
        $start = microtime(true);
        echo "Starting... " . $this->url, PHP_EOL;

        $this->visitedPages[] = $this->url;
        $this->getPageData();
        $this->parseImages();
        echo "Done for " . intval(microtime(true) - $start) . " seconds.", PHP_EOL;
        $this->goToAnotherPage();
    }

    public function run()
    {
        echo "Path to saved pictures: $this->pathToFolder", PHP_EOL;

        $this->parsePage();
    }
}